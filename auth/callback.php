<?php
/**
 * Powrót z Authentika po zalogowaniu (OIDC redirect flow).
 *
 * Authentik odsyła tu przeglądarkę z jednorazowym kodem. Wymieniamy go na
 * tokeny (serwer-serwer), pobieramy dane konta i zakładamy własną sesję PHP.
 *
 * Kluczowa różnica względem starego logowania: przeglądarka była przed chwilą
 * NA STRONIE Authentika i została z jego ciasteczkiem sesji. Dzięki temu panel
 * Pterodactyla rozpozna ją bez ponownego pytania o hasło - to jest to SSO,
 * o które chodziło.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

const LOGIN_URL = '/login';

/** Wraca na ekran logowania z komunikatem. */
function callback_fail(string $message, ?string $logDetail = null): never
{
    if ($logDetail !== null) {
        error_log('OIDC callback: ' . $logDetail);
    }
    $_SESSION['error'] = $message;
    unset($_SESSION['oidc_login']);
    header('Location: ' . LOGIN_URL);
    exit();
}

// ------------------------------------------------------------
// 1. Authentik może wrócić z błędem zamiast kodu (np. odmowa dostępu)
// ------------------------------------------------------------
if (isset($_GET['error'])) {
    $err = (string)$_GET['error'];
    callback_fail(
        $err === 'access_denied'
            ? 'Logowanie zostało anulowane.'
            : 'Logowanie nie powiodło się. Spróbuj ponownie.',
        'Authentik zwrocil blad: ' . $err . ' / ' . (string)($_GET['error_description'] ?? '')
    );
}

// ------------------------------------------------------------
// 2. Musimy mieć rozpoczętą próbę logowania w sesji
// ------------------------------------------------------------
$pending = $_SESSION['oidc_login'] ?? null;

if (!is_array($pending) || empty($pending['state']) || empty($pending['verifier'])) {
    callback_fail('Sesja logowania wygasła. Spróbuj ponownie.', 'brak oidc_login w sesji');
}

if (time() - (int)($pending['created'] ?? 0) > OIDC_LOGIN_TTL) {
    callback_fail('Sesja logowania wygasła. Spróbuj ponownie.', 'proba logowania przeterminowana');
}

// ------------------------------------------------------------
// 3. state - powrót musi pasować do żądania, które sami rozpoczęliśmy
// ------------------------------------------------------------
$state = (string)($_GET['state'] ?? '');
if ($state === '' || !hash_equals((string)$pending['state'], $state)) {
    callback_fail('Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.', 'niezgodny state');
}

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    callback_fail('Logowanie nie powiodło się. Spróbuj ponownie.', 'brak parametru code');
}

// ------------------------------------------------------------
// 4. Kod -> tokeny (z PKCE), potem dane użytkownika
// ------------------------------------------------------------
try {
    $tokens = oidc_exchange_code($code, (string)$pending['verifier']);

    // nonce wiąże id_token z TĄ próbą logowania (ochrona przed odtworzeniem).
    if (!empty($tokens['id_token'])) {
        $claims = oidc_decode_id_token((string)$tokens['id_token']);
        $nonce  = (string)($claims['nonce'] ?? '');
        if ($nonce === '' || !hash_equals((string)$pending['nonce'], $nonce)) {
            callback_fail('Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.', 'niezgodny nonce');
        }
    }

    $user = oidc_userinfo((string)$tokens['access_token']);
} catch (RuntimeException $e) {
    callback_fail('Nie udało się dokończyć logowania. Spróbuj ponownie.', $e->getMessage());
}

$username = (string)($user['preferred_username'] ?? $user['nickname'] ?? '');
$email    = (string)($user['email'] ?? '');

if ($username === '' && $email === '') {
    callback_fail('Konto nie ma nazwy użytkownika ani adresu e-mail.', 'userinfo bez username i email');
}
if ($username === '') {
    $username = $email;
}

// ------------------------------------------------------------
// 5. Nasza sesja. Te same klucze co dotychczas, żeby navbar i reszta
//    strony działały bez żadnych zmian.
// ------------------------------------------------------------
session_regenerate_id(true);

$_SESSION['authentik_username']     = $username;
$_SESSION['authentik_email']        = $email !== '' ? $email : null;
$_SESSION['authentik_is_superuser'] = (bool)($user['is_superuser'] ?? false);
$_SESSION['authentik_sub']          = (string)($user['sub'] ?? '');
$_SESSION['logged_in_at']           = time();

// Potrzebny przy wylogowaniu jako id_token_hint - bez niego Authentik nie
// odeśle użytkownika z powrotem na stronę, tylko zostawi go na swoim ekranie.
$_SESSION['oidc_id_token'] = (string)($tokens['id_token'] ?? '');

$returnTo = oidc_safe_return_to((string)($pending['return_to'] ?? '/index.php'));
unset($_SESSION['oidc_login']);

// ------------------------------------------------------------
// 6. Konto w Pterodactylu zakładamy z wyprzedzeniem (JIT), żeby pierwsze
//    wejście do panelu nie kończyło się błędem "brak użytkownika".
//    Błąd tutaj nie może zablokować logowania na samą stronę.
// ------------------------------------------------------------
if ($email !== '') {
    try {
        $given  = (string)($user['given_name'] ?? '');
        $family = (string)($user['family_name'] ?? '');

        pterodactyl_ensure_user(
            $email,
            $username,
            $given !== '' ? $given : 'User',
            $family !== '' ? $family : 'Account'
        );
    } catch (Throwable $e) {
        error_log('OIDC callback: JIT Pterodactyl nieudany dla ' . $email . ': ' . $e->getMessage());
    }
}

// Jedno konto DNCT: to samo konto dostaje też hosting WWW (pakiet bez
// zasobów, dopóki nic nie kupi). Tak jak wyżej - błąd nie blokuje logowania.
if ($email !== '') {
    try {
        hosting_ensure_user($email, (string)($user['name'] ?? $username));
    } catch (Throwable $e) {
        error_log('OIDC callback: JIT hostingu nieudany dla ' . $email . ': ' . $e->getMessage());
    }
}

$_SESSION['flash'][] = ['type' => 'success', 'message' => 'Zalogowano jako ' . $username];

header('Location: ' . $returnTo);
exit();
