<?php
/**
 * Klient Authentika - jedyne miejsce, które rozmawia z magazynem kont.
 *
 * Konfiguracja (URL, token admina, slug flow logowania) pochodzi z .env,
 * dzięki czemu token nie leży już w kodzie źródłowym.
 */

declare(strict_types=1);

function authentik_url(): string
{
    return rtrim((string)env('AUTHENTIK_URL', 'http://192.168.50.12:9000'), '/');
}

function authentik_token(): string
{
    return env_required('AUTHENTIK_API_TOKEN');
}

function authentik_login_flow_slug(): string
{
    return (string)env('AUTHENTIK_LOGIN_FLOW_SLUG', 'default-authentication-flow');
}

function site_home_url(): string
{
    return (string)env('SITE_HOME_URL', 'https://beta.dnct.pl');
}

// ============================================================
// CORE API (operacje administracyjne - z tokenem)
// ============================================================
function authentik_api_request(string $method, string $path, ?array $body = null): array
{
    try {
        $token = authentik_token();
    } catch (RuntimeException $e) {
        error_log('Authentik: ' . $e->getMessage());
        return ['http_code' => 0, 'body' => null, 'error' => $e->getMessage()];
    }

    $ch = curl_init(authentik_url() . $path);
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method === 'PATCH') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body'      => $response !== false ? json_decode($response, true) : null,
        'error'     => $curlError,
    ];
}

/**
 * Czy w Authentiku istnieje już użytkownik o podanym loginie / e-mailu?
 *
 * Porównanie jest CASE-INSENSITIVE (jeśli istnieje "Aleks", to "aleks" jest
 * zajęty). Authentik traktuje login jako case-sensitive, więc filtrowanie
 * robimy po naszej stronie na wynikach wyszukiwania.
 *
 * @param string $field 'username' albo 'email'
 * @return bool|null true = zajęte, false = wolne, null = nie udało się sprawdzić
 */
function authentik_user_exists(string $field, string $value): ?bool
{
    if (!in_array($field, ['username', 'email'], true) || $value === '') {
        return null;
    }

    $result = authentik_api_request(
        'GET',
        '/api/v3/core/users/?page_size=100&search=' . rawurlencode($value)
    );

    if ($result['http_code'] !== 200 || !isset($result['body']['results']) || !is_array($result['body']['results'])) {
        error_log('Authentik: nie udało się sprawdzić dostępności (' . $field . '): ' . json_encode($result));
        return null;
    }

    $needle = mb_strtolower($value, 'UTF-8');
    foreach ($result['body']['results'] as $user) {
        if (!isset($user[$field])) {
            continue;
        }
        if (mb_strtolower((string)$user[$field], 'UTF-8') === $needle) {
            return true;
        }
    }

    return false;
}

/**
 * Tworzy konto i ustawia hasło (samo utworzenie użytkownika hasła NIE ustawia).
 *
 * @return array{ok:bool, code:string, user_id?:int|string}
 *         code: 'created' | 'conflict' | 'create_failed' | 'password_failed'
 */
function authentik_create_user(array $data): array
{
    $create = authentik_api_request('POST', '/api/v3/core/users/', [
        'username'   => $data['username'],
        'name'       => $data['display_name'],
        'email'      => $data['email'],
        'is_active'  => true,
        'attributes' => [
            'marketing_consent' => (bool)($data['marketing'] ?? false),
            'terms_accepted_at' => gmdate('c'),
            'email_verified_at' => $data['email_verified_at'] ?? gmdate('c'),
            'registration_ip'   => client_ip(),
        ],
    ]);

    if ($create['http_code'] !== 201 || !isset($create['body']['pk'])) {
        error_log('Authentik: nie udało się utworzyć użytkownika ' . $data['username'] . ': ' . json_encode($create));
        // 400 z polami username/email = wyścig (konto powstało między naszym
        // sprawdzeniem a zapisem) - Authentik zwraca błędy walidacji DRF-style,
        // kluczowane nazwą pola. Każdy INNY kształt 400 (np. odrzucone hasło
        // mimo przejścia naszej walidacji, zbyt długie pole) NIE jest
        // konfliktem - traktowanie go jako "zajęty login" pokazywałoby
        // klientowi mylący komunikat i utrudniało diagnozę zgłoszeń.
        $body = is_array($create['body'] ?? null) ? $create['body'] : [];
        $isConflict = $create['http_code'] === 400
            && (isset($body['username']) || isset($body['email']));
        return ['ok' => false, 'code' => $isConflict ? 'conflict' : 'create_failed'];
    }

    $userId = $create['body']['pk'];

    $setPassword = authentik_api_request('POST', "/api/v3/core/users/{$userId}/set_password/", [
        'password' => $data['password'],
    ]);

    if (!in_array($setPassword['http_code'], [200, 204], true)) {
        error_log('Authentik: konto ' . $data['username'] . ' utworzone, ale nie udało się ustawić hasła: ' . json_encode($setPassword));
        return ['ok' => false, 'code' => 'password_failed', 'user_id' => $userId];
    }

    // uid = `sub` w OIDC (providery mają sub_mode=hashed_user_id) - niezmienny
    // identyfikator, którym łączymy konta w usługach (migracja 009).
    return ['ok' => true, 'code' => 'created', 'user_id' => $userId, 'uid' => (string)($create['body']['uid'] ?? '')];
}

// ============================================================
// FLOW EXECUTOR API (logowanie - bez tokenu, sesja w ciasteczku)
// ============================================================
class AuthentikLoginException extends Exception
{
}

function authentik_flow_request(string $url, string $method, ?array $body, string $cookieFile): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new AuthentikLoginException('Błąd połączenia z Authentikiem: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new AuthentikLoginException('Nieoczekiwana odpowiedź Authentika.');
    }

    return ['http_code' => $httpCode, 'body' => $decoded];
}
