<?php
declare(strict_types=1);
require_once __DIR__.'/support_message_features.php';
require_once __DIR__.'/support_media_response.php';

function chat_media_path(string $key): ?string
{
    if (!preg_match('/\A[a-f0-9]{64}\z/D',$key)) return null;
    // This shared library lives on beta even when called by the admin application.
    $root=defined('CHAT_MEDIA_STORAGE_ROOT') ? CHAT_MEDIA_STORAGE_ROOT : msg_media_root('chat');
    return $root.'/'.substr($key,0,2).'/'.$key.'.blob';
}

/** Caller owns the message transaction; track new paths for rollback cleanup. */
function chat_store_media(PDO $db,int $messageId,array $files,array &$stored): void
{
    msg_upload_charge($db,$files,'chat');
    foreach ($files as $file) {
        $key=bin2hex(random_bytes(32));$path=chat_media_path($key);
        support_message_ensure_media_directory(dirname($path));
        if (!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('Nie udało się zapisać załącznika.');
        $stored[]=$path;chmod($path,0660);
        msg_media_register($db,'chat',$key,$file['byte_size']);
        $db->prepare('INSERT INTO chat_message_attachments(message_id,storage_key,original_name,mime_type,media_type,byte_size) VALUES(?,?,?,?,?,?)')
            ->execute([$messageId,$key,$file['original_name'],$file['mime_type'],$file['kind'],$file['byte_size']]);
    }
}

/** Only called with message IDs already authorized by chat_messages. */
function chat_media_for_messages(PDO $db,array $ids): array
{
    if (!$ids) return [];
    $q=$db->prepare('SELECT id,message_id,original_name,mime_type,media_type,byte_size FROM chat_message_attachments WHERE message_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');
    $q->execute($ids);$out=[];
    $base=defined('ADMIN_BASE_PATH')?'/czat-api?action=media':'/api/chat.php?action=media';
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $a['id']=(int)$a['id'];$a['message_id']=(int)$a['message_id'];$a['byte_size']=(int)$a['byte_size'];
        $a['url']=$base.'&attachment_id='.$a['id'];$out[$a['message_id']][]=$a;
    }
    return $out;
}

/** Membership, withdrawal and per-user hiding are rechecked on every byte-range request. */
function chat_media_file(PDO $db,int $attachmentId,string $actorId): ?array
{
    msg_security_bind($db);
    $q=$db->prepare('SELECT a.* FROM chat_message_attachments a
        JOIN chat_messages m ON m.id=a.message_id
        JOIN chat_members member ON member.conversation_id=m.conversation_id AND member.user_id=?
        LEFT JOIN chat_message_hides hidden ON hidden.message_id=m.id AND hidden.user_id=?
        WHERE NOT EXISTS(SELECT 1 FROM chat_group_membership gm WHERE gm.conversation_id=m.conversation_id AND gm.user_id=member.user_id AND gm.state IN ("invited","ignored")) AND a.id=? AND m.withdrawn_at IS NULL AND hidden.message_id IS NULL LIMIT 1');
    $q->execute([$actorId,$actorId,$attachmentId]);$file=$q->fetch(PDO::FETCH_ASSOC);
    if (!$file || !($path=chat_media_path($file['storage_key']))) return null;
    if (!is_file($path)) $path=dirname(__DIR__).'/storage/chat-media/'.substr($file['storage_key'],0,2).'/'.$file['storage_key'].'.blob';
    if (!is_file($path) || filesize($path)!==(int)$file['byte_size']) return null;
    return ['path'=>$path,'mime_type'=>$file['mime_type'],'original_name'=>$file['original_name'],'byte_size'=>(int)$file['byte_size']];
}
