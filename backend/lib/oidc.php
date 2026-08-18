<?php
/**
 * Klient OIDC (OpenID Connect) - logowanie przez przekierowanie do Authentika.
 *
 * DLACZEGO TAK, A NIE PRZEZ FLOW EXECUTOR API:
 * Poprzednie logowanie (autch.php) wysyłało login i hasło do Authentika
 * z serwera, przez cURL. Ciasteczko sesji Authentika lądowało wtedy w pliku
 * tymczasowym na serwerze i zaraz było kasowane - przeglądarka użytkownika
 * nigdy go nie widziała. Dla Authentika taki użytkownik pozostawał
 * niezalogowany, więc panel Pterodactyla i tak pytał go o dane.
 *
 * Tutaj logowanie odbywa się W PRZEGLĄDARCE: odsyłamy ją do Authentika,
 * użytkownik loguje się u niego i wraca do nas z jednorazowym kodem. Efekt
 * uboczny (a właściwie cały sens) jest taki, że przeglądarka zostaje z ważnym
 * ciasteczkiem sesji Authentika - i to ono sprawia, że panel wpuszcza bez
 * pytania. To jest właśnie SSO.
 *
 * Bezpieczeństwo:
 *  - state  - jednorazowy losowy ciąg wiążący powrót z naszym żądaniem (CSRF),
 *  - nonce  - wiąże id_token z tą konkretną próbą logowania (replay),
 *  - PKCE   - kod można wymienić na token tylko znając verifier, który nigdy
 *             nie opuścił serwera. Chroni, gdyby kod wyciekł z adresu URL.
 */

declare(strict_types=1);

/** Ile sekund ważna jest rozpoczęta próba logowania. */
const OIDC_LOGIN_TTL = 600; // 10 minut

function oidc_client_id(): string
{
    return env_required('OIDC_CLIENT_ID');
}

function oidc_client_secret(): string
{
    return env_required('OIDC_CLIENT_SECRET');
}

function oidc_discovery_url(): string
{
    return env_required('OIDC_DISCOVERY_URL');
}

/**
 * Adres, pod który Authentik odsyła przeglądarkę po zalogowaniu.
 *
 * Budujemy go z bieżącego hosta, żeby ta sama instalacja działała na dnct.pl,
 * beta.dnct.pl i w panelu administracyjnym bez przestawiania konfiguracji.
 * Wszystkie te adresy są zarejestrowane w Authentiku; host spoza listy
 * zostanie przez niego odrzucony, więc nie da się tędy przekierować
 * użytkownika gdzie indziej.
 *
 * Schemat jest przypisany do hosta, a nie zgadywany z żądania. `admin.lan.dnct.pl`
 * to jedyny wpis po HTTP i tak ma zostać: panel administracyjny stoi wewnątrz
 * LAN-u, poza reverse proxy, więc nie ma tam czym zakończyć TLS-a. Gdyby schemat
 * brał się z nagłówków, wystarczyłby jeden `X-Forwarded-Proto` od klienta,
 * żeby zbudować adres powrotu, którego Authentik nie rozpozna - i logowanie
 * przestałoby działać w sposób trudny do wyśledzenia.
 *
 * (Ta wersja funkcji doszyta ręcznie do stanu z produkcji - main dostał
 * wpis dla admin.lan.dnct.pl bezpośrednim hotfixem, którego nie było w dev.)
 */
function oidc_redirect_uri(): string
{
    // Twarda lista dozwolonych hostów - nagłówek Host pochodzi od klienta.
    $allowed = [
        'dnct.pl'           => 'https',
        'beta.dnct.pl'      => 'https',
        'admin.lan.dnct.pl' => 'http',
        'account.dnct.pl'   => 'https',
    ];

    $host = (string)($_SERVER['HTTP_HOST'] ?? 'dnct.pl');
    if (!isset($allowed[$host])) {
        $host = 'dnct.pl';
    }

    return $allowed[$host] . '://' . $host . '/auth/callback.php';
}

/**
 * Dokument discovery Authentika (adresy endpointów).
 * Trzymamy go w cache na dysku, żeby nie odpytywać przy każdym logowaniu.
 *
 * @return array<string,mixed>
 */
