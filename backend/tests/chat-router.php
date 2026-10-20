<?php
// Only the loopback test server uses this router. A per-run random token is mandatory.
declare(strict_types=1);
$token=(string)getenv('DNCT_CHAT_TEST_TOKEN');
if(strlen($token)<32||!hash_equals($token,(string)($_SERVER['HTTP_X_CHAT_TEST_TOKEN']??''))){http_response_code(404);exit;}
$base=dirname(__DIR__,3);$beta=$base.'/beta.dnct.pl';$adminRoot=$base.'/admin.dnct.pl';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$role=(string)($_SERVER['HTTP_X_CHAT_TEST_ROLE']??'customer');if(!in_array($role,['customer','admin','outsider'],true))$role='customer';
if(str_starts_with($path,'/assets/')){ $file=realpath(($role==='admin'?$adminRoot:$beta).$path);if(!$file||!str_starts_with($file,$base.'/')||!is_file($file)||!in_array(pathinfo($file,PATHINFO_EXTENSION),['css','js','json','svg','png','webp','woff2'],true)){http_response_code(404);exit;}header('Content-Type: '.match(pathinfo($file,PATHINFO_EXTENSION)){'css'=>'text/css','js'=>'application/javascript','json'=>'application/json','svg'=>'image/svg+xml',default=>'application/octet-stream'});readfile($file);exit;}
putenv('DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env');
if($role==='admin')require $adminRoot.'/backend/bootstrap.php';else require $beta.'/backend/bootstrap.php';
$tag=(string)getenv('DNCT_CHAT_TEST_TAG');if(!preg_match('/^codex-chat-check-[a-f0-9]{12}$/D',$tag))exit;
$_SESSION['authentik_username']=$tag.'-'.$role;$_SESSION['authentik_email']=$tag.'@example.invalid';$_SESSION['authentik_is_superuser']=$role==='admin';$_SESSION['support_access']=$role==='outsider'?$tag.'-outsider':$tag;
$_SESSION['chat_identity']=['profile'=>['id'=>$tag.'-'.$role,'username'=>$tag.'-'.$role,'name'=>$role==='admin'?'Administrator testowy':'Klient testowy','is_admin'=>$role==='admin'],'until'=>time()+600];
if($path==='/api/support/ticket.php'&&($_SERVER['REQUEST_METHOD']??'')==='POST'&&request_string('action')==='create'){
    // Exercise the real creation transaction without sending test mail/phone notifications.
    csrf_guard();require $beta.'/backend/lib/support_chat_service.php';require $beta.'/backend/lib/support.php';
    $username=$_SESSION['authentik_username'];$fields=['owner'=>hash('sha256','account:'.$username),'access'=>support_access_hash(),'request'=>request_string('request_key'),'username'=>$username,'name'=>request_string('name'),'email'=>$_SESSION['authentik_email'],'category'=>request_string('category'),'label'=>support_categories()[request_string('category')]??'Test','ip'=>hash('sha256','127.0.0.1'),'body'=>request_string('message')];
    if(mb_strlen($fields['body'])<20)json_error('Opisz problem (minimum 20 znaków).',422);
    $result=support_chat_create(dnct_db(),$fields);$ticket=$result['ticket'];$key=support_message_viewer_key_customer('account:'.$username);
    json_ok(['ticket'=>array_intersect_key($ticket,array_flip(['id','public_id','status','assigned_admin','customer_name','closed_by_user','close_reason']))]+support_chat_page(dnct_db(),$ticket,'customer',$key,'/api/support/media.php',$fields['name']));
}
if($path==='/api/chat.php'){require $beta.'/api/chat.php';exit;}
if($path==='/czat-api'){require $adminRoot.'/czat-api.php';exit;}
if(in_array($path,['/api/support/ticket.php','/api/support/media.php','/api/support/realtime.php'],true)){require $beta.$path;exit;}
if(in_array($path,['/api/wsparcie-chat.php','/api/wsparcie-media.php','/api/wsparcie-realtime.php'],true)){require $adminRoot.$path;exit;}
if($path==='/wsparcie'&&$role==='admin'&&($_SERVER['REQUEST_METHOD']??'')==='GET'){require $adminRoot.'/wsparcie.php';exit;}
if($path==='/'){
    if(isset($_GET['full'])){$pageTitle='Test czatu DNCT';$content='<div style="min-height:2000px;padding:120px 30px"><h1>Rozmowy i wsparcie</h1></div>';require $beta.'/assets/clear/clear.php';exit;}
    ?><!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Test czatu DNCT</title><style>body{margin:0;min-height:2400px;background:#0b0911;color:#eee;font:16px system-ui}header#main-header{height:80px;background:#171320}#main-header nav{height:70px;padding:0 30px;display:flex;align-items:center}h1{padding:40px}button{cursor:pointer}</style></head><body><header id="main-header"><nav>DNCT · Test interfejsu</nav></header><h1>Rozmowy i wsparcie</h1><?php
    if($role!=='admin'){require $beta.'/assets/clear/support-chat.php';require $beta.'/assets/clear/support-ticket-panel.php';}
    require $beta.'/assets/clear/direct-chat.php';echo '</body></html>';exit;
}
http_response_code(404);
