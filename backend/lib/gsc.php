<?php
/**
 * Klient Google Search Console - jedyne miejsce, które rozmawia z API GSC.
 *
 * Autoryzacja idzie przez konto usługi (service account), nie OAuth
 * użytkownika - kluczem prywatnym podpisujemy krótkotrwały JWT i wymieniamy
 * go na token dostępu (godzina ważności, RFC 7523 - JWT Bearer). Żaden
 * pakiet Composera do tego nie jest potrzebny: cały przepływ to dwa podpisy
 * openssl i dwa zapytania HTTP, więc nie warto ściągać google/apiclient
 * dla dwóch endpointów.
 *
 * GSC_SERVICE_ACCOUNT_FILE w .env wskazuje plik JSON pobrany z Google Cloud
 * Console (Credentials -> Service Accounts -> Keys). To konto ma w Search
 * Console dostęp "Restricted" (tylko odczyt) - dodane ręcznie w GSC, nie
 * przez ten kod.
 */

declare(strict_types=1);

const GSC_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GSC_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';
const GSC_SEARCHANALYTICS_BASE = 'https://www.googleapis.com/webmasters/v3';
const GSC_INSPECTION_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

/** Dane konta usługi wczytane raz na żądanie. */
function gsc_credentials(): ?array
{
    static $creds = null;
    if ($creds !== null) {
        return $creds ?: null;
    }

    $path = (string)env('GSC_SERVICE_ACCOUNT_FILE', '');
    if ($path === '' || !is_readable($path)) {
        error_log('GSC: brak lub nieczytelny GSC_SERVICE_ACCOUNT_FILE (' . $path . ')');
        $creds = false;
        return null;
    }

    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
        error_log('GSC: plik konta usługi jest niekompletny');
        $creds = false;
        return null;
    }

    $creds = $data;
    return $creds;
}

function gsc_base64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * Token dostępu, cache'owany na dysku - godzina ważności, więc nie ma sensu
 * podpisywać nowego JWT przy każdym odświeżeniu widgetu w panelu. Ten sam
 * trik co cache dokumentu discovery OIDC (patrz CLAUDE.md), z tym samym
 * zastrzeżeniem: Apache ma PrivateTmp=yes, więc tego pliku nie zobaczysz
 * w zwykłym /tmp spoza procesu PHP-FPM.
 */
function gsc_access_token(): ?string
{
    $creds = gsc_credentials();
    if ($creds === null) {
        return null;
    }

    $cacheFile = sys_get_temp_dir() . '/dnct_gsc_token_' . md5((string)$creds['client_email']) . '.json';
    if (is_readable($cacheFile)) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return (string)$cached['access_token'];
        }
    }

    $now = time();
    $header = gsc_base64url((string)json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = gsc_base64url((string)json_encode([
        'iss'   => $creds['client_email'],
        'scope' => GSC_SCOPE,
        'aud'   => GSC_TOKEN_URL,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signature = '';
    $signed = openssl_sign($header . '.' . $claims, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
    if (!$signed) {
        error_log('GSC: podpisanie JWT nie powiodło się (błędny klucz prywatny?)');
        return null;
    }

    $jwt = $header . '.' . $claims . '.' . gsc_base64url($signature);

    $ch = curl_init(GSC_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = $response !== false ? json_decode($response, true) : null;
    if ($httpCode !== 200 || !is_array($body) || empty($body['access_token'])) {
        error_log('GSC: wymiana JWT na token nie powiodła się (HTTP ' . $httpCode . '): ' . (string)$response);
        return null;
    }

    file_put_contents($cacheFile, (string)json_encode([
        'access_token' => $body['access_token'],
        'expires_at'   => $now + (int)($body['expires_in'] ?? 3600),
    ]));

    return (string)$body['access_token'];
}

/** Wspólny wykonawca żądań do API GSC - Bearer token, JSON w obie strony. */
function gsc_request(string $method, string $url, ?array $body = null): array
{
    $token = gsc_access_token();
    if ($token === null) {
        return ['http_code' => 0, 'body' => null, 'error' => 'Brak tokenu dostępu'];
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? []);
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

/** Lista właściwości (properties) widocznych temu kontu usługi w GSC. */
function gsc_sites(): array
{
    $res = gsc_request('GET', GSC_SEARCHANALYTICS_BASE . '/sites');
    return $res['body']['siteEntry'] ?? [];
}

/**
 * Kliknięcia/wyświetlenia/CTR/pozycja z ostatnich $days dni.
 *
 * @param string $siteUrl Dokładnie tak jak zwraca gsc_sites() - dla property
 *                         typu Domain to "sc-domain:dnct.pl", NIE adres URL.
 * @return array{rows: array, error: ?string}
 */
function gsc_search_analytics(string $siteUrl, int $days = 28, array $dimensions = ['query']): array
{
    $end   = date('Y-m-d', strtotime('-2 days'));   // GSC ma ok. 2-dniowe opóźnienie danych
    $start = date('Y-m-d', strtotime('-' . ($days + 2) . ' days'));

    $res = gsc_request('POST', GSC_SEARCHANALYTICS_BASE . '/sites/' . rawurlencode($siteUrl) . '/searchAnalytics/query', [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => $dimensions,
        'rowLimit'   => 25,
    ]);

    if ($res['http_code'] !== 200) {
        error_log('GSC: search analytics HTTP ' . $res['http_code'] . ': ' . json_encode($res['body']));
        return ['rows' => [], 'error' => 'Błąd zapytania do Search Console (HTTP ' . $res['http_code'] . ')'];
    }

    return ['rows' => $res['body']['rows'] ?? [], 'error' => null];
}

/** Status indeksacji pojedynczego URL-a - to samo co widać w "URL Inspection" w GSC. */
function gsc_url_inspection(string $siteUrl, string $inspectUrl): array
{
    $res = gsc_request('POST', GSC_INSPECTION_URL, [
        'inspectionUrl' => $inspectUrl,
        'siteUrl'       => $siteUrl,
    ]);

    if ($res['http_code'] !== 200) {
        error_log('GSC: url inspection HTTP ' . $res['http_code'] . ': ' . json_encode($res['body']));
        return ['result' => null, 'error' => 'Błąd zapytania do Search Console (HTTP ' . $res['http_code'] . ')'];
    }

    return ['result' => $res['body']['inspectionResult'] ?? null, 'error' => null];
}
