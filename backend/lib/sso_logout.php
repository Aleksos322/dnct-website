<?php
/**
 * Wylogowanie zwrotne (OIDC Back-Channel Logout) - Authentik woła nas
 * serwer-serwer, gdy sesja DNCT się kończy (wylogowanie gdziekolwiek,
 * usunięcie sesji przez admina, dezaktywacja konta).
 *
 * Nie kasujemy plików sesji PHP: katalog sesji nie daje się listować, a beta
 * i produkcja nie muszą trzymać ich w tym samym miejscu. Zamiast tego
 * endpoint zostawia znacznik w SSO_LOGOUT_DIR, a bootstrap przy każdym
 * żądaniu sprawdza, czy sesja tej przeglądarki nie jest na liście.
 * Katalog jest wspólny dla wszystkich stron na provider 10 (dnct, beta,
 * account, web), więc jedno wywołanie wylogowuje je wszystkie.
 */

declare(strict_types=1);

const SSO_LOGOUT_EVENT  = 'http://schemas.openid.net/event/backchannel-logout';
const SSO_LOGOUT_KEEP   = 172800; // znaczniki starsze niż 2 doby nic już nie łapią (sesje żyją krócej)
const SSO_LOGOUT_MAXAGE = 3600;   // logout_token starszy niż godzina = odrzucony

function sso_logout_dir(): string
{
    // .../public_html/WEB/<strona>  ->  .../public_html/storage/sso-logout
    return dirname(DNCT_BASE_PATH, 2) . '/storage/sso-logout';
}

function sso_logout_marker(string $kind, string $value): string
{
    return sso_logout_dir() . '/' . $kind . '-' . hash('sha256', $value);
}

/**
 * Czy bieżąca sesja została zakończona w Authentiku. Wołane z bootstrapu
 * zaraz po session_start(); przy trafieniu czyści sesję.
 */
function sso_logout_enforce(): void
{
    $idToken = (string)($_SESSION['oidc_id_token'] ?? '');
    if ($idToken === '') {
        return;
    }

    // id_token przyszedł od Authentika serwer-serwer przy logowaniu, więc
    // samo zdekodowanie (bez podpisu) wystarcza. Liczymy raz na sesję.
    if (!isset($_SESSION['oidc_sid'], $_SESSION['oidc_sid_sub'])) {
        $claims = oidc_decode_id_token($idToken);
        $_SESSION['oidc_sid']     = (string)($claims['sid'] ?? '');
        $_SESSION['oidc_sid_sub'] = (string)($claims['sub'] ?? '');
    }

    $sid = (string)$_SESSION['oidc_sid'];
    $sub = (string)$_SESSION['oidc_sid_sub'];

    // Tylko sesje zalogowane PRZED znacznikiem. Authentik wysyła logout_token
    // także przy zdarzeniach, po których jego sesja (ten sam sid) żyje dalej -
    // bez tego warunku każde ponowne logowanie w niej byłoby od razu ubijane.
    $loggedAt = (int)($_SESSION['logged_in_at'] ?? 0);
    $sidFile  = $sid !== '' ? sso_logout_marker('sid', $sid) : '';
    $revoked  = $sidFile !== '' && is_file($sidFile) && $loggedAt < (int)@filemtime($sidFile);

    // Token bez sid oznacza "wszystkie sesje tego użytkownika do chwili iat".
    if (!$revoked && $sub !== '') {
        $subFile = sso_logout_marker('sub', $sub);
        if (is_file($subFile)) {
            $iat = (int)@file_get_contents($subFile);
            $revoked = $loggedAt <= $iat;
        }
    }

    if ($revoked) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

/** Klucze podpisujące Authentika (JWKS), cache na dysku jak discovery. */
function sso_logout_jwks(bool $fresh = false): array
{
    $uri = (string)(oidc_discovery()['jwks_uri'] ?? '');
    if ($uri === '') {
        throw new RuntimeException('discovery bez jwks_uri');
    }

    $cacheFile = sys_get_temp_dir() . '/dnct_oidc_jwks_' . substr(hash('sha256', $uri), 0, 16) . '.json';
    if (!$fresh && is_readable($cacheFile) && time() - (int)@filemtime($cacheFile) < 3600) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached['keys'] ?? null)) {
            return $cached['keys'];
        }
    }

    $ch = curl_init($uri);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = $code === 200 ? json_decode((string)$raw, true) : null;
    if (!is_array($data['keys'] ?? null)) {
        throw new RuntimeException('nie udalo sie pobrac JWKS (HTTP ' . $code . ')');
    }

    @file_put_contents($cacheFile, json_encode($data));
    return $data['keys'];
}

