<?php
// Local-only integration fixtures. Never served by the application.
declare(strict_types=1);
putenv('DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env');
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/lib/support_chat_service.php';
require_once dirname(__DIR__).'/lib/chat.php';
$db=dnct_db();
$tag=(string)getenv('DNCT_CHAT_TEST_TAG');
if(!preg_match('/^codex-chat-check-[a-f0-9]{12}$/D',$tag))throw new RuntimeException('Explicit isolated fixture tag required.');
$customer=['id'=>$tag.'-customer','username'=>$tag.'-customer','name'=>'Klient testowy','is_admin'=>false];
$admin=['id'=>$tag.'-admin','username'=>$tag.'-admin','name'=>'Administrator testowy','is_admin'=>true];
$fields=['owner'=>hash('sha256','account:'.$customer['username']),'access'=>hash('sha256',$tag),'request'=>hash('sha256',$tag.'-create'),'username'=>$customer['username'],'name'=>$customer['name'],'email'=>$tag.'@example.invalid','category'=>'panel','label'=>'Kwestie Techniczne / Problemy z panelem','ip'=>hash('sha256','127.0.0.1'),'body'=>'Test rozmowy: panel serwera nie odpowiada.'];
$action=$argv[1]??'setup';
if($action==='setup'){
    $created=support_chat_create($db,$fields);$ticket=$created['ticket'];
    $again=support_chat_create($db,$fields);if($again['created']||(int)$again['ticket']['id']!==(int)$ticket['id'])throw new RuntimeException('Creation is not idempotent');
    $db->prepare('UPDATE support_tickets SET status="open",assigned_admin=?,assigned_at=NOW() WHERE id=?')->execute([$admin['username'],$ticket['id']]);
    support_chat_mutate($db,(int)$ticket['id'],'admin',support_message_viewer_key_admin($admin['username']),$admin['username'],'message',['message'=>'Cześć! Sprawdzam Twoje zgłoszenie.','message_key'=>hash('sha256',$tag.'-hello')]);
    $conversation=chat_start($db,$customer,$admin);
    chat_send($db,$conversation,$customer,'Moja pierwsza wiadomość',hash('sha256',$tag.'-dm1'));
    chat_send($db,$conversation,$admin,'Witaj! Jak mogę pomóc?',hash('sha256',$tag.'-dm2'));
    echo json_encode(['ticket'=>(int)$ticket['id'],'conversation'=>$conversation,'customer'=>$customer,'admin'=>$admin]);
}elseif($action==='close-admin'){
    $id=(int)($argv[2]??0);$q=$db->prepare('UPDATE support_tickets SET status="closed",active_owner_key=NULL,closed_at=NOW(),updated_at=NOW() WHERE id=? AND customer_username=?');$q->execute([$id,$customer['username']]);if($q->rowCount())support_realtime_emit($db,$id,'ticket_closed');echo 'closed';
}elseif($action==='delete-ticket'){
    $db->prepare('DELETE FROM support_tickets WHERE id=? AND customer_username=?')->execute([(int)($argv[2]??0),$customer['username']]);echo 'deleted';
}elseif($action==='cleanup'){
    $q=$db->prepare('SELECT a.id FROM support_message_attachments a JOIN support_messages m ON m.id=a.message_id JOIN support_tickets t ON t.id=m.ticket_id WHERE t.customer_username=?');$q->execute([$customer['username']]);
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){$file=support_attachment_stream_path($db,(int)$id);if($file&&is_file($file['path']))unlink($file['path']);}
    $db->prepare('DELETE FROM support_tickets WHERE customer_username=?')->execute([$customer['username']]);
    $q=$db->prepare('SELECT conversation_id FROM chat_members WHERE user_id=?');$q->execute([$customer['id']]);
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){
        $db->prepare('DELETE r FROM chat_message_reactions r JOIN chat_messages m ON m.id=r.message_id WHERE m.conversation_id=?')->execute([$id]);
        $db->prepare('DELETE h FROM chat_message_hides h JOIN chat_messages m ON m.id=h.message_id WHERE m.conversation_id=?')->execute([$id]);
        foreach(['chat_events','chat_messages','chat_members'] as $table)$db->prepare('DELETE FROM '.$table.' WHERE conversation_id=?')->execute([$id]);
        $db->prepare('DELETE FROM chat_conversations WHERE id=?')->execute([$id]);
    }
    foreach(['chat_presence','chat_realtime_tokens'] as $table)$db->prepare('DELETE FROM '.$table.' WHERE user_id IN (?,?)')->execute([$customer['id'],$admin['id']]);
    echo 'Synthetic chat fixtures removed';
}
