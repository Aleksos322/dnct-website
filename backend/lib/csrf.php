<?php
/**
 * Ochrona przed CSRF - jeden token na sesję, porównywany w czasie stałym.
 */

declare(strict_types=1);

/** Token dla formularza (tworzony leniwie przy pierwszym użyciu). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Weryfikacja tokenu z klasycznego POST-a (formularz przeładowujący stronę).
 */
function csrf_check(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

/**
 * Weryfikacja tokenu dla endpointów API - token może przyjść w ciele
 * (JSON/form) albo w nagłówku X-CSRF-Token.
 */
function csrf_check_request(): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    $payload = request_payload();
    $token = (string)($payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Przerywa żądanie API, gdy token jest nieprawidłowy.
 * 419 = "Page Expired" - front rozpoznaje ten kod i prosi o odświeżenie strony.
 */
function csrf_guard(): void
{
    if (!csrf_check_request()) {
        json_error('Sesja wygasła. Odśwież stronę i spróbuj ponownie.', 419);
    }
}
