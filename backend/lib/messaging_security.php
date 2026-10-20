<?php
declare(strict_types=1);
/** Beta security policy. Admin production tickets deliberately keep their production backend. */
function msg_security_bind(PDO $db): bool {
    if(defined('WSPARCIE_ZRODLO') && WSPARCIE_ZRODLO!=='beta')return false;
    $GLOBALS['msg_security_db']=$db;return true;
}
function msg_actor_key(): string {
    $username=(string)($_SESSION['authentik_username']??'');
    return hash('sha256',$username!==''?'user:'.$username:'guest:'.session_id());
}
function msg_security_log(PDO $db,string $event,int $resource=0,?string $subject=null): void {
    // Fixed event codes only: never serialize input, exceptions, tokens or message contents.
    if(!preg_match('/^[a-z_]{3,48}$/D',$event))return;
    $subject=$subject??msg_actor_key();$ip=hash('sha256',function_exists('client_ip')?client_ip():'cli');
    $key=hash('sha256',$event.':'.$subject.':'.$resource.':'.intdiv(time(),60));
    try{$q=$db->prepare('INSERT INTO messaging_security_audit(event_key,event_type,subject_key,ip_hash,resource_id,occurred_at,last_seen) VALUES(?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE hits=hits+1,last_seen=NOW()');$q->execute([$key,$event,$subject,$ip,$resource]);}catch(Throwable $e){error_log('messaging_security: audit_unavailable');}
}
function msg_rate_hit(PDO $db,string $subject,string $scope,int $second=5,int $minute=50): int {
    if($db->inTransaction())throw new LogicException('Rate limit must precede the message transaction.');
    $key=hash('sha256',$scope."\0".$subject);
    $db->beginTransaction();
    try{
        $db->prepare("INSERT INTO messaging_limits(bucket,hits,updated_at) VALUES(?,'[]',NOW()) ON DUPLICATE KEY UPDATE bucket=VALUES(bucket)")->execute([$key]);
        $q=$db->prepare('SELECT hits,blocked_until FROM messaging_limits WHERE bucket=? FOR UPDATE');$q->execute([$key]);$row=$q->fetch(PDO::FETCH_ASSOC);$now=(int)floor(microtime(true)*1000);
        $hits=array_values(array_filter(json_decode($row['hits'],true,512,JSON_THROW_ON_ERROR),fn($t)=>$t>$now-60000));
        $until=(int)$row['blocked_until'];
        if($until<=$now){
            if(count(array_filter($hits,fn($t)=>$t>$now-1000))>=$second)$until=$now+3000;
            elseif(count($hits)>=$minute)$until=max($now+3000,(int)$hits[0]+60000);
            else{$hits[]=$now;$until=0;}
        }
        $db->prepare('UPDATE messaging_limits SET hits=?,blocked_until=?,updated_at=NOW() WHERE bucket=?')->execute([json_encode($hits),$until,$key]);$db->commit();
        return $until>$now?(int)ceil(($until-$now)/1000):0;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function msg_rate_guard(PDO $db,string $scope='message',int $second=5,int $minute=50): void {
    if(!msg_security_bind($db))return;
    try{$retry=msg_rate_hit($db,msg_actor_key(),$scope,$second,$minute);}
    catch(Throwable $e){msg_security_log($db,'limiter_unavailable');json_error('Wysyłanie jest chwilowo niedostępne. Spróbuj ponownie.',503);}
    if($retry){msg_security_log($db,'rate_limited');header('Retry-After: '.$retry);json_error('Wysyłasz wiadomości zbyt szybko. Odczekaj '.$retry.' sekundy.',429,[],['retry_after'=>$retry]);}
}
function msg_identity_fingerprint(array $user): string {
    $groups=array_map('strval',$user['groups']??[]);sort($groups,SORT_STRING);
    return hash('sha256',json_encode([(bool)($user['is_active']??false),(bool)($user['is_superuser']??false),$groups],JSON_UNESCAPED_SLASHES));
}
function msg_security_session(PDO $db): string {
    $hash=hash('sha256',session_id());$username=(string)($_SESSION['authentik_username']??'');
    $q=$db->prepare('SELECT revoked,identity_fingerprint FROM messaging_security_sessions WHERE session_hash=?');$q->execute([$hash]);$old=$q->fetch(PDO::FETCH_ASSOC);
    if($old&&(int)$old['revoked']){msg_security_log($db,'session_revoked');json_error('Sesja czatu wygasła. Zaloguj się ponownie.',401);}
    $cached=$_SESSION['messaging_identity']??null;
    if($username!==''&&(!is_array($cached)||$cached['username']!==$username||$cached['until']<time())){
        $r=authentik_api_request('GET','/api/v3/core/users/?username='.rawurlencode($username).'&page_size=100');
        if($r['http_code']!==200){msg_security_log($db,'identity_unavailable');json_error('Nie można potwierdzić uprawnień. Spróbuj ponownie.',503);}
        $u=null;foreach($r['body']['results']??[] as $item)if(($item['username']??'')===$username)$u=$item;
        $fingerprint=$u?msg_identity_fingerprint($u):'';
        if(!$u||!($u['is_active']??false)||(bool)($_SESSION['authentik_is_superuser']??false)!==(bool)($u['is_superuser']??false)||($old&&$old['identity_fingerprint']!==$fingerprint)){
            msg_security_revoke($db);msg_security_log($db,'identity_revoked');$_SESSION=[];session_regenerate_id(true);json_error('Uprawnienia konta uległy zmianie. Zaloguj się ponownie.',401);
        }
        $_SESSION['messaging_identity']=$cached=['username'=>$username,'fingerprint'=>$fingerprint,'until'=>time()+60];
    }
    $fingerprint=$username!==''?$cached['fingerprint']:hash('sha256','guest');
    $q=$db->prepare('INSERT INTO messaging_security_sessions(session_hash,subject_key,username,identity_fingerprint,sid_hash,sub_hash,logged_at,expires_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE)) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at)');
    $q->execute([$hash,msg_actor_key(),$username,$fingerprint,!empty($_SESSION['oidc_sid'])?hash('sha256',$_SESSION['oidc_sid']):'',!empty($_SESSION['oidc_sid_sub'])?hash('sha256',$_SESSION['oidc_sid_sub']):'',(int)($_SESSION['logged_in_at']??time())]);
    return $hash;
}
function msg_security_revoke(PDO $db): void {
    $db->prepare('UPDATE messaging_security_sessions SET revoked=1 WHERE session_hash=?')->execute([hash('sha256',session_id())]);
}
function msg_ws_token(PDO $db,string $scope,string $userId,int $ticket,string $role,string $participant): string {
    msg_rate_guard($db,'token',2,20);$session=msg_security_session($db);$token=bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO messaging_ws_grants(token_hash,session_hash,scope,user_id,ticket_id,role,participant_key,expires_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))')
       ->execute([hash('sha256',$token),$session,$scope,$userId,$ticket,$role,$participant]);
    return $token;
}
function msg_media_root(string $module): string {
    if(!in_array($module,['chat','support','notification'],true))throw new LogicException('Invalid module');
    return '/home/jakub/dnct-private-media/beta/'.$module;
}
function msg_validate_media_file(string $tmp,string $original,int $size,int $imageLimit=10485760): array {
    if(!is_uploaded_file($tmp)||$size<1)throw new InvalidArgumentException('Nieprawidłowy lub pusty załącznik.');
    $allowed=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp'],'image/gif'=>['gif'],'image/avif'=>['avif'],'video/mp4'=>['mp4'],'video/webm'=>['webm'],'video/quicktime'=>['mov']];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
    if(!isset($allowed[$mime])||!in_array($ext,$allowed[$mime],true))throw new InvalidArgumentException('Format pliku nie zgadza się z rozszerzeniem. Dozwolone: JPG, PNG, WebP, GIF, MP4, WebM, MOV.');
    $image=str_starts_with($mime,'image/');
    if($size>($image?$imageLimit:52428800))throw new InvalidArgumentException('Załącznik jest za duży. Zdjęcie: 10 MB, film: 50 MB.');
    if($image){$info=@getimagesize($tmp);if(!$info||($info['mime']??'')!==$mime||$info[0]>12000||$info[1]>12000||$info[0]*$info[1]>40000000)throw new InvalidArgumentException('Nieprawidłowy obraz lub przekroczony limit 40 megapikseli.');}
    return ['mime'=>$mime,'kind'=>$image?'image':'video'];
}
function msg_upload_charge(PDO $db,array $files,string $module): void {
    if(!$files||!msg_security_bind($db))return;
    $bytes=array_sum(array_map(fn($f)=>(int)($f['byte_size']??$f['size']),$files));
    msg_quota_charge($db,$bytes,0);
}
function msg_quota_charge(PDO $db,int $upload,int $download,?int $storageDelta=null): void {
    $storageDelta=$storageDelta??$upload;
    if($upload<0||$download<0||$storageDelta<0)throw new LogicException('Invalid quota amount');
    $own=!$db->inTransaction();if($own)$db->beginTransaction();
    try{
        $actor=msg_actor_key();$db->prepare('INSERT INTO messaging_media_usage(subject_key,day_key) VALUES(?,CURRENT_DATE()) ON DUPLICATE KEY UPDATE subject_key=VALUES(subject_key)')->execute([$actor]);
        $q=$db->prepare('SELECT *,day_key=CURRENT_DATE() AS today FROM messaging_media_usage WHERE subject_key=? FOR UPDATE');$q->execute([$actor]);$u=$q->fetch(PDO::FETCH_ASSOC);
        $up=($u['today']?(int)$u['uploaded_bytes']:0)+$upload;$down=($u['today']?(int)$u['downloaded_bytes']:0)+$download;
        if((int)$u['stored_bytes']+$storageDelta>1073741824||$up>524288000||$down>2147483648)throw new DomainException('Osiągnięto limit załączników: 1 GB miejsca, 500 MB wysyłania lub 2 GB pobierania na dobę.');
        $db->prepare('UPDATE messaging_media_usage SET stored_bytes=stored_bytes+?,day_key=CURRENT_DATE(),uploaded_bytes=?,downloaded_bytes=? WHERE subject_key=?')->execute([$storageDelta,$up,$down,$actor]);
        if($own)$db->commit();
    }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();msg_security_log($db,'media_quota_exceeded');throw $e;}
}
function msg_media_register(PDO $db,string $module,string $key,int $size):void{
    if(!msg_security_bind($db))return;
    $db->prepare('INSERT INTO messaging_media_ledger(module,storage_key,subject_key,byte_size) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE storage_key=VALUES(storage_key)')->execute([$module,$key,msg_actor_key(),$size]);
}
function msg_transfer_lease(PDO $db,string $kind,int $limit):string{
    // Serialize leases on the same quota row; leases expire even after an interrupted PHP worker.
    $own=!$db->inTransaction();if($own)$db->beginTransaction();
    try{
        $actor=msg_actor_key();$db->prepare('INSERT INTO messaging_media_usage(subject_key,day_key) VALUES(?,CURRENT_DATE()) ON DUPLICATE KEY UPDATE subject_key=VALUES(subject_key)')->execute([$actor]);
        $q=$db->prepare('SELECT subject_key FROM messaging_media_usage WHERE subject_key=? FOR UPDATE');$q->execute([$actor]);
        $q=$db->prepare('SELECT COUNT(*) FROM messaging_transfer_leases WHERE subject_key=? AND kind=? AND expires_at>NOW()');$q->execute([$actor,$kind]);
        if((int)$q->fetchColumn()>=$limit)throw new DomainException('Za dużo równoległych transferów. Odczekaj chwilę.');
        $id=bin2hex(random_bytes(16));$db->prepare('INSERT INTO messaging_transfer_leases VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 3 MINUTE))')->execute([$id,$actor,$kind]);if($own)$db->commit();
        register_shutdown_function(static function()use($db,$id){try{$db->prepare('DELETE FROM messaging_transfer_leases WHERE lease_id=?')->execute([$id]);}catch(Throwable $e){}});return $id;
    }catch(Throwable $e){if($own&&$db->inTransaction())$db->rollBack();throw $e;}
}
