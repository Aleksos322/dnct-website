<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'notifications',
    RATE_LIMIT_NOTIFICATIONS,
    'Zbyt wiele operacji z tego adresu IP. Odczekaj chwilę.'
);

if (!isset($_SESSION['authentik_username'])) {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

require_once dirname(__DIR__,2).'/backend/lib/messaging_security.php';
msg_security_session(dnct_db());

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

// Klient dostaje potwierdzony wynik, zamiast tylko optymistycznej zmiany wyglądu.
$scope=request_string('scope','normal');if(!in_array($scope,['normal','admin'],true))json_error('Nieprawidłowa zakładka.',400);if($scope==='admin'&&!$isAdmin)json_error('Brak uprawnień.',403);
$marked = hide_read_notifications(dnct_db(), $userId, $isAdmin,$scope);

json_ok(['hidden' => $marked]);
