<?php
/**
 * backend/lib/notifications.php
 *
 * Trwałe powiadomienia (baza danych) - osobna warstwa od flash messages.
 * Użyj tego gdy powiadomienie ma:
 *   - dotrzeć do konkretnego usera / adminów / wszystkich,
 *   - poczekać aż user je zobaczy (nie znika po odświeżeniu),
 *   - być generowane automatycznie przez system (cron, eventy).
 *
 * PRZYKŁADY UŻYCIA:
 *
 *   // Wyślij do jednego usera
 *   notify_user(dnct_db(), 'jan_kowalski', 'warning',
 *       'Serwer wkrótce wygaśnie',
 *       'Twój serwer "SMP #1" wygaśnie za 3 dni.',
 *       ['event_key' => 'expiry_42_3d', 'link' => '/panel/server/42']
 *   );
 *
 *   // Wyślij do wszystkich adminów
 *   notify_admins(dnct_db(), 'error', 'Błąd płatności', 'Webhook Stripe zwrócił 500.');
 *
 *   // Wyślij do wszystkich userów
 *   notify_all(dnct_db(), 'info', 'Przerwa techniczna', 'Serwery będą offline 02:00-03:00.');
 *
 * event_key (opcjonalny) zabezpiecza przed wysłaniem tego samego powiadomienia
 * dwa razy (np. przy każdym uruchomieniu crona) - jeśli już istnieje, insert
 * jest po cichu pomijany.
 */

require_once __DIR__ . '/ntfy.php';
require_once __DIR__.'/notification_events.php';

require_once __DIR__ . '/db.php';
require_once __DIR__.'/notification_visibility.php';

/** Czy zalogowany user jest adminem (superuserem Authentika). */
function dnct_is_admin(): bool
{
    return (bool) ($_SESSION['authentik_is_superuser'] ?? false);
}

function notify_user(PDO $pdo, string $userId, string $type, string $title, string $message, array $opts = []): ?int
{
    return dnct_insert_notification($pdo, $type, $title, $message, 'user', $userId, $opts);
}

function notify_admins(PDO $pdo, string $type, string $title, string $message, array $opts = []): ?int
{
    // Generic automation errors are operational; priority 5 is reserved for an explicit critical source.
    $priority=(int)($opts['ntfy'][1]??(in_array($type,['error','warning'],true)?4:3));
    $type=$priority>=5?'error':($priority>=4?'warning':($type==='success'?'success':'info'));
    $opts['ntfy']??=[$priority>=4?'awarie':'sprzedaz',$priority,$priority>=5?'rotating_light':($priority>=4?'warning':'information_source')];

    $id = dnct_insert_notification($pdo, $type, $title, $message, 'admins', null, $opts);
    // Telefon admina (lib/ntfy.php). Duplikat event_key zwraca null - wtedy też bez drugiego dzwonka.
    if ($id !== null) {
        ntfy_from_admin_notification($type, $title, $message, $opts);
    }
    return $id;
}

function notify_all(PDO $pdo, string $type, string $title, string $message, array $opts = []): ?int
{
    return dnct_insert_notification($pdo, $type, $title, $message, 'all', null, $opts);
}

/**
 * Wspólna funkcja insertująca. Zwraca ID nowego powiadomienia,
 * albo null jeśli pominięto z powodu duplikatu event_key.
 */
function dnct_insert_notification(
    PDO $pdo,
    string $type,
    string $title,
    string $message,
    string $targetType,
    ?string $targetUserId,
    array $opts = []
): ?int {
    $sql = "INSERT INTO notifications
                (type, title, message, target_type, target_user_id, event_key, link, created_by, expires_at,category,action_label)
            VALUES
                (:type, :title, :message, :target_type, :target_user_id, :event_key, :link, :created_by, :expires_at,:category,:action_label)";

    $params = [
        ':type'           => $type,
        ':title'          => $title,
        ':message'        => $message,
        ':target_type'    => $targetType,
        ':target_user_id' => $targetUserId,
        ':event_key'      => $opts['event_key'] ?? null,
        ':link'           => $opts['link'] ?? null,
        ':created_by'     => $opts['created_by'] ?? ($_SESSION['authentik_username'] ?? 'system'),
        ':expires_at'     => $opts['expires_at'] ?? null,
    ];

    try {
        try {
            $pdo->prepare($sql)->execute($params + [
                ':category' => $opts['category'] ?? null, ':action_label' => $opts['action_label'] ?? null,
            ]);
        } catch (PDOException $e) {
            // 42S22 = brak kolumn category/action_label: baza produkcji nie ma schematu
            // systemu wiadomości (ten jest na becie) - zapis w starym układzie.
            if ($e->getCode() !== '42S22') {
                throw $e;
            }
            $pdo->prepare(
                'INSERT INTO notifications
                    (type, title, message, target_type, target_user_id, event_key, link, created_by, expires_at)
                 VALUES
                    (:type, :title, :message, :target_type, :target_user_id, :event_key, :link, :created_by, :expires_at)'
            )->execute($params);
        }
    } catch (PDOException $e) {
        // 23000 = naruszenie unikalności (duplikat event_key) -> po cichu pomiń
        if ($e->getCode() === '23000') {
            return null;
        }
        throw $e;
    }

    $id = (int) $pdo->lastInsertId();
    dnct_forward_notification_to_messaging($type, $title, $message, $targetType, $targetUserId, $opts);

    return $id;
}

