<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'notifications',
    RATE_LIMIT_NOTIFICATIONS,
    'Zbyt wiele operacji z tego adresu IP. Odczekaj chwilę.'
);

if (!isset($_SESSION['authentik_username'])) {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$id = (int) request_string('id', '0');

if ($id <= 0) {
    json_error('Nieprawidłowe ID powiadomienia.', 400, [], ['reason' => 'invalid_id']);
}

require_once dirname(__DIR__,2).'/backend/lib/messaging_security.php';
msg_security_session(dnct_db());

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

if(request_string('action')==='bookmark'){
 $saved=request_string('saved');if(!in_array($saved,['0','1'],true))json_error('Nieprawidłowy stan zakładki.',422);
 try{bookmark_notification(dnct_db(),$id,$userId,$isAdmin,$saved==='1');}catch(DomainException $e){json_error($e->getMessage(),404);}json_ok(['is_saved'=>$saved==='1']);
}
mark_notification_read(dnct_db(), $id, $userId, $isAdmin);

json_ok();
