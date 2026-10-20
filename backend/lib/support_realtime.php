<?php
declare(strict_types=1);
require_once __DIR__.'/messaging_security.php';

/**
 * Wspólne prymitywy realtime dla ticketów. PHP pozostaje źródłem prawdy
 * dla formularzy i uprawnień; bramka WebSocket przekazuje jedynie zdarzenia
 * oraz obsługuje kanał rozmowy po otrzymaniu krótkotrwałego biletu.
 */

const SUPPORT_REALTIME_PAGE_SIZE = 50;
const SUPPORT_REALTIME_TOKEN_TTL = 300;

/** @return array{messages:list<array<string,mixed>>,has_more:bool,next_before_id:?int} */
function support_messages_page(PDO $db, int $ticketId, int $beforeId = 0, int $limit = SUPPORT_REALTIME_PAGE_SIZE): array
{
    $limit = max(1, min(SUPPORT_REALTIME_PAGE_SIZE, $limit));
    $sql = 'SELECT m.id, m.author_role, m.author_name, m.body, m.client_message_key,
            m.reply_to_message_id, m.created_at,
            CASE WHEN m.author_role = \'admin\' THEN m.author_name ELSE COALESCE(t.customer_username, \'\') END AS username,
            parent.author_name AS reply_author_name, parent.body AS reply_body
        FROM support_messages m
        JOIN support_tickets t ON t.id = m.ticket_id
        LEFT JOIN support_messages parent ON parent.id = m.reply_to_message_id
        WHERE m.ticket_id = :ticket';
    if ($beforeId > 0) {
        $sql .= ' AND m.id < :before';
    }
    $sql .= ' ORDER BY m.id DESC LIMIT ' . ($limit + 1);
    $statement = $db->prepare($sql);
    $statement->bindValue(':ticket', $ticketId, PDO::PARAM_INT);
    if ($beforeId > 0) {
        $statement->bindValue(':before', $beforeId, PDO::PARAM_INT);
    }
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }
    $rows = array_reverse($rows);
    $oldest = $rows[0]['id'] ?? null;

    return [
        'messages' => $rows,
        'has_more' => $hasMore,
        'next_before_id' => $hasMore && $oldest !== null ? (int)$oldest : null,
    ];
}

/** @return list<array{participant_role:string,participant_key:string,last_message_id:int,read_at:string}> */
function support_message_receipts(PDO $db, int $ticketId): array
{
    $statement = $db->prepare("SELECT r.participant_role, r.participant_key, r.last_message_id, r.read_at,
        CASE WHEN r.participant_role = 'admin' THEN r.participant_key ELSE COALESCE(t.customer_username, '') END AS username,
        CASE WHEN r.participant_role = 'admin' THEN r.participant_key ELSE t.customer_name END AS display_name
        FROM support_message_receipts r JOIN support_tickets t ON t.id = r.ticket_id WHERE r.ticket_id = :ticket");
    $statement->execute([':ticket' => $ticketId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function support_realtime_emit(PDO $db, int $ticketId, string $type, array $payload = []): void
{
    if($type==='ticket_closed'&&function_exists('notify_user')){$q=$db->prepare('SELECT customer_username,public_id,status,closed_by_user,closed_at FROM support_tickets WHERE id=?');$q->execute([$ticketId]);$t=$q->fetch(PDO::FETCH_ASSOC);if($t&&$t['status']==='closed'&&!$t['closed_by_user']&&!empty($t['customer_username'])){require_once __DIR__.'/notification_events.php';notification_event($db,$t['customer_username'],'support.closed',$ticketId.':'.$t['closed_at'],'Zgłoszenie #'.$t['public_id'].' zostało zamknięte','Zespół wsparcia oznaczył Twoje zgłoszenie jako rozwiązane.','/?wsparcie=1');}}

    $statement = $db->prepare('INSERT INTO support_realtime_events(ticket_id, event_type, payload)
        VALUES(:ticket, :type, :payload)');
    $statement->execute([
        ':ticket' => $ticketId,
        ':type' => $type,
        ':payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/** @return array{token:string,expires_at:string} */
function support_realtime_issue_token(PDO $db, int $ticketId, string $role, string $participantKey): array
{
    if (!in_array($role, ['customer', 'admin'], true) || $participantKey === '') {
        throw new InvalidArgumentException('Nieprawidłowy uczestnik rozmowy.');
    }
    if (!defined('WSPARCIE_ZRODLO') || WSPARCIE_ZRODLO === 'beta') return ['token'=>msg_ws_token($db,'support','',$ticketId,$role,$participantKey),'expires_at'=>date('Y-m-d H:i:s',time()+300)];
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $expiresAt = (new DateTimeImmutable('+'.SUPPORT_REALTIME_TOKEN_TTL.' seconds'))->format('Y-m-d H:i:s');
    $db->prepare('DELETE FROM support_realtime_tokens WHERE expires_at < NOW()')->execute();
    $statement = $db->prepare('INSERT INTO support_realtime_tokens
        (token_hash, ticket_id, participant_role, participant_key, expires_at)
        VALUES(:hash, :ticket, :role, :participant, :expires)');
    $statement->execute([
        ':hash' => hash('sha256', $token),
        ':ticket' => $ticketId,
        ':role' => $role,
        ':participant' => $participantKey,
        ':expires' => $expiresAt,
    ]);
    return ['token' => $token, 'expires_at' => $expiresAt];
}

function support_realtime_mark_read(PDO $db, int $ticketId, string $role, string $participantKey, int $lastMessageId): void
{
    if ($lastMessageId < 1 || !in_array($role, ['customer', 'admin'], true) || $participantKey === '') {
        return;
    }
    $exists = $db->prepare('SELECT id FROM support_messages WHERE ticket_id = :ticket AND id = :message');
    $exists->execute([':ticket' => $ticketId, ':message' => $lastMessageId]);
    if (!$exists->fetchColumn()) {
        return;
    }
    $statement = $db->prepare('INSERT INTO support_message_receipts
        (ticket_id, participant_role, participant_key, last_message_id, read_at)
        VALUES(:ticket, :role, :participant, :message, NOW())
        ON DUPLICATE KEY UPDATE
            last_message_id = GREATEST(last_message_id, VALUES(last_message_id)),
            read_at = NOW()');
    $statement->execute([
        ':ticket' => $ticketId,
        ':role' => $role,
        ':participant' => $participantKey,
        ':message' => $lastMessageId,
    ]);
    support_realtime_emit($db, $ticketId, 'read_receipt', [
        'participant_role' => $role,
        'participant_key' => $participantKey,
        'last_message_id' => $lastMessageId,
    ]);
}
