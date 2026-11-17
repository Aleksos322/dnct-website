<?php
declare(strict_types=1);
require_once __DIR__.'/support.php';
require_once __DIR__.'/support_chat_service.php';
require_once __DIR__.'/support_realtime.php';

const CHAT_REPORT_CATEGORY='zgloszenie-czat';
const CHAT_REPORT_CHUNK=1900;
const CHAT_REPORT_LAST_MESSAGES=50;

/** Wiadomości widoczne dla zgłaszającego, od najstarszej, podzielone na kawałki mieszczące się w limicie wiadomości ticketu. */
function chat_report_transcript(array $messages): array {
    $lines=[];
    foreach($messages as $m){
        if(!empty($m['system']))continue;
        $author=($m['display_name']??'Użytkownik').' (@'.($m['username']??'?').')';
        $text=!empty($m['is_withdrawn'])?'[wiadomość cofnięta przez autora]':trim((string)($m['body']??''));
        $files=count($m['attachments']??[]);if($files)$text=trim($text.' [załączniki: '.$files.']');
        if($text==='')continue;
        $lines[]='['.substr((string)($m['created_at']??''),0,16).'] '.$author.': '.$text;
    }
    $chunks=[];$current='';
    foreach($lines as $line){
        while(mb_strlen($line)>CHAT_REPORT_CHUNK){$piece=mb_substr($line,0,CHAT_REPORT_CHUNK);$line=mb_substr($line,CHAT_REPORT_CHUNK);if($current!==''){$chunks[]=$current;$current='';}$chunks[]=$piece;}
        if($current!==''&&mb_strlen($current)+1+mb_strlen($line)>CHAT_REPORT_CHUNK){$chunks[]=$current;$current='';}
        $current=$current===''?$line:$current."\n".$line;
    }
    if($current!=='')$chunks[]=$current;
    return $chunks;
}
function chat_report_intro(PDO $db,int $id,array $actor,array $reported,string $reason,int $count): string {
    $q=$db->prepare('SELECT name FROM chat_groups WHERE conversation_id=?');$q->execute([$id]);$group=$q->fetchColumn();
    return "ZGŁOSZENIE UŻYTKOWNIKA Z CZATU\nZgłaszający: ".$actor['name'].' (@'.$actor['username'].")\nZgłaszany: ".$reported['display_name'].' (@'.$reported['username'].")\n"
        .'Rozmowa: #'.$id.', '.($group!==false&&$group!==null?'grupa „'.$group.'”':'rozmowa prywatna')."\n"
        .'Powód: '.($reason!==''?$reason:'(nie podano)')."\n"
        .($count>0?'Poniżej ostatnie wiadomości widoczne dla zgłaszającego (do '.CHAT_REPORT_LAST_MESSAGES.', czas UTC).':'W rozmowie nie ma jeszcze wiadomości tekstowych.');
}
/**
 * Zgłoszenie użytkownika: zakłada ticket (albo dopisuje do aktywnego ticketu zgłaszającego) z opisem i
 * ostatnimi wiadomościami rozmowy. Treść pochodzi z serwera, więc zgłaszający nie może jej podrobić.
 * @return array{ticket_id:int,public_id:string,created:bool,new_ticket:bool}
 */
