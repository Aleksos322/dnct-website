<?php
/**
 * Podpisane wołania z PRODUKCJI: prośba o opiekę nad stroną trafia na czat (bety).
 *
 * Ten sam klucz Ed25519 co api/internal-notify.php, ale OSOBNE `purpose` = "care": ładunek mostu użytkownika
 * ani powiadomienia nie jest tu ważny. Ważność 60 s, nonce jednorazowy. Wejście tylko serwer-serwer.
 * Akcje: request {order_id, client, opiekun, price_text}.
 */
declare(strict_types=1);

function care_deny(int $status = 403): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo '{"ok":false}';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    care_deny();
}
$raw = base64_decode((string)($_POST['payload'] ?? ''), true);
$sig = base64_decode((string)($_POST['signature'] ?? ''), true);
$key = @file_get_contents(__DIR__ . '/../backend/lib/panel-messaging-public.key');
if (!is_string($raw) || strlen($raw) > 8192 || !is_string($sig) || strlen($sig) !== 64
    || !is_string($key) || strlen($key) !== 32 || !sodium_crypto_sign_verify_detached($sig, $raw, $key)) {
    care_deny();
}
$p = json_decode($raw, true);
if (!is_array($p) || ($p['v'] ?? 0) !== 1 || ($p['purpose'] ?? '') !== 'care'
    || abs(time() - (int)($p['time'] ?? 0)) > 60
    || !preg_match('/^[a-f0-9]{48}$/D', (string)($p['nonce'] ?? ''))) {
    care_deny();
}
$orderId = (int)($p['order_id'] ?? 0);
$client = (string)($p['client'] ?? '');
$opiekun = (string)($p['opiekun'] ?? '');
$price = (string)($p['price_text'] ?? '');
if (($p['action'] ?? '') !== 'request' || $orderId <= 0 || $client === '' || $opiekun === ''
    || strlen($client) > 150 || strlen($opiekun) > 150 || mb_strlen($price) > 80) {
    care_deny(422);
}

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/lib/chat.php';
require_once __DIR__ . '/../backend/lib/chat_care.php';
$db = dnct_db();
try {
    $db->prepare('INSERT INTO panel_messaging_nonces(nonce,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 2 MINUTE))')->execute([$p['nonce']]);
} catch (Throwable $e) {
    care_deny();
}
try {
    $res = chat_care_create_request($db, chat_user_by_username($client), chat_user_by_username($opiekun), $orderId, $price);
} catch (Throwable $e) {
    error_log('internal-care: ' . $e->getMessage());
    care_deny(500);
}
header('Content-Type: application/json');
echo json_encode(['ok' => true] + $res);
