<?php
/**
 * Stan wieloetapowej rejestracji + jednorazowy kod (OTP) weryfikujący e-mail.
 *
 * Cały stan trzymamy w sesji PHP po stronie serwera. Dzięki temu:
 *  - krok 3 używa e-maila/loginu ZWERYFIKOWANYCH w kroku 1-2, a nie tego,
 *    co klient dośle przy finalnym zapisie (nie da się podmienić adresu
 *    po przejściu weryfikacji),
 *  - sam kod nigdy nie trafia do sesji jawnie - trzymamy tylko jego HMAC.
 */

declare(strict_types=1);

const OTP_LENGTH            = 6;
const OTP_TTL               = 600;  // kod ważny 10 minut
const OTP_MAX_ATTEMPTS      = 5;    // 5 błędnych prób unieważnia kod
const OTP_RESEND_COOLDOWN   = 60;   // 60 s między wysyłkami (zgodnie z UI)
const OTP_MAX_RESENDS       = 5;    // maks. 5 ponowień w ramach jednej rejestracji
const REGISTRATION_TTL      = 1800; // 30 min na dokończenie całego procesu

const REGISTRATION_SESSION_KEY = 'registration';

/**
 * Bieżący stan rejestracji albo null, gdy nie rozpoczęto / wygasł.
 *
 * @return array<string,mixed>|null
 */
function registration_state(): ?array
{
    $state = $_SESSION[REGISTRATION_SESSION_KEY] ?? null;
    if (!is_array($state)) {
        return null;
    }

    // Cały proces ma swój limit czasu - porzucone rejestracje nie mogą
    // wisieć w sesji bez końca.
    if (($state['started_at'] ?? 0) + REGISTRATION_TTL < time()) {
        registration_clear();
        return null;
    }

    return $state;
}

function registration_save(array $state): void
{
    $_SESSION[REGISTRATION_SESSION_KEY] = $state;
}

function registration_clear(): void
{
    unset($_SESSION[REGISTRATION_SESSION_KEY]);
}

/**
 * Rozpoczyna (lub aktualizuje) rejestrację danymi z kroku 1.
 *
 * Gdy adres e-mail się NIE zmienił, zachowujemy dotychczasowy kod, licznik
 * ponowień i ewentualne potwierdzenie. Dzięki temu powrót do kroku 1 (np. po
 * literówce w nazwisku) nie unieważnia już wysłanego kodu, ale też nie pozwala
 * wyzerować limitu wysyłek. Zmiana adresu kasuje wszystko - nowy adres musi
 * zostać potwierdzony od zera.
 *
 * @param array{first_name:string,last_name:string,username:string,email:string} $identity
 */
function registration_start(array $identity): void
{
    $previous = registration_state();
    $sameEmail = is_array($previous)
        && strcasecmp((string)($previous['identity']['email'] ?? ''), $identity['email']) === 0;

    registration_save([
        'started_at'   => $sameEmail ? (int)($previous['started_at'] ?? time()) : time(),
        'identity'     => $identity,
        'otp'          => $sameEmail ? ($previous['otp'] ?? null) : null,
        'verified_at'  => $sameEmail ? ($previous['verified_at'] ?? null) : null,
        'resend_count' => $sameEmail ? (int)($previous['resend_count'] ?? 0) : 0,
    ]);
}

/**
 * Dane z kroku 1 albo null, gdy krok 1 nie został ukończony.
 *
 * @return array{first_name:string,last_name:string,username:string,email:string}|null
 */
function registration_identity(): ?array
{
    $state = registration_state();
    $identity = $state['identity'] ?? null;
    return is_array($identity) ? $identity : null;
}

// ============================================================
// KOD JEDNORAZOWY (OTP)
// ============================================================

