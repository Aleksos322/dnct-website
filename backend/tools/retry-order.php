<?php
/**
 * Ponawia JEDNO zamówienie utknięte w "pending"/"failed" - odpytuje Stripe
 * o realny status sesji i przepuszcza przez dokładnie tę samą ścieżkę co
 * webhook (webhook_checkout_completed).
 *
 *     php backend/tools/retry-order.php --order=11            # podgląd
 *     php backend/tools/retry-order.php --order=11 --apply    # wykonuje
 *
 * PO CO TO ISTNIEJE OSOBNO OD reconcile-stripe.php: tamto narzędzie
 * przetwarza WSZYSTKIE zaległe zdarzenia z ostatnich N godzin - dobre do
 * uprzątnięcia po awarii, złe do wywołania "napraw MI TO JEDNO zamówienie"
 * z panelu admina jednym kliknięciem. To drugie zna tylko jeden numer
 * zamówienia i kończy się jasnym komunikatem sukcesu/porażki, który panel
 * admina (WEB/admin.dnct.pl/backend/lib/zamowienia_admin.php) pokazuje
 * wprost jako wynik akcji "spróbuj ponownie".
 *
 * Uruchamiane WYŁĄCZNIE przez zamowienia_admin_ponow_beta() (osobny proces
 * PHP z DNCT_ENV_FILE=config/beta.env) - panel admina nie może wczytać
 * stripe_events.php bety w tym samym procesie, w którym ma już wczytane
 * stripe_events.php produkcji (identyczne nazwy funkcji, dwie gałęzie tego
 * samego repozytorium).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("To narzędzie działa wyłącznie z wiersza poleceń.\n");
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/stripe.php';
require_once __DIR__ . '/../lib/stripe_events.php';

$apply   = in_array('--apply', $argv, true);
$orderId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--order=')) {
        $orderId = (int)substr($arg, 8);
    }
}

if ($orderId === null || $orderId <= 0) {
    fwrite(STDERR, "Podaj --order=ID.\n");
    exit(1);
}

$dnct  = dnct_db();
$order = order_by_id($dnct, $orderId);

if ($order === null) {
    fwrite(STDERR, "BEZ ZMIAN: zamówienie #{$orderId} nie istnieje.\n");
    exit(1);
}

if (!in_array($order['status'], ['pending', 'failed'], true)) {
    fwrite(STDERR, "BEZ ZMIAN: zamówienie #{$orderId} ma status \"{$order['status']}\" - ponawianie ma sens tylko dla pending/failed.\n");
    exit(1);
}

if (empty($order['stripe_session_id'])) {
    fwrite(STDERR, "BEZ ZMIAN: zamówienie #{$orderId} nie ma zapisanej sesji Stripe.\n");
    exit(1);
}

echo ($apply ? "WYKONUJĘ" : "PODGLĄD (dodaj --apply)") . " - zamówienie #{$orderId}, status: {$order['status']}\n";

if (!$apply) {
    exit(0);
}

stripe_init();

try {
    $session = \Stripe\Checkout\Session::retrieve((string)$order['stripe_session_id']);
} catch (\Stripe\Exception\ApiErrorException $e) {
    fwrite(STDERR, 'BEZ ZMIAN: Stripe nie zna tej sesji - ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'status sesji Stripe: ' . (string)$session->status . ' / płatność: ' . (string)$session->payment_status . "\n";

webhook_checkout_completed($dnct, $session);

$swiezy = order_by_id($dnct, $orderId);

if ($swiezy === null || $swiezy['status'] === $order['status']) {
    $powod = $swiezy['failure_reason'] ?? null;
    fwrite(STDERR, 'BEZ ZMIAN: status zostaje przy "' . $order['status'] . '"'
        . ($powod ? ' - ' . $powod : ' - płatność najwyraźniej jeszcze nie przeszła.') . "\n");
    exit(1);
}

echo "OK: nowy status - {$swiezy['status']}\n";
exit(0);
