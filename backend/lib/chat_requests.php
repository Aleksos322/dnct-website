<?php
declare(strict_types=1);
function chat_request_on_send(PDO $db,int $id,string $actor): void {
    $db->prepare("INSERT IGNORE INTO chat_requests(conversation_id,requester_id) SELECT ?,? WHERE NOT EXISTS(SELECT 1 FROM chat_messages WHERE conversation_id=?) AND NOT EXISTS(SELECT 1 FROM chat_groups WHERE conversation_id=?)")->execute([$id,$actor,$id,$id]);
    // Caller holds the conversation row lock until message storage commits.
    $q=$db->prepare('SELECT * FROM chat_requests WHERE conversation_id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)return;
    if($r['state']==='rejected')throw new DomainException('Ta prośba o rozmowę została odrzucona.');
    if($r['state']==='draft')$db->prepare("UPDATE chat_requests SET requester_id=?,state='pending',updated_at=NOW() WHERE conversation_id=?")->execute([$actor,$id]);
    elseif($r['state']==='pending' && $r['requester_id']!==$actor)$db->prepare("UPDATE chat_requests SET state='accepted',updated_at=NOW() WHERE conversation_id=?")->execute([$id]);
}
function chat_request_reject(PDO $db,int $id,array $actor): void {
    $db->beginTransaction();
    try {
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);
        chat_member($db,$id,$actor['id']);
        $q=$db->prepare('SELECT * FROM chat_requests WHERE conversation_id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r || $r['state']!=='pending' || $r['requester_id']===$actor['id'])throw new DomainException('Nie możesz odrzucić tej prośby.');
        $db->prepare("UPDATE chat_requests SET state='rejected',updated_at=NOW() WHERE conversation_id=?")->execute([$id]);
        // Permanently remove request history; retain a tombstone to prevent resurrection.
        $db->prepare('DELETE FROM chat_messages WHERE conversation_id=?')->execute([$id]);
        $db->prepare('DELETE FROM chat_typing WHERE conversation_id=?')->execute([$id]);
        chat_emit($db,$id);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
