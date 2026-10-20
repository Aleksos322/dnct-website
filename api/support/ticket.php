<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backend/lib/support_unread.php';
require_once dirname(__DIR__,2).'/backend/bootstrap.php';
require_once dirname(__DIR__,2).'/backend/lib/support.php';
require_once dirname(__DIR__,2).'/backend/lib/support_realtime.php';
require_once dirname(__DIR__,2).'/backend/mail/support_mail.php';
require_once dirname(__DIR__,2).'/backend/lib/notifications.php';
require_once dirname(__DIR__,2).'/backend/lib/support_chat_service.php';

$db=dnct_db();
msg_security_session($db);
$username=trim((string)($_SESSION['authentik_username']??''));
$isAdmin=(bool)($_SESSION['authentik_is_superuser']??false);
$access=support_access_hash();
$viewerKey=support_message_viewer_key_customer($username!==''?'account:'.$username:'guest:'.$access);

function support_owner_key(string $username,string $email): string { return hash('sha256',$username!==''?'account:'.$username:'email:'.strtolower($email)); }
function support_active_ticket(PDO $db,string $username,string $access): ?array {
    $q=$db->prepare('SELECT * FROM support_tickets WHERE status IN ("new","open") AND (access_token_hash=:access OR (:has_user=1 AND customer_username=:username)) ORDER BY FIELD(status,"open","new"), created_at ASC LIMIT 1');
    $q->execute([':access'=>$access,':has_user'=>$username!==''?1:0,':username'=>$username]);
    $ticket=$q->fetch(PDO::FETCH_ASSOC); return is_array($ticket)?$ticket:null;
}
function support_customer_ticket(PDO $db,string $username,string $access): ?array {
    $id=(int)($_GET['ticket_id']??request_string('ticket_id'));
    if($id>0){$q=$db->prepare('SELECT * FROM support_tickets WHERE id=? AND (access_token_hash=? OR (?<>"" AND customer_username=?))');$q->execute([$id,$access,$username,$username]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
    $q=$db->prepare('SELECT * FROM support_tickets WHERE status IN ("new","open","closed") AND (access_token_hash=:access OR (:has_user=1 AND customer_username=:username)) ORDER BY FIELD(status,"open","new","closed"), updated_at DESC LIMIT 1');
    $q->execute([':access'=>$access,':has_user'=>$username!==''?1:0,':username'=>$username]);$ticket=$q->fetch(PDO::FETCH_ASSOC);return is_array($ticket)?$ticket:null;
}
function support_public_ticket(?array $ticket): ?array {
    if(!$ticket)return null;
    return ['id'=>(int)$ticket['id'],'public_id'=>(string)$ticket['public_id'],'request_key'=>(string)($ticket['request_key']??''),'customer_name'=>(string)$ticket['customer_name'],'preview_remaining_seconds'=>$ticket['preview_remaining_seconds']??null,'closed_by_name'=>$ticket['closed_by_name']??null,'category_label'=>(string)$ticket['category_label'],'status'=>(string)$ticket['status'],'assigned_admin'=>$ticket['assigned_admin']?:null,'closed_at'=>$ticket['closed_at']!==null?(string)$ticket['closed_at']:null,'closed_by_user'=>(bool)($ticket['closed_by_user']??false),'close_reason'=>$ticket['close_reason']!==null?(string)$ticket['close_reason']:null];
}
function support_payload(PDO $db,?array $ticket,array $extra=[]): never {
    global $viewerKey;
    if ($ticket && !in_array($ticket['status'], ['new','open'], true)) {
        $q=$db->prepare('SELECT GREATEST(0,300-TIMESTAMPDIFF(SECOND,closed_at,NOW())) FROM support_tickets WHERE id=?');
        $q->execute([$ticket['id']]);
        $remaining=(int)$q->fetchColumn();
        if ($remaining <= 0) $ticket=null;
        else {
            $ticket['preview_remaining_seconds']=$remaining;
            $q=$db->prepare('SELECT actor_username FROM support_ticket_audit WHERE ticket_id=? AND action IN ("closed","closed_by_user") ORDER BY id DESC LIMIT 1');
            $q->execute([$ticket['id']]);
            $ticket['closed_by_name']=$q->fetchColumn() ?: (!empty($ticket['closed_by_user']) ? $ticket['customer_name'] : 'Administrator');
        }
    }
    $page=$ticket?support_chat_page($db,$ticket,'customer',$viewerKey,'/api/support/media.php',(string)$ticket['customer_name'],(int)($_GET['before_id']??0)):['messages'=>[],'has_more'=>false,'next_before_id'=>null];
    json_ok(['customer_unread'=>$ticket?support_customer_unread($db,(int)$ticket['id']):0,'ticket'=>support_public_ticket($ticket),'receipts'=>$ticket?support_message_receipts($db,(int)$ticket['id']):[]]+$page+$extra);
}
function support_payload_after_create(PDO $db,array $ticket): never {
    $q=$db->prepare("SELECT id,author_role,author_name,body,client_message_key,reply_to_message_id,created_at FROM support_messages WHERE ticket_id=:id ORDER BY id");
    $q->execute([":id"=>$ticket["id"]]);
    $messages=$q->fetchAll(PDO::FETCH_ASSOC);
    try {
    if(function_exists("exec")){
        $base=dirname(__DIR__,2);
        $worker='require '.var_export($base.'/backend/bootstrap.php',true).';require '.var_export($base.'/backend/mail/support_mail.php',true).';$q=dnct_db()->prepare("SELECT * FROM support_tickets WHERE id=:id");$q->execute([":id"=>(int)getenv("SUPPORT_TICKET_ID")]);$t=$q->fetch(PDO::FETCH_ASSOC);if($t){send_support_received_mail($t);}';
        // Ta sama konfiguracja co bieżące żądanie: beta ma DNCT_ENV_FILE z vhosta, produkcja nie ma
        // żadnego (domyślny .env). Dawniej na sztywno beta.env - produkcja wysyłałaby z bazy bety.
        $envFile=(string)($_SERVER['DNCT_ENV_FILE']??getenv('DNCT_ENV_FILE')?:'');
        $command="SUPPORT_TICKET_ID=".(int)$ticket["id"].($envFile!==''?" DNCT_ENV_FILE=".escapeshellarg($envFile):"")." /usr/bin/php -r ".escapeshellarg($worker)." >/dev/null 2>&1 &";
        @exec($command);
    }else{error_log("support receipt: exec unavailable");}
    } catch (Throwable $e) { error_log('support receipt dispatch: '.get_class($e)); }
    json_ok(['ticket'=>support_public_ticket($ticket),'messages'=>$messages,'has_more'=>false,'next_before_id'=>null]);
}
function support_audit(PDO $db,int $ticketId,?string $actor,string $action,string $details=''): void {
    $q=$db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(:ticket,:actor,:action,:details)');
    $q->execute([':ticket'=>$ticketId,':actor'=>$actor,':action'=>$action,':details'=>$details?:null]);
}

if(($_SERVER['REQUEST_METHOD']??'')==='GET') support_payload($db,support_customer_ticket($db,$username,$access));
require_method('POST');csrf_guard();$action=request_string('action');

// Compatibility for the production endpoint used by existing game tickets.
if($action==='close_user'&&!function_exists('support_chat_mutate')){
    require_once dirname(__DIR__,2).'/backend/lib/support_customer_close.php';
    rate_limit_guard('support_message',RATE_LIMIT_SUPPORT_MESSAGE,'Wysyłasz żądania zbyt szybko.');
    try{$closed=support_close_customer_ticket($db,(int)request_string('ticket_id'),$username,$access,request_string('reason'));}
    catch(InvalidArgumentException $e){json_error($e->getMessage(),422);}
    catch(DomainException $e){json_error($e->getMessage(),409);}
    support_payload($db,$closed);
}

if($action==='create'){
    if(!$isAdmin) rate_limit_guard('support_ticket',RATE_LIMIT_SUPPORT_TICKET,'Za dużo zgłoszeń z tego adresu. Spróbuj później.');
    if(request_string('website')!=='')json_error('Nie udało się wysłać formularza.',422);
    $name=request_string('name');$email=strtolower(request_string('email'));$category=request_string('category');$body=support_clean_body(request_string('message'));$errors=[];
    if(($e=validate_person_name($name,'Podaj imię.'))!==null)$errors['name']=$e;
    if(($e=validate_email_value($email))!==null)$errors['email']=$e;
    if(!isset(support_categories()[$category]))$errors['category']='Wybierz kategorię.';
    if(mb_strlen($body)<SUPPORT_MESSAGE_MIN||mb_strlen($body)>SUPPORT_MESSAGE_MAX)$errors['message']='Wiadomość musi mieć od 20 do 2000 znaków.';
    if($errors)json_error('Popraw oznaczone pola.',422,$errors);
    if($username!==''&&!empty($_SESSION['authentik_email']))$email=strtolower((string)$_SESSION['authentik_email']);
    $owner=support_owner_key($username,$email);
    $requestKey=request_string('request_key');if(!preg_match('/^[a-f0-9]{64}$/',$requestKey))$requestKey=hash('sha256',bin2hex(random_bytes(32)));
    $existing=support_active_ticket($db,$username,$access);
    if($existing) support_payload($db,$existing,['already_exists'=>true]);
    $result=support_chat_create($db,['owner'=>$owner,'access'=>$access,'request'=>$requestKey,'username'=>$username,'name'=>$name,'email'=>$email,'category'=>$category,'label'=>support_categories()[$category],'ip'=>hash('sha256',client_ip()),'body'=>$body]);
    $ticket=$result['ticket'];
    if(!$result['created'])support_payload($db,$ticket,['already_exists'=>true]);
    try{notification_admin_event($db,'support.new',(string)$ticket['id'],'Nowe zgłoszenie '.$ticket['public_id'],$name.' — '.support_categories()[$category],'http://admin.lan.dnct.pl/wsparcie?source=beta&ticket='.$ticket['id']);}catch(Throwable $e){error_log('support notify: '.$e->getMessage());}
    support_payload_after_create($db,$ticket);
}
if(in_array($action,['message','edit_message','withdraw_message','react','close_user'],true)){
    if ($action !== 'message') rate_limit_guard('support_message',RATE_LIMIT_SUPPORT_MESSAGE,'Wysyłasz wiadomości zbyt szybko.');
    $ticket=support_customer_ticket($db,$username,$access);
    if(!$ticket||(int)$ticket['id']!==(int)request_string('ticket_id'))json_error('Nie znaleziono zgłoszenia.',404);
    $input=[];foreach(['message','message_key','message_id','reply_to_id','scope','emoji','reason'] as $field)$input[$field]=request_string($field);
    try{support_chat_mutate($db,(int)$ticket['id'],'customer',$viewerKey,(string)$ticket['customer_name'],$action,$input,$_FILES['attachments']??[]);}
    catch(InvalidArgumentException $e){json_error($e->getMessage(),422);}
    catch(DomainException $e){json_error($e->getMessage(),409);}
    support_payload($db,support_customer_ticket($db,$username,$access));
}
json_error('Nieznana akcja.',400);