function sso_logout_b64(string $part): string|false
{
    return base64_decode(strtr($part, '-_', '+/') . str_repeat('=', (4 - strlen($part) % 4) % 4), true);
}

/**
 * Sprawdza logout_token (podpis RS256 kluczem z JWKS + claimy wg OIDC
 * Back-Channel Logout 1.0, pkt 2.6) i zwraca jego claimy.
 *
 * @throws RuntimeException z powodem odrzucenia
 */
function sso_logout_verify(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('to nie JWT');
    }

    $header = json_decode((string)sso_logout_b64($parts[0]), true);
    $claims = json_decode((string)sso_logout_b64($parts[1]), true);
    $sig    = sso_logout_b64($parts[2]);
    if (!is_array($header) || !is_array($claims) || $sig === false) {
        throw new RuntimeException('uszkodzony JWT');
    }
    if (($header['alg'] ?? '') !== 'RS256') {
        throw new RuntimeException('niedozwolony alg ' . (string)($header['alg'] ?? ''));
    }

    $kid  = (string)($header['kid'] ?? '');
    $find = static function (array $keys) use ($kid): ?array {
        foreach ($keys as $k) {
            if (($k['kid'] ?? '') === $kid && !empty($k['x5c'][0])) {
                return $k;
            }
        }
        return null;
    };
    $key = $find(sso_logout_jwks()) ?? $find(sso_logout_jwks(true));
    if ($key === null) {
        throw new RuntimeException('nieznany kid');
    }

    $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split((string)$key['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
    if (openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('zly podpis');
    }

    $now = time();
    $aud = (array)($claims['aud'] ?? []);

    if (($claims['iss'] ?? '') !== (string)(oidc_discovery()['issuer'] ?? '')) {
        throw new RuntimeException('zly iss');
    }
    if (!in_array(oidc_client_id(), $aud, true)) {
        throw new RuntimeException('zly aud');
    }
    $iat = (int)($claims['iat'] ?? 0);
    if ($iat > $now + 60 || $iat < $now - SSO_LOGOUT_MAXAGE) {
        throw new RuntimeException('iat poza oknem');
    }
    if (isset($claims['exp']) && (int)$claims['exp'] < $now - 60) {
        throw new RuntimeException('token wygasl');
    }
    if (!is_array($claims['events'] ?? null) || !array_key_exists(SSO_LOGOUT_EVENT, $claims['events'])) {
        throw new RuntimeException('brak zdarzenia backchannel-logout');
    }
    if (array_key_exists('nonce', $claims)) {
        throw new RuntimeException('logout_token z nonce');
    }
    if (empty($claims['sid']) && empty($claims['sub'])) {
        throw new RuntimeException('brak sid i sub');
    }

    return $claims;
}

/** Zapisuje znacznik zakończonej sesji i sprząta stare. */
function sso_logout_revoke(array $claims): void
{
    $dir = sso_logout_dir();

    if (!empty($claims['sid'])) {
        $ok = @touch(sso_logout_marker('sid', (string)$claims['sid']));
    } else {
        $ok = @file_put_contents(sso_logout_marker('sub', (string)$claims['sub']), (string)(int)$claims['iat']) !== false;
    }
    if (!$ok) {
        throw new RuntimeException('nie moge zapisac znacznika w ' . $dir);
    }

    foreach (glob($dir . '/*') ?: [] as $f) {
        if (time() - (int)@filemtime($f) > SSO_LOGOUT_KEEP) {
            @unlink($f);
        }
    }
}
