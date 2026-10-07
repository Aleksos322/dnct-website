<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/support.php';
require_once dirname(__DIR__, 2) . '/backend/lib/support_realtime.php';

$db = dnct_db();
$username = trim((string) ($_SESSION['authentik_username'] ?? ''));
$access = support_access_hash();

function support_realtime_current_ticket(PDO $db, string $username, string $access): ?array
{
    $statement = $db->prepare('SELECT * FROM support_tickets
        WHERE status IN ("new", "open")
          AND (access_token_hash = :access OR (:has_user = 1 AND customer_username = :username))
        ORDER BY FIELD(status, "open", "new"), created_at ASC LIMIT 1');
    $statement->execute([
        ':access' => $access,
        ':has_user' => $username !== '' ? 1 : 0,
        ':username' => $username,
    ]);
    $ticket = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($ticket) ? $ticket : null;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if ($method === 'GET') {
    $ticket = support_realtime_current_ticket($db, $username, $access);
    if (!$ticket) {
        json_error('Nie znaleziono aktywnej rozmowy.', 404);
    }
    $participant = $username !== '' ? $username : 'guest:' . substr($access, 0, 48);
    $token = support_realtime_issue_token($db, (int) $ticket['id'], 'customer', $participant);
    json_ok([
        'ticket_id' => (int) $ticket['id'],
        'token' => $token['token'],
        'expires_at' => $token['expires_at'],
        'ws_url' => 'wss://' . (preg_match('/^[a-z0-9.-]+$/i', (string)($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'game.dnct.pl') . '/ws/support',
    ]);
}

require_method('POST');
csrf_guard();
$action = request_string('action');
$ticket = support_realtime_current_ticket($db, $username, $access);
if (!$ticket) {
    json_error('Nie znaleziono aktywnej rozmowy.', 404);
}
if ($action !== 'read') {
    json_error('Nieznana akcja.', 400);
}
$lastMessageId = (int) request_string('last_message_id');
$participant = $username !== '' ? $username : 'guest:' . substr($access, 0, 48);
support_realtime_mark_read($db, (int) $ticket['id'], 'customer', $participant, $lastMessageId);
json_ok(['last_message_id' => $lastMessageId]);
