<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();

// Powiadomienia systemowe idą tym samym, już dopuszczonym w mostach endpointem
// (strona, game.dnct.pl i panel mają listę dozwolonych ścieżek - nowy plik
// wymagałby zmian w trzech miejscach). Osobny limit per konto: "jedna karta"
// zgłasza się przy każdej nowej wiadomości i nie może zjadać limitu odczytów.
$action = request_string('action');
$desktop = in_array($action, ['desktop', 'desktop-snooze', 'desktop-claim'], true);

if (!$desktop) {
    rate_limit_guard(
        'notifications',
        RATE_LIMIT_NOTIFICATIONS,
        'Zbyt wiele operacji z tego adresu IP. Odczekaj chwilę.'
    );
}

if (!isset($_SESSION['authentik_username'])) {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

require_once dirname(__DIR__,2).'/backend/lib/messaging_security.php';

$userId  = (string) $_SESSION['authentik_username'];
$isAdmin = dnct_is_admin();

if ($desktop) {
    rate_limit_guard('notification-desktop-' . substr(hash('sha256', $userId), 0, 24), [240, 60], 'Zbyt wiele operacji. Odczekaj chwilę.');
    msg_security_session(dnct_db());
    require_once dirname(__DIR__, 2) . '/backend/lib/notification_desktop.php';
    $db = dnct_db();
    if ($action === 'desktop') {
        $enabled = request_string('enabled');
        if (!in_array($enabled, ['0', '1'], true)) json_error('Nieprawidłowy stan.', 422);
        notification_desktop_set($db, $userId, $enabled === '1');
    } elseif ($action === 'desktop-snooze') {
        notification_desktop_snooze($db, $userId);
    } else {
        $key = request_string('key');
        if (!preg_match('/^[a-z]{1,12}:[0-9:]{1,60}$/D', $key)) json_error('Nieprawidłowy klucz.', 422);
        json_ok(['claimed' => notification_desktop_claim($db, $userId, $key)]);
    }
    json_ok(['desktop' => notification_desktop_state($db, $userId)]);
}

$id = (int) request_string('id', '0');

if ($id <= 0) {
    json_error('Nieprawidłowe ID powiadomienia.', 400, [], ['reason' => 'invalid_id']);
}

msg_security_session(dnct_db());

if($action==='bookmark'){
 $saved=request_string('saved');if(!in_array($saved,['0','1'],true))json_error('Nieprawidłowy stan zakładki.',422);
 try{bookmark_notification(dnct_db(),$id,$userId,$isAdmin,$saved==='1');}catch(DomainException $e){json_error($e->getMessage(),404);}json_ok(['is_saved'=>$saved==='1']);
}
mark_notification_read(dnct_db(), $id, $userId, $isAdmin);

json_ok();
