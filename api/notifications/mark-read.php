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

$id = (int) request_string('id', '0');

if ($id <= 0) {
    json_error('Nieprawidłowe ID powiadomienia.', 400, [], ['reason' => 'invalid_id']);
}

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

mark_notification_read(dnct_db(), $id, $userId, $isAdmin);

json_ok();
