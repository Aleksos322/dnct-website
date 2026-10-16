<?php
declare(strict_types=1);

/* Zachowujemy stare identyfikatory i ceny dla istniejących klientów.
   Nowa oferta ma osobne pakiety: nie zmienia limitów ani abonamentów wstecz. */
const HOSTING_PLANS = [
    'dnct-start' => ['name'=>'Start', 'price'=>900, 'sites'=>1, 'disk_gb'=>5, 'mailboxes'=>5, 'databases'=>2],
    'dnct-pro' => ['name'=>'Pro', 'price'=>1900, 'sites'=>5, 'disk_gb'=>20, 'mailboxes'=>25, 'databases'=>10],
    'dnct-biznes' => ['name'=>'Biznes', 'price'=>3900, 'sites'=>20, 'disk_gb'=>50, 'mailboxes'=>100, 'databases'=>50],
    'dnct-web-start' => ['name'=>'Start', 'price'=>999, 'sites'=>1, 'disk_gb'=>5, 'mailboxes'=>0, 'databases'=>0, 'staging'=>0],
    'dnct-web-pro' => ['name'=>'Pro', 'price'=>1999, 'sites'=>5, 'disk_gb'=>10, 'mailboxes'=>5, 'databases'=>5, 'staging'=>1],
    'dnct-web-biznes' => ['name'=>'Biznes', 'price'=>3999, 'sites'=>15, 'disk_gb'=>20, 'mailboxes'=>5, 'databases'=>10, 'staging'=>1],
];
const HOSTING_OFFER_KEYS = ['dnct-web-start', 'dnct-web-pro', 'dnct-web-biznes'];
const HOSTING_PERIOD_DAYS = 30;
const HOSTING_TRIAL_DAYS = 7;
const HOSTING_MANAGED_PRICE = 5500;

function hosting_price(string $package, string $billing): int
{
    if (!isset(HOSTING_PLANS[$package]) || !in_array($billing, ['payment', 'subscription'], true)) {
        throw new InvalidArgumentException('Nieprawidłowy pakiet lub sposób rozliczenia.');
    }
    $base = HOSTING_PLANS[$package]['price'];
    return $billing === 'payment' && in_array($package, HOSTING_OFFER_KEYS, true)
        ? intdiv($base * 110 + 50, 100) : $base;
}

/** Czysta specyfikacja Checkout, wspólna dla endpointu i testów. */
function hosting_checkout_spec(string $package, string $billing, bool $trial): array
{
    $amount = hosting_price($package, $billing);
    if ($trial && ($billing !== 'subscription' || !in_array($package, HOSTING_OFFER_KEYS, true))) {
        throw new InvalidArgumentException('Test jest dostępny wyłącznie z abonamentem nowej oferty.');
    }
    $out = ['mode'=>$billing === 'subscription' ? 'subscription' : 'payment', 'amount'=>$amount];
    if ($trial) {
        $out['payment_method_types'] = ['card'];
        $out['payment_method_collection'] = 'always';
        $out['trial_period_days'] = HOSTING_TRIAL_DAYS;
        $out['missing_payment_method'] = 'cancel';
    }
    return $out;
}
