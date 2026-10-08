<?php
/**
 * Wspólny start dla wszystkiego, co po stronie serwera: sesja, konfiguracja
 * i biblioteki. Każdy endpoint API oraz każdy widok robi require_once tego
 * pliku i nie musi wiedzieć, gdzie leżą poszczególne moduły.
 */

declare(strict_types=1);

define('DNCT_BASE_PATH', dirname(__DIR__));       // .../public_html/private
define('DNCT_BACKEND_PATH', __DIR__);             // .../private/backend

require_once __DIR__ . '/lib/assets.php';
require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/czas.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/rate_limit.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/authentik.php';
require_once __DIR__ . '/lib/oidc.php';
require_once __DIR__ . '/lib/sso_logout.php';
require_once __DIR__ . '/lib/registration.php';
require_once __DIR__ . '/lib/pterodactyl.php';
require_once __DIR__ . '/lib/hosting.php';
require_once __DIR__ . '/lib/capacity.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/pricing.php';
require_once __DIR__ . '/lib/orders.php';
require_once __DIR__ . '/lib/panel_sso.php';
require_once __DIR__ . '/lib/gsc.php';
require_once __DIR__ . '/lib/odwiedziny.php';
require_once __DIR__ . '/lib/ntfy.php';

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

// Sesja DNCT zakończona w Authentiku (wylogowanie w innej aplikacji) - patrz lib/sso_logout.php.
sso_logout_enforce();

// Statystyki odwiedzin (lib/odwiedziny.php). Bufor co 8 KB tylko liczy bajty -
// duże pliki (np. /pobierz) płyną dalej strumieniem.
if (PHP_SAPI !== 'cli') {
    $GLOBALS['odwiedziny_bajty'] = 0;
    ob_start(static function (string $b): string { $GLOBALS['odwiedziny_bajty'] += strlen($b); return $b; }, 8192);
    register_shutdown_function('odwiedziny_po_zadaniu');
}
