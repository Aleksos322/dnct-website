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

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

mark_all_read(dnct_db(), $userId, $isAdmin);

json_ok();
