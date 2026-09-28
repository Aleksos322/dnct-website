<?php
/**
 * POST /api/auth/register
 *
 * Krok 3 - finalne założenie konta. Dane identyfikacyjne bierzemy WYŁĄCZNIE
 * z sesji (potwierdzonej kodem w kroku 2), a nie z ciała żądania - dzięki temu
 * nie da się przejść weryfikacji jednym adresem, a założyć konta na inny.
 *
 * Wejście: { password, password_confirm, terms, marketing, website_hp, csrf_token }
 * Wyjście: { ok: true, redirect: "/login" }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/jellyfin.php';
require_once dirname(__DIR__, 2) . '/backend/lib/jellyfin_mail.php';
require_once dirname(__DIR__, 2) . '/backend/lib/user_profiles.php';

require_method('POST');
csrf_guard();

rate_limit_guard(
    'register',
    RATE_LIMIT_REGISTER,
    'Zbyt wiele prób rejestracji z tego adresu IP. Spróbuj ponownie za kilkanaście minut.'
);

const REGISTER_REDIRECT = '/login';

// Honeypot - bot dostaje odpowiedź nieodróżnialną od sukcesu.
if (request_string('website_hp') !== '') {
    error_log('Rejestracja: krok 3 odrzucony przez honeypot, IP: ' . client_ip());
    json_ok(['redirect' => REGISTER_REDIRECT]);
}

// ------------------------------------------------------------
// 1. Bez potwierdzonego e-maila nie ma rejestracji.
// ------------------------------------------------------------
$identity = registration_identity();
if ($identity === null) {
    json_error('Sesja rejestracji wygasła. Zacznij od pierwszego kroku.', 409, [], ['reset' => true]);
}

if (!registration_is_verified()) {
    json_error('Najpierw potwierdź adres e-mail kodem z wiadomości.', 409, [], ['step' => 2]);
}

// ------------------------------------------------------------
// 2. Walidacja hasła i zgód (hasła nie przycinamy - spacja to legalny znak).
// ------------------------------------------------------------
$password = request_raw('password');
$passwordConfirm = request_raw('password_confirm');
$acceptedTerms = request_bool('terms');
$marketing = request_bool('marketing');

$errors = [];

if (($passwordError = validate_password_value($password)) !== null) {
    $errors['password'] = $passwordError;
}
if ($passwordConfirm === '') {
    $errors['password_confirm'] = 'Powtórz hasło.';
} elseif (!hash_equals($password, $passwordConfirm)) {
    $errors['password_confirm'] = 'Hasła nie są identyczne.';
}
if (!$acceptedTerms) {
    $errors['terms'] = 'Musisz zaakceptować Regulamin i Politykę Prywatności.';
}

if ($errors) {
    json_error(reset($errors), 422, $errors);
}

// ------------------------------------------------------------
// 3. Ostatnie sprawdzenie unikalności - konto mogło powstać w trakcie
//    wypełniania formularza przez kogoś innego.
// ------------------------------------------------------------
$usernameTaken = authentik_user_exists('username', $identity['username']);
if ($usernameTaken === true) {
    json_error('Ta nazwa użytkownika została w międzyczasie zajęta.', 409, [
        'username' => 'Ta nazwa użytkownika jest już zajęta.',
    ], ['step' => 1]);
}

$emailTaken = authentik_user_exists('email', $identity['email']);
if ($emailTaken === true) {
    json_error('Ten adres e-mail został w międzyczasie zarejestrowany.', 409, [
        'email' => 'Ten adres e-mail jest już zarejestrowany.',
    ], ['step' => 1]);
}

if ($usernameTaken === null || $emailTaken === null) {
    json_error('Nie udało się teraz zweryfikować danych. Spróbuj ponownie za chwilę.', 503);
}

// ------------------------------------------------------------
// 4. Zakładamy konto w Authentiku.
// ------------------------------------------------------------
$displayName = trim($identity['first_name'] . ' ' . $identity['last_name']);
if ($displayName === '') {
    $displayName = $identity['username'];
}

$result = authentik_create_user([
    'username'          => $identity['username'],
    'display_name'      => $displayName,
    'email'             => $identity['email'],
    'password'          => $password,
    'marketing'         => $marketing,
    'email_verified_at' => registration_verified_at_iso(),
]);

if (!$result['ok']) {
    if ($result['code'] === 'conflict') {
        json_error('Login lub e-mail jest już zajęty.', 409, [
            'username' => 'Ta nazwa użytkownika lub e-mail są już zajęte.',
        ], ['step' => 1]);
    }

    if ($result['code'] === 'password_failed') {
        // Konto istnieje, ale bez hasła - użytkownik nie może go już założyć
        // ponownie, więc kierujemy go do wsparcia zamiast kazać próbować dalej.
        registration_clear();
        json_error(
            'Konto zostało utworzone, ale wystąpił problem z ustawieniem hasła. Skontaktuj się z nami: pomoc@dnct.pl',
            500
        );
    }

    json_error('Nie udało się utworzyć konta. Spróbuj ponownie później.', 500);
}

// ------------------------------------------------------------
// 4.5. JUST-IN-TIME PROVISIONING - stwórz konto w Pterodactylu
// ------------------------------------------------------------
// Po pomyślnym stworzeniu konta w Authentiku, automatycznie tworzymy
// użytkownika w Pterodactylu. To umożliwia SSO bez dodatkowego kroku.
$pterodactylUser = pterodactyl_ensure_user(
    email: $identity['email'],
    username: $identity['username'],
    firstName: $identity['first_name'],
    lastName: $identity['last_name']
);

if ($pterodactylUser === null) {
    // JIT provisioning zawiódł - logujemy błąd, ale nie blokujemy rejestracji
    // Użytkownik może się zalogować, a administrator rozwiąże problem
    error_log('Rejestracja: JIT provisioning zawiódł dla ' . $identity['email']);
}

// ------------------------------------------------------------
// 4.55. Konto hostingu WWW - to samo konto DNCT, pakiet bez zasobów do czasu
//       zakupu. Jak przy Pterodactylu: błąd tylko w logu, logowanie i tak
//       dołoży brakujące konto (auth/callback.php).
// ------------------------------------------------------------
if (hosting_ensure_user($identity['email'], $displayName) === null && hosting_enabled()) {
    error_log('Rejestracja: konto hostingu nie powstało dla ' . $identity['email']);
}

// ------------------------------------------------------------
// 4.6. JUST-IN-TIME PROVISIONING - stwórz konto w Jellyfinie
// ------------------------------------------------------------
// W przeciwieństwie do Pterodactyla NIE MA find-or-create po e-mailu (Jellyfin
// nie zwraca e-maila w /Users) - dlatego to woła się WYŁĄCZNIE tutaj, raz,
// przy zakładaniu konta, nigdy przy logowaniu (patrz backend/lib/jellyfin.php).
$jellyfinAccount = jellyfin_create_user(
    jellyfin_username_from_authentik($identity['username'])
);

if ($jellyfinAccount === null) {
    error_log('Rejestracja: tworzenie konta Jellyfin zawiodło dla ' . $identity['email']);
} else {
    $mailResult = send_jellyfin_credentials_mail($identity['email'], $identity['first_name'], $jellyfinAccount);
    if (!$mailResult['ok']) {
        error_log('Rejestracja: mail z danymi Jellyfina nie dotarł do ' . $identity['email'] . ': ' . $mailResult['error']);
    }
}

user_profile_create(dnct_db(), [
    'authentik_username'   => $identity['username'],
    'authentik_email'      => $identity['email'],
    'pterodactyl_user_id'  => $pterodactylUser['id'] ?? null,
    'jellyfin_user_id'     => $jellyfinAccount['id'] ?? null,
    'jellyfin_linked_at'   => $jellyfinAccount !== null ? gmdate('Y-m-d H:i:s') : null,
    'jellyfin_link_source' => $jellyfinAccount !== null ? 'created' : null,
]);

// ------------------------------------------------------------
// 5. Sukces - czyścimy stan rejestracji i przekazujemy komunikat na logowanie.
// ------------------------------------------------------------
registration_clear();
session_regenerate_id(true);
$_SESSION['success'] = 'Konto zostało utworzone. Możesz się teraz zalogować.';

json_ok([
    'message'  => 'Konto zostało utworzone.',
    'redirect' => REGISTER_REDIRECT,
]);
