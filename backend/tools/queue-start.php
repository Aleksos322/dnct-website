<?php
/**
 * Kolejka przedłużeń: uruchamia zawieszone serwery, gdy zwolni się miejsce, i zwraca
 * pieniądze po QUEUE_REFUND_DAYS dniach oczekiwania.
 *
 *     php backend/tools/queue-start.php           # podgląd
 *     php backend/tools/queue-start.php --apply   # wykonuje
 *
 * Wzorzec jak lifecycle.php: bez --apply nic nie zmienia. W warunkach bety ustaw
 * DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env.
 *
 * KOLEJNOŚĆ jest ścisła (FIFO wg queued_at): jeśli pierwszy w kolejce się nie mieści,
 * kolejni czekają za nim, nawet gdyby ich mniejszy serwer by się zmieścił - inaczej duży
 * serwer mógłby czekać w nieskończoność. Opłacony czas liczymy OD URUCHOMIENIA.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/stripe_events.php';
require_once __DIR__ . '/../lib/extension_queue.php';
require_once __DIR__ . '/../lib/queue_mail.php';

$apply = in_array('--apply', $argv, true);
$dnct = dnct_db();
$panel = panel_db();

printf("[%s] %s\n", date('Y-m-d H:i:s'), $apply ? 'WYKONUJĘ' : 'PODGLĄD (dodaj --apply)');

$lock = 'dnct_queue_start';
$st = $dnct->prepare('SELECT GET_LOCK(?, 0)');
$st->execute([$lock]);
if ((int)$st->fetchColumn() !== 1) {
    exit("Inny przebieg kolejki jeszcze trwa.\n");
}

$queue = extension_queue_list($dnct);
echo "-- kolejka (" . count($queue) . ") --\n";
if (!$queue) {
    echo "  pusto\n";
}

$blocked = false;
foreach ($queue as $o) {
    $id = (int)$o['id'];
    $parent = order_by_id($dnct, (int)$o['extends_order_id']);
    $age = (time() - strtotime((string)$o['queued_at'])) / 86400;

    // Zwrot po terminie ma pierwszeństwo przed ponowną próbą startu.
    if ($age >= QUEUE_REFUND_DAYS) {
        printf("  #%d czeka %.1f dni -> ZWROT\n", $id, $age);
        if ($apply) {
            queue_refund($dnct, $o);
        }
        continue;
    }

    if ($parent === null || empty($parent['ptero_server_id']) || !empty($parent['deleted_at'])) {
        printf("  #%d serwer nadrzędny zniknął -> ZWROT\n", $id);
        if ($apply) {
            queue_refund($dnct, $o, 'serwer nie istnieje');
        }
        continue;
    }

    if ($blocked) {
        printf("  #%d czeka za poprzednim (FIFO)\n", $id);
        continue;
    }

    // Sprawdzamy sam węzeł, bez kolejki przed nami (my JESTEŚMY kolejką).
    $cap = $GLOBALS['DNCT_TEST_CAPACITY'] ?? null;
    $srv = $panel->prepare('SELECT (s.status <=> \'suspended\') AS suspended, s.memory, s.cpu, n.name AS node_name FROM servers s JOIN nodes n ON n.id = s.node_id WHERE s.id = ?');
    $srv->execute([(int)$parent['ptero_server_id']]);
    $row = $srv->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        printf("  #%d serwer %d nie istnieje w panelu -> pomijam\n", $id, (int)$parent['ptero_server_id']);
        continue;
    }
    $cap = $cap ?? node_capacity($panel, (string)$row['node_name']);
    $fits = (int)$row['suspended'] !== 1
        || ($cap !== null && $cap['free_memory'] >= (int)$row['memory'] && $cap['free_cpu'] >= (int)$row['cpu']);

    if (!$fits) {
        printf("  #%d serwer %d (%d MB) - brak miejsca, czeka %.1f dni\n", $id, (int)$parent['ptero_server_id'], (int)$row['memory'], $age);
        $blocked = true;
        continue;
    }

    printf("  #%d serwer %d -> URUCHAMIAM (+%d dni od teraz)\n", $id, (int)$parent['ptero_server_id'], (int)$o['period_days']);
    if (!$apply) {
        continue;
    }

    if (!provision_unsuspend((int)$parent['ptero_server_id'])) {
        echo "    BŁĄD: panel nie zdjął zawieszenia - zostaje w kolejce\n";
        $blocked = true;
        continue;
    }
    order_extend($dnct, (int)$parent['id'], (int)$o['period_days']);
    $dnct->prepare('UPDATE orders SET queued_at = NULL, queue_started_at = NOW(), status = "provisioned", expires_at = NOW() WHERE id = ?')->execute([$id]);
    $fresh = order_by_id($dnct, (int)$parent['id']);

    notify_user(
        $dnct, (string)$o['user_name'], 'success', 'Serwer uruchomiony',
        'Zwolniło się miejsce - serwer działa. Opłacony czas liczymy od teraz, do ' . lifecycle_when((string)($fresh['expires_at'] ?? '')) . '.',
        ['link' => '/twoje-serwery', 'action_label' => 'Otwórz serwery']
    );
    try {
        send_queue_started_mail($o, $parent, (string)($fresh['expires_at'] ?? ''));
    } catch (Throwable $e) {
        error_log('Kolejka: mail o starcie #' . $id . ' - ' . $e->getMessage());
    }
    ntfy_send('sprzedaz', 'Kolejka: serwer uruchomiony', (string)$o['user_email'] . ', zamówienie #' . (int)$parent['id'], 3, 'white_check_mark');
}

$dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);

/** Zwrot całej kwoty przez Stripe + zamknięcie zamówienia + wiadomości. */
function queue_refund(PDO $dnct, array $o, string $why = 'brak miejsca'): void
{
    $id = (int)$o['id'];
    try {
        stripe_init();
        $session = \Stripe\Checkout\Session::retrieve((string)$o['stripe_session_id']);
        $pi = (string)($session->payment_intent ?? '');
        if ($pi === '') {
            throw new RuntimeException('sesja bez payment_intent');
        }
        \Stripe\Refund::create(['payment_intent' => $pi, 'metadata' => ['order_id' => (string)$id, 'powod' => 'kolejka: ' . $why]]);
    } catch (Throwable $e) {
        echo "    BŁĄD zwrotu: " . $e->getMessage() . "\n";
        notify_admins($dnct, 'error', 'Kolejka: zwrot NIE przeszedł (#' . $id . ')', $e->getMessage() . ' - zwróć ręcznie w Stripe.');
        return;
    }
    $dnct->prepare('UPDATE orders SET queued_at = NULL, status = "cancelled", failure_reason = ? WHERE id = ?')
         ->execute(['Zwrot automatyczny: ' . $why . ' (kolejka)', $id]);
    notify_user($dnct, (string)$o['user_name'], 'info', 'Zwróciliśmy pieniądze za przedłużenie',
        'Nie zwolniło się miejsce na serwer, więc zwróciliśmy całą kwotę. Przepraszamy za kłopot.', ['link' => '/twoje-serwery']);
    try {
        send_queue_refund_mail($o);
    } catch (Throwable $e) {
        error_log('Kolejka: mail o zwrocie #' . $id . ' - ' . $e->getMessage());
    }
    ntfy_send('sprzedaz', 'Kolejka: zwrot ' . pricing_format((int)$o['amount_gross']) . ' zł', (string)$o['user_email'] . ', zamówienie #' . $id . ' (' . $why . ')', 3, 'money_with_wings');
    echo "    zwrot wykonany\n";
}