/**
 * Produkcja: przekazuje powiadomienie do systemu wiadomości na becie, bo to
 * stamtąd czytają dzwonek klienta i admin/powiadomienia (patrz
 * api/internal-notify.php po stronie bety). Na becie (DNCT_ENV_FILE ustawione)
 * nie robi nic - tam zapis lokalny JEST bazą wiadomości.
 *
 * Podpis tym samym kluczem co api/messaging.php, `purpose` = "notify".
 * Błąd tu nigdy nie przerywa procesu: powiadomienie jest już w bazie lokalnej,
 * a cykl życia serwera nie może się wywrócić o niedostępny dzwonek.
 */
function dnct_forward_notification_to_messaging(
    string $type,
    string $title,
    string $message,
    string $targetType,
    ?string $targetUserId,
    array $opts
): void {
    if (($_SERVER['DNCT_ENV_FILE'] ?? getenv('DNCT_ENV_FILE')) || !function_exists('sodium_crypto_sign_detached')) {
        return;
    }
    $key = @file_get_contents('/home/jakub/dnct-private-media/site-messaging.key');
    if (!is_string($key) || strlen($key) !== 64) {
        return;
    }

    try {
        $payload = json_encode([
            'v' => 1, 'purpose' => 'notify', 'time' => time(), 'nonce' => bin2hex(random_bytes(24)),
            'type' => $type, 'target_type' => $targetType, 'target_user_id' => $targetUserId,
            'title' => mb_substr($title, 0, 120), 'message' => mb_substr($message, 0, 2000),
            'event_key' => $opts['event_key'] ?? null, 'link' => $opts['link'] ?? null,
            'category' => $opts['category'] ?? null, 'action_label' => $opts['action_label'] ?? null,
            'expires_at' => $opts['expires_at'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $ch = curl_init('http://127.0.0.1:8910/api/internal-notify.php');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'payload'   => base64_encode($payload),
                'signature' => base64_encode(sodium_crypto_sign_detached($payload, $key)),
            ],
            CURLOPT_HTTPHEADER     => ['Host: beta.dnct.pl'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status !== 200) {
            error_log('notifications: przekazanie do systemu wiadomości nieudane, HTTP ' . $status);
        }
    } catch (Throwable $e) {
        error_log('notifications: przekazanie do systemu wiadomości nieudane: ' . $e->getMessage());
    }
}

/**
 * Pobiera powiadomienia widoczne dla danego usera (własne + admins jeśli isAdmin + all),
 * wraz z informacją czy user je przeczytał.
 */
function get_notifications_for_user(PDO $pdo, string $userId, bool $isAdmin, int $limit = 20, string $scope = 'all', bool $savedOnly = false): array
{
    $scopeSql=notification_scope_sql($scope,$isAdmin);
    $savedFilter=$savedOnly?'r.saved_at IS NOT NULL':'r.saved_at IS NULL';
    $sql = "SELECT n.id, n.target_type, n.type, n.title, n.message, n.link, n.created_at,n.category,n.action_label,n.event_key,
                   (r.read_at IS NOT NULL) AS is_read, (r.saved_at IS NOT NULL) AS is_saved
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id
            WHERE (r.saved_at IS NOT NULL OR n.expires_at IS NULL OR n.expires_at > NOW()) {$scopeSql} AND {$savedFilter}
              AND (r.saved_at IS NOT NULL OR n.target_type = 'admins' OR r.read_at IS NULL OR r.read_at > DATE_SUB(NOW(), INTERVAL 7 DAY))
              AND r.hidden_at IS NULL
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ORDER BY is_read ASC, n.created_at DESC, n.id DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['is_saved']=(bool)$row['is_saved'];$row['is_read'] = (bool) $row['is_read'];$row=notification_presentation($row);unset($row['event_key']);
    }
    return $rows;
}

function count_unread_for_user(PDO $pdo, string $userId, bool $isAdmin, string $scope = 'all'): int
{
    $scopeSql=notification_scope_sql($scope,$isAdmin);
    $sql = "SELECT COUNT(*) AS cnt
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id
            WHERE r.read_at IS NULL
              AND r.hidden_at IS NULL
              AND (n.expires_at IS NULL OR n.expires_at > NOW()) {$scopeSql}
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetch()['cnt'];
}

/**
 * INSERT ... SELECT zamiast gołego INSERT - wstawia wiersz WYŁĄCZNIE gdy
 * $notificationId istnieje i jest faktycznie widoczny dla $userId (własne /
 * admins+isAdmin / all), tym samym warunkiem co get_notifications_for_user().
 * Bez tego dowolny zalogowany mógł oznaczyć jako przeczytane cudze,
 * nieadresowane do niego powiadomienie - zaśmiecenie stanu, nie wyciek
 * danych, ale i tak brak własności nad ID, które dostaje z requestu.
 */
function mark_notification_read(PDO $pdo, int $notificationId, string $userId, bool $isAdmin): void
{
    $visibility=notification_visible_sql(false);
    $sql = "INSERT INTO notification_reads (notification_id, user_id)
            SELECT n.id, :user_id
            FROM notifications n
            WHERE n.id = :notification_id {$visibility}
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ON DUPLICATE KEY UPDATE read_at = COALESCE(notification_reads.read_at, CURRENT_TIMESTAMP)"; // no-op jeśli już przeczytane

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    if (isset($visibility)) $stmt->bindValue(':visibility_user', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
}

function mark_all_read(PDO $pdo, string $userId, bool $isAdmin, string $scope = 'all'): int
{
    $scopeSql=notification_scope_sql($scope,$isAdmin);
    $sql = "INSERT INTO notification_reads (notification_id, user_id)
            SELECT n.id, :user_id
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id2
            WHERE r.read_at IS NULL
              AND r.hidden_at IS NULL
              AND (n.expires_at IS NULL OR n.expires_at > NOW()) {$scopeSql}
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id3)
                  )
            ON DUPLICATE KEY UPDATE read_at = COALESCE(notification_reads.read_at, CURRENT_TIMESTAMP)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id3', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

/**
 * Ukrywa powiadomienie dla jednego użytkownika (przycisk ✕ w centrum pomocy).
 * Wiersz w notifications zostaje - inni adresaci dalej je widzą.
 */
function hide_notification(PDO $pdo, int $notificationId, string $userId, bool $isAdmin): void
{
    $sql = "INSERT INTO notification_reads (notification_id, user_id, hidden_at)
            SELECT n.id, :user_id, NOW()
            FROM notifications n
            WHERE n.id = :notification_id
              AND NOT EXISTS(SELECT 1 FROM notification_reads sr WHERE sr.notification_id=n.id AND sr.user_id=:saved_user AND sr.saved_at IS NOT NULL)
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ON DUPLICATE KEY UPDATE hidden_at = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    if (isset($visibility)) $stmt->bindValue(':visibility_user', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':saved_user', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
}

function notification_scope_sql(string $scope,bool $isAdmin):string {
 if(!in_array($scope,['all','normal','admin'],true))throw new InvalidArgumentException('Nieprawidłowa zakładka powiadomień.');
 if($scope==='admin'&&!$isAdmin)throw new DomainException('Brak uprawnień administratora.');
 return notification_visible_sql() . match($scope){'normal'=>" AND n.target_type <> 'admins'",'admin'=>" AND n.target_type = 'admins'",default=>''};
}

/** Hide only previously read messages visible to this account in the selected tab. */
function hide_read_notifications(PDO $pdo,string $userId,bool $isAdmin,string $scope):int {
 $scopeSql=notification_scope_sql($scope,$isAdmin);
 $q=$pdo->prepare("UPDATE notification_reads r JOIN notifications n ON n.id=r.notification_id SET r.hidden_at=NOW() WHERE r.user_id=:user AND r.saved_at IS NULL AND r.read_at IS NOT NULL AND r.hidden_at IS NULL AND (n.target_type='all' OR (n.target_type='user' AND n.target_user_id=:recipient) OR (n.target_type='admins' AND :admin=1)) {$scopeSql}");
 $q->execute([':user'=>$userId,':recipient'=>$userId,':admin'=>$isAdmin?1:0]);return $q->rowCount();
}

/** Explicit desired state makes retries idempotent. Ownership/scope are checked in SQL. */
function bookmark_notification(PDO $pdo,int $id,string $user,bool $admin,bool $saved):void {
 $scope=notification_scope_sql('all',$admin);
 $pdo->beginTransaction();try{
  $q=$pdo->prepare("SELECT n.id FROM notifications n LEFT JOIN notification_reads r ON r.notification_id=n.id AND r.user_id=:reader WHERE n.id=:id AND r.hidden_at IS NULL AND (r.saved_at IS NOT NULL OR n.expires_at IS NULL OR n.expires_at>NOW()) AND (r.saved_at IS NOT NULL OR r.read_at IS NULL OR r.read_at>DATE_SUB(NOW(),INTERVAL 7 DAY)) AND (n.target_type='all' OR (n.target_type='user' AND n.target_user_id=:recipient) OR (n.target_type='admins' AND :admin=1)) {$scope} FOR UPDATE");
  $q->execute([':reader'=>$user,':id'=>$id,':recipient'=>$user,':admin'=>$admin?1:0]);if(!$q->fetchColumn())throw new DomainException('Powiadomienie nie jest dostępne.');
  $q=$pdo->prepare("INSERT INTO notification_reads(notification_id,user_id,read_at,saved_at) VALUES(?,?,NOW(),".($saved?'NOW()':'NULL').") ON DUPLICATE KEY UPDATE saved_at=VALUES(saved_at),read_at=NOW()");$q->execute([$id,$user]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
