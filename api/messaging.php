<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/backend/bootstrap.php';
if(!isset($_SESSION['authentik_username'],$_SESSION['authentik_email']))json_error('Zaloguj się, aby korzystać z komunikacji.',401);
$path=(string)($_GET['bridge_path']??'');
if(!preg_match('~^(frame|revoke|api/(chat\.php|support/(ticket|realtime|media)\.php|notifications/(list|unread-count|mark-read|mark-all-read|hide|clear-read|media)\.php)|assets/(css/(tailwind|chat-shell|chat-ui|notification-bell)\.css|js/(chat-ui|notifications|messenger-controller|support-controller|support-realtime|sound|panel-messaging-frame|messaging-native)\.js|data/emoji-pl\.json))$~D',$path))json_error('Nie znaleziono zasobu.',404);
$method=$_SERVER['REQUEST_METHOD'];if(!in_array($method,['GET','POST'],true))json_error('Niedozwolona metoda.',405);
if($path==='revoke'&&$method!=='POST')json_error('Niedozwolona metoda.',405);
if($method==='POST')csrf_guard();
$params=request_payload();$query=$_GET;unset($query['bridge_path']);
$files=[];$parts=[];
foreach($_FILES as $field=>$file){
 if(!in_array($field,['attachments','avatar','file','image'],true))json_error('Nieprawidłowy załącznik.',422);
 foreach(is_array($file['tmp_name'])?$file['tmp_name']:[$file['tmp_name']] as $i=>$tmp){
  $array=is_array($file['tmp_name']);$name=$array?$field.'['.$i.']':$field;
  $size=$array?$file['size'][$i]:$file['size'];$error=$array?$file['error'][$i]:$file['error'];$original=$array?$file['name'][$i]:$file['name'];
  if($error!==UPLOAD_ERR_OK||!is_uploaded_file($tmp)||$size>52428800||count($files)>=4)json_error('Nieprawidłowy lub zbyt duży załącznik.',422);
  $files[$name]=hash_file('sha256',$tmp);$parts[$name]=new CURLFile($tmp,'application/octet-stream',$original);
 }
}
$payload=json_encode(['v'=>1,'time'=>time(),'nonce'=>bin2hex(random_bytes(24)),
 'session'=>hash('sha256','site:'.session_id()),'email'=>$_SESSION['authentik_email'],
 'csrf'=>csrf_token(),'ip'=>client_ip(),'method'=>$method,'path'=>$path,'query'=>$query,
 'params'=>$params,'files'=>$files,'range'=>$_SERVER['HTTP_RANGE']??''],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$key=@file_get_contents('/home/jakub/dnct-private-media/site-messaging.key');
if(!is_string($key)||strlen($key)!==64)json_error('Komunikacja jest chwilowo niedostępna.',503);
$parts['bridge_payload']=base64_encode($payload);$parts['bridge_signature']=base64_encode(sodium_crypto_sign_detached($payload,$key));
session_write_close();
$stream=fopen('php://temp','w+');$headers=[];
$ch=curl_init('http://127.0.0.1:8910/api/panel-messaging.php');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$parts,CURLOPT_HTTPHEADER=>['Host: beta.dnct.pl'],CURLOPT_FILE=>$stream,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$path==='revoke'?3:65,CURLOPT_FOLLOWLOCATION=>false,
 CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);}]);
$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
if($ok===false||$status<100)json_error('Komunikacja jest chwilowo niedostępna.',503);
http_response_code($status);header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
$type=$headers['content-type']??'application/octet-stream';header('Content-Type: '.$type);
foreach(['content-disposition','content-range','accept-ranges','retry-after'] as $h)if(isset($headers[$h]))header($h.': '.$headers[$h]);
rewind($stream);
if(str_contains($type,'application/json')){
 $raw=stream_get_contents($stream);$data=json_decode($raw,true);
 if(is_array($data)){$rewrite=static function(&$node)use(&$rewrite){foreach($node as $key=>&$value){if(is_array($value))$rewrite($value);elseif(is_string($value)&&in_array($key,['url','media_url','download_url','group_avatar_url'],true)&&str_starts_with($value,'/api/'))$value='/messaging'.$value;elseif($key==='ws_path'&&$value==='/ws/support')$value='wss://panel.dnct.pl/ws/messaging';elseif($key==='ws_url'&&is_string($value))$value='wss://panel.dnct.pl/ws/messaging';}};$rewrite($data);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}else echo $raw;
}elseif(preg_match('~text/html|javascript|text/css~',$type))echo preg_replace('~(?<!/messaging)/(assets|api)/~', '/messaging/$1/',stream_get_contents($stream));
else fpassthru($stream);
fclose($stream);
