<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backend/bootstrap.php';
require_once dirname(__DIR__,2).'/backend/lib/support.php';
require_once dirname(__DIR__,2).'/backend/lib/support_realtime.php';
require_once dirname(__DIR__,2).'/backend/mail/support_mail.php';

$db=dnct_db();
$username=trim((string)($_SESSION['authentik_username']??''));
$isAdmin=(bool)($_SESSION['authentik_is_superuser']??false);
$access=support_access_hash();

function support_owner_key(string $username,string $email): string { return hash('sha256',$username!==''?'account:'.$username:'email:'.strtolower($email)); }
function support_active_ticket(PDO $db,string $username,string $access): ?array {
    $q=$db->prepare('SELECT * FROM support_tickets WHERE status IN ("new","open") AND (access_token_hash=:access OR (:has_user=1 AND customer_username=:username)) ORDER BY FIELD(status,"open","new"), created_at ASC LIMIT 1');
    $q->execute([':access'=>$access,':has_user'=>$username!==''?1:0,':username'=>$username]);
    $ticket=$q->fetch(PDO::FETCH_ASSOC); return is_array($ticket)?$ticket:null;
}
function support_payload(PDO $db,?array $ticket,array $extra=[]): never {
    $messages=[];$hasMore=false;$nextBeforeId=null;$receipts=[];
    if($ticket){$page=support_messages_page($db,(int)$ticket['id'],(int)($_GET['before_id']??0),(int)($_GET['limit']??SUPPORT_REALTIME_PAGE_SIZE));$messages=$page['messages'];$hasMore=$page['has_more'];$nextBeforeId=$page['next_before_id'];$receipts=support_message_receipts($db,(int)$ticket['id']);}
    json_ok(['ticket'=>$ticket,'messages'=>$messages,'has_more'=>$hasMore,'next_before_id'=>$nextBeforeId,'receipts'=>$receipts]+$extra);
}
function support_payload_after_create(PDO $db,array $ticket): never {
    $q=$db->prepare("SELECT id,author_role,author_name,body,client_message_key,reply_to_message_id,created_at FROM support_messages WHERE ticket_id=:id ORDER BY id");
    $q->execute([":id"=>$ticket["id"]]);
    $messages=$q->fetchAll(PDO::FETCH_ASSOC);
    if(!headers_sent()){http_response_code(200);header("Content-Type: application/json; charset=utf-8");header("Cache-Control: no-store, no-cache, must-revalidate");header("Pragma: no-cache");header("X-Content-Type-Options: nosniff");}
    echo json_encode(["ok"=>true,"ticket"=>$ticket,"messages"=>$messages],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(function_exists("exec")){
        $base=dirname(__DIR__,2);
        $worker="require ".var_export($base."/backend/bootstrap.php",true).";require ".var_export($base."/backend/mail/support_mail.php",true).";$q=dnct_db()->prepare(\"SELECT * FROM support_tickets WHERE id=:id\");$q->execute([\":id\"=>(int)getenv(\"SUPPORT_TICKET_ID\")]);$t=$q->fetch(PDO::FETCH_ASSOC);if($t){send_support_received_mail($t);}";
        // Ta sama konfiguracja co bieżące żądanie: beta ma DNCT_ENV_FILE z vhosta, produkcja nie ma
        // żadnego (domyślny .env). Dawniej na sztywno beta.env - produkcja wysyłałaby z bazy bety.
        $envFile=(string)($_SERVER['DNCT_ENV_FILE']??getenv('DNCT_ENV_FILE')?:'');
        $command="SUPPORT_TICKET_ID=".(int)$ticket["id"].($envFile!==''?" DNCT_ENV_FILE=".escapeshellarg($envFile):"")." /usr/bin/php -r ".escapeshellarg($worker)." >/dev/null 2>&1 &";
        @exec($command);
    }else{error_log("support receipt: exec unavailable");}
    exit;
}
function support_audit(PDO $db,int $ticketId,?string $actor,string $action,string $details=''): void {
    $q=$db->prepare('INSERT INTO support_ticket_audit(ticket_id,actor_username,action,details) VALUES(:ticket,:actor,:action,:details)');
    $q->execute([':ticket'=>$ticketId,':actor'=>$actor,':action'=>$action,':details'=>$details?:null]);
}

if(($_SERVER['REQUEST_METHOD']??'')==='GET') support_payload($db,support_active_ticket($db,$username,$access));
require_method('POST');csrf_guard();$action=request_string('action');

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
    try{
        $db->beginTransaction();
        // Unikalny numer: 9000 możliwości i UNIQUE na public_id - powtórka losowania kończyła się
        // wyjątkiem 23000 i zgłoszenie nie powstawało. Losujemy do skutku (zwykle 1 próba).
        $free=$db->prepare('SELECT 1 FROM support_tickets WHERE public_id=:n');
        for($try=0;$try<20;$try++){$number='TKT-'.random_int(1000,9999);$free->execute([':n'=>$number]);if(!$free->fetchColumn())break;}
        $q=$db->prepare('INSERT INTO support_tickets(public_id,access_token_hash,owner_key,active_owner_key,request_key,customer_username,customer_name,customer_email,category_key,category_label,ip_hash,status) VALUES(:number,:access,:owner,:active_owner,:request,:username,:name,:email,:category,:label,:ip,"new")');
        $q->execute([':number'=>$number,':access'=>$access,':owner'=>$owner,':active_owner'=>$owner,':request'=>$requestKey,':username'=>$username?:null,':name'=>$name,':email'=>$email,':category'=>$category,':label'=>support_categories()[$category],':ip'=>hash('sha256',client_ip())]);
        $id=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body) VALUES(:id,"customer",:name,:body)')->execute([':id'=>$id,':name'=>$name,':body'=>$body]);
        support_audit($db,$id,$username?:null,'created');
        $db->commit();
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if($e->getCode()!=='23000')throw $e;$q=$db->prepare('SELECT * FROM support_tickets WHERE request_key=:request OR active_owner_key=:owner ORDER BY id DESC LIMIT 1');$q->execute([':request'=>$requestKey,':owner'=>$owner]);$ticket=$q->fetch(PDO::FETCH_ASSOC);if($ticket)support_payload($db,$ticket,['already_exists'=>true]);throw $e;}
    $ticket=support_active_ticket($db,$username,$access);if($ticket) support_payload_after_create($db,$ticket);support_payload($db,$ticket);
}
if($action==='message'){
    rate_limit_guard('support_message',RATE_LIMIT_SUPPORT_MESSAGE,'Wysyłasz wiadomości zbyt szybko.');
    $id=(int)request_string('ticket_id');$body=support_clean_body(request_string('message'));$key=request_string('message_key');
    if($id<1||mb_strlen($body)<1||mb_strlen($body)>SUPPORT_MESSAGE_MAX)json_error('Wiadomość musi mieć od 1 do 2000 znaków.',422,['message'=>'Sprawdź wiadomość.']);
    if(!preg_match('/^[a-f0-9]{64}$/',$key))$key=hash('sha256',bin2hex(random_bytes(32)));
    $ticket=support_active_ticket($db,$username,$access);
    if(!$ticket||(int)$ticket['id']!==$id)json_error('Nie znaleziono aktywnego zgłoszenia.',404);
    if($ticket['status']!=='open'||empty($ticket['assigned_admin']))json_error('Poczekaj, aż administrator przypisze zgłoszenie do siebie.',409);
    $replyId=(int)request_string('reply_to_id');if($replyId>0){$reply=$db->prepare('SELECT id FROM support_messages WHERE id=:reply AND ticket_id=:ticket');$reply->execute([':reply'=>$replyId,':ticket'=>$id]);if(!$reply->fetchColumn())$replyId=0;}
    try{$db->prepare('INSERT INTO support_messages(ticket_id,author_role,author_name,body,client_message_key,reply_to_message_id) VALUES(:id,"customer",:name,:body,:key,:reply)')->execute([':id'=>$id,':name'=>$ticket['customer_name'],':body'=>$body,':key'=>$key,':reply'=>$replyId?:null]);$db->prepare('UPDATE support_tickets SET updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;}
    support_payload($db,support_active_ticket($db,$username,$access));
}
json_error('Nieznana akcja.',400);
