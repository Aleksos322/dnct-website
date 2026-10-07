<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
$db = dnct_db();
$stmt = $db->prepare('DELETE FROM support_tickets WHERE status = "closed" AND delete_after IS NOT NULL AND delete_after <= NOW()');
$stmt->execute();
echo 'Usunięto zamkniętych ticketów: ' . $stmt->rowCount() . PHP_EOL;
