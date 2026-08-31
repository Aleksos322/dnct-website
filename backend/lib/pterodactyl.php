<?php
/**
 * Klient Pterodactyla - Just-In-Time Provisioning
 *
 * Obsługuje automatyczne tworzenie kont w Pterodactylu
 * w momencie rejestracji użytkownika w Authentiku.
 */

declare(strict_types=1);

/**
 * URL API Pterodactyla
 */
function pterodactyl_api_url(): string
{
    return rtrim((string)env('PTERO_API_URL', 'https://panel.dnct.pl'), '/');
}

/**
 * API Key do Pterodactyla (wymagany: Users Read & Write)
 */
function pterodactyl_api_key(): string
{
    $key = env('PTERO_API_KEY', '');
    if ($key === '') {
        throw new RuntimeException('PTERO_API_KEY nie skonfigurowany w .env');
    }
    return $key;
}

/**
 * Wysyła żądanie do API Pterodactyla
 */
function pterodactyl_api_request(
    string $method,
    string $path,
    ?array $body = null
): array {
    try {
        $apiUrl = pterodactyl_api_url();
        $apiKey = pterodactyl_api_key();
    } catch (RuntimeException $e) {
        error_log('Pterodactyl: ' . $e->getMessage());
        return ['http_code' => 0, 'body' => null, 'error' => $e->getMessage()];
    }

    $url = $apiUrl . $path;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($method !== 'GET' && $body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);

    curl_close($ch);

    if ($curlErrno !== 0) {
        error_log("Pterodactyl CURL Error #{$curlErrno}: {$curlError}");
        return ['http_code' => 0, 'body' => null, 'error' => $curlError];
    }

    return [
        'http_code' => $httpCode,
        'body' => $response ? json_decode($response, true) : null,
        'error' => null,
    ];
}

/**
 * Konwertuje email na bezpieczny username Pterodactyla
 * Pterodactyl: tylko a-z, 0-9, _, -  (max 32 znaki, lowercase)
 */
function pterodactyl_username_from_email(string $email): string
{
    // Bierz część przed @
    $localPart = explode('@', $email)[0];

    // Zamień niedozwolone znaki na _
    $username = preg_replace('/[^a-z0-9_\-]/i', '_', $localPart);

    // Lowercase
    $username = strtolower($username);

    // Obetnij do 32
    $username = substr($username, 0, 32);

    // Fallback jeśli pusty
    if (empty($username)) {
        $username = 'user_' . bin2hex(random_bytes(4));
    }

    return $username;
}

/**
 * Generuje losowe bezpieczne hasło
 */
function pterodactyl_generate_password(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

/**
 * Szuka użytkownika w Pterodactylu po emailu
 * Zwraca: ['id' => int, 'email' => string, 'username' => string, ...]
 */
function pterodactyl_user_by_email(string $email): ?array
{
    if (empty($email)) {
        return null;
    }

    $result = pterodactyl_api_request(
        'GET',
        '/api/application/users?filter[email]=' . urlencode($email)
    );

    if ($result['http_code'] !== 200 || !is_array($result['body'])) {
        error_log('Pterodactyl: Błąd wyszukiwania użytkownika ' . $email);
        return null;
    }

    $data = $result['body']['data'] ?? [];
    if (count($data) === 0) {
        return null; // User nie istnieje
    }

    return $data[0]['attributes'] ?? null;
}

/**
 * Tworzy nowego użytkownika w Pterodactylu
 */
function pterodactyl_create_user(
    string $email,
    string $username,
    string $firstName = 'User',
    string $lastName = 'Account',
    string $password = ''
): ?array {
    if (empty($password)) {
        $password = pterodactyl_generate_password(16);
    }

    $payload = [
        'email' => $email,
        'username' => $username,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'password' => $password,
    ];

    $result = pterodactyl_api_request(
        'POST',
        '/api/application/users',
        $payload
    );

    // HTTP 201 = Created
    if ($result['http_code'] === 201 && is_array($result['body'])) {
        $attrs = $result['body']['attributes'] ?? null;
        if (is_array($attrs)) {
            error_log('Pterodactyl: Stworzono użytkownika ' . $email . ' (ID: ' . ($attrs['id'] ?? '?') . ')');
            return $attrs;
        }
    }

    error_log('Pterodactyl API Error (HTTP ' . $result['http_code'] . '): ' . json_encode($result['body']));
    return null;
}

/**
 * Just-In-Time Provisioning
 *
 * Jeśli user istnieje → zwróć jego dane
 * Jeśli nie istnieje → stwórz go automatycznie → zwróć dane
 *
 * @param string $email Email użytkownika (unique key)
 * @param string $username Username z Authentiku (preferowany) - jeśli pusty, generuje się z emaila
 * @param string $firstName Imię (opcjonalne)
 * @param string $lastName Nazwisko (opcjonalne)
 *
 * Zwraca: ['id' => int, 'email' => string, 'username' => string, ...]
 * lub null jeśli błąd
 */
function pterodactyl_ensure_user(
    string $email,
    string $username = '',
    string $firstName = 'User',
    string $lastName = 'Account'
): ?array {
    // 1. Sprawdzenie czy już istnieje (po emailu)
    $existing = pterodactyl_user_by_email($email);
    if ($existing !== null) {
        error_log('Pterodactyl JIT: User już istnieje: ' . $email . ' (ID: ' . $existing['id'] . ')');
        return $existing;
    }

    // 2. Nie istnieje - tworzymy
    // Jeśli username nie podany, generujemy z emaila
    if (empty($username)) {
        $username = pterodactyl_username_from_email($email);
    }

    $password = pterodactyl_generate_password(16);

    $newUser = pterodactyl_create_user($email, $username, $firstName, $lastName, $password);

    if ($newUser === null) {
        error_log('Pterodactyl JIT: Nie udało się stworzyć użytkownika: ' . $email . ' (username: ' . $username . ')');
        return null;
    }

    error_log('Pterodactyl JIT: Stworzono nowego użytkownika: ' . $email . ' → username: ' . $newUser['username'] . ' (ID: ' . $newUser['id'] . ')');
    return $newUser;
}

/**
 * Czy serwer o tym ID nadal istnieje w Pterodactylu.
 *
 * Zapytanie do bazy (`panel_db()`), nie do API - jedno proste zapytanie
 * zamiast żądania HTTP, a ten sam efekt (404 z API i brak wiersza w bazie
 * znaczą dokładnie to samo: serwera nie ma).
 *
 * DLACZEGO TO W OGÓLE POTRZEBNE: `orders.ptero_server_id` nie znika sam,
 * gdy ktoś skasuje serwer RĘCZNIE w panelu Pterodactyla (z pominięciem
 * backend/tools/lifecycle.php). Zamówienie zostaje ze statusem "provisioned"
 * i martwym ID - bez tej kontroli klient nadal widziałby taki serwer na
 * /twoje-serwery, a cykl życia dalej wysyłałby o nim ostrzeżenia i próbował
 * go zawieszać/kasować.
 */
function pterodactyl_server_exists(PDO $panel, int $serverId): bool
{
    $stmt = $panel->prepare('SELECT 1 FROM servers WHERE id = ? LIMIT 1');
    $stmt->execute([$serverId]);
    return $stmt->fetchColumn() !== false;
}
