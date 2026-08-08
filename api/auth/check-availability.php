<?php
/**
 * POST /api/auth/check-availability
 *
 * Sprawdza w tle, czy login / e-mail są jeszcze wolne (podpowiedź inline
 * w kroku 1). Odpowiedź jest tylko wskazówką - wiążącą decyzję podejmuje
 * /api/auth/register.php przy finalnym zapisie.
 *
 * Wejście:  { field: "username"|"email", value: string, csrf_token: string }
 * Wyjście:  { ok: true, status: "available"|"taken"|"invalid"|"unknown", message: string }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

require_method('POST');
csrf_guard();

// Ochrona przed masowym enumerowaniem kont z jednego IP.
rate_limit_guard('avail', RATE_LIMIT_AVAILABILITY, 'Zbyt wiele zapytań. Spróbuj ponownie za chwilę.');

$field = request_string('field');
if (!in_array($field, ['username', 'email'], true)) {
    json_error('Nieznane pole do sprawdzenia.', 400);
}

$value = request_string('value');

$error = $field === 'username'
    ? validate_username_value($value)
    : validate_email_value($value);

if ($error !== null) {
    json_ok(['status' => 'invalid', 'message' => $error]);
}

$exists = authentik_user_exists($field, $value);

if ($exists === null) {
    // Nie blokujemy użytkownika, gdy Authentik nie odpowiada.
    json_ok(['status' => 'unknown', 'message' => 'Nie udało się teraz sprawdzić dostępności.']);
}

if ($exists) {
    json_ok([
        'status'  => 'taken',
        'message' => $field === 'username'
            ? 'Ta nazwa użytkownika jest już zajęta.'
            : 'Ten adres e-mail jest już zarejestrowany.',
    ]);
}

json_ok([
    'status'  => 'available',
    'message' => $field === 'username' ? 'Nazwa użytkownika jest wolna.' : 'Adres e-mail jest wolny.',
]);
