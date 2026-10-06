<?php
/**
 * Liczenie odwiedzin stron (game, web, account) - po stronie serwera, bez
 * ciasteczek. Zapis przy zamknięciu żądania, wyłącznie dla zwykłych stron
 * HTML (bez API, zasobów, przekierowań, panelu admina i bety). Błąd zapisu
 * nigdy nie psuje strony. Panel gier zapisuje do tej samej tabeli z
 * resources/views/templates/wrapper.blade.php (ta sama sól i te same reguły).
 * Tabela: backend/migrations/017_odwiedziny.sql. Pulpit: admin.lan /statystyki.
 */

declare(strict_types=1);

const ODWIEDZINY_POMIN_HOSTY = ['admin.lan.dnct.pl', 'beta.dnct.pl'];
const ODWIEDZINY_POMIN_SCIEZKI = '#^/(api|assets|auth/backchannel-logout|check_session|pobierz|cdn-cgi)(/|\.|$)|\.(xml|txt|json|ico|png|jpe?g|svg|webp|css|js|map)$#i';

/** [przegladarka, system, urzadzenie, robot] z nagłówka User-Agent. */
function odwiedziny_ua(string $ua): array
{
    $robot = $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|uptime|curl|wget|python|go-http|java\/|headless|lighthouse|scan|httpclient|axios|node-fetch|okhttp|semrush|ahrefs|mj12/i', $ua);
    $b = match (true) {
        (bool)preg_match('/Edg\//', $ua) => 'Edge',
        (bool)preg_match('/OPR\/|Opera/', $ua) => 'Opera',
        (bool)preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
        (bool)preg_match('/Firefox\//', $ua) => 'Firefox',
        (bool)preg_match('/Chrome\//', $ua) => 'Chrome',
        (bool)preg_match('/Safari\//', $ua) => 'Safari',
        default => 'Inne',
    };
    $s = match (true) {
        (bool)preg_match('/Android/', $ua) => 'Android',
        (bool)preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
        (bool)preg_match('/Windows/', $ua) => 'Windows',
        (bool)preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
        (bool)preg_match('/Linux|X11/', $ua) => 'Linux',
        default => 'Inne',
    };
    $u = preg_match('/iPad|Tablet/', $ua) ? 'tablet' : (preg_match('/Mobi|iPhone|Android/', $ua) ? 'telefon' : 'komputer');
    return [$b, $s, $u, $robot];
}

/** Skrót gościa: nie da się z niego odtworzyć IP, zmienia się co miesiąc. */
function odwiedziny_gosc(string $ip, string $ua): string
{
    $sol = (string)env('STATS_SALT', '');
    return substr(hash_hmac('sha256', $ip . '|' . $ua . '|' . gmdate('Y-m'), $sol !== '' ? $sol : 'dnct'), 0, 16);
}

function odwiedziny_zapisz(string $host, string $sciezka, int $status, string $ip, string $ua, string $referer, string $kraj, ?int $bajty): void
{
    try {
        [$b, $s, $u, $robot] = odwiedziny_ua($ua);
        $zrodlo = strtolower((string)parse_url($referer, PHP_URL_HOST));
        if ($zrodlo === $host || $zrodlo === '') {
            $zrodlo = null; // wejście bezpośrednie albo przejście w obrębie strony
        }
        $kraj = preg_match('/^[A-Z]{2}$/', $kraj) && $kraj !== 'XX' ? $kraj : null;
        dnct_db()->prepare(
            'INSERT INTO odwiedziny (czas, host, sciezka, status, gosc, kraj, przegladarka, system, urzadzenie, zrodlo, robot, bajty)
             VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([substr($host, 0, 64), substr($sciezka, 0, 255), $status, odwiedziny_gosc($ip, $ua), $kraj, $b, $s, $u,
                    $zrodlo !== null ? substr($zrodlo, 0, 100) : null, $robot ? 1 : 0, $bajty]);
    } catch (Throwable $e) {
        error_log('odwiedziny: ' . $e->getMessage());
    }
}

/** Rejestrowane w bootstrapie - decyzja, czy liczyć, zapada po wygenerowaniu strony. */
function odwiedziny_po_zadaniu(): void
{
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || ($_SERVER['HTTP_X_FRAGMENT'] ?? '') === '1') {
        return;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $sciezka = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if ($host === '' || in_array($host, ODWIEDZINY_POMIN_HOSTY, true) || preg_match(ODWIEDZINY_POMIN_SCIEZKI, $sciezka)) {
        return;
    }
    $status = (int)http_response_code();
    if ($status >= 300 && $status < 400) {
        return;
    }
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) {
            return;
        }
    }
    odwiedziny_zapisz($host, $sciezka, $status ?: 200, client_ip(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        (string)($_SERVER['HTTP_REFERER'] ?? ''), strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')),
        (int)($GLOBALS['odwiedziny_bajty'] ?? 0) + (int)ob_get_length());
}
