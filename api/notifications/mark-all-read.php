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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$userId  = $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

mark_all_read(dnct_db(), $userId, $isAdmin);

echo json_encode(['ok' => true]);