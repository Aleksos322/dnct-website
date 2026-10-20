<?php
/**
 * Podpisane zapisy powiadomień z PRODUKCJI do bazy systemu wiadomości (bety).
 *
 * System wiadomości (dzwonek, czat, admin/powiadomienia) żyje na becie i czyta
 * tylko swoją bazę (backend/lib/PANEL-MESSAGING.md). Cykl życia serwera i inne
 * automaty produkcji zapisują powiadomienia do bazy `dnct`, więc bez tego
 * klient nie zobaczy ich w dzwonku, a admin w admin/powiadomienia.
 *
 * Wejście tylko serwer-serwer: ten sam podpis Ed25519 co most panel-messaging.php
 * (klucz prywatny poza DocumentRootem, publiczny w backend/lib), ale OSOBNE
 * `purpose` = "notify" - podpisany ładunek mostu użytkownika nie jest tu ważny,
 * a ten nie jest ważny w moście. Ważność 60 s, nonce jednorazowy, pola walidowane
 * i zawężone do zwykłego powiadomienia (nic nie wykonuje, nie tworzy sesji).
 */
declare(strict_types=1);

function notify_deny(int $status = 403): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo '{"ok":false}';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    notify_deny();
}

$raw = base64_decode((string)($_POST['payload'] ?? ''), true);
$sig = base64_decode((string)($_POST['signature'] ?? ''), true);
$key = @file_get_contents(__DIR__ . '/../backend/lib/panel-messaging-public.key');
if (!is_string($raw) || strlen($raw) > 16384 || !is_string($sig) || strlen($sig) !== 64
    || !is_string($key) || strlen($key) !== 32 || !sodium_crypto_sign_verify_detached($sig, $raw, $key)) {
    notify_deny();
}

$p = json_decode($raw, true);
if (!is_array($p) || ($p['v'] ?? 0) !== 1 || ($p['purpose'] ?? '') !== 'notify'
    || abs(time() - (int)($p['time'] ?? 0)) > 60
    || !preg_match('/^[a-f0-9]{48}$/D', (string)($p['nonce'] ?? ''))) {
    notify_deny();
}

$type = (string)($p['type'] ?? '');
$target = (string)($p['target_type'] ?? '');
$title = (string)($p['title'] ?? '');
$message = (string)($p['message'] ?? '');
$user = $p['target_user_id'] ?? null;
$eventKey = $p['event_key'] ?? null;
$link = $p['link'] ?? null;
$category = $p['category'] ?? null;
$action = $p['action_label'] ?? null;
$expires = $p['expires_at'] ?? null;

$ok = in_array($type, ['info', 'success', 'warning', 'error'], true)
    && in_array($target, ['all', 'admins', 'user'], true)
    && $title !== '' && mb_strlen($title) <= 120
    && $message !== '' && mb_strlen($message) <= 2000
    && ($target === 'user' ? (is_string($user) && $user !== '' && strlen($user) <= 64 && !preg_match('/[\x00-\x1f]/', $user)) : $user === null)
    && ($eventKey === null || (is_string($eventKey) && $eventKey !== '' && strlen($eventKey) <= 180))
    && ($link === null || (is_string($link) && strlen($link) <= 255 && preg_match('~^(/[^/\\\\]|https://)~', $link)))
    && ($category === null || (is_string($category) && preg_match('/^[a-z_]{1,32}$/D', $category)))
    && ($action === null || (is_string($action) && mb_strlen($action) <= 80))
    && ($expires === null || (is_string($expires) && strtotime($expires) !== false));
if (!$ok) {
    notify_deny(422);
}

require_once __DIR__ . '/../backend/lib/env.php';
require_once __DIR__ . '/../backend/lib/db.php';
$db = dnct_db();

try {
    $db->prepare('INSERT INTO panel_messaging_nonces(nonce,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 2 MINUTE))')
       ->execute([$p['nonce']]);
} catch (Throwable $e) {
    notify_deny();   // powtórzony nonce albo baza niedostępna
}

// Wpisy produkcji w osobnej przestrzeni event_key; admin widzi, skąd przyszło.
if ($target === 'admins') {
    $title = '[Produkcja] ' . $title;
}
try {
    $db->prepare(
        'INSERT INTO notifications
            (type, title, message, target_type, target_user_id, event_key, link, created_by, expires_at, category, action_label)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $type, $title, $message, $target, $target === 'user' ? $user : null,
        $eventKey === null ? null : 'prod:' . $eventKey, $link, 'system',
        $expires === null ? null : date('Y-m-d H:i:s', (int)strtotime($expires)), $category, $action,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        header('Content-Type: application/json');
        echo '{"ok":true,"duplicate":true}';   // ten sam event_key - już jest
        exit;
    }
    error_log('internal-notify: ' . $e->getMessage());
    notify_deny(500);
}

header('Content-Type: application/json');
echo '{"ok":true}';
