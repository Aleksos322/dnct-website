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

require_once __DIR__ . '/db.php';

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
    return dnct_insert_notification($pdo, $type, $title, $message, 'admins', null, $opts);
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
                (type, title, message, target_type, target_user_id, event_key, link, created_by, expires_at)
            VALUES
                (:type, :title, :message, :target_type, :target_user_id, :event_key, :link, :created_by, :expires_at)";

    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':type'           => $type,
            ':title'          => $title,
            ':message'        => $message,
            ':target_type'    => $targetType,
            ':target_user_id' => $targetUserId,
            ':event_key'      => $opts['event_key'] ?? null,
            ':link'           => $opts['link'] ?? null,
            ':created_by'     => $opts['created_by'] ?? ($_SESSION['authentik_username'] ?? 'system'),
            ':expires_at'     => $opts['expires_at'] ?? null,
        ]);
    } catch (PDOException $e) {
        // 23000 = naruszenie unikalności (duplikat event_key) -> po cichu pomiń
        if ($e->getCode() === '23000') {
            return null;
        }
        throw $e;
    }

    return (int) $pdo->lastInsertId();
}

/**
 * Pobiera powiadomienia widoczne dla danego usera (własne + admins jeśli isAdmin + all),
 * wraz z informacją czy user je przeczytał.
 */
function get_notifications_for_user(PDO $pdo, string $userId, bool $isAdmin, int $limit = 20): array
{
    $sql = "SELECT n.id, n.type, n.title, n.message, n.link, n.created_at,
                   (r.read_at IS NOT NULL) AS is_read
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id
            WHERE (n.expires_at IS NULL OR n.expires_at > NOW())
              AND r.hidden_at IS NULL
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ORDER BY n.created_at DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['is_read'] = (bool) $row['is_read'];
    }
    return $rows;
}

function count_unread_for_user(PDO $pdo, string $userId, bool $isAdmin): int
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id
            WHERE r.read_at IS NULL
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
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
    $sql = "INSERT INTO notification_reads (notification_id, user_id)
            SELECT n.id, :user_id
            FROM notifications n
            WHERE n.id = :notification_id
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ON DUPLICATE KEY UPDATE read_at = notification_reads.read_at"; // no-op jeśli już przeczytane

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
}

function mark_all_read(PDO $pdo, string $userId, bool $isAdmin): int
{
    $sql = "INSERT INTO notification_reads (notification_id, user_id)
            SELECT n.id, :user_id
            FROM notifications n
            LEFT JOIN notification_reads r
                   ON r.notification_id = n.id AND r.user_id = :user_id2
            WHERE r.read_at IS NULL
              AND (n.expires_at IS NULL OR n.expires_at > NOW())
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id3)
                  )
            ON DUPLICATE KEY UPDATE read_at = notification_reads.read_at";

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
              AND (
                    n.target_type = 'all'
                    OR (n.target_type = 'admins' AND :is_admin = 1)
                    OR (n.target_type = 'user' AND n.target_user_id = :user_id2)
                  )
            ON DUPLICATE KEY UPDATE hidden_at = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':user_id2', $userId, PDO::PARAM_STR);
    $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
}
