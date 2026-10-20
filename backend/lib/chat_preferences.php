<?php
declare(strict_types=1);
function chat_blocked(PDO $db,int $id): bool {
    $q=$db->prepare('SELECT 1 FROM chat_preferences WHERE conversation_id=? AND blocked=1 LIMIT 1');$q->execute([$id]);return (bool)$q->fetchColumn();
}
function chat_preference(PDO $db,int $id,array $actor,string $operation,int $minutes=0): void {
    chat_member($db,$id,$actor['id']);
    if(!in_array($operation,['mute','unmute','archive','unarchive','hide','block','unblock','unread','read'],true))throw new InvalidArgumentException('Nieznana opcja rozmowy.');
    if(in_array($operation,['block','unblock'],true)){$q=$db->prepare('SELECT 1 FROM chat_groups WHERE conversation_id=?');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Blokowanie osoby jest dostępne w rozmowie prywatnej.');}
    if($operation==='mute'&&!in_array($minutes,[0,15,60,480,1440],true))throw new InvalidArgumentException('Nieprawidłowy czas wyciszenia.');
    $db->beginTransaction();
    try {
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$id]);
        $db->prepare('INSERT IGNORE INTO chat_preferences(conversation_id,user_id) VALUES(?,?)')->execute([$id,$actor['id']]);
        if($operation==='unarchive'&&chat_blocked($db,$id))throw new DomainException('Odblokuj rozmowę, aby przywrócić ją z archiwum.');
        $set=match($operation){
            'mute'=>$minutes===0?'mute_forever=1,muted_until=NULL':'mute_forever=0,muted_until=DATE_ADD(NOW(),INTERVAL '.$minutes.' MINUTE)',
            'unmute'=>'mute_forever=0,muted_until=NULL','archive'=>'archived=1','unarchive'=>'archived=0,hidden=0',
            'hide'=>'hidden=1,marked_unread=0','block'=>'blocked=1','unblock'=>'blocked=0','unread'=>'marked_unread=1','read'=>'marked_unread=0'
        };
        $db->prepare('UPDATE chat_preferences SET '.$set.' WHERE conversation_id=? AND user_id=?')->execute([$id,$actor['id']]);
        if(in_array($operation,['block','unblock'],true)){
            $blocked=chat_blocked($db,$id);
            $db->prepare('INSERT IGNORE INTO chat_preferences(conversation_id,user_id) SELECT conversation_id,user_id FROM chat_members WHERE conversation_id=?')->execute([$id]);
            $db->prepare('UPDATE chat_preferences SET archived=?,hidden=0 WHERE conversation_id=?')->execute([$blocked?1:0,$id]);
            if($blocked)$db->prepare('DELETE FROM chat_typing WHERE conversation_id=?')->execute([$id]);
        }
        if($operation==='read'){$db->prepare('UPDATE chat_members SET last_read_id=COALESCE((SELECT MAX(id) FROM chat_messages WHERE conversation_id=?),0) WHERE conversation_id=? AND user_id=?')->execute([$id,$id,$actor['id']]);}
        if($operation==='hide'){
            $db->prepare('INSERT IGNORE INTO chat_message_hides(message_id,user_id) SELECT id,? FROM chat_messages WHERE conversation_id=?')->execute([$actor['id'],$id]);
            $db->prepare('UPDATE chat_members SET last_read_id=COALESCE((SELECT MAX(id) FROM chat_messages WHERE conversation_id=?),0) WHERE conversation_id=? AND user_id=?')->execute([$id,$id,$actor['id']]);
        }
        chat_emit($db,$id);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