/** Losowy kod numeryczny o stałej długości (z wiodącymi zerami). */
function otp_generate(): string
{
    $max = (10 ** OTP_LENGTH) - 1;
    return str_pad((string)random_int(0, $max), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/** Zapisuje w sesji wyłącznie HMAC kodu - nigdy samego kodu. */
function otp_store(string $code): void
{
    $state = registration_state();
    if ($state === null) {
        return;
    }

    $salt = bin2hex(random_bytes(16));
    $state['otp'] = [
        'hash'       => hash_hmac('sha256', $code, $salt),
        'salt'       => $salt,
        'expires_at' => time() + OTP_TTL,
        'sent_at'    => time(),
        'attempts'   => 0,
    ];
    $state['resend_count'] = (int)($state['resend_count'] ?? 0) + 1;

    registration_save($state);
}

/**
 * Czy wolno teraz wysłać kod ponownie?
 *
 * @return array{allowed:bool, wait:int, reason:string}
 */
function otp_resend_status(): array
{
    $state = registration_state();
    if ($state === null) {
        return ['allowed' => true, 'wait' => 0, 'reason' => ''];
    }

    if ((int)($state['resend_count'] ?? 0) >= OTP_MAX_RESENDS) {
        return [
            'allowed' => false,
            'wait'    => 0,
            'reason'  => 'Wyczerpano limit wysyłek kodu. Rozpocznij rejestrację od nowa.',
        ];
    }

    $sentAt = (int)($state['otp']['sent_at'] ?? 0);
    $wait = ($sentAt + OTP_RESEND_COOLDOWN) - time();
    if ($sentAt > 0 && $wait > 0) {
        return [
            'allowed' => false,
            'wait'    => $wait,
            'reason'  => 'Kolejny kod możesz wysłać za ' . $wait . ' s.',
        ];
    }

    return ['allowed' => true, 'wait' => 0, 'reason' => ''];
}

/** Sekundy pozostałe do wygaśnięcia bieżącego kodu (0 = brak ważnego kodu). */
function otp_seconds_left(): int
{
    $state = registration_state();
    $expiresAt = (int)($state['otp']['expires_at'] ?? 0);
    return max(0, $expiresAt - time());
}

/**
 * Weryfikuje kod wpisany przez użytkownika.
 *
 * @return array{ok:bool, code:string, message:string, attempts_left?:int}
 *         code: 'ok' | 'no_session' | 'expired' | 'locked' | 'mismatch' | 'format'
 */
function otp_verify(string $code): array
{
    $state = registration_state();
    if ($state === null || !is_array($state['otp'] ?? null)) {
        return [
            'ok'      => false,
            'code'    => 'no_session',
            'message' => 'Sesja rejestracji wygasła. Zacznij od pierwszego kroku.',
        ];
    }

    if (!preg_match('/^\d{' . OTP_LENGTH . '}$/', $code)) {
        return [
            'ok'      => false,
            'code'    => 'format',
            'message' => 'Kod musi składać się z ' . OTP_LENGTH . ' cyfr.',
        ];
    }

    $otp = $state['otp'];

    if ((int)$otp['expires_at'] < time()) {
        return [
            'ok'      => false,
            'code'    => 'expired',
            'message' => 'Kod wygasł. Wyślij go ponownie.',
        ];
    }

    if ((int)$otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return [
            'ok'      => false,
            'code'    => 'locked',
            'message' => 'Zbyt wiele błędnych prób. Wyślij nowy kod.',
        ];
    }

    $expected = hash_hmac('sha256', $code, (string)$otp['salt']);
    if (!hash_equals((string)$otp['hash'], $expected)) {
        $state['otp']['attempts'] = (int)$otp['attempts'] + 1;
        registration_save($state);

        $left = max(0, OTP_MAX_ATTEMPTS - $state['otp']['attempts']);
        return [
            'ok'            => false,
            'code'          => 'mismatch',
            'message'       => $left > 0
                ? 'Nieprawidłowy kod. Pozostałe próby: ' . $left . '.'
                : 'Zbyt wiele błędnych prób. Wyślij nowy kod.',
            'attempts_left' => $left,
        ];
    }

    // Sukces - kod jest jednorazowy, więc od razu go usuwamy.
    $state['otp'] = null;
    $state['verified_at'] = time();
    registration_save($state);

    return ['ok' => true, 'code' => 'ok', 'message' => 'Adres e-mail został potwierdzony.'];
}

/** Czy użytkownik przeszedł weryfikację e-maila i może ustawić hasło? */
function registration_is_verified(): bool
{
    $state = registration_state();
    return $state !== null && !empty($state['verified_at']);
}

/** Znacznik czasu potwierdzenia e-maila (ISO 8601) - trafia do atrybutów konta. */
function registration_verified_at_iso(): string
{
    $state = registration_state();
    $timestamp = (int)($state['verified_at'] ?? time());
    return gmdate('c', $timestamp);
}
