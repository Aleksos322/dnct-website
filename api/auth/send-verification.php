<?php
/**
 * POST /api/auth/send-verification
 *
 * Krok 1 -> krok 2. Waliduje dane identyfikacyjne, zapisuje je w sesji,
 * generuje 6-cyfrowy kod i wysyła go mailem.
 *
 * Wejście (mode="start", domyślnie):
 *   { first_name, last_name, username, email, website_hp, csrf_token }
 * Wejście (mode="resend"): { mode: "resend", csrf_token }
 *
 * Wyjście: { ok: true, email, email_masked, expires_in, resend_in, verified }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/mail/mail.php';

require_method('POST');
csrf_guard();

/** Zaciera adres na potrzeby UI: jan.kowalski@example.com -> j***********i@example.com */
function mask_email(string $email): string
{
    $atPosition = strrpos($email, '@');
    if ($atPosition === false || $atPosition < 1) {
        return $email;
    }

    $local = substr($email, 0, $atPosition);
    $domain = substr($email, $atPosition);
    $length = mb_strlen($local, 'UTF-8');

    if ($length <= 2) {
        return mb_substr($local, 0, 1, 'UTF-8') . '*' . $domain;
    }

    return mb_substr($local, 0, 1, 'UTF-8')
        . str_repeat('*', $length - 2)
        . mb_substr($local, -1, 1, 'UTF-8')
        . $domain;
}

/** Wspólna odpowiedź sukcesu - front na jej podstawie ustawia krok 2. */
function respond_with_state(string $email, bool $mailSent): never
{
    json_ok([
        'email'        => $email,
        'email_masked' => mask_email($email),
        'expires_in'   => otp_seconds_left(),
        'resend_in'    => max(OTP_RESEND_COOLDOWN - (time() - (int)(registration_state()['otp']['sent_at'] ?? 0)), 0),
        'verified'     => registration_is_verified(),
        'mail_sent'    => $mailSent,
    ]);
}

$mode = request_string('mode', 'start') === 'resend' ? 'resend' : 'start';

// ------------------------------------------------------------
// Limit wysyłek per IP - zanim wygenerujemy kod i ruszymy SMTP.
// ------------------------------------------------------------
rate_limit_guard(
    'otp_send',
    RATE_LIMIT_OTP_SEND,
    'Zbyt wiele prób wysłania kodu z tego adresu IP. Spróbuj ponownie później.'
);

// ============================================================
// TRYB "resend" - dane mamy już w sesji, wysyłamy nowy kod
// ============================================================
if ($mode === 'resend') {
    $identity = registration_identity();
    if ($identity === null) {
        json_error('Sesja rejestracji wygasła. Zacznij od pierwszego kroku.', 409, [], ['reset' => true]);
    }

    $resend = otp_resend_status();
    if (!$resend['allowed']) {
        json_error($resend['reason'], 429, [], ['resend_in' => $resend['wait']]);
    }

    $code = otp_generate();
    otp_store($code);

    $result = send_verification_code_mail($identity['email'], $code, $identity['first_name'], (int)(OTP_TTL / 60));
    if (!$result['ok']) {
        json_error('Nie udało się wysłać wiadomości. Spróbuj ponownie za chwilę.', 502);
    }

    respond_with_state($identity['email'], true);
}

// ============================================================
// TRYB "start" - pełna walidacja kroku 1
// ============================================================

// Honeypot: pole ukryte przed człowiekiem. Wypełnione = bot. Odpowiadamy
// sukcesem (bot nie dowie się, że został wykryty), ale nie zakładamy sesji
// rejestracji ani nie wysyłamy maila.
if (request_string('website_hp') !== '') {
    error_log('Rejestracja: krok 1 odrzucony przez honeypot, IP: ' . client_ip());
    $decoy = request_string('email');
    json_ok([
        'email'        => $decoy,
        'email_masked' => mask_email($decoy),
        'expires_in'   => OTP_TTL,
        'resend_in'    => OTP_RESEND_COOLDOWN,
        'verified'     => false,
        'mail_sent'    => true,
    ]);
}

$identity = [
    'first_name' => request_string('first_name'),
    'last_name'  => request_string('last_name'),
    'username'   => request_string('username'),
    'email'      => (string)filter_var(request_string('email'), FILTER_SANITIZE_EMAIL),
];

$errors = validate_identity_step($identity);
if ($errors) {
    json_error(reset($errors), 422, $errors);
}

// ------------------------------------------------------------
// Unikalność loginu i e-maila (case-insensitive).
// Gdy Authentik nie odpowiada, przepuszczamy dalej - finalny zapis
// w /api/auth/register.php i tak sprawdzi to ponownie.
// ------------------------------------------------------------
if (authentik_user_exists('username', $identity['username']) === true) {
    json_error('Ta nazwa użytkownika jest już zajęta.', 422, [
        'username' => 'Ta nazwa użytkownika jest już zajęta.',
    ]);
}

if (authentik_user_exists('email', $identity['email']) === true) {
    json_error('Ten adres e-mail jest już zarejestrowany. Zaloguj się lub odzyskaj hasło.', 422, [
        'email' => 'Ten adres e-mail jest już zarejestrowany.',
    ]);
}

// Zachowuje kod / potwierdzenie, jeśli adres e-mail się nie zmienił.
registration_start($identity);

// Adres już potwierdzony (powrót do kroku 1 bez zmiany e-maila) - nie ma po co
// wysyłać kolejnego kodu, front przeskoczy od razu do kroku 3.
if (registration_is_verified()) {
    respond_with_state($identity['email'], false);
}

// Ważny kod jest już w drodze i trwa cooldown - nie wysyłamy drugiego maila.
if (otp_seconds_left() > 0 && !otp_resend_status()['allowed']) {
    respond_with_state($identity['email'], false);
}

$code = otp_generate();
otp_store($code);

$result = send_verification_code_mail($identity['email'], $code, $identity['first_name'], (int)(OTP_TTL / 60));
if (!$result['ok']) {
    json_error(
        'Nie udało się wysłać kodu na podany adres. Sprawdź adres e-mail lub spróbuj ponownie za chwilę.',
        502,
        ['email' => 'Nie udało się dostarczyć wiadomości na ten adres.']
    );
}

respond_with_state($identity['email'], true);
