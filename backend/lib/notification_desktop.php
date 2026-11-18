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
function notification_desktop_state(PDO $db, string $user): array
{
    $q = $db->prepare('SELECT enabled, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), snoozed_until)) AS snoozed_for
                       FROM notification_desktop_prefs WHERE username = ?');
    $q->execute([$user]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    return [
        'enabled'     => $row && $row['enabled'] !== null ? (bool)(int)$row['enabled'] : null,
        'snoozed_for' => $row ? (int)$row['snoozed_for'] : 0,
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
