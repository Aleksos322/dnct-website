<?php
/**
 * Klient Jellyfina - Just-In-Time Provisioning i ręczne linkowanie kont.
 *
 * W przeciwieństwie do pterodactyl.php NIE MA tu find-or-create po e-mailu:
 * odpowiedź `GET /Users` Jellyfina nie zawiera e-maila (potwierdzone na żywo
 * na 192.168.50.9), więc nie ma jak wykryć, że dany klient już ma konto.
 * Dlatego dwie ścieżki są rozdzielone i jawne:
 *
 *   - jellyfin_create_user()  - ZAWSZE zakłada nowe konto. Wołać wyłącznie raz,
 *     z rejestracji (api/auth/register.php) - nigdy przy logowaniu, bo dwa
 *     logowania założyłyby dwa konta.
 *   - jellyfin_authenticate() - potwierdza istniejące konto logiem+hasłem
 *     (POST /Users/AuthenticateByName), do ręcznego linkowania starych kont
 *     (rodzina/znajomi sprzed tego systemu) bez trzymania ich haseł u nas.
 *
 * Zachowanie obu endpointów zweryfikowane na żywo względem Jellyfin 10.11.11
 * (192.168.50.9) przed napisaniem tego pliku - patrz plan account.dnct.pl.
 */

declare(strict_types=1);

/** URL API Jellyfina - domyślnie LAN, port wystawiony na 0.0.0.0. */
function jellyfin_api_url(): string
{
    return rtrim((string)env('JELLYFIN_API_URL', 'http://192.168.50.9:8096'), '/');
}

/** Klucz API Jellyfina - dedykowany dla strony, NIE ten używany przez skill do sortowania mediów. */
function jellyfin_api_key(): string
{
    $key = env('JELLYFIN_API_KEY', '');
    if ($key === '') {
        throw new RuntimeException('JELLYFIN_API_KEY nie skonfigurowany w .env');
    }
    return $key;
}

/** Wysyła żądanie do API Jellyfina. Wzorem pterodactyl_api_request(). */
function jellyfin_api_request(string $method, string $path, ?array $body = null): array
{
    try {
        $apiUrl = jellyfin_api_url();
        $apiKey = jellyfin_api_key();
    } catch (RuntimeException $e) {
        error_log('Jellyfin: ' . $e->getMessage());
        return ['http_code' => 0, 'body' => null, 'error' => $e->getMessage()];
    }

    $ch = curl_init($apiUrl . $path);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            // Jellyfin 12 domyslnie odrzuca stare naglowki (X-Emby-Token itd.)
            'Authorization: MediaBrowser Token="' . $apiKey . '"',
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    if ($method !== 'GET' && $body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response  = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    if ($curlErrno !== 0) {
        error_log("Jellyfin CURL Error #{$curlErrno}: {$curlError}");
        return ['http_code' => 0, 'body' => null, 'error' => $curlError];
    }

    return [
        'http_code' => $httpCode,
        'body'      => $response !== '' && $response !== false ? json_decode($response, true) : null,
        'error'     => null,
    ];
}

/** Generuje losowe bezpieczne hasło. Kopia pterodactyl_generate_password() -
 *  celowo zduplikowana, żeby jellyfin.php nie zależał od pterodactyl.php. */
function jellyfin_generate_password(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/** Sanityzacja username z Authentika pod wymogi Jellyfina. */
function jellyfin_username_from_authentik(string $username): string
{
    $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $username) ?? '';
    $clean = substr($clean, 0, 32);

    if ($clean === '') {
        $clean = 'user_' . bin2hex(random_bytes(4));
    }

    return $clean;
}

/**
 * ZAWSZE tworzy NOWE konto - patrz nagłówek pliku. Wołać wyłącznie z rejestracji.
 *
 * Jellyfin traktuje nazwy bez rozróżniania wielkości liter i na kolizję
 * odpowiada gołym `400 Error processing request.` (bez treści JSON) - nie da
 * się tego odróżnić od innego błędu inaczej niż po kodzie HTTP. Skoro nie ma
 * find-or-create (brak e-maila w API), a nowy klient regularnie trafi na login
 * pokrywający się z jednym z kont sprzed tego systemu (np. zarejestruje się
 * jako "kimor" albo "root"), retry z dopiskiem jest jedynym bezpiecznym
 * wyjściem - NIE wolno przy kolizji cicho "połączyć" konta z cudzym Jellyfinem,
 * bo to obcy człowiek, nie ten sam użytkownik pod inną wielkością liter.
 *
 * @return array{id:string,name:string,password:string}|null
 */
function jellyfin_create_user(string $username, string $password = ''): ?array
{
    if ($password === '') {
        $password = jellyfin_generate_password(16);
    }

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $tryName = $attempt === 0 ? $username : $username . '_' . bin2hex(random_bytes(2));

        $result = jellyfin_api_request('POST', '/Users/New', [
            'Name'     => $tryName,
            'Password' => $password,
        ]);

        if (in_array($result['http_code'], [200, 201], true) && is_array($result['body'])) {
            $id = $result['body']['Id'] ?? null;
            if (is_string($id) && $id !== '') {
                return ['id' => $id, 'name' => (string)($result['body']['Name'] ?? $tryName), 'password' => $password];
            }
            error_log('Jellyfin: odpowiedź /Users/New bez Id dla ' . $tryName);
            return null;
        }

        // 400 bez treści = najpewniej kolizja nazwy - spróbuj z dopiskiem.
        // Inny kod błędu (autoryzacja, sieć) nie ma sensu ponawiać w kółko.
        if ($result['http_code'] !== 400) {
            error_log('Jellyfin: nie udało się utworzyć użytkownika ' . $tryName . ' (HTTP ' . $result['http_code'] . '): ' . json_encode($result['body']));
            return null;
        }
    }

    error_log('Jellyfin: nie udało się utworzyć użytkownika ' . $username . ' po 3 próbach (same kolizje nazw)');
    return null;
}

/**
 * Weryfikuje istniejące konto logiem+hasłem, bez trzymania hasła u nas.
 * Jeden komunikat błędu na "nie ma takiego loginu" i "złe hasło" - nie
 * ujawniamy które (patrz api/jellyfin/link.php).
 *
 * @return array{id:string,name:string}|null
 */
function jellyfin_authenticate(string $username, string $password): ?array
{
    $result = jellyfin_api_request('POST', '/Users/AuthenticateByName', [
        'Username' => $username,
        'Pw'       => $password,
    ]);

    if ($result['http_code'] !== 200 || !is_array($result['body'])) {
        return null;
    }

    $user = $result['body']['User'] ?? null;
    if (!is_array($user) || empty($user['Id'])) {
        return null;
    }

    return ['id' => (string)$user['Id'], 'name' => (string)($user['Name'] ?? $username)];
}

/** GET /Users/{Id} - stan konta do ekranu profilu. */
function jellyfin_user_by_id(string $id): ?array
{
    $result = jellyfin_api_request('GET', '/Users/' . rawurlencode($id));

    if ($result['http_code'] !== 200 || !is_array($result['body'])) {
        return null;
    }

    return $result['body'];
}
