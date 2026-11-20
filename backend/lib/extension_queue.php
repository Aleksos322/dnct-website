<?php
/**
 * backend/lib/extension_queue.php
 *
 * Kolejka przedłużeń zawieszonych serwerów, gdy na węźle nie ma miejsca w RAM.
 *
 * ZASADA (decyzja właściciela, 2026-11-20): serwer ZAWIESZONY nie blokuje RAM-u
 * ani CPU węzła (patrz node_capacity). Przez to jego właściciel, który chce
 * przedłużyć, może nie mieć dokąd wrócić. Wtedy:
 *   1. przed płatnością pokazujemy komunikat "zostaliśmy wyprzedani" i pytamy,
 *      czy mimo to zapłacić,
 *   2. po płatności serwer zostaje zawieszony, a zamówienie dostaje `queued_at`,
 *   3. kolejka (backend/tools/queue-start.php, cron) uruchamia serwery po kolei
 *      (FIFO wg `queued_at`), gdy tylko zwolni się miejsce,
 *   4. opłacony czas liczy się OD URUCHOMIENIA, nie od płatności - dlatego
 *      order_extend() wołamy dopiero w chwili startu,
 *   5. po QUEUE_REFUND_DAYS dniach bez miejsca - automatyczny zwrot.
 *
 * Dysk liczy się dla wszystkich serwerów (pliki zawieszonego i tak leżą na węźle).
 */

declare(strict_types=1);

require_once __DIR__ . '/capacity.php';

/** Po ilu dniach oczekiwania w kolejce zwracamy pieniądze. */
const QUEUE_REFUND_DAYS = 14;

/**
 * Czy serwer z zamówienia nadrzędnego mieści się z powrotem na węźle.
 *
 * Serwer, który NIE jest zawieszony, już zajmuje swoje miejsce - przedłużenie
 * nie wymaga wtedy nic poza płatnością. Kolejka ma pierwszeństwo: dopóki ktoś
 * czeka, nowy płacący staje za nim, nawet jeśli akurat jest miejsce
 * (cron i tak uruchomi najpierw tego, kto był pierwszy).
 *
 * @return array{fits:bool,suspended:bool,ahead:int,position:int,reason:string}
 */
function extension_capacity(PDO $panel, PDO $dnct, array $parent): array
{
    $ahead = (int)$dnct->query('SELECT COUNT(*) FROM orders WHERE queued_at IS NOT NULL')->fetchColumn();
    $out = ['fits' => true, 'suspended' => false, 'ahead' => $ahead, 'position' => $ahead + 1, 'reason' => 'ok'];

    $serverId = (int)($parent['ptero_server_id'] ?? 0);
    if ($serverId <= 0) {
        return $out;
    }

    $st = $panel->prepare(
        'SELECT (s.status <=> \'suspended\') AS suspended, s.memory, s.cpu, n.name AS node_name
           FROM servers s JOIN nodes n ON n.id = s.node_id WHERE s.id = ? LIMIT 1'
    );
    $st->execute([$serverId]);
    $srv = $st->fetch(PDO::FETCH_ASSOC);

    // Brak serwera w panelu albo serwer działa: nic do rezerwowania.
    if (!$srv || (int)$srv['suspended'] !== 1) {
        return $out;
    }
    $out['suspended'] = true;

    // Hak testowy: ustawiany wyłącznie przez skrypty testowe, w produkcji nieużywany.
    $cap = $GLOBALS['DNCT_TEST_CAPACITY'] ?? node_capacity($panel, (string)$srv['node_name']);
    if ($cap === null) {
        return $out; // nieznany węzeł: nie blokujemy przedłużenia zgadywaniem
    }

    $needRam = (int)$srv['memory'];
    $needCpu = (int)$srv['cpu'];
    $fitsRam = $needRam <= 0 || $cap['free_memory'] >= $needRam;
    $fitsCpu = $needCpu <= 0 || $cap['free_cpu'] >= $needCpu;

    if (!$fitsRam || !$fitsCpu) {
        $out['fits'] = false;
        $out['reason'] = 'no_capacity';
    } elseif ($ahead > 0) {
        $out['fits'] = false;
        $out['reason'] = 'queue_ahead';
    }

    return $out;
}

/**
 * Wstawia opłacone przedłużenie do kolejki. Serwer zostaje zawieszony,
 * dni NIE są doliczane (order_extend dopiero przy starcie).
 */
function extension_enqueue(PDO $dnct, array $order, array $parent): bool
{
    /* status = paid: pieniądze są u nas (inaczej zamówienie wyglądałoby w adminie i raportach
       na nieopłacone), a drugie doręczenie tego samego zdarzenia wychodzi wcześnie na
       warunku "już opłacone" w webhook_checkout_completed. */
    $dnct->prepare('UPDATE orders SET queued_at = NOW(), status = "paid" WHERE id = ? AND queued_at IS NULL')
         ->execute([(int)$order['id']]);

    $pos = (int)$dnct->query('SELECT COUNT(*) FROM orders WHERE queued_at IS NOT NULL')->fetchColumn();

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'warning',
        'Jesteś w kolejce na uruchomienie serwera',
        'Płatność dotarła, ale na węźle nie ma teraz miejsca. Uruchomimy serwer od razu, gdy zwolni się miejsce '
        . '(pozycja w kolejce: ' . $pos . '). Opłacony czas zacznie biec dopiero od uruchomienia. '
        . 'Jeśli miejsce nie zwolni się w ciągu ' . QUEUE_REFUND_DAYS . ' dni, zwrócimy pieniądze.',
        ['link' => '/twoje-serwery', 'action_label' => 'Zobacz stan']
    );
    try {
        require_once __DIR__ . '/queue_mail.php';
        send_queued_mail($order, $parent, $pos);
    } catch (Throwable $e) {
        error_log('Kolejka: mail o kolejce #' . (int)$order['id'] . ' - ' . $e->getMessage());
    }
    ntfy_send(
        'sprzedaz',
        'KOLEJKA: ' . pricing_format((int)$order['amount_gross']) . ' zł zapłacone, brak miejsca',
        (string)$order['user_email'] . ', serwer z zamówienia #' . (int)$parent['id'] . ', pozycja ' . $pos
        . '. Zwolnij miejsce na węźle, a ruszy sam.',
        4,
        'hourglass'
    );

    return true;
}

/** Zamówienia czekające w kolejce, od najstarszego. */
function extension_queue_list(PDO $dnct): array
{
    return $dnct->query('SELECT * FROM orders WHERE queued_at IS NOT NULL ORDER BY queued_at, id')
                ->fetchAll(PDO::FETCH_ASSOC);
}
