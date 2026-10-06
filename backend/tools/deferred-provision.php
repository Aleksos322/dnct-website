<?php
/**
 * Uruchamia opłacone usługi, dla których Klient nie zażądał startu przed
 * upływem terminu odstąpienia.
 *
 *     php backend/tools/deferred-provision.php           # podgląd
 *     php backend/tools/deferred-provision.php --apply   # uruchamia należne
 *
 * Ten proces nie tworzy nowych zamówień i nie pobiera płatności. Dotyka tylko
 * wierszy `paid`, których service_start_at już nadszedł. Na każdym zamówieniu
 * zakłada ten sam MySQL GET_LOCK co webhook Stripe'a, więc nie może stworzyć
 * drugiej usługi przy równoległym zdarzeniu płatniczym.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/stripe_events.php';

$apply = in_array('--apply', $argv, true);
$dnct = dnct_db();

printf("[%s] %s\n", date('Y-m-d H:i:s'), $apply ? 'WYKONUJĘ' : 'PODGLĄD (dodaj --apply)');

$games = $dnct->query(
    'SELECT * FROM orders
      WHERE status = "paid" AND kind = "new" AND ptero_server_id IS NULL
        AND immediate_service_requested_at IS NULL
        AND service_start_at IS NOT NULL AND service_start_at <= NOW()
      ORDER BY service_start_at ASC LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC);

echo "-- serwery gier --\n";
if (!$games) {
    echo "  nic\n";
}
foreach ($games as $order) {
    $id = (int)$order['id'];
    printf("  #%d %s -> start\n", $id, (string)$order['user_email']);
    if (!$apply) {
        continue;
    }

    $lock = 'dnct_order_' . $id;
    $stmt = $dnct->prepare('SELECT GET_LOCK(?, 15)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) {
        echo "    pomijam: zamówienie jest właśnie obsługiwane\n";
        continue;
    }
    try {
        $fresh = order_by_id($dnct, $id);
        if ($fresh === null || $fresh['status'] !== 'paid' || !empty($fresh['immediate_service_requested_at'])
            || empty($fresh['service_start_at']) || strtotime((string)$fresh['service_start_at']) > time()) {
            echo "    pomijam: stan zmienił się podczas oczekiwania\n";
            continue;
        }
        webhook_provision_paid_order($dnct, $fresh);
    } finally {
        $dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}

$hosting = $dnct->query(
    'SELECT * FROM hosting_orders
      WHERE status = "paid" AND extends_order_id IS NULL AND hosting_user IS NULL
        AND immediate_service_requested_at IS NULL
        AND service_start_at IS NOT NULL AND service_start_at <= NOW()
      ORDER BY service_start_at ASC LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC);

echo "-- hosting WWW --\n";
if (!$hosting) {
    echo "  nic\n";
}
foreach ($hosting as $order) {
    $id = (int)$order['id'];
    printf("  #%d %s -> start\n", $id, (string)$order['user_email']);
    if (!$apply) {
        continue;
    }

    $lock = 'dnct_hosting_order_' . $id;
    $stmt = $dnct->prepare('SELECT GET_LOCK(?, 15)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) {
        echo "    pomijam: zamówienie jest właśnie obsługiwane\n";
        continue;
    }
    try {
        $fresh = hosting_order_by($dnct, 'id', $id);
        if ($fresh === null || $fresh['status'] !== 'paid' || !empty($fresh['immediate_service_requested_at'])
            || empty($fresh['service_start_at']) || strtotime((string)$fresh['service_start_at']) > time()) {
            echo "    pomijam: stan zmienił się podczas oczekiwania\n";
            continue;
        }
        hosting_activate_paid_order($dnct, $fresh);
    } finally {
        $dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}

echo "Gotowe.\n";
