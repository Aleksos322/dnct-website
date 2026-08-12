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
 *
 * DLACZEGO 403, A NIE 419.
 *
 * Wcześniej było tu 419 ("Page Expired" - konwencja Laravela) z komentarzem,
 * że front rozpoznaje ten kod. Dwie rzeczy się nie zgadzały:
 *
 *   1. 419 nie jest zarejestrowanym kodem HTTP i ten stos zamienia go po
 *      drodze na 500 - sprawdzone: 400, 422, 451 i 503 przechodzą nietknięte,
 *      419 wraca do przeglądarki jako 500. Nieudany token wyglądał więc jak
 *      awaria serwera.
 *   2. Żaden plik w assets/js nigdy tego kodu nie sprawdzał, więc obiecana
 *      obsługa "odśwież stronę" nie istniała.
 *
 * 403 przechodzi bez zmian i znaczy dokładnie to, co się stało: żądanie było
 * zrozumiałe, ale nie zostało wpuszczone. Rozpoznanie po stronie frontu opiera
 * się na polu `reason` w treści, a nie na kodzie - pole przeżyje każdy proxy.
 */
function csrf_guard(): void
{
    if (!csrf_check_request()) {
        json_error(
            'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
            403,
            [],
            ['reason' => 'csrf_expired']
        );
    }
}