function oidc_discovery(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    // Nazwa pliku zawiera skrót ADRESU discovery. Bez tego cache przeżywał
    // zmianę OIDC_DISCOVERY_URL w .env: przy przenoszeniu Authentika na
    // auth.dnct.pl strona przez godzinę wysyłała ludzi na stary, wewnętrzny
    // host - mimo poprawnej konfiguracji. Teraz zmiana adresu unieważnia cache
    // sama z siebie, bo to po prostu inny plik.
    $cacheFile = sys_get_temp_dir() . '/dnct_oidc_discovery_' . substr(hash('sha256', oidc_discovery_url()), 0, 16) . '.json';
    $maxAge = 3600; // 1 h

    if (is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $maxAge) {
        $fromCache = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($fromCache) && isset($fromCache['authorization_endpoint'])) {
            return $cached = $fromCache;
        }
    }

    $ch = curl_init(oidc_discovery_url());
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $code === 200 ? json_decode((string)$raw, true) : null;

    if (!is_array($data) || !isset($data['authorization_endpoint'], $data['token_endpoint'])) {
        // Awaryjnie sięgamy po ostatni udany dokument, nawet przeterminowany -
        // chwilowa niedostępność Authentika nie powinna kłaść logowania.
        if (is_readable($cacheFile)) {
            $stale = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($stale) && isset($stale['authorization_endpoint'])) {
                error_log('OIDC: discovery niedostepne (HTTP ' . $code . '), uzywam cache');
                return $cached = $stale;
            }
        }
        throw new RuntimeException('Nie udało się pobrać konfiguracji OIDC (HTTP ' . $code . ')');
    }

    @file_put_contents($cacheFile, json_encode($data));
    return $cached = $data;
}

/**
 * Rozpoczyna logowanie: zapamiętuje state/nonce/verifier w sesji i zwraca
 * adres Authentika, pod który trzeba odesłać przeglądarkę.
 */
function oidc_authorize_url(?string $returnTo = null): string
{
    $discovery = oidc_discovery();

    $state    = bin2hex(random_bytes(32));
    $nonce    = bin2hex(random_bytes(32));
    $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');

    // S256: wysyłamy tylko skrót, sam verifier zostaje na serwerze.
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $_SESSION['oidc_login'] = [
        'state'     => $state,
        'nonce'     => $nonce,
        'verifier'  => $verifier,
        'created'   => time(),
        'return_to' => oidc_safe_return_to($returnTo),
    ];

    $params = [
        'client_id'             => oidc_client_id(),
        'redirect_uri'          => oidc_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid profile email',
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ];

    return $discovery['authorization_endpoint'] . '?' . http_build_query($params);
}

/**
 * Przycina adres powrotu do ścieżki względnej.
 * Bez tego ktoś mógłby podać `?return_to=https://cudza-strona/` i użyć naszego
 * logowania jako trampoliny do phishingu (open redirect).
 */
function oidc_safe_return_to(?string $candidate): string
{
    $default = '/index.php';

    if ($candidate === null || $candidate === '') {
        return $default;
    }
    // Musi być ścieżką lokalną: zaczynać się od "/" i nie od "//" (to już inny host).
    if ($candidate[0] !== '/' || str_starts_with($candidate, '//')) {
        return $default;
    }
    if (str_contains($candidate, "\r") || str_contains($candidate, "\n")) {
        return $default;
    }

    return $candidate;
}

/**
 * Wymienia jednorazowy kod na tokeny (żądanie serwer-serwer).
 *
 * @return array<string,mixed>
 */
