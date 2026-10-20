<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('GET');
if (!isset($_SESSION['authentik_username'])) {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

require_once dirname(__DIR__,2).'/backend/lib/messaging_security.php';
msg_security_session(dnct_db());

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

// Jedna odpowiedź dla listy i licznika; osobny limit od operacji zapisu.
rate_limit_guard(
    'notification-list-beta-' . substr(hash('sha256', $userId), 0, 24),
    [120, 60],
    'Zbyt wiele odświeżeń. Odczekaj chwilę.'
);
try {
    $pdo = dnct_db();
    $notifications=get_notifications_for_user($pdo,$userId,$isAdmin,50,'normal');$normal=count_unread_for_user($pdo,$userId,$isAdmin,'normal');$adminUnread=$isAdmin?count_unread_for_user($pdo,$userId,true,'admin'):0;
    $savedNotifications=get_notifications_for_user($pdo,$userId,$isAdmin,5000,'all',true);
    $notifications=array_merge($notifications,$savedNotifications);
    if($notifications){
      $ids=array_column($notifications,'id');$q=$pdo->prepare('SELECT l.notification_id,a.id,a.original_name,a.mime_type,a.byte_size FROM notification_attachment_links l JOIN notification_attachments a ON a.id=l.attachment_id WHERE l.notification_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$files=[];
      foreach($q->fetchAll(PDO::FETCH_ASSOC) as $file){$nid=$file['notification_id'];unset($file['notification_id']);$files[$nid][]=$file;}
      $q=$pdo->prepare('SELECT notification_id,username FROM notification_authors WHERE notification_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$authors=$q->fetchAll(PDO::FETCH_KEY_PAIR);
      foreach($notifications as &$notification){$notification['attachments']=$files[$notification['id']]??[];$notification['author']=$authors[$notification['id']]??null;}unset($notification);
    }
    json_ok(['notifications'=>array_values(array_filter($notifications,fn($n)=>$n['target_type']!=='admins')),'admin_notifications'=>$isAdmin?array_merge(get_notifications_for_user($pdo,$userId,true,50,'admin'),array_values(array_filter($savedNotifications,fn($n)=>$n['target_type']==='admins'))):[],'is_admin'=>$isAdmin,'counts'=>['normal'=>$normal,'admin'=>$adminUnread],'unread'=>$normal+$adminUnread]);
} catch (Throwable $e) {
    error_log('notifications/list: ' . $e->getMessage());
    json_error('Powiadomienia są chwilowo niedostępne. Spróbuj ponownie.', 503);
}
