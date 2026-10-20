<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/support.php';
require_once dirname(__DIR__, 2) . '/backend/lib/support_message_features.php';
require_once dirname(__DIR__, 2) . '/backend/lib/support_media_response.php';

$attachmentId = (int) ($_GET['attachment_id'] ?? 0);
if ($attachmentId < 1) { http_response_code(404); exit; }
$access = support_access_hash();
$username = trim((string) ($_SESSION['authentik_username'] ?? ''));
$db = dnct_db();
msg_security_session($db);
$statement = $db->prepare('SELECT t.id FROM support_message_attachments a
    JOIN support_messages m ON m.id = a.message_id
    JOIN support_tickets t ON t.id = m.ticket_id
    WHERE a.id = ? AND m.withdrawn_at IS NULL AND (t.access_token_hash = ? OR (? <> "" AND t.customer_username = ?)) LIMIT 1');
$statement->execute([$attachmentId, $access, $username, $username]);
if (!$statement->fetchColumn() || !($file = support_attachment_stream_path($db, $attachmentId))) { http_response_code(404); exit; }

support_media_response($file);
