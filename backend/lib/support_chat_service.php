<?php
declare(strict_types=1);

require_once __DIR__ . '/support_message_features.php';
require_once __DIR__ . '/support_realtime.php';
require_once __DIR__ . '/chat_emoji.php';
require_once __DIR__ . '/messaging_security.php';

/** Creation is independent of mail/notifications, and can be exercised without contacting anyone. */
function support_chat_create(PDO $db, array $fields): array
{
    for ($attempt=0;$attempt<20;$attempt++) {
        try {
            $db->beginTransaction();
            $q=$db->prepare('SELECT * FROM support_tickets WHERE active_owner_key=? OR (owner_key=? AND request_key=?) LIMIT 1');
            $q->execute([$fields['owner'],$fields['owner'],$fields['request']]);
            if ($existing=$q->fetch(PDO::FETCH_ASSOC)) { $db->commit(); return ['ticket'=>$existing,'created'=>false]; }
            $number='TKT-'.random_int(1000,9999);
            $q=$db->prepare('INSERT INTO support_tickets(public_id,access_token_hash,owner_key,active_owner_key,request_key,customer_username,customer_name,customer_email,category_key,category_label,ip_hash,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,"new")');
            $q->execute([$number,$fields['access'],$fields['owner'],$fields['owner'],$fields['request'],$fields['username'] ?: null,$fields['name'],$fields['email'],$fields['category'],$fields['label'],$fields['ip']]);
            $id=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body) VALUES(?,"customer",?,?)')->execute([$id,$fields['name'],$fields['body']]);
            $db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action) VALUES(?,?,"created")')->execute([$id,$fields['username'] ?: null]);
            $q=$db->prepare('SELECT * FROM support_tickets WHERE id=?');$q->execute([$id]);$ticket=$q->fetch(PDO::FETCH_ASSOC);
            $db->commit(); return ['ticket'=>$ticket,'created'=>true];
        } catch (PDOException $e) { if ($db->inTransaction()) $db->rollBack(); if ($e->getCode() !== '23000' || $attempt === 19) throw $e; }
        catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    throw new RuntimeException('Nie udało się nadać numeru zgłoszenia. Spróbuj ponownie.');
}

/** Authorization of the ticket itself is the endpoint's responsibility. */
function support_chat_page(PDO $db, array $ticket, string $role, string $viewerKey, string $mediaBase, string $name, int $before = 0): array
{
    $page = support_messages_for_viewer($db, (int)$ticket['id'], $role, $viewerKey, $mediaBase, $before, 100);
    $ids = array_column($page['messages'], 'id'); $reactions = [];
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $q = $db->prepare("SELECT message_id,emoji,COUNT(*) total,SUM(participant_role=? AND participant_key=?) mine FROM support_message_reactions WHERE message_id IN ($marks) GROUP BY message_id,emoji ORDER BY emoji");
        $q->execute(array_merge([$role, $viewerKey], $ids));
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $reactions[(int)$r['message_id']][] = ['emoji'=>$r['emoji'], 'count'=>(int)$r['total'], 'mine'=>(bool)$r['mine']];
    }
    foreach ($page['messages'] as &$m) {
        $m['username'] = $m['author_role'] === 'customer' ? (string)($ticket['customer_username'] ?? '') : $m['author_name'];
        $m['mine'] = $m['author_role'] === $role && ($role === 'customer' || strcasecmp($m['author_name'], $name) === 0);
        $m['reactions'] = $m['is_withdrawn'] ? [] : ($reactions[$m['id']] ?? []);
    }
    unset($m);
    return $page;
}

