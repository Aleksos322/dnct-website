<?php
/**
 * Odzyskiwanie zdarzeń, których webhook nie dostał.
 *
 * Uruchamiane z wiersza poleceń:
 *
 *     php backend/tools/reconcile-stripe.php            # podgląd, nic nie zmienia
 *     php backend/tools/reconcile-stripe.php --apply    # przetwarza zaległe
 *     php backend/tools/reconcile-stripe.php --apply --hours=48
 *
 * PO CO TO ISTNIEJE. Webhook jest doręczeniem z zewnątrz i potrafi nie dojść:
 * padnie DNS, wygaśnie certyfikat, ktoś zmieni adres, proxy zwróci 502 przez
 * kilka godzin. Stripe ponawia przez trzy doby, ale po tym czasie odpuszcza -
 * a po naszej stronie zostaje zamówienie opłacone i bez serwera. Bez tego
 * narzędzia jedynym wyjściem byłoby ręczne klikanie w bazie.
 *
 * Zdarzenia idą przez DOKŁADNIE tę samą obsługę co webhook
 * (backend/lib/stripe_events.php), łącznie z tabelą `stripe_events`. Zdarzenie
 * już przetworzone zostanie pominięte, więc narzędzie można puścić dwa razy
 * bez skutków ubocznych.
 *
 * NIE MA TU WERYFIKACJI PODPISU i to jest w porządku: zdarzenia pobieramy sami
 * z API Stripe'a uwierzytelnionym kluczem, więc ich pochodzenie jest pewne
 * z definicji. Podpis chroni endpoint HTTP, do którego może zapukać ktokolwiek.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/stripe.php';
require_once __DIR__ . '/../lib/stripe_events.php';

$apply = in_array('--apply', $argv, true);
$hours = 72;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--hours=')) {
        $hours = max(1, (int)substr($arg, 8));
    }
}

/* Obsługujemy tylko te typy, na które webhook jest zapisany. Przetwarzanie
   wszystkiego, co Stripe wygenerował, oznaczałoby reagowanie na zdarzenia,
   których logiki nie napisaliśmy. */
const RECONCILE_TYPES = [
    'checkout.session.completed',
    'checkout.session.async_payment_succeeded',
    'invoice.paid',
    'invoice.payment_failed',
    'customer.subscription.deleted',
];

stripe_init();
$dnct = dnct_db();

echo $apply ? "TRYB: przetwarzanie\n" : "TRYB: podgląd (dodaj --apply, żeby wykonać)\n";
printf("Zakres: ostatnie %d h · konto %s\n\n", $hours, stripe_is_test_mode() ? 'TESTOWE' : 'PRODUKCYJNE');

$since = time() - $hours * 3600;
$seen  = $dnct->prepare('SELECT status FROM stripe_events WHERE event_id = ?');

$events = \Stripe\Event::all([
    'limit'   => 100,
    'created' => ['gte' => $since],
    'types'   => RECONCILE_TYPES,
]);

/* Stripe oddaje zdarzenia od NAJNOWSZEGO. Odwracamy kolejność, bo obsługa
   zakłada chronologię: opłacenie sesji musi wyprzedzić fakturę odnowieniową,
   inaczej przedłużenie trafiłoby w zamówienie bez serwera. */
$list = array_reverse($events->data);

$done = $skipped = $failed = 0;

foreach ($list as $event) {
    $seen->execute([$event->id]);
    $known = $seen->fetchColumn();

    if ($known === 'done') {
        $skipped++;
        continue;
    }

    printf("  %-30s %-44s %s\n", $event->id, $event->type, date('d.m H:i', $event->created));

    if (!$apply) {
        continue;
    }

    try {
        // Ten sam zamek co w webhooku: zapis PRZED obsługą. Wiersz w stanie
        // "error" nadpisujemy, bo właśnie po to tu jesteśmy.
        $dnct->prepare(
            'INSERT INTO stripe_events (event_id, type, status) VALUES (?, ?, "processing")
             ON DUPLICATE KEY UPDATE status = "processing", error = NULL'
        )->execute([$event->id, $event->type]);

        webhook_dispatch($dnct, $event);

        $dnct->prepare('UPDATE stripe_events SET status = "done", handled_at = NOW() WHERE event_id = ?')
             ->execute([$event->id]);

        echo "      -> zrobione\n";
        $done++;
    } catch (Throwable $e) {
        $dnct->prepare('UPDATE stripe_events SET status = "error", error = ?, handled_at = NOW() WHERE event_id = ?')
             ->execute([mb_substr($e->getMessage(), 0, 500), $event->id]);

        echo "      -> BŁĄD: " . $e->getMessage() . "\n";
        $failed++;
    }
}

printf(
    "\nPodsumowanie: %d przetworzonych, %d pominiętych (już obsłużone), %d z błędem\n",
    $done,
    $skipped,
    $failed
);
