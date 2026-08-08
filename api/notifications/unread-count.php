<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../backend/lib/notifications.php';

if (!isset($_SESSION['authentik_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$userId  = $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

$count = count_unread_for_user(dnct_db(), $userId, $isAdmin);

echo json_encode(['unread' => $count]);