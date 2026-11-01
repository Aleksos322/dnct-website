<?php
/**
 * Przypomina klientom, że opłacony serwer ruszy dopiero po 14 dniach na
 * odstąpienie, i daje im przycisk "uruchom teraz".
 *
 *     php backend/tools/deferred-reminder.php           # podgląd
 *     php backend/tools/deferred-reminder.php --apply   # wysyła maile
 *
 * Wybiera zamówienia `paid`, którym minęła godzina od zakupu, a serwer dalej
 * czeka (klient nie zaznaczył natychmiastowego startu i nie kliknął przycisku
 * na karcie w /twoje-serwery). Każdemu wysyła JEDEN mail - po wysłaniu zapisuje
 * orders.deferred_mail_at. Kto kliknął przycisk, nie spełnia warunków, więc
 * mail do niego nie idzie.
 *
 * Ten sam .env co reszta narzędzi: dla bety ustaw DNCT_ENV_FILE.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/stripe_events.php';
require_once __DIR__ . '/../lib/order_mail.php';

$apply = in_array('--apply', $argv, true);
$dnct = dnct_db();

printf("[%s] %s\n", date('Y-m-d H:i:s'), $apply ? 'WYKONUJĘ' : 'PODGLĄD (dodaj --apply)');

$rows = $dnct->query(
    'SELECT * FROM orders
      WHERE status = "paid" AND kind = "new" AND ptero_server_id IS NULL
        AND immediate_service_requested_at IS NULL
        AND service_start_at IS NOT NULL AND service_start_at > NOW()
        AND deferred_mail_at IS NULL
        AND terms_accepted_at IS NOT NULL
        AND terms_accepted_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      ORDER BY id ASC LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "  nic do wysłania\n";
}

foreach ($rows as $order) {
    $id = (int)$order['id'];
    printf("  #%d %s\n", $id, (string)$order['user_email']);
    if (!$apply) {
        continue;
    }

    // Ten sam zamek co przycisk, webhook i cron startu - klient mógł kliknąć w tej chwili.
    $lock = 'dnct_order_' . $id;
    $stmt = $dnct->prepare('SELECT GET_LOCK(?, 5)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) {
        echo "    pomijam: zamówienie jest właśnie obsługiwane\n";
        continue;
    }
    try {
        $fresh = order_by_id($dnct, $id);
        if ($fresh === null || $fresh['status'] !== 'paid' || !empty($fresh['immediate_service_requested_at'])
            || !empty($fresh['ptero_server_id']) || !empty($fresh['deferred_mail_at'])) {
            echo "    pomijam: stan zmienił się podczas oczekiwania\n";
            continue;
        }

        $res = send_deferred_start_reminder_mail($fresh);
        if (!$res['ok']) {
            echo '    BŁĄD maila: ' . $res['error'] . "\n";
            continue;
        }
        $dnct->prepare('UPDATE orders SET deferred_mail_at = NOW() WHERE id = ?')->execute([$id]);
        echo "    wysłano\n";
    } finally {
        $dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}

echo "Gotowe.\n";
