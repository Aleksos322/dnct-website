<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
putenv('DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env');
require __DIR__.'/../bootstrap.php';
$db=dnct_db();
$queries=[
 'chat'=>"SELECT a.storage_key,a.byte_size,u.username FROM chat_message_attachments a JOIN chat_messages m ON m.id=a.message_id LEFT JOIN chat_members u ON u.conversation_id=m.conversation_id AND u.user_id=m.author_id",
 'support'=>"SELECT a.storage_key,a.byte_size,CASE WHEN m.author_role='admin' THEN m.author_name ELSE t.customer_username END AS username FROM support_message_attachments a JOIN support_messages m ON m.id=a.message_id JOIN support_tickets t ON t.id=m.ticket_id",
 'notification'=>"SELECT a.storage_key,a.byte_size,MIN(au.username) AS username FROM notification_attachments a LEFT JOIN notification_attachment_links l ON l.attachment_id=a.id LEFT JOIN notification_authors au ON au.notification_id=l.notification_id GROUP BY a.storage_key,a.byte_size"
];
$apply=in_array('--apply',$argv,true);$count=0;$unknown=0;
$db->beginTransaction();
try{
 foreach($queries as $module=>$sql)foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row){
  $q=$db->prepare('SELECT 1 FROM messaging_media_ledger WHERE module=? AND storage_key=?');$q->execute([$module,$row['storage_key']]);if($q->fetchColumn())continue;
  $count++;if(!$row['username'])$unknown++;
  if(!$apply)continue;
  $actor=hash('sha256',$row['username']?'user:'.$row['username']:'legacy:'.$module.':'.$row['storage_key']);
  $db->prepare('INSERT INTO messaging_media_usage(subject_key,day_key) VALUES(?,CURRENT_DATE()) ON DUPLICATE KEY UPDATE subject_key=VALUES(subject_key)')->execute([$actor]);
  $db->prepare('SELECT subject_key FROM messaging_media_usage WHERE subject_key=? FOR UPDATE')->execute([$actor]);
  $q=$db->prepare('INSERT IGNORE INTO messaging_media_ledger VALUES(?,?,?,?)');$q->execute([$module,$row['storage_key'],$actor,$row['byte_size']]);
  if($q->rowCount())$db->prepare('UPDATE messaging_media_usage SET stored_bytes=stored_bytes+? WHERE subject_key=?')->execute([$row['byte_size'],$actor]);
 }
 if($apply)$db->commit();else $db->rollBack();
 echo json_encode(['apply'=>$apply,'files'=>$count,'without_account'=>$unknown]),PHP_EOL;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,"Backfill failed; transaction rolled back.\n");exit(1);}