function oidc_exchange_code(string $code, string $verifier): array
{
    $discovery = oidc_discovery();

    $ch = curl_init($discovery['token_endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => oidc_redirect_uri(),
            'client_id'     => oidc_client_id(),
            'client_secret' => oidc_client_secret(),
            'code_verifier' => $verifier,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$raw, true);

    if ($http !== 200 || !is_array($data) || !isset($data['access_token'])) {
        // Treść odpowiedzi trafia tylko do logu - może zawierać szczegóły błędu.
        error_log('OIDC: wymiana kodu nieudana (HTTP ' . $http . '): ' . substr((string)$raw, 0, 300));
        throw new RuntimeException('Nie udało się dokończyć logowania.');
    }

    return $data;
}

/**
 * Pobiera dane użytkownika (imię, e-mail, grupy) na podstawie access_tokenu.
 *
 * @return array<string,mixed>
 */
function oidc_userinfo(string $accessToken): array
{
    $discovery = oidc_discovery();

    $ch = curl_init($discovery['userinfo_endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $http === 200 ? json_decode((string)$raw, true) : null;

    if (!is_array($data)) {
        error_log('OIDC: userinfo nieudane (HTTP ' . $http . ')');
        throw new RuntimeException('Nie udało się pobrać danych konta.');
    }

    return $data;
}

/**
 * Odczytuje payload id_tokenu (środkowa część JWT).
 *
 * UWAGA: NIE weryfikuje podpisu i nie wolno na tym opierać decyzji o dostępie.
 * Używamy go wyłącznie do sprawdzenia nonce - sam token przyszedł bezpośrednio
 * z Authentika po HTTPS, w odpowiedzi na uwierzytelnione żądanie serwer-serwer
 * (z client_secret), więc jego pochodzenie jest już potwierdzone kanałem.
 * Tożsamość użytkownika bierzemy z /userinfo, też bezpośrednio od Authentika.
 *
 * @return array<string,mixed>
 */
function oidc_decode_id_token(string $idToken): array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return [];
    }

    $payload = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4), true);
    if ($payload === false) {
        return [];
    }

    $data = json_decode($payload, true);
    return is_array($data) ? $data : [];
}

/**
 * Adres wylogowania w Authentiku (kończy też sesję SSO, nie tylko naszą).
 *
 * Doklejamy post_logout_redirect_uri, żeby użytkownik wrócił na stronę zamiast
 * utknąć na gołym "Logout successful" Authentika. Authentik przyjmie ten adres
 * tylko wtedy, gdy jest zarejestrowany u dostawcy jako redirect_uri typu
 * "logout" - i wymaga id_token_hint, żeby wiedzieć, czyją sesję kończy.
 *
 * @param string $postLogoutUrl Pełny adres, na który wracamy po wylogowaniu.
 * @param string $idToken       id_token z logowania (pusty = bez powrotu).
 */
function oidc_end_session_url(string $postLogoutUrl, string $idToken = ''): string
{
    try {
        $discovery = oidc_discovery();
    } catch (RuntimeException $e) {
        return $postLogoutUrl;
    }

    if (empty($discovery['end_session_endpoint'])) {
        return $postLogoutUrl;
    }

    $endpoint = (string)$discovery['end_session_endpoint'];

    // Bez id_token_hint Authentik zignoruje adres powrotu i pokaże własną
    // stronę potwierdzenia - wtedy lepiej nie doklejać parametrów w ogóle.
    if ($idToken === '') {
        return $endpoint;
    }

    return $endpoint . '?' . http_build_query([
        'id_token_hint'            => $idToken,
        'post_logout_redirect_uri' => $postLogoutUrl,
    ]);
}

/**
 * Publiczny adres Authentika (schemat + host), odczytany z dokumentu discovery.
 *
 * Nie bierzemy go z AUTHENTIK_URL, bo tam siedzi adres wewnętrzny
 * (http://192.168.x.x) używany do zapytań serwer-serwer. Przeglądarkę trzeba
 * odesłać pod adres publiczny - a ten Authentik sam ogłasza w discovery.
 */
function oidc_public_base(): string
{
    $discovery = oidc_discovery();
    $parts = parse_url((string)($discovery['authorization_endpoint'] ?? ''));

    if (empty($parts['scheme']) || empty($parts['host'])) {
        throw new RuntimeException('Nie udało się ustalić publicznego adresu Authentika.');
    }

    return $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '');
}

/*
 * Był tu oidc_flow_url() - wejście na konto demo przez osobny proces
 * w Authentiku. Usunięte świadomie i NIE należy do tego wracać: każde
 * logowanie przez Authentika zakłada sesję SSO wspólną dla przeglądarki,
 * więc po obejrzeniu demo kliknięcie "Zaloguj" wpuszczało z powrotem na
 * konto demo, a dnct.pl uznawało gościa za zalogowanego jako demo.
 * Demo chodzi teraz jednorazowym biletem prosto do panelu - patrz
 * backend/lib/panel_sso.php.
 */

/** Pełny adres strony głównej bieżącego hosta (do powrotu po wylogowaniu). */
function oidc_site_home_url(): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'dnct.pl');

    $allowed = ['dnct.pl', 'beta.dnct.pl'];
    if (!in_array($host, $allowed, true)) {
        $host = 'dnct.pl';
    }

    return 'https://' . $host . '/';
}
