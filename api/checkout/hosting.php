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

$billing = request_string('billing_mode', 'subscription') === 'subscription' ? 'subscription' : 'payment';
$dnct    = dnct_db();
$current = hosting_current_order($dnct, $email);
$extends = null;
$trial = request_bool('trial');
if ($trial && ($billing !== 'subscription' || !request_bool('trial_accepted') || !request_bool('immediate_service_start'))) {
    json_error('Test wymaga abonamentu, natychmiastowego startu i zgody na cenę po 7 dniach.', 422);
}

if (request_string('extend') === '1') {
    if ($trial) { json_error('Test nie jest dostępny przy przedłużeniu.', 422); }
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
    if (!request_bool('terms_accepted')) {
        json_error('Zaakceptuj Regulamin, aby przejść do płatności.', 422, [
            'terms_accepted' => 'Akceptacja Regulaminu jest wymagana.',
        ]);
    }
    $immediateServiceStart = request_bool('immediate_service_start');
    $package = request_string('package');
    if (!in_array($package, HOSTING_OFFER_KEYS, true)) {
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
if ($extends === null && empty($immediateServiceStart) && $billing === 'subscription') {
    json_error(
        'Wybierz płatność jednorazową albo zaznacz natychmiastowe uruchomienie. Abonamentu nie uruchamiamy przed startem usługi.',
        422,
        ['billing_mode' => 'Przy uruchomieniu po 14 dniach wybierz płatność jednorazową.']
    );
}
$orderId = hosting_order_create(
    $dnct,
    $email,
    $name,
    $package,
    $billing,
    $extends,
    $extends === null ? ($immediateServiceStart ?? false) : null
);
$isSub   = $billing === 'subscription';
$spec = hosting_checkout_spec($package, $billing, $trial);
if ($trial) {
    $sub = (string)($_SESSION['authentik_sub'] ?? '');
    if ($sub === '') { json_error('Zaloguj się ponownie, aby rozpocząć test.', 401); }
    if (!hosting_trial_claim($dnct, $sub, $email, $orderId)) {
        $dnct->prepare('UPDATE hosting_orders SET status = "cancelled" WHERE id = ?')->execute([$orderId]);
        json_error('Test na tym koncie został już wykorzystany lub trwa rezerwacja zamówienia. Wróć do rozpoczętego Stripe Checkout albo spróbuj po 31 minutach.', 409);
    }
}

$priceData = [
    'currency'     => 'pln',
    'unit_amount'  => $spec['amount'],
    'product_data' => [
        'name'        => sprintf('DNCT Hosting WWW - pakiet %s%s', $plan['name'], $extends ? ' (przedłużenie)' : ''),
        'description' => sprintf('%d %s · %d GB SSD · %d skrzynek pocztowych · %d dni',
            $plan['sites'], $plan['sites'] === 1 ? 'strona' : 'stron', $plan['disk_gb'], $plan['mailboxes'], HOSTING_PERIOD_DAYS),
    ],
];
if ($isSub) {
    $priceData['recurring'] = stripe_interval_for_days(HOSTING_PERIOD_DAYS);
}

// Numer zamówienia idzie pod OSOBNYM kluczem, nie "order_id": to konto Stripe obsługuje też webhook
// sklepu SG, który po "order_id" szuka własnych zamówień i nie sprawdza produktu - numer hostingu
// mógłby trafić w cudze, nieopłacone zamówienie. Czyta go hosting_session_order_id().
$meta = ['hosting_order_id' => (string)$orderId, 'product' => 'hosting', 'trial_days' => $trial ? '7' : '0'];
if ($extends === null) {
    $meta['terms_version'] = DNCT_TERMS_VERSION;
    $meta['immediate_service_start'] = !empty($immediateServiceStart) ? 'yes' : 'no';
}

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
        if ($trial) {
            $params['payment_method_types'] = $spec['payment_method_types'];
            $params['payment_method_collection'] = $spec['payment_method_collection'];
            $params['expires_at'] = time() + 1800;
            $params['subscription_data']['trial_period_days'] = $spec['trial_period_days'];
            $params['subscription_data']['trial_settings'] = ['end_behavior'=>['missing_payment_method'=>'cancel']];
            $params['custom_text']['submit']['message'] = '7 dni bez opłat. Następnie ' . pricing_format($spec['amount']) . ' zł co 30 dni. Wyłącz odnawianie przed końcem testu na web.dnct.pl, jeśli nie chcesz płacić.';
        }
    }
    $session = \Stripe\Checkout\Session::create($params, ['idempotency_key'=>'hosting-order-' . $orderId]);
} catch (Throwable $e) {
    error_log('Checkout hostingu: sesja dla zamówienia #' . $orderId . ' nieudana - ' . $e->getMessage());
    json_error('Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 503, [], ['reason' => 'stripe_error']);
}

$dnct->prepare('UPDATE hosting_orders SET stripe_session_id = ? WHERE id = ?')->execute([$session->id, $orderId]);

json_ok(['url' => $session->url, 'order_id' => $orderId]);
