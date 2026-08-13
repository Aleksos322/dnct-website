<?php
/**
 * POST /api/checkout/extend.php - dokupienie kolejnego okresu do serwera.
 *
 * Wejście:  { order_id, period_days, csrf_token }
 * Wyjście:  { ok: true, url: "https://checkout.stripe.com/..." }
 *
 * DWA MODELE, ALE NIE ZAWSZE OBA.
 *
 * Klient może dokupić okres jednorazowo albo przejść na abonament, który będzie
 * się odnawiał sam. Abonament oferujemy WYŁĄCZNIE wtedy, gdy serwer jeszcze go
 * nie ma: druga subskrypcja obok działającej to dwie równoległe płatności za ten
 * sam czas i pytanie "za co właściwie zapłaciłem" przy pierwszym rachunku.
 * Rezygnacja z istniejącej subskrypcji idzie przez api/subscription/cancel.php.
 *
 * Parametry serwera bierzemy ze STAREGO zamówienia, nie z cennika. Klient
 * przedłuża to, co ma - nawet jeśli plan zdążył podrożeć albo zniknąć z oferty.
 * Zmiana pakietu to osobna decyzja i osobna ścieżka.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/stripe.php';

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

$email = (string)($_SESSION['authentik_email'] ?? '');
$name  = (string)($_SESSION['authentik_username'] ?? '');

if ($email === '' || $name === '') {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$dnct     = dnct_db();
$parentId = (int)request_string('order_id', '0');
$parent   = $parentId > 0 ? order_by_id($dnct, $parentId) : null;

/* Właściciela sprawdzamy ZAWSZE i tym samym komunikatem co przy braku
   zamówienia. Numery zamówień idą po kolei, więc rozróżnialna odpowiedź
   pozwoliłaby przejechać po nich pętlą i policzyć cudze zakupy. */
if ($parent === null || !hash_equals((string)$parent['user_email'], $email)) {
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}

if ($parent['status'] !== 'provisioned') {
    json_error('Ten serwer nie jest jeszcze gotowy - nie ma czego przedłużać.', 409, [], ['reason' => 'not_ready']);
}

$mode = request_string('billing_mode', 'payment');
if (!in_array($mode, ['payment', 'subscription'], true)) {
    json_error('Nieznany model rozliczenia.', 422);
}

/* Abonament tylko wtedy, gdy serwer jeszcze go nie ma - patrz nagłówek pliku.
   Sprawdzamy stan zamówienia, a nie to, co przysłał front: przycisk mógł
   zostać wyświetlony, zanim ktoś w innej karcie włączył odnawianie. */
$hasActiveSubscription = $parent['billing_mode'] === 'subscription'
    && $parent['status'] !== 'cancelled'
    && !empty($parent['stripe_subscription_id']);

if ($mode === 'subscription' && $hasActiveSubscription) {
    json_error(
        'Ten serwer już ma abonament - kolejny okres pobierze się sam.',
        409,
        [],
        ['reason' => 'already_subscribed']
    );
}

$panel = new PDO(
    'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
    (string)env('DB_USERNAME'),
    (string)env('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

try {
    $quote = pricing_quote($panel, [
        'game_type'    => (string)$parent['game_type'],
        'plan_id'      => (int)($parent['plan_id'] ?? 0),
        'slots'        => (int)($parent['slots'] ?? 0),
        'period_days'  => (int)request_string('period_days', '0'),
        'billing_mode' => $mode,
    ]);
} catch (RuntimeException $e) {
    /* Plan mógł w międzyczasie zniknąć z cennika albo wyprzedać się na węźle.
       Odmowa przedłużenia komuś, kto już tam siedzi i zajmuje miejsce, byłaby
       absurdem - wyceniamy więc po zapisanej cenie zamówienia. */
    try {
        $period = pricing_period($panel, (int)request_string('period_days', '0'));
    } catch (RuntimeException $inner) {
        json_error($inner->getMessage(), 422, [], ['reason' => 'invalid_period']);
    }

    // Cena bazowa odtworzona z pierwotnego zamówienia i JEGO okresu - inaczej
    // przedłużenie rocznego serwera na miesiąc kosztowałoby jak cały rok.
    $parentPeriod = pricing_period($panel, (int)$parent['period_days']);
    $baseMonthly  = pricing_unscale((int)$parent['amount_gross'] / 100, $parentPeriod);

    $quote = [
        'game_type'    => (string)$parent['game_type'],
        'game_name'    => (string)$parent['game_type'],
        'plan_id'      => $parent['plan_id'] !== null ? (int)$parent['plan_id'] : null,
        'slots'        => $parent['slots'] !== null ? (int)$parent['slots'] : null,
        'label'        => 'przedłużenie',
        'ram_mb'       => (int)$parent['ram_mb'],
        'cpu_cores'    => (float)$parent['cpu_cores'],
        'ssd_mb'       => (int)$parent['ssd_mb'],
        'backups_limit'=> (int)$parent['backups_limit'],
        'period_days'  => $period['days'],
        'period_label' => $period['label'],
        'billing_mode' => $mode,
        'amount_gross' => (int)round(pricing_apply_period($baseMonthly, $period) * 100),
        'currency'     => 'pln',
    ];
}

$orderId = order_create($dnct, $quote, $email, $name);
$dnct->prepare('UPDATE orders SET kind = "extension", extends_order_id = ? WHERE id = ?')
     ->execute([$parentId, $orderId]);

stripe_init();
$base = stripe_base_url();

$isSub = $mode === 'subscription';

$priceData = [
    'currency'     => $quote['currency'],
    'unit_amount'  => $quote['amount_gross'],
    'product_data' => [
        'name'        => $isSub
            ? 'DNCT Hosting - abonament serwera'
            : 'DNCT Hosting - przedłużenie serwera',
        'description' => sprintf('Zamówienie #%d · %s', $parentId, $quote['period_label']),
    ],
];

if ($isSub) {
    $priceData['recurring'] = stripe_interval_for_days((int)$quote['period_days']);
}

try {
    $params = [
        'mode'       => $isSub ? 'subscription' : 'payment',
        // Metod nie wymieniamy - bierze je Stripe z panelu i sam odsiewa te,
        // które nie umieją płatności cyklicznych. Sztywna lista wywracała
        // zakup, gdy któraś nie była aktywowana na koncie (patrz create.php).
        'line_items' => [['price_data' => $priceData, 'quantity' => 1]],
        'allow_promotion_codes' => true,
        'customer_email'        => $email,
        'client_reference_id'   => (string)$orderId,
        'metadata'              => ['order_id' => (string)$orderId, 'extends' => (string)$parentId],
        'success_url'           => $base . '/sukces?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'            => $base . '/twoje-serwery',
    ];

    if ($isSub) {
        $params['subscription_data'] = ['metadata' => ['order_id' => (string)$orderId]];
    }

    $session = \Stripe\Checkout\Session::create($params);
} catch (Throwable $e) {
    error_log('Przedłużenie: sesja nieutworzona dla zamówienia #' . $orderId . ' - ' . $e->getMessage());
    json_error('Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 503, [], ['reason' => 'stripe_error']);
}

order_attach_session($dnct, $orderId, $session->id);

json_ok(['url' => $session->url, 'order_id' => $orderId]);