/** All writers lock the ticket first, so closing cannot race with a send or edit. */
function support_chat_mutate(PDO $db, int $ticketId, string $role, string $viewerKey, string $name, string $action, array $input, array $files = []): void
{
    if ($action === 'message') msg_rate_guard($db);
    $db->beginTransaction();
    try {
        $q = $db->prepare('SELECT * FROM support_tickets WHERE id=? FOR UPDATE'); $q->execute([$ticketId]); $ticket = $q->fetch(PDO::FETCH_ASSOC);
        if (!$ticket || !in_array($ticket['status'], ['new','open'], true)) throw new DomainException('Ticket został zamknięty.');
        if ($role === 'admin' && ($ticket['status'] !== 'open' || strcasecmp((string)$ticket['assigned_admin'], $name) !== 0)) throw new DomainException('Ten ticket jest tylko do odczytu.');
        if ($action === 'close_user') {
            if ($role !== 'customer') throw new DomainException('Niedozwolona operacja.');
            $reason = trim((string)($input['reason'] ?? ''));
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) throw new InvalidArgumentException('Podaj powód zamknięcia (od 3 do 500 znaków).');
            $db->prepare('UPDATE support_tickets SET status="closed",active_owner_key=NULL,closed_at=NOW(),closed_by_user=1,close_reason=?,delete_after=DATE_ADD(NOW(),INTERVAL 7 DAY),updated_at=NOW() WHERE id=?')->execute([$reason,$ticketId]);
            $db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(?,?,"closed_by_user",?)')->execute([$ticketId,$name,$reason]);
            support_realtime_emit($db,$ticketId,'ticket_closed'); $db->commit(); return;
        }
        if ($action === 'message') {
            if ($ticket['status'] !== 'open') throw new DomainException('Poczekaj, aż administrator przyjmie zgłoszenie.');
            $body = trim(str_replace(["\r\n","\r"], "\n", (string)($input['message'] ?? '')));
            if (($body === '' && !$files) || mb_strlen($body) > 2000) throw new InvalidArgumentException('Wpisz wiadomość (do 2000 znaków) albo załącz plik.');
            $key = (string)($input['message_key'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new InvalidArgumentException('Nieprawidłowy identyfikator wiadomości. Odśwież stronę.');
            $q = $db->prepare('SELECT id,body,author_role,author_name FROM support_messages WHERE ticket_id=? AND client_message_key=?'); $q->execute([$ticketId,$key]); $old = $q->fetch(PDO::FETCH_ASSOC);
            if ($old) {
                if ($old['author_role'] !== $role || strcasecmp($old['author_name'],$name) !== 0) throw new DomainException('Identyfikator wiadomości został już użyty.');
                $db->commit(); return;
            }
            $replyId = (int)($input['reply_to_id'] ?? 0);
            if ($replyId) { $q = $db->prepare('SELECT id FROM support_messages WHERE ticket_id=? AND id=? AND withdrawn_at IS NULL'); $q->execute([$ticketId,$replyId]); if (!$q->fetchColumn()) throw new InvalidArgumentException('Wiadomość, na którą odpowiadasz, nie jest już dostępna.'); }
            $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body,client_message_key,reply_to_message_id) VALUES(?,?,?,?,?,?)')->execute([$ticketId,$role,$name,$body,$key,$replyId ?: null]);
            support_store_message_attachments($db,(int)$db->lastInsertId(),$files);
            if($role==='admin'&&!empty($ticket['customer_username'])){require_once __DIR__.'/notification_events.php';notification_event($db,(string)$ticket['customer_username'],'support.reply',$ticketId.':'.$key,'Nowa odpowiedź w zgłoszeniu #'.$ticket['public_id'],'Konsultant odpowiedział: '.($body!==''?mb_substr($body,0,180):'Przesłano załącznik.'),'/?wsparcie=1');}
        } elseif (in_array($action,['edit_message','withdraw_message','react'],true)) {
            $messageId = (int)($input['message_id'] ?? 0);
            $q = $db->prepare('SELECT *, created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) AS edit_allowed FROM support_messages WHERE ticket_id=? AND id=? FOR UPDATE'); $q->execute([$ticketId,$messageId]); $message = $q->fetch(PDO::FETCH_ASSOC);
            if (!$message || $message['withdrawn_at'] !== null) throw new InvalidArgumentException('Ta wiadomość nie jest już dostępna.');
            if ($action !== 'react' && ($message['author_role'] !== $role || ($role === 'admin' && strcasecmp($message['author_name'],$name) !== 0))) throw new DomainException('Możesz zmieniać tylko własne wiadomości.');
            if ($action === 'edit_message') {
                if (!$message['edit_allowed']) throw new DomainException('Wiadomość można edytować tylko przez 5 minut od wysłania.');
                $body = trim((string)($input['message'] ?? ''));
                if ($body === '' || mb_strlen($body) > 2000) throw new InvalidArgumentException('Wiadomość musi mieć od 1 do 2000 znaków.');
                $db->prepare('UPDATE support_messages SET body=?,edited_at=NOW() WHERE id=?')->execute([$body,$messageId]);
            } elseif ($action === 'withdraw_message') {
                if (($input['scope'] ?? '') === 'me') support_message_hide_for_viewer($db,$messageId,$role,$viewerKey);
                else $db->prepare('UPDATE support_messages SET body="",withdrawn_at=NOW(),withdrawn_by_role=?,withdrawn_by_key=? WHERE id=?')->execute([$role,$viewerKey,$messageId]);
            } else {
                $emoji = (string)($input['emoji'] ?? ''); if (!chat_valid_emoji($emoji)) throw new InvalidArgumentException('Wybierz prawidłowe emoji.');
                $q = $db->prepare('DELETE FROM support_message_reactions WHERE message_id=? AND participant_role=? AND participant_key=? AND emoji=?'); $q->execute([$messageId,$role,$viewerKey,$emoji]);
                if (!$q->rowCount()) $db->prepare('INSERT INTO support_message_reactions(message_id,participant_role,participant_key,emoji) VALUES(?,?,?,?)')->execute([$messageId,$role,$viewerKey,$emoji]);
            }
        } else throw new InvalidArgumentException('Nieznana akcja.');
        $db->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=?')->execute([$ticketId]);
        support_realtime_emit($db,$ticketId,'refresh'); $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); if(msg_security_bind($db))msg_security_log($db,'support_mutation_denied',$ticketId); throw $e; }
}
