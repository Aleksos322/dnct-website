<?php
/**
 * Limitowanie liczby żądań per IP (przesuwane okno czasowe, magazyn plikowy).
 *
 * Topologia beta.dnct.pl: klient -> Cloudflare -> reverse proxy 192.168.50.10
 * -> Apache (vhost :8910). Proxy nadpisuje X-Forwarded-For / X-Real-IP adresem
 * swojego bezpośredniego klienta (czyli IP krawędzi Cloudflare), natomiast
 * nagłówek CF-Connecting-IP z prawdziwym adresem klienta przekazuje bez zmian -
 * i tylko on daje poprawny adres do liczenia limitów.
 */

declare(strict_types=1);

/** Adresy zaufanych proxy. Dopiero żądaniu z nich ufamy w kwestii nagłówków. */
const TRUSTED_PROXIES = ['192.168.50.10', '127.0.0.1', '::1'];

// Limity w formacie [liczba żądań, okno w sekundach].
const RATE_LIMIT_REGISTER     = [10, 900];  // 10 finalnych rejestracji / 15 min
const RATE_LIMIT_AVAILABILITY = [40, 300];  // 40 zapytań o dostępność / 5 min
const RATE_LIMIT_LOGIN        = [10, 900];  // 10 prób logowania / 15 min
const RATE_LIMIT_OTP_SEND     = [8, 900];   // 8 wysyłek kodu / 15 min (anty-spam mailowy)
const RATE_LIMIT_OTP_VERIFY   = [20, 900];  // 20 prób weryfikacji kodu / 15 min

function rate_limit_dir(): string
{
    return sys_get_temp_dir() . '/dnct_rate_limit';
}

function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    // Nagłówkom ufamy WYŁĄCZNIE gdy żądanie przyszło od zaufanego proxy -
    // inaczej dowolny klient podszyłby się pod cudze IP i ominął limity.
    if (!in_array($remote, TRUSTED_PROXIES, true)) {
        return $remote;
    }

    // 1. Cloudflare: prawdziwy adres klienta, nadpisywany na krawędzi.
    $cfIp = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
        return $cfIp;
    }

    // 2. Bez Cloudflare: ostatni wpis X-Forwarded-For to adres widziany przez
    //    zaufane proxy (wcześniejsze wpisy potrafi podrobić klient).
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        $candidate = (string)end($chain);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return $remote;
}

/**
 * Przesuwane okno czasowe per IP + koszyk.
 * Zwraca ['allowed' => bool, 'retry_after' => int].
 * W razie problemów z zapisem przepuszcza żądanie (fail-open) - limit nie może
 * zablokować rejestracji na serwerze bez zapisywalnego katalogu tymczasowego.
 */
function rate_limit_hit(string $bucket, array $rule): array
{
    [$limit, $window] = $rule;
    $pass = ['allowed' => true, 'retry_after' => 0];

    $dir = rate_limit_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return $pass;
    }

    $file = $dir . '/' . $bucket . '_' . hash('sha256', client_ip()) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return $pass;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return $pass;
        }

        $raw = stream_get_contents($handle);
        $hits = json_decode((string)$raw, true);
        if (!is_array($hits)) {
            $hits = [];
        }

        $now = time();
        $hits = array_values(array_filter($hits, static function ($timestamp) use ($now, $window) {
            return is_int($timestamp) && $timestamp > $now - $window;
        }));

        $allowed = count($hits) < $limit;
        if ($allowed) {
            $hits[] = $now;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)json_encode($hits));
        fflush($handle);

        return [
            'allowed'     => $allowed,
            'retry_after' => $allowed ? 0 : max(1, ($hits[0] + $window) - $now),
        ];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
        rate_limit_gc();
    }
}

/**
 * Limit dla endpointu API - w razie przekroczenia kończy żądanie kodem 429
 * wraz z nagłówkiem Retry-After.
 */
function rate_limit_guard(string $bucket, array $rule, string $message): void
{
    $limit = rate_limit_hit($bucket, $rule);
    if ($limit['allowed']) {
        return;
    }

    header('Retry-After: ' . $limit['retry_after']);
    json_error($message, 429, [], ['retry_after' => $limit['retry_after']]);
}

/** Sprzątanie starych liczników (uruchamiane losowo, ~1% żądań). */
function rate_limit_gc(): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }

    $cutoff = time() - 86400;
    foreach (glob(rate_limit_dir() . '/*.json') ?: [] as $file) {
        if ((int)@filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}
