<?php
/**
 * GET /api/server-status.php?order_id=123
 *
 * Krótki, odczytowy stan usługi na ekranie „Twoje serwery”. Dane o użyciu
 * pobieramy dopiero po wyrenderowaniu karty: panel nie czeka wtedy na Wings,
 * a jedna niedostępna maszyna nie blokuje pozostałych usług klienta.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';
require_once dirname(__DIR__) . '/backend/lib/server_archive.php';

require_method('GET');
rate_limit_guard('server_status', RATE_LIMIT_AKTYWNOSC, 'Zbyt wiele odświeżeń stanu. Spróbuj ponownie za chwilę.');

$email = (string)($_SESSION['authentik_email'] ?? '');
if ($email === '') {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId <= 0) {
    json_error('Nieprawidłowy serwer.', 422, [], ['reason' => 'invalid_order']);
}

$dnct = dnct_db();
$orderStmt = $dnct->prepare(
    'SELECT id, ptero_server_id FROM orders WHERE id = ? AND user_email = ? AND status = "provisioned" LIMIT 1'
);
$orderStmt->execute([$orderId, $email]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$order || empty($order['ptero_server_id'])) {
    json_error('Nie znaleziono aktywnego serwera.', 404, [], ['reason' => 'not_found']);
}

$panel = panel_db();
$stmt = $panel->prepare(
    'SELECT s.uuidShort, s.status, s.backup_limit,
            COUNT(b.id) AS backups_used,
            MAX(CASE WHEN b.is_successful = 1 THEN COALESCE(b.completed_at, b.created_at) END) AS last_backup_at
       FROM servers s
       LEFT JOIN backups b ON b.server_id = s.id AND b.deleted_at IS NULL
      WHERE s.id = ?
      GROUP BY s.id, s.uuidShort, s.status, s.backup_limit'
);
$stmt->execute([(int)$order['ptero_server_id']]);
$server = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$server) {
    json_error('Serwer nie jest już dostępny w panelu.', 404, [], ['reason' => 'server_missing']);
}

/* Klucz klienta pozostaje wyłącznie po stronie serwera. Gdy Wings chwilowo
   nie odpowie, nadal zwracamy stan panelu i backupy — karta nie znika. */
$state = ($server['status'] ?? '') === 'suspended' ? 'suspended' : 'unknown';
$resources = null;
try {
    // To jest dane dodatkowe na karcie, nie operacja administracyjna: po 5 s
    // wolimy oddać „brak odczytu” niż zajmować proces PHP przez pół minuty.
    $live = ptero_client_request('GET', '/api/client/servers/' . rawurlencode((string)$server['uuidShort']) . '/resources', null, 5);
    $attrs = $live['code'] === 200 && is_array($live['json']) ? ($live['json']['attributes'] ?? null) : null;
    if (is_array($attrs)) {
        $state = (string)($attrs['current_state'] ?? $state);
        $raw = $attrs['resources'] ?? null;
        if (is_array($raw)) {
            $resources = [
                'memory_bytes' => max(0, (int)($raw['memory_bytes'] ?? 0)),
                'cpu_absolute' => max(0, (float)($raw['cpu_absolute'] ?? 0)),
                'disk_bytes'   => max(0, (int)($raw['disk_bytes'] ?? 0)),
                'uptime'       => max(0, (int)($raw['uptime'] ?? 0)),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('server-status: zasoby serwera #' . $orderId . ' - ' . $e->getMessage());
}

json_ok([
    'state' => $state,
    'resources' => $resources,
    'backups' => [
        'used' => (int)$server['backups_used'],
        'limit' => (int)$server['backup_limit'],
        'last' => $server['last_backup_at'] !== null ? czas_fmt((string)$server['last_backup_at'], 'd.m.Y, H:i') : null,
    ],
]);
