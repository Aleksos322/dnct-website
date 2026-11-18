<?php
declare(strict_types=1);
/**
 * Powiadomienia systemowe (na komputerze) - preferencja konta i "jedna karta".
 *
 * Zgoda przeglądarki jest per domena (game.dnct.pl, panel.dnct.pl, beta), więc
 * włączenie/wyłączenie trzymamy na koncie, a nie w localStorage. Tabele:
 * backend/migrations/055_notification_desktop.sql (baza systemu wiadomości).
 */

const NOTIFICATION_DESKTOP_SNOOZE_HOURS = 24;   // "Nie teraz" w prośbie o zgodę

/** ['enabled' => null|bool, 'snoozed_for' => sekundy do końca odłożenia]. */
function notification_desktop_state(PDO $db, string $user, string $device = ''): array
{
    $q = $db->prepare('SELECT enabled, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), snoozed_until)) AS snoozed_for
                       FROM notification_desktop_prefs WHERE username = ?');
    $q->execute([$user]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return [
        'enabled'     => $row && $row['enabled'] !== null ? (bool)(int)$row['enabled'] : null,
        'snoozed_for' => $row ? (int)$row['snoozed_for'] : 0,
        // Web Push: klucz serwera do subskrypcji i to, czy ta przeglądarka już ją ma.
        'push_key'    => notification_push_vapid()['publicKey'] ?? null,
        'push_device' => $device !== '' && notification_push_device($db, $user, $device),
    ];
}

function notification_desktop_set(PDO $db, string $user, bool $enabled): void
{
    $db->prepare('INSERT INTO notification_desktop_prefs (username, enabled) VALUES (?, ?)
                  ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)')
       ->execute([$user, $enabled ? 1 : 0]);
}

function notification_desktop_snooze(PDO $db, string $user): void
{
    $db->prepare('INSERT INTO notification_desktop_prefs (username, snoozed_until)
                  VALUES (?, DATE_ADD(NOW(), INTERVAL ' . NOTIFICATION_DESKTOP_SNOOZE_HOURS . ' HOUR))
                  ON DUPLICATE KEY UPDATE snoozed_until = VALUES(snoozed_until)')
       ->execute([$user]);
}

/**
 * Pierwsza karta (dowolnej domeny), która zgłosi dany klucz, pokazuje powiadomienie.
 * Klucz jest haszowany - do bazy nie trafia nic z treści.
 */
function notification_desktop_claim(PDO $db, string $user, string $key): bool
{
    if (random_int(1, 200) === 1) {
        $db->exec('DELETE FROM notification_desktop_claims WHERE claimed_at < DATE_SUB(NOW(), INTERVAL 3 DAY)');
    }
    $q = $db->prepare('INSERT IGNORE INTO notification_desktop_claims (username, item_key) VALUES (?, ?)');
    $q->execute([$user, hash('sha256', $key)]);
    return $q->rowCount() === 1;
}

/* --- Web Push (wariant B) ------------------------------------------------------
 * Jedna subskrypcja na przeglądarkę: device_id to ciasteczko dnct_dev na .dnct.pl,
 * wspólne dla game.dnct.pl, panelu i bety. Wysyłka: backend/tools/push-worker.php.
 * Tabele: backend/migrations/056_push_subscriptions.sql. */

function notification_push_vapid(): ?array
{
    static $vapid = false;
    if ($vapid === false) {
        $raw = @file_get_contents((string)env('WEBPUSH_VAPID_FILE', '/var/www/dnct.pl/config/webpush-vapid.json'));
        $vapid = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($vapid) || empty($vapid['publicKey'])) $vapid = null;
    }
    return $vapid;
}

function notification_push_valid_device(string $device): bool
{
    return preg_match('/^[a-f0-9]{32}$/D', $device) === 1;
}

/** Czy ta przeglądarka ma działającą subskrypcję tego konta. */
function notification_push_device(PDO $db, string $user, string $device): bool
{
    if (!notification_push_valid_device($device)) return false;
    $q = $db->prepare('SELECT 1 FROM push_subscriptions WHERE username = ? AND device_id = ?');
    $q->execute([$user, $device]);
    return (bool)$q->fetchColumn();
}

/** Zapis subskrypcji z przeglądarki. Tylko znane usługi push i poprawne klucze. */
function notification_push_save(PDO $db, string $user, string $device, string $json, string $origin, bool $isAdmin): void
{
    if (!notification_push_valid_device($device)) throw new InvalidArgumentException('Nieprawidłowe urządzenie.');
    if (!in_array($origin, ['https://game.dnct.pl', 'https://dnct.pl', 'https://panel.dnct.pl', 'https://beta.dnct.pl'], true)) {
        throw new InvalidArgumentException('Nieprawidłowa strona.');
    }
    $sub = json_decode($json, true);
    $endpoint = (string)($sub['endpoint'] ?? '');
    $url = parse_url($endpoint) ?: [];
    $host = strtolower((string)($url['host'] ?? ''));
    $known = $host === 'fcm.googleapis.com' || $host === 'web.push.apple.com'
        || str_ends_with($host, '.push.services.mozilla.com') || $host === 'updates.push.services.mozilla.com'
        || str_ends_with($host, '.notify.windows.com');
    if (!$known || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || strlen($endpoint) > 2048) {
        throw new InvalidArgumentException('Nieobsługiwana usługa powiadomień.');
    }
    foreach (['p256dh' => 65, 'auth' => 16] as $k => $size) {
        $v = (string)($sub['keys'][$k] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $v) || strlen((string)base64_decode(strtr($v, '-_', '+/'), true)) !== $size) {
            throw new InvalidArgumentException('Nieprawidłowy klucz subskrypcji.');
        }
    }
    $clean = json_encode(['endpoint' => $endpoint, 'keys' => ['p256dh' => $sub['keys']['p256dh'], 'auth' => $sub['keys']['auth']]], JSON_UNESCAPED_SLASHES);
    $site = ($_SESSION['messaging_site'] ?? '') === 'game' ? 'prod' : 'beta';
    $hash = hash('sha256', $endpoint);
    $db->beginTransaction();
    try {
        // Ta sama przeglądarka (inna domena) albo ten sam endpoint u innego konta: zostaje jeden wpis.
        $db->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ? OR (username = ? AND device_id = ?)')->execute([$hash, $user, $device]);
        $db->prepare('INSERT INTO push_subscriptions (username, device_id, endpoint_hash, subscription, site, origin, is_admin) VALUES (?,?,?,?,?,?,?)')
           ->execute([$user, $device, $hash, $clean, $site, $origin, $isAdmin ? 1 : 0]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function notification_push_remove(PDO $db, string $user, string $device): void
{
    if (!notification_push_valid_device($device)) return;
    $db->prepare('DELETE FROM push_subscriptions WHERE username = ? AND device_id = ?')->execute([$user, $device]);
}