function chat_report(PDO $db,int $id,array $actor,string $target,string $reason,string $key,string $email='',string $transcript=''): array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Nieprawidłowe zgłoszenie.');
    $reason=trim((string)preg_replace("/\r\n?/","\n",$reason));
    if(mb_strlen($reason)>500)throw new InvalidArgumentException('Powód może mieć do 500 znaków.');
    chat_member($db,$id,$actor['id']);
    $q=$db->prepare('SELECT r.ticket_id,t.public_id FROM chat_reports r LEFT JOIN support_tickets t ON t.id=r.ticket_id WHERE r.reporter_id=? AND r.client_key=?');$q->execute([$actor['id'],$key]);
    if($done=$q->fetch(PDO::FETCH_ASSOC))return ['ticket_id'=>(int)$done['ticket_id'],'public_id'=>(string)$done['public_id'],'created'=>false,'new_ticket'=>false];
    $reported=null;foreach(chat_custom_members($db,$id) as $m)if($m['username']===$target)$reported=$m;
    if(!$reported||$reported['user_id']===$actor['id'])throw new InvalidArgumentException('Wybierz użytkownika z tej rozmowy.');
    $page=chat_messages($db,$id,$actor,0);$messages=array_slice($page['messages'],-CHAT_REPORT_LAST_MESSAGES);
    // Szyfrowanych wiadomości serwer nie czyta: treść dostarcza przeglądarka zgłaszającego (id i autorzy pochodzą z bazy).
    $client=[];$list=json_decode($transcript,true);
    if(is_array($list))foreach(array_slice($list,0,CHAT_REPORT_LAST_MESSAGES) as $item)if(is_array($item)&&is_int($item['id']??null)&&is_string($item['text']??null))$client[$item['id']]=mb_substr($item['text'],0,2000);
    $fromClient=false;
    foreach($messages as &$m)if(str_starts_with((string)($m['body']??''),CHAT_E2E_PREFIX)){if(isset($client[(int)$m['id']])){$m['body']=$client[(int)$m['id']];$fromClient=true;}else $m['body']='[wiadomość zaszyfrowana, treść niedostępna]';}
    unset($m);
    $chunks=chat_report_transcript($messages);$intro=chat_report_intro($db,$id,$actor,$reported,$reason,count($chunks));
    if($fromClient)$intro.="\nUwaga: treść szyfrowanych wiadomości odszyfrowała przeglądarka zgłaszającego. Serwer jej nie widzi i nie może jej zweryfikować.";
    $access=support_access_hash();$name=mb_substr((string)$actor['name'],0,60);
    $q=$db->prepare('SELECT * FROM support_tickets WHERE status IN ("new","open") AND (access_token_hash=? OR customer_username=?) ORDER BY FIELD(status,"open","new"),created_at LIMIT 1');$q->execute([$access,$actor['username']]);
    $ticket=$q->fetch(PDO::FETCH_ASSOC);$newTicket=false;
    if(!$ticket){
        $label=support_categories()[CHAT_REPORT_CATEGORY]??'INNE / Zgłoszenie użytkownika z czatu';
        $result=support_chat_create($db,['owner'=>hash('sha256','chat-report:'.$actor['id'].':'.$key),'access'=>$access,'request'=>$key,'username'=>$actor['username'],'name'=>$name,'email'=>$email,'category'=>CHAT_REPORT_CATEGORY,'label'=>$label,'ip'=>hash('sha256',client_ip()),'body'=>$intro]);
        $ticket=$result['ticket'];$newTicket=$result['created'];$intro='';
    }
    $ids=[];$db->beginTransaction();
    try{
        $insert=$db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body,client_message_key) VALUES(?,"customer",?,?,?)');
        foreach(array_merge($intro!==''?[$intro]:[],$chunks) as $body){$insert->execute([$ticket['id'],$name,$body,bin2hex(random_bytes(32))]);$ids[]=(int)$db->lastInsertId();}
        $db->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=?')->execute([$ticket['id']]);
        $db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(?,?,"chat_report",?)')->execute([$ticket['id'],$actor['username'],'konwersacja #'.$id]);
        $db->prepare('INSERT INTO chat_reports(conversation_id,reporter_id,reported_id,ticket_id,client_key) VALUES(?,?,?,?,?)')->execute([$id,$actor['id'],$reported['user_id'],$ticket['id'],$key]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    foreach($ids as $messageId)support_realtime_emit($db,(int)$ticket['id'],'message',['message_id'=>$messageId]);
    return ['ticket_id'=>(int)$ticket['id'],'public_id'=>(string)$ticket['public_id'],'created'=>true,'new_ticket'=>$newTicket];
}
