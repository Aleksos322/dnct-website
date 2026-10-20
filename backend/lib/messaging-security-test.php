<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env');
require __DIR__.'/../bootstrap.php';
require __DIR__.'/messaging_security.php';
require __DIR__.'/notification_media.php';
require_once __DIR__.'/notifications.php';
$db=dnct_db();
foreach(['messaging_limits','messaging_media_usage','messaging_security_audit','notifications','notification_reads','notification_attachments','notification_attachment_links'] as $t){
 $ddl=$db->query("SHOW CREATE TABLE $t")->fetch(PDO::FETCH_NUM)[1];
 $db->exec(preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl));
}
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$actor=hash('sha256','test-user');
for($i=0;$i<5;$i++)check(msg_rate_hit($db,$actor,'message')===0,'burst accepted '.($i+1));
check(msg_rate_hit($db,$actor,'message')===3,'sixth message rejected, cooldown 3 s');
check(msg_rate_hit($db,$actor,'message')>0,'cooldown persists across requests');
check(count(json_decode($db->query('SELECT hits FROM messaging_limits')->fetchColumn(),true))===5,'excess not queued');
$key=hash('sha256',"message\0".$actor);
$hits=array_fill(0,50,(int)floor(microtime(true)*1000)-2000);
$db->prepare('UPDATE messaging_limits SET hits=?,blocked_until=0 WHERE bucket=?')->execute([json_encode($hits),$key]);
check(msg_rate_hit($db,$actor,'message')>=57,'minute cap shared with burst counter');
$db->prepare('UPDATE messaging_limits SET hits=?,blocked_until=0 WHERE bucket=?')->execute([json_encode(array_fill(0,50,(int)floor(microtime(true)*1000)-61000)),$key]);
check(msg_rate_hit($db,$actor,'message')===0,'window expires');
$id=dnct_insert_notification($db,'info','Test','Test','user','recipient');$aid=str_repeat('a',32);
$db->prepare('INSERT INTO notification_attachments VALUES(?,?,?,?,?)')->execute([$aid,str_repeat('b',64),'test.png','image/png',12]);
$db->prepare('INSERT INTO notification_attachment_links VALUES(?,?)')->execute([$id,$aid]);
check((bool)notification_media_for_user($db,$aid,'recipient'),'recipient can access');
check(!notification_media_for_user($db,$aid,'stranger'),'other account denied');
$db->prepare('INSERT INTO notification_reads(notification_id,user_id,read_at,hidden_at) VALUES(?,?,NOW(),NOW())')->execute([$id,'recipient']);
check(!notification_media_for_user($db,$aid,'recipient'),'hidden notification denied');
$db->prepare('UPDATE notification_reads SET hidden_at=NULL,read_at=DATE_SUB(NOW(),INTERVAL 7 DAY) WHERE notification_id=?')->execute([$id]);
check(!notification_media_for_user($db,$aid,'recipient'),'seven-day retention enforced');
$id2=dnct_insert_notification($db,'info','Test2','Test2','user','recipient');
$db->prepare('INSERT INTO notification_attachment_links VALUES(?,?)')->execute([$id2,$aid]);
check((bool)notification_media_for_user($db,$aid,'recipient'),'another active notification preserves access');
$_SESSION['authentik_username']='quota-test';
msg_quota_charge($db,100,200);
check((int)$db->query('SELECT stored_bytes FROM messaging_media_usage')->fetchColumn()===100,'quota accounted');
try{msg_quota_charge($db,524288000,0);throw new RuntimeException('quota accepted');}catch(DomainException $e){}
check((int)$db->query('SELECT stored_bytes FROM messaging_media_usage')->fetchColumn()===100,'rejected quota rolled back');
echo "Temporary tables only; no customer messages or notifications created.\n";

