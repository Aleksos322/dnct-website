<?php
/**
 * POST /api/auth/verify-code
 *
 * Krok 2 -> krok 3. Sprawdza 6-cyfrowy kod wpisany przez użytkownika.
 * Sam kod nie jest nigdzie przechowywany jawnie - porównujemy HMAC-i.
 *
 * Wejście: { code: "123456", csrf_token: string }
 * Wyjście: { ok: true, message: string }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

require_method('POST');
csrf_guard();

// Kluczowa ochrona przed zgadywaniem kodu: limit per IP uzupełnia limit prób
// per sesja (ten drugi da się obejść, kasując ciasteczko).
rate_limit_guard(
    'otp_verify',
    RATE_LIMIT_OTP_VERIFY,
    'Zbyt wiele prób weryfikacji z tego adresu IP. Spróbuj ponownie później.'
);

// Front wysyła 6 osobnych pól, ale akceptujemy też kod sklejony i z odstępami
// (np. wklejony z wiadomości).
$code = preg_replace('/\D/', '', request_string('code'));

$result = otp_verify((string)$code);

if (!$result['ok']) {
    // Wygasła sesja rejestracji = trzeba wrócić do kroku 1.
    if ($result['code'] === 'no_session') {
        json_error($result['message'], 409, [], ['reset' => true]);
    }

    json_error($result['message'], 422, ['code' => $result['message']], [
        'reason'        => $result['code'],
        'attempts_left' => $result['attempts_left'] ?? null,
        'can_resend'    => otp_resend_status()['allowed'],
    ]);
}

json_ok(['message' => $result['message']]);
