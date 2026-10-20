<?php
declare(strict_types=1);
/** CLI only. Dry run by default; deletion requires an explicit --apply. */
if(PHP_SAPI!=='cli')exit;
putenv('DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env');
require __DIR__.'/../bootstrap.php';
require __DIR__.'/messaging_security.php';
$db=dnct_db();$apply=in_array('--apply',$argv,true);$eligible=0;$removed=0;
if($apply){
 // Recipient rows are independent: one user's read status never expires another user's copy.
 $db->exec("UPDATE notifications n LEFT JOIN notification_reads r ON r.notification_id=n.id AND r.user_id=n.target_user_id SET n.expires_at=NOW() WHERE n.target_type='user' AND r.saved_at IS NULL AND r.read_at IS NULL AND n.created_at<=DATE_SUB(NOW(),INTERVAL 1 MONTH) AND (n.expires_at IS NULL OR n.expires_at>NOW())");
}
$live="SELECT 1 FROM notification_attachments a JOIN notification_attachment_links l ON l.attachment_id=a.id JOIN notifications n ON n.id=l.notification_id LEFT JOIN notification_reads r ON r.notification_id=n.id AND r.user_id=n.target_user_id WHERE a.storage_key=? AND (
 EXISTS (SELECT 1 FROM notification_campaign_items ci JOIN notification_campaigns c ON c.id=ci.campaign_id WHERE ci.notification_id=n.id AND c.deleted_at IS NULL)
 OR EXISTS(SELECT 1 FROM notification_reads saved WHERE saved.notification_id=n.id AND saved.saved_at IS NOT NULL AND saved.hidden_at IS NULL)
 OR ((n.expires_at IS NULL OR n.expires_at>NOW()) AND (n.target_type<>'user' OR (r.hidden_at IS NULL AND (r.read_at IS NULL OR r.read_at>DATE_SUB(NOW(),INTERVAL 7 DAY)))))) LIMIT 1";
foreach($db->query("SELECT storage_key,subject_key FROM messaging_media_ledger WHERE module='notification'")->fetchAll(PDO::FETCH_ASSOC) as $row){
 if(!preg_match('/^[a-f0-9]{64}$/D',$row['storage_key']))continue;
 $db->beginTransaction();
 try{
  // Notification upload locks this same owner's quota before writing files/links.
  $q=$db->prepare('SELECT subject_key FROM messaging_media_usage WHERE subject_key=? FOR UPDATE');$q->execute([$row['subject_key']]);if(!$q->fetchColumn()){$db->rollBack();continue;}
  $q=$db->prepare("SELECT byte_size FROM messaging_media_ledger WHERE module='notification' AND storage_key=? FOR UPDATE");$q->execute([$row['storage_key']]);$size=$q->fetchColumn();if($size===false){$db->rollBack();continue;}
  $q=$db->prepare($live);$q->execute([$row['storage_key']]);if($q->fetchColumn()){$db->rollBack();continue;}
  $eligible++;if(!$apply){$db->rollBack();continue;}
  $path=msg_media_root('notification').'/'.$row['storage_key'];
  if(is_link($path))throw new RuntimeException('Unexpected symlink');
  if(is_file($path)&&!unlink($path))throw new RuntimeException('Cannot remove unused file');
  $db->prepare('DELETE l FROM notification_attachment_links l JOIN notification_attachments a ON a.id=l.attachment_id WHERE a.storage_key=?')->execute([$row['storage_key']]);
  $db->prepare('DELETE FROM notification_attachments WHERE storage_key=?')->execute([$row['storage_key']]);
  $db->prepare("DELETE FROM messaging_media_ledger WHERE module='notification' AND storage_key=?")->execute([$row['storage_key']]);
  $db->prepare('UPDATE messaging_media_usage SET stored_bytes=GREATEST(0,stored_bytes-?) WHERE subject_key=?')->execute([(int)$size,$row['subject_key']]);
  $db->commit();$removed++;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,"Cleanup failed; metadata transaction rolled back.\n");exit(1);}
}
if($apply){
 $db->exec('DELETE FROM messaging_ws_grants WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)');
 $db->exec('DELETE FROM messaging_transfer_leases WHERE expires_at<NOW()');
 $db->exec('DELETE FROM messaging_limits WHERE updated_at<DATE_SUB(NOW(),INTERVAL 2 DAY)');
}
echo json_encode(['apply'=>$apply,'eligible'=>$eligible,'removed'=>$removed]),PHP_EOL;

