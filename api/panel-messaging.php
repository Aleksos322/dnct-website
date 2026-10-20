<?php
/** Only signed server-to-server requests; no browser login or public proxy. */
declare(strict_types=1);
function bridge_deny(int $status=403): never { http_response_code($status);header('Content-Type: application/json');echo '{"ok":false,"message":"Brak dostępu do komunikacji."}';exit; }
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') bridge_deny();
$raw=base64_decode((string)($_POST['bridge_payload']??''),true);
$sig=base64_decode((string)($_POST['bridge_signature']??''),true);
$key=@file_get_contents(__DIR__.'/../backend/lib/panel-messaging-public.key');
if(!is_string($raw)||strlen($raw)>1048576||!is_string($sig)||strlen($sig)!==64||!is_string($key)||strlen($key)!==32||!sodium_crypto_sign_verify_detached($sig,$raw,$key))bridge_deny();
$p=json_decode($raw,true);
if(!is_array($p)||($p['v']??0)!==1||abs(time()-(int)($p['time']??0))>60||!preg_match('/^[a-f0-9]{48}$/D',$p['nonce']??'')||!preg_match('/^[a-f0-9]{64}$/D',$p['session']??'')||!filter_var($p['email']??'',FILTER_VALIDATE_EMAIL))bridge_deny();
$path=(string)($p['path']??'');
if(!preg_match('~^(frame|revoke|api/(chat\.php|support/(ticket|realtime|media)\.php|notifications/(list|unread-count|mark-read|mark-all-read|hide|clear-read|media)\.php)|assets/(css/(tailwind|chat-shell|chat-ui|notification-bell)\.css|js/(chat-ui|notifications|messenger-controller|support-controller|support-realtime|sound|panel-messaging-frame|messaging-native)\.js|data/emoji-pl\.json))$~D',$path))bridge_deny(404);
if(!in_array($p['method']??'', ['GET','POST'],true))bridge_deny(405);
if($path==='revoke'&&$p['method']!=='POST')bridge_deny(405);
$actual=[];
foreach($_FILES as $field=>$file){
    foreach(is_array($file['tmp_name'])?$file['tmp_name']:[$file['tmp_name']] as $i=>$tmp){
        $name=is_array($file['tmp_name'])?$field.'['.$i.']':$field;
        if(!is_string($tmp)||!is_uploaded_file($tmp))bridge_deny(422);
        $actual[$name]=hash_file('sha256',$tmp);
    }
}
$expected=$p['files']??[];ksort($actual);ksort($expected);if($actual!==$expected)bridge_deny();
// Distinct PHP session, no browser cookies, stable only for this panel session.
ini_set('session.use_cookies','0');ini_set('session.use_strict_mode','0');
session_name('DNCT_PANEL_BRIDGE');session_id(hash('sha256','panel-messaging:'.$p['session']));
$_COOKIE=[];$_GET=is_array($p['query']??null)?$p['query']:[];$_POST=is_array($p['params']??null)?$p['params']:[];
$_SERVER['REQUEST_METHOD']=$p['method'];$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';
$_SERVER['HTTP_X_CSRF_TOKEN']=(string)$p['csrf'];$_SERVER['HTTP_RANGE']=(string)($p['range']??'');
$_SERVER['HTTP_CF_CONNECTING_IP']=filter_var($p['ip']??'',FILTER_VALIDATE_IP)?$p['ip']:'127.0.0.1';
require_once __DIR__.'/../backend/bootstrap.php';
$db=dnct_db();
try{$q=$db->prepare('INSERT INTO panel_messaging_nonces(nonce,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 2 MINUTE))');$q->execute([$p['nonce']]);}catch(Throwable $e){bridge_deny();}
if(random_int(1,100)===1)$db->exec('DELETE FROM panel_messaging_nonces WHERE expires_at<NOW()');
require_once __DIR__.'/../backend/lib/messaging_security.php';
if($path==='revoke'){msg_security_revoke($db);json_ok();}
if(isset($_SESSION['panel_email'])&&!hash_equals($_SESSION['panel_email'],$p['email']))bridge_deny();
if(empty($_SESSION['authentik_username'])){
    $r=authentik_api_request('GET','/api/v3/core/users/?email='.rawurlencode($p['email']).'&page_size=100');
    if($r['http_code']!==200)bridge_deny(503);
    $matches=array_values(array_filter($r['body']['results']??[],fn($u)=>strcasecmp((string)($u['email']??''),$p['email'])===0&&($u['is_active']??false)&&($u['type']??'')!=='service_account'));
    if(count($matches)!==1)bridge_deny();$u=$matches[0];
    $_SESSION['panel_email']=$p['email'];$_SESSION['authentik_email']=$u['email'];$_SESSION['authentik_username']=$u['username'];
    $_SESSION['authentik_is_superuser']=(bool)($u['is_superuser']??false);$_SESSION['logged_in_at']=time();
}
$_SESSION['csrf_token']=(string)$p['csrf'];
$_SESSION['messaging_site']=($p['site']??'')==='game'?'game':'beta';
msg_security_session($db);
header('Cache-Control: private, no-store');
if($path==='frame'){require __DIR__.'/../backend/lib/panel-messaging-frame.php';exit;}
if(str_starts_with($path,'assets/')){
    $ext=pathinfo($path,PATHINFO_EXTENSION);header('Content-Type: '.(['js'=>'application/javascript','css'=>'text/css','json'=>'application/json'][$ext]??'application/octet-stream'));
    session_write_close();readfile(__DIR__.'/../'.$path);exit;
}
require __DIR__.'/../'.$path;
