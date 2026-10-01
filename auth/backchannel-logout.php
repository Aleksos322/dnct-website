<?php
/**
 * OIDC Back-Channel Logout - Authentik (provider 10) woła ten adres
 * serwer-serwer, gdy sesja DNCT się kończy. Zarejestrowany w providerze jako
 * logout_uri bez ".php" (.htaccess dopisuje rozszerzenie, a 301 zgubiłby POST).
 *
 * Jak webhook Stripe'a: bez csrf_guard() (Authentik nie zna naszej sesji),
 * zamiast tego podpis logout_tokenu kluczem z JWKS. Kody wg specyfikacji:
 * 200 = przyjęte, 400 = zły token, 500 = spróbuj jeszcze raz.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('{"error":"method_not_allowed"}');
}

$token = (string)($_POST['logout_token'] ?? '');
if ($token === '') {
    http_response_code(400);
    exit('{"error":"invalid_request"}');
}

try {
    $claims = sso_logout_verify($token);
} catch (RuntimeException $e) {
    error_log('SSO backchannel: odrzucony - ' . $e->getMessage());
    http_response_code(400);
    exit('{"error":"invalid_request"}');
}

try {
    sso_logout_revoke($claims);
} catch (RuntimeException $e) {
    error_log('SSO backchannel: ' . $e->getMessage());
    http_response_code(500);
    exit('{"error":"server_error"}');
}

error_log(sprintf(
    'SSO backchannel: wylogowano sid=%s sub=%s',
    substr((string)($claims['sid'] ?? '-'), 0, 8),
    substr((string)($claims['sub'] ?? '-'), 0, 8)
));
exit('{}');
