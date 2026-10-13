<?php
/**
 * Pomocniki HTTP dla endpointów API (JSON in / JSON out).
 */

declare(strict_types=1);

/** Kończy żądanie odpowiedzią JSON. */
function json_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/** Sukces: {"ok":true, ...} */
function json_ok(array $payload = []): never
{
    if (isset($payload['message']) && is_string($payload['message'])) {
        $payload['message'] = json_tlumacz($payload['message']);
    }
    json_response(['ok' => true] + $payload);
}

/**
 * Komunikat dla człowieka w języku strony (EN/DE, backend/lib/i18n.php).
 * API nie przechodzi przez filtr tłumaczeń HTML, więc teksty z json_error()
 * i json_ok() tłumaczymy tu - tym samym słownikiem i wzorcami. Tekst bez
 * wpisu zostaje po polsku.
 */
function json_tlumacz(string $tekst): string
{
    if (!function_exists('i18n_lang') || i18n_lang() === 'pl') {
        return $tekst;
    }
    $t = trim((string)preg_replace('~\s+~u', ' ', $tekst));
    return i18n_ceny(i18n_tekst($t, i18n_lang()) ?? $t, i18n_lang());
}

/**
 * Błąd: {"ok":false,"message":...,"errors":{pole:komunikat}}
 * `errors` pozwala frontendowi podświetlić konkretne pola.
 *
 * @param array<string,string> $fieldErrors
 */
function json_error(string $message, int $status = 422, array $fieldErrors = [], array $extra = []): never
{
    json_response([
        'ok'      => false,
        'message' => json_tlumacz($message),
        'errors'  => (object)array_map(static fn($e) => is_string($e) ? json_tlumacz($e) : $e, $fieldErrors),
    ] + $extra, $status);
}

/** Wymusza konkretną metodę HTTP na endpoincie. */
function require_method(string $method): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== $method) {
        header('Allow: ' . $method);
        json_error('Nieobsługiwana metoda żądania.', 405);
    }
}

/**
 * Odczytuje ciało żądania niezależnie od tego, czy przyszło jako
 * application/json czy application/x-www-form-urlencoded.
 *
 * @return array<string,mixed>
 */
function request_payload(): array
{
    static $payload = null;
    if ($payload !== null) {
        return $payload;
    }

    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        $payload = is_array($decoded) ? $decoded : [];
    } else {
        $payload = $_POST;
    }

    return $payload;
}

/** Pojedyncze pole tekstowe z ciała żądania (przycięte). */
function request_string(string $key, string $default = ''): string
{
    $payload = request_payload();
    $value = $payload[$key] ?? $default;
    return is_scalar($value) ? trim((string)$value) : $default;
}

/** Pole typu checkbox: '1' / 'true' / 'on' / true => true. */
function request_bool(string $key): bool
{
    $payload = request_payload();
    $value = $payload[$key] ?? false;
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'on', 'yes'], true);
}

/**
 * Hasło pobieramy BEZ trim() - spacja na końcu jest legalnym znakiem hasła,
 * a jej ucięcie sprawiłoby, że użytkownik nie zalogowałby się później tym,
 * co wpisał przy rejestracji.
 */
function request_raw(string $key): string
{
    $payload = request_payload();
    $value = $payload[$key] ?? '';
    return is_scalar($value) ? (string)$value : '';
}
