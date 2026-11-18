<?php
declare(strict_types=1);

/* Zachowujemy stare identyfikatory i ceny dla istniejących klientów.
   Nowa oferta ma osobne pakiety: nie zmienia limitów ani abonamentów wstecz. */
const HOSTING_PLANS = [
    'dnct-start' => ['name'=>'Start', 'price'=>900, 'sites'=>1, 'disk_gb'=>5, 'mailboxes'=>5, 'databases'=>2],
    'dnct-pro' => ['name'=>'Pro', 'price'=>1900, 'sites'=>5, 'disk_gb'=>20, 'mailboxes'=>25, 'databases'=>10],
    'dnct-biznes' => ['name'=>'Biznes', 'price'=>3900, 'sites'=>20, 'disk_gb'=>50, 'mailboxes'=>100, 'databases'=>50],
    'dnct-web-start' => ['name'=>'Start', 'price'=>999, 'sites'=>1, 'disk_gb'=>5, 'mailboxes'=>0, 'databases'=>1, 'staging'=>0],
    'dnct-web-pro' => ['name'=>'Pro', 'price'=>1999, 'sites'=>5, 'disk_gb'=>10, 'mailboxes'=>5, 'databases'=>5, 'staging'=>1],
    'dnct-web-biznes' => ['name'=>'Biznes', 'price'=>3999, 'sites'=>15, 'disk_gb'=>20, 'mailboxes'=>5, 'databases'=>10, 'staging'=>1],
];
const HOSTING_OFFER_KEYS = ['dnct-web-start', 'dnct-web-pro', 'dnct-web-biznes'];
const HOSTING_PERIOD_DAYS = 30;
const HOSTING_TRIAL_DAYS = 7;
const HOSTING_MANAGED_PRICE = 2999; // hosting w "Stronie pod opieką"; opiekun rozlicza się z klientem osobno
const HOSTING_YEAR_DAYS = 365;
const HOSTING_YEAR_PAID_MONTHS = 11; // rok = 11 miesiecy w cenie, jeden gratis

/** 'year' -> 365 dni, wszystko inne -> 30 dni. */
function hosting_period_days(string $period): int
{
    return $period === 'year' ? HOSTING_YEAR_DAYS : HOSTING_PERIOD_DAYS;
}

/** Opis okresu do tekstow: "30 dni" / "rok". */
function hosting_period_text(int $days): string
{
    return $days >= HOSTING_YEAR_DAYS ? 'rok' : '30 dni';
}

/** "co 30 dni" / "co rok". */
function hosting_period_every(int $days): string
{
    return 'co ' . hosting_period_text($days);
}

function hosting_price(string $package, string $billing, string $period = 'month'): int
{
    if (!isset(HOSTING_PLANS[$package]) || !in_array($billing, ['payment', 'subscription'], true)) {
        throw new InvalidArgumentException('Nieprawidłowy pakiet lub sposób rozliczenia.');
    }
    $base = HOSTING_PLANS[$package]['price'];
    $monthly = $billing === 'payment' && in_array($package, HOSTING_OFFER_KEYS, true)
        ? intdiv($base * 110 + 50, 100) : $base;
    return $period === 'year' ? $monthly * HOSTING_YEAR_PAID_MONTHS : $monthly;
}

/** Czysta specyfikacja Checkout, wspólna dla endpointu i testów. */
function hosting_checkout_spec(string $package, string $billing, bool $trial, string $period = 'month'): array
{
    $amount = hosting_price($package, $billing, $period);
    if ($trial && $period === 'year') {
        throw new InvalidArgumentException('Test jest dostepny tylko przy rozliczeniu miesiecznym.');
    }
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
