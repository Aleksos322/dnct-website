<?php
declare(strict_types=1);
function messaging_site_revoke(): void {
 if(!isset($_SESSION['authentik_email'])||!in_array(strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]),['dnct.pl','www.dnct.pl','game.dnct.pl'],true))return;
 try{
  $key=@file_get_contents('/home/jakub/dnct-private-media/site-messaging.key');if(!is_string($key)||strlen($key)!==64)return;
  $raw=json_encode(['v'=>1,'time'=>time(),'nonce'=>bin2hex(random_bytes(24)),'session'=>hash('sha256','site:'.session_id()),'email'=>$_SESSION['authentik_email'],'csrf'=>csrf_token(),'ip'=>client_ip(),'method'=>'POST','path'=>'revoke','query'=>[],'params'=>[],'files'=>[]],JSON_THROW_ON_ERROR);
  $ch=curl_init('http://127.0.0.1:8910/api/panel-messaging.php');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['bridge_payload'=>base64_encode($raw),'bridge_signature'=>base64_encode(sodium_crypto_sign_detached($raw,$key))],CURLOPT_HTTPHEADER=>['Host: beta.dnct.pl'],CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>3,CURLOPT_RETURNTRANSFER=>true]);curl_exec($ch);curl_close($ch);
 }catch(Throwable $e){error_log('messaging_site: revocation unavailable');}
}
