<?php
declare(strict_types=1);
/** Close an owned ticket atomically, retaining its history for the support team. */
function support_close_customer_ticket(PDO $db,int $id,string $username,string $access,string $reason): array {
    $reason=trim($reason);
    if(mb_strlen($reason)<3||mb_strlen($reason)>500)throw new InvalidArgumentException('Podaj powód zamknięcia (od 3 do 500 znaków).');
    $own=!$db->inTransaction();if($own)$db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM support_tickets WHERE id=? AND (access_token_hash=? OR (?<>\'\' AND customer_username=?)) FOR UPDATE');
        $q->execute([$id,$access,$username,$username]);$ticket=$q->fetch(PDO::FETCH_ASSOC);
        if(!$ticket)throw new DomainException('Nie znaleziono zgłoszenia.');
        if(!in_array($ticket['status'],['new','open'],true))throw new DomainException('Zgłoszenie zostało już zamknięte.');
        $db->prepare('UPDATE support_tickets SET status="closed",active_owner_key=NULL,closed_at=NOW(),closed_by_user=1,close_reason=?,delete_after=DATE_ADD(NOW(),INTERVAL 7 DAY),updated_at=NOW() WHERE id=?')->execute([$reason,$id]);
        $db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(?,?,"closed_by_user",?)')->execute([$id,$username!==''?$username:null,$reason]);
        support_realtime_emit($db,$id,'ticket_closed');
        $q=$db->prepare('SELECT * FROM support_tickets WHERE id=?');$q->execute([$id]);$ticket=$q->fetch(PDO::FETCH_ASSOC);
        if($own)$db->commit();return $ticket;
    }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
}
