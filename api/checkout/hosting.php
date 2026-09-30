<?php
/**
 * POST /api/checkout/hosting
 *
 * Wywoływany z web.dnct.pl (cienka nakładka w WEB/web.dnct.pl/api/checkout/),
 * więc sesja i CSRF są tamtego hosta. Stripe wraca na hosting_site_url().
 *
 * Zakup albo przedłużenie hostingu WWW - odpowiednik api/checkout/create.php
 * i extend.php dla serwerów gier, z własną tabelą zamówień (hosting_orders).
 *
 * Wejście:  { package, billing_mode, extend (0|1), csrf_token }
 * Wyjście:  { ok: true, url: "https://checkout.stripe.com/..." }
 *
 * Kwota pochodzi WYŁĄCZNIE z HOSTING_PLANS. Klient przysyła, co wybrał.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/hosting_orders.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'checkout',
    RATE_LIMIT_CHECKOUT,
    'Zbyt wiele prób rozpoczęcia płatności z tego adresu IP. Odczekaj kilkanaście minut.'
);

if (!stripe_sales_enabled()) {
    json_error('Sprzedaż jest chwilowo wyłączona - trwa przerwa techniczna.', 503, [], ['reason' => 'sales_disabled']);
}
if (!hosting_sales_enabled()) {
    json_error('Sprzedaż hostingu WWW jeszcze nie ruszyła.', 503, [], ['reason' => 'hosting_disabled']);
}

$email = (string)($_SESSION['authentik_email'] ?? '');
$name  = (string)($_SESSION['authentik_username'] ?? '');
if ($email === '' || $name === '') {
    json_error('Musisz być zalogowany, żeby złożyć zamówienie.', 401, [], ['reason' => 'unauthorized']);
}

$billing = request_string('billing_mode', 'payment') === 'subscription' ? 'subscription' : 'payment';
$dnct    = dnct_db();
$current = hosting_current_order($dnct, $email);
$extends = null;

if (request_string('extend') === '1') {
    // Przedłużenie dotyczy WYŁĄCZNIE bieżącego hostingu tego klienta -
    // identyfikatora zamówienia nie bierzemy z żądania.
    if ($current === null) {
        json_error('Nie masz aktywnego hostingu do przedłużenia.', 409);
    }
    if ($current['billing_mode'] === 'subscription') {
        json_error('Abonament odnawia się sam - nie trzeba go przedłużać.', 409);
    }
    $package = (string)$current['package'];
    $extends = (int)$current['id'];
} else {
    $package = request_string('package');
    if (!isset(HOSTING_PLANS[$package])) {
        json_error('Wybierz pakiet hostingu.', 422, [], ['reason' => 'invalid_selection']);
    }
    // Drugi pakiet równolegle = podwójna opłata za to samo konto. Zmiana
    // pakietu w trakcie okresu wymaga rozliczenia różnicy - na razie ręcznie.
    if ($current !== null) {
        json_error(
            'Masz już pakiet ' . HOSTING_PLANS[$current['package']]['name'] . '. Przedłuż go albo napisz do nas, jeśli chcesz zmienić pakiet.',
            409,
            [],
            ['reason' => 'has_hosting']
        );
    }
}

$plan    = HOSTING_PLANS[$package];
$orderId = hosting_order_create($dnct, $email, $name, $package, $billing, $extends);
$isSub   = $billing === 'subscription';

$priceData = [
    'currency'     => 'pln',
    'unit_amount'  => $plan['price'],
    'product_data' => [
        'name'        => sprintf('DNCT Hosting WWW - pakiet %s%s', $plan['name'], $extends ? ' (przedłużenie)' : ''),
        'description' => sprintf('%d %s · %d GB SSD · %d skrzynek pocztowych · %d dni',
            $plan['sites'], $plan['sites'] === 1 ? 'strona' : 'stron', $plan['disk_gb'], $plan['mailboxes'], HOSTING_PERIOD_DAYS),
    ],
];
if ($isSub) {
    $priceData['recurring'] = stripe_interval_for_days(HOSTING_PERIOD_DAYS);
}

$meta = ['order_id' => (string)$orderId, 'product' => 'hosting'];

try {
    stripe_init();
    $base   = hosting_site_url();
    $params = [
        'mode'                  => $isSub ? 'subscription' : 'payment',
        'line_items'            => [['price_data' => $priceData, 'quantity' => 1]],
        'allow_promotion_codes' => true,
        'customer_email'        => $email,
        'client_reference_id'   => 'hosting-' . $orderId,
        'metadata'              => $meta,
        'success_url'           => $base . '/?oplacono=1',
        'cancel_url'            => $base . '/#pakiety',
    ];
    if ($isSub) {
        $params['subscription_data'] = ['metadata' => $meta];
    }
    $session = \Stripe\Checkout\Session::create($params);
} catch (Throwable $e) {
    error_log('Checkout hostingu: sesja dla zamówienia #' . $orderId . ' nieudana - ' . $e->getMessage());
    json_error('Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 503, [], ['reason' => 'stripe_error']);
}

$dnct->prepare('UPDATE hosting_orders SET stripe_session_id = ? WHERE id = ?')->execute([$session->id, $orderId]);

json_ok(['url' => $session->url, 'order_id' => $orderId]);
