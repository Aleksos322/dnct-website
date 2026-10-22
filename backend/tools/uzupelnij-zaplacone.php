<?php
/**
 * Jednorazowe uzupełnienie orders.amount_paid ze Stripe dla zamówień z kodem
 * rabatowym (migracja 047). Bez --apply tylko pokazuje, co by zapisało.
 *
 *   php backend/tools/uzupelnij-zaplacone.php [--apply]
 *   DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env php ... (beta)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/stripe.php';

$apply = in_array('--apply', $argv, true);
$dnct  = dnct_db();
stripe_init();

$rows = $dnct->query(
    'SELECT id, promo_code, amount_gross, stripe_session_id FROM orders
      WHERE promo_code IS NOT NULL AND promo_code <> "" AND amount_paid IS NULL
        AND stripe_session_id IS NOT NULL AND status IN ("paid", "provisioned", "cancelled")'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    try {
        $s = \Stripe\Checkout\Session::retrieve((string)$r['stripe_session_id']);
    } catch (Throwable $e) {
        echo "#{$r['id']} błąd Stripe: {$e->getMessage()}\n";
        continue;
    }
    if (($s->payment_status ?? '') !== 'paid' || !isset($s->amount_total)) {
        echo "#{$r['id']} sesja nieopłacona - pomijam\n";
        continue;
    }
    printf("#%d %s: cennik %s zł, zapłacono %s zł%s\n", $r['id'], $r['promo_code'],
        pricing_format((int)$r['amount_gross']), pricing_format((int)$s->amount_total), $apply ? ' - zapisano' : '');
    if ($apply) {
        $dnct->prepare('UPDATE orders SET amount_paid = ? WHERE id = ? AND amount_paid IS NULL')
             ->execute([(int)$s->amount_total, (int)$r['id']]);
    }
}
echo count($rows), " zamówień z kodem bez kwoty zapłaconej", $apply ? '' : ' (podgląd, dodaj --apply)', "\n";
