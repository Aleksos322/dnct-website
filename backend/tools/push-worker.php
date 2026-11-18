<?php
declare(strict_types=1);
/**
 * backend/tools/push-worker.php - wysyłka Web Push (powiadomienia systemowe przy zamkniętej karcie).
 *
 * Cron co minutę (crontab deployera, z flock), w środku pętla co 5 s przez ~55 s, więc
 * opóźnienie to najwyżej kilka sekund. Źródła:
 *   - notifications (dzwonek) - także kampanie z admina i wpisy przekazane z produkcji,
 *     zaplanowane wchodzą dopiero, gdy ich created_at minie;
 *   - chat_messages - do członków rozmowy poza autorem (bez wyciszonych i zablokowanych);
 *   - support_messages od admina - do właściciela ticketu (beta: ta baza, game: baza produkcji).
 * Subskrypcje: push_subscriptions (jedna na przeglądarkę), preferencja: notification_desktop_prefs.
 *
 * Uruchomienie ręczne (zawsze z bazą systemu wiadomości, czyli betą):
 *   DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env php backend/tools/push-worker.php --once --dry
 *   --once  jedno przejście zamiast pętli,  --dry  pokaż, co by poszło, nic nie wysyłaj i nie zapisuj.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
require $root . '/lib/env.php';
require $root . '/lib/db.php';
require $root . '/lib/notification_desktop.php';
require $root . '/webpush/vendor/autoload.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

$once = in_array('--once', $argv, true);
$dry  = in_array('--dry', $argv, true);

if (!($_SERVER['DNCT_ENV_FILE'] ?? getenv('DNCT_ENV_FILE'))) {
    fwrite(STDERR, "Ustaw DNCT_ENV_FILE na beta.env - powiadomienia i czat żyją w bazie bety.\n");
    exit(2);
}
$vapid = notification_push_vapid();
if (!$vapid || empty($vapid['privateKey'])) { fwrite(STDERR, "Brak kluczy VAPID.\n"); exit(2); }

$db = dnct_db();
$prod = push_prod_db();   // tickety game.dnct.pl; null = pomijamy je, reszta działa
$webPush = new WebPush(['VAPID' => [
    'subject' => $vapid['subject'] ?? 'https://game.dnct.pl',
    'publicKey' => $vapid['publicKey'], 'privateKey' => $vapid['privateKey'],
]], ['TTL' => 3600, 'urgency' => 'normal'], 10);
$webPush->setReuseVAPIDHeaders(true);

$end = time() + 55;
do {
    try {
        push_tick($db, $prod, $webPush, $dry);
    } catch (Throwable $e) {
        fwrite(STDERR, date('c') . ' push-worker: ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    }
    if ($once) break;
    sleep(5);
} while (time() < $end);

/** Baza produkcji (tickety game.dnct.pl) z domyślnego .env, bez ruszania env() bety. */
function push_prod_db(): ?PDO
{
    $file = (string)env('PROD_ENV_FILE', '/var/www/dnct.pl/public_html/.env');
    $v = [];
    foreach (@file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^\s*(MY_DB_[A-Z]+)\s*=\s*(.*)$/', $line, $m)) $v[$m[1]] = trim($m[2], " \t\"'");
    }
    if (empty($v['MY_DB_DATABASE'])) return null;
    try {
        return new PDO('mysql:host=' . ($v['MY_DB_HOST'] ?? '127.0.0.1') . ';port=' . ($v['MY_DB_PORT'] ?? '3306')
            . ';dbname=' . $v['MY_DB_DATABASE'] . ';charset=utf8mb4', $v['MY_DB_USERNAME'] ?? '', $v['MY_DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (Throwable $e) {
        fwrite(STDERR, "push-worker: baza produkcji niedostępna - tickety game.dnct.pl pominięte.\n");
        return null;
    }
}

function push_cursor(PDO $db, string $name, string $maxSql, ?PDO $source = null): int
{
    $q = $db->prepare('SELECT last_id FROM push_cursors WHERE name = ?');
    $q->execute([$name]);
    $v = $q->fetchColumn();
    if ($v !== false) return (int)$v;
    // Pierwsze uruchomienie: od teraz, bez zaległości.
    $max = (int)($source ?? $db)->query($maxSql)->fetchColumn();
    $db->prepare('INSERT IGNORE INTO push_cursors (name, last_id) VALUES (?, ?)')->execute([$name, $max]);
    return $max;
}

function push_set_cursor(PDO $db, string $name, int $id): void
{
    $db->prepare('UPDATE push_cursors SET last_id = GREATEST(last_id, ?) WHERE name = ?')->execute([$id, $name]);
}

/** Tekst do podglądu: bez znaczników Markdown, zaszyfrowane bez treści. */
function push_preview(?string $body): string
{
    $body = (string)$body;
    if (str_starts_with($body, 'e2e1:')) return 'Wiadomość zaszyfrowana';
    $body = preg_replace(['/\|\|.*?\|\|/s', '/[*_~`]+/', '/^>\s?/m', '/\s+/u'], ['[ukryte]', '', '', ' '], $body);
    $body = trim((string)$body);
    return $body === '' ? 'Załącznik' : mb_substr($body, 0, 200);
}

function push_base(array $sub): string
{
    return $sub['site'] === 'beta' ? 'https://beta.dnct.pl' : 'https://game.dnct.pl';
}

function push_tick(PDO $db, ?PDO $prod, WebPush $webPush, bool $dry): void
{
    // Subskrypcje kont, które nie wyłączyły powiadomień na komputerze.
    $byUser = [];
    foreach ($db->query('SELECT s.*, p.enabled FROM push_subscriptions s LEFT JOIN notification_desktop_prefs p ON p.username = s.username') as $s) {
        if ($s['enabled'] !== null && (int)$s['enabled'] === 0) continue;
        $byUser[$s['username']][] = $s;
    }
    $out = [];   // [subskrypcja, ładunek]
    $add = static function (array $sub, string $title, string $body, string $tag, string $url) use (&$out): void {
        $out[] = [$sub, ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 240), 'tag' => $tag, 'url' => $url,
            'icon' => push_base($sub) . '/assets/media/icons/favicon.png']];
    };

    // 1) Dzwonek. Klucz w push_sent zamiast kursora: kampanie zaplanowane mają created_at w przyszłości.
    $rows = $db->query("SELECT n.id, n.title, n.message, n.link, n.target_type, n.target_user_id,
            (SELECT c.environment FROM notification_campaign_items i JOIN notification_campaigns c ON c.id = i.campaign_id WHERE i.notification_id = n.id LIMIT 1) AS env,
            EXISTS (SELECT 1 FROM notification_campaign_items i JOIN notification_campaigns c ON c.id = i.campaign_id WHERE i.notification_id = n.id AND c.deleted_at IS NOT NULL) AS deleted
        FROM notifications n
        WHERE n.created_at <= NOW() AND n.created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          AND (n.expires_at IS NULL OR n.expires_at > NOW())
          AND NOT EXISTS (SELECT 1 FROM push_sent s WHERE s.item_key = CONCAT('n:', n.id))
        ORDER BY n.id LIMIT 300")->fetchAll();
    $read = $db->prepare('SELECT 1 FROM notification_reads WHERE notification_id = ? AND user_id = ? AND (read_at IS NOT NULL OR hidden_at IS NOT NULL)');
    foreach ($rows as $n) {
        if (!$dry) {
            $q = $db->prepare('INSERT IGNORE INTO push_sent (item_key) VALUES (?)');
            $q->execute(['n:' . $n['id']]);
            if ($q->rowCount() !== 1) continue;   // inny proces już to wysłał
        }
        if ((int)$n['deleted']) continue;
        $users = match ($n['target_type']) {
            'user' => isset($byUser[$n['target_user_id']]) ? [$n['target_user_id']] : [],
            default => array_keys($byUser),
        };
        foreach ($users as $user) {
            $read->execute([$n['id'], $user]);
            if ($read->fetchColumn()) continue;   // już przeczytane w otwartej karcie
            foreach ($byUser[$user] as $sub) {
                if ($n['target_type'] === 'admins' && !(int)$sub['is_admin']) continue;
                if ($n['env'] !== null && $n['env'] !== $sub['site']) continue;   // kampania tylko dla bety albo tylko dla klientów
                $link = (string)$n['link'];
                $url = preg_match('~^https://([a-z0-9-]+\.)*dnct\.pl(/|$)~', $link) ? $link : (str_starts_with($link, '/') ? push_base($sub) . $link : push_base($sub) . '/');
                $add($sub, (string)$n['title'], (string)$n['message'], 'dnct:n:' . $n['id'], $url);
            }
        }
    }
    if (!$dry && random_int(1, 500) === 1) $db->exec('DELETE FROM push_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');

    // 2) Czat: wszystkie strony korzystają z tej samej bazy rozmów.
    $last = push_cursor($db, 'chat', 'SELECT COALESCE(MAX(id), 0) FROM chat_messages');
    $msgs = $db->prepare('SELECT m.id, m.conversation_id, m.author_id, m.body, m.silent_at, m.withdrawn_at, a.display_name, a.username AS author
        FROM chat_messages m JOIN chat_members a ON a.conversation_id = m.conversation_id AND a.user_id = m.author_id
        WHERE m.id > ? ORDER BY m.id LIMIT 300');
    $msgs->execute([$last]);
    $recipients = $db->prepare("SELECT r.username FROM chat_members r
        LEFT JOIN chat_preferences p ON p.conversation_id = r.conversation_id AND p.user_id = r.user_id
        LEFT JOIN chat_group_membership g ON g.conversation_id = r.conversation_id AND g.user_id = r.user_id
        WHERE r.conversation_id = ? AND r.user_id <> ? AND r.last_read_id < ?
          AND COALESCE(p.blocked, 0) = 0 AND COALESCE(p.mute_forever, 0) = 0 AND (p.muted_until IS NULL OR p.muted_until <= NOW())
          AND (g.user_id IS NULL OR (g.state = 'active' AND (g.muted_until IS NULL OR g.muted_until <= NOW())))");
    $max = $last;
    foreach ($msgs->fetchAll() as $m) {
        $max = max($max, (int)$m['id']);
        if ($m['silent_at'] !== null || $m['withdrawn_at'] !== null) continue;
        $recipients->execute([$m['conversation_id'], $m['author_id'], $m['id']]);
        foreach ($recipients->fetchAll(PDO::FETCH_COLUMN) as $user) {
            foreach ($byUser[$user] ?? [] as $sub) {
                $add($sub, 'Czaty | DNCT.PL', ($m['display_name'] ?: $m['author'] ?: 'Użytkownik') . "\n" . push_preview($m['body']),
                    'dnct:chat:' . $m['conversation_id'] . ':' . $m['id'], push_base($sub) . '/');
            }
        }
    }
    if (!$dry && $max > $last) push_set_cursor($db, 'chat', $max);

    // 3) Odpowiedzi admina w ticketach: beta w tej bazie, game.dnct.pl w bazie produkcji.
    foreach (['beta' => $db, 'prod' => $prod] as $site => $source) {
        if (!$source) continue;
        $name = 'ticket-' . $site;
        $last = push_cursor($db, $name, 'SELECT COALESCE(MAX(id), 0) FROM support_messages', $source);
        $q = $source->prepare("SELECT m.id, m.ticket_id, m.author_role, m.author_name, m.body, m.withdrawn_at, t.customer_username
            FROM support_messages m JOIN support_tickets t ON t.id = m.ticket_id WHERE m.id > ? ORDER BY m.id LIMIT 300");
        $q->execute([$last]);
        $max = $last;
        foreach ($q->fetchAll() as $m) {
            $max = max($max, (int)$m['id']);
            if ($m['author_role'] !== 'admin' || $m['withdrawn_at'] !== null || (string)$m['customer_username'] === '') continue;
            foreach ($byUser[$m['customer_username']] ?? [] as $sub) {
                if ($sub['site'] !== $site) continue;
                $add($sub, 'Zgłoszenie | DNCT.PL', ($m['author_name'] ?: 'Wsparcie DNCT') . "\n" . push_preview($m['body']),
                    'dnct:ticket:' . $m['ticket_id'] . ':' . $m['id'], push_base($sub) . '/');
            }
        }
        if (!$dry && $max > $last) push_set_cursor($db, $name, $max);
    }

    if (!$out) return;
    if ($dry) {
        foreach ($out as [$sub, $p]) echo "[dry] {$sub['username']} ({$sub['site']}): {$p['title']} | " . str_replace("\n", ' / ', $p['body']) . " -> {$p['url']}\n";
        return;
    }
    $bySub = [];
    foreach ($out as [$sub, $payload]) {
        $bySub[$sub['endpoint_hash']] = $sub;
        $webPush->queueNotification(Subscription::create(json_decode($sub['subscription'], true) + ['contentEncoding' => 'aes128gcm']),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $ok = 0; $gone = 0; $fail = 0;
    foreach ($webPush->flush() as $report) {
        $hash = hash('sha256', $report->getEndpoint());
        if ($report->isSuccess()) {
            $ok++;
            $db->prepare('UPDATE push_subscriptions SET failures = 0 WHERE endpoint_hash = ?')->execute([$hash]);
        } elseif ($report->isSubscriptionExpired()) {
            $gone++;   // przeglądarka cofnęła zgodę albo wyczyściła dane
            $db->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?')->execute([$hash]);
        } else {
            $fail++;
            $db->prepare('UPDATE push_subscriptions SET failures = failures + 1 WHERE endpoint_hash = ?')->execute([$hash]);
            $db->exec('DELETE FROM push_subscriptions WHERE failures >= 20');
            fwrite(STDERR, date('c') . ' push-worker: ' . substr($report->getReason(), 0, 200) . "\n");
        }
    }
    echo date('c') . " push: wysłane {$ok}, wygasłe {$gone}, błędy {$fail}\n";
}
