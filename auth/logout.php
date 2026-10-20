<?php
/**
 * Wylogowanie.
 *
 * Kasujemy naszą sesję PHP, a potem odsyłamy przeglądarkę do Authentika, żeby
 * zakończył także sesję SSO. Bez tego drugiego kroku "wyloguj" byłoby złudne:
 * ciasteczko Authentika zostawałoby w przeglądarce, więc ponowne wejście na
 * logowanie wpuszczałoby od razu, bez pytania o hasło - a panel Pterodactyla
 * nadal by tę osobę znał.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

require_once dirname(__DIR__).'/backend/lib/messaging_security.php';
try { msg_security_revoke(dnct_db()); } catch(Throwable $e) { error_log('messaging_security: logout_revoke_failed'); }
require_once dirname(__DIR__) . '/backend/lib/messaging_site.php';
messaging_site_revoke();

$wasLoggedIn = isset($_SESSION['authentik_username']);

// Zapamiętujemy PRZED wyczyszczeniem sesji - Authentik potrzebuje id_tokenu,
// żeby wiedzieć, czyją sesję kończy i czy wolno odesłać nas z powrotem.
$idToken  = (string)($_SESSION['oidc_id_token'] ?? '');
$homeUrl  = oidc_site_home_url();

// 1. Nasza sesja - czyścimy zawartość, ciasteczko i identyfikator.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

// 2. Sesja Authentika (SSO).
//
// Bez id_tokenu NIE wolno tam odsyłać: Authentik nie wie wtedy, czyją sesję
// kończy, więc zamiast odesłać użytkownika z powrotem, przekierowuje go na
// swój ekran logowania - czyli zostawia w ślepym zaułku. W takim wypadku
// lepiej po prostu wrócić na stronę główną z już wyczyszczoną sesją.
if (!$wasLoggedIn || $idToken === '') {
    header('Location: /');
    exit();
}

header('Location: ' . oidc_end_session_url($homeUrl, $idToken));
exit();
