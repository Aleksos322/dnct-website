<?php
/**
 * Wspólny start dla wszystkiego, co po stronie serwera: sesja, konfiguracja
 * i biblioteki. Każdy endpoint API oraz każdy widok robi require_once tego
 * pliku i nie musi wiedzieć, gdzie leżą poszczególne moduły.
 */

declare(strict_types=1);

define('DNCT_BASE_PATH', dirname(__DIR__));       // .../public_html/private
define('DNCT_BACKEND_PATH', __DIR__);             // .../private/backend

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/rate_limit.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/authentik.php';
require_once __DIR__ . '/lib/oidc.php';
require_once __DIR__ . '/lib/registration.php';
require_once __DIR__ . '/lib/pterodactyl.php';
require_once __DIR__ . '/lib/capacity.php';

// ------------------------------------------------------------
// Sesja - ciasteczko niedostępne dla JS i wysyłane tylko po HTTPS.
// ------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);

    session_start();
}
