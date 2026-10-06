<?php
/**
 * POST /api/checkout/create
 *
 * Zakłada zamówienie i sesję Stripe Checkout, po czym oddaje adres, pod który
 * front ma przekierować przeglądarkę.
 *
 * Wejście:  { game_type, plan_id | slots, period_days, billing_mode,
 *             terms_accepted, immediate_service_start, csrf_token }
 * Wyjście:  { ok: true, url: "https://checkout.stripe.com/..." }
 *
 * CZEGO TU NIE MA, I TO CELOWO: kwoty. Klient przysyła wyłącznie to, CO wybrał;
 * ile to kosztuje, liczy backend/lib/pricing.php z bazy. Cena z formularza
 * oznaczałaby serwer za grosz po jednej podmianie pola w narzędziach
 * deweloperskich.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/stripe.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'checkout',
    RATE_LIMIT_CHECKOUT,
    'Zbyt wiele prób rozpoczęcia płatności z tego adresu IP. Odczekaj kilkanaście minut.'
);

/* Sprzedaż wyłączona to nie awaria - to stan, w którym strona jest dzisiaj
   (przerwa techniczna). Odpowiadamy wprost, zamiast udawać błąd. */
if (!stripe_sales_enabled()) {
    json_error('Sprzedaż jest chwilowo wyłączona - trwa przerwa techniczna.', 503, [], ['reason' => 'sales_disabled']);
}

$email = (string)($_SESSION['authentik_email'] ?? '');
$name  = (string)($_SESSION['authentik_username'] ?? '');

if ($email === '' || $name === '') {
    json_error('Musisz być zalogowany, żeby złożyć zamówienie.', 401, [], ['reason' => 'unauthorized']);
}

/* Akceptacja Regulaminu i żądanie natychmiastowego startu są osobnymi
   decyzjami. Brak drugiej NIE blokuje zakupu: usługa zostaje wtedy opłacona,
   ale uruchomiona dopiero po ustawowym terminie odstąpienia. */
if (!request_bool('terms_accepted')) {
    json_error('Zaakceptuj Regulamin, aby przejść do płatności.', 422, [
        'terms_accepted' => 'Akceptacja Regulaminu jest wymagana.',
    ]);
}
$immediateServiceStart = request_bool('immediate_service_start');

$panel = new PDO(
    'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
    (string)env('DB_USERNAME'),
    (string)env('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

try {
    $quote = pricing_quote($panel, [
        'game_type'    => request_string('game_type'),
        'plan_id'      => (int)request_string('plan_id', '0'),
        'slots'        => (int)request_string('slots', '0'),
        'period_days'  => (int)request_string('period_days', '0'),
        'billing_mode' => request_string('billing_mode', 'payment'),
    ], dnct_is_admin());
} catch (RuntimeException $e) {
    // Komunikaty z wyceny są pisane pod klienta ("Ten plan jest już
    // niedostępny"), więc idą wprost - nie ma tu nic wrażliwego.
    json_error($e->getMessage(), 422, [], ['reason' => 'invalid_selection']);
}

if (!$immediateServiceStart && $quote['billing_mode'] === 'subscription') {
    json_error(
        'Wybierz płatność jednorazową albo zaznacz natychmiastowe uruchomienie. Abonamentu nie uruchamiamy przed startem usługi.',
        422,
        ['billing_mode' => 'Przy uruchomieniu po 14 dniach wybierz płatność jednorazową.']
    );
}

$dnct    = dnct_db();
$orderId = order_create($dnct, $quote, $email, $name, $immediateServiceStart);

stripe_init();

/* METOD PŁATNOŚCI NIE WYMIENIAMY - WYBIERA JE STRIPE.
   Sztywna lista `['card','blik','p24']` wywracała cały zakup: konto
   produkcyjne nie miało włączonych Przelewów24, więc Stripe odrzucał sesję
   ("payment method type provided: p24 is invalid"), a front pokazywał
   klientowi "Brak połączenia z serwerem".
   Bez tego pola Stripe bierze metody włączone w panelu i sam odsiewa te,
   które nie pasują do trybu - przy abonamencie znikają BLIK i Przelewy24,
   bo nie umieją płatności cyklicznych. Włączenie nowej metody to odtąd
   zmiana w panelu Stripe, nie w kodzie. */
$isSubscription = $quote['billing_mode'] === 'subscription';

$productName = sprintf('DNCT Hosting - %s (%s)', $quote['game_name'], $quote['label']);
$productDesc = sprintf(
    '%d MB RAM · %s vCore · %d MB SSD · %s',
    $quote['ram_mb'],
    rtrim(rtrim(number_format($quote['cpu_cores'], 1, '.', ''), '0'), '.'),
    $quote['ssd_mb'],
    $quote['period_label']
);

$priceData = [
    'currency'     => $quote['currency'],
    'unit_amount'  => $quote['amount_gross'],
    'product_data' => ['name' => $productName, 'description' => $productDesc],
];

if ($isSubscription) {
    $priceData['recurring'] = stripe_interval_for_days($quote['period_days']);
}

$base = stripe_base_url();

try {
    $params = [
        'mode'       => $isSubscription ? 'subscription' : 'payment',
        'line_items' => [['price_data' => $priceData, 'quantity' => 1]],

        // Kupony obsługuje Stripe. Włączenie tego pola daje w Checkoucie pole
        // "kod rabatowy", a poprawność kodu, limity i termin ważności sprawdza
        // Stripe - my nie liczymy zniżek u siebie (patrz migracja 001).
        'allow_promotion_codes' => true,

        'customer_email'      => $email,
        'client_reference_id' => (string)$orderId,
        'metadata'            => [
            'order_id' => (string)$orderId,
            'terms_version' => DNCT_TERMS_VERSION,
            'immediate_service_start' => $immediateServiceStart ? 'yes' : 'no',
        ],

        'success_url' => $base . '/sukces?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $base . '/cennik',
    ];

    /* Metadane trafiają TAKŻE na subskrypcję. Zdarzenia `invoice.*` przychodzą
       z identyfikatorem subskrypcji, nie sesji - bez tego przy pierwszym
       odnowieniu nie byłoby po czym odnaleźć zamówienia, gdyby zapis
       identyfikatora przy checkout.session.completed się nie powiódł. */
    if ($isSubscription) {
        $params['subscription_data'] = ['metadata' => ['order_id' => (string)$orderId]];
    }

    $session = \Stripe\Checkout\Session::create($params);
} catch (Throwable $e) {
    error_log('Checkout: nie udało się utworzyć sesji dla zamówienia #' . $orderId . ' - ' . $e->getMessage());
    /* 503, NIGDY 502. Cloudflare traktuje 502 jako wlasny blad bramy i podmienia
       tresc odpowiedzi na "error code: 502". Front dostaje wtedy cos, co nie jest
       JSON-em, i zamiast prawdziwego powodu pokazuje "Brak polaczenia z serwerem"
       - czyli odsyla klienta do sprawdzania wlasnego internetu, choc problem jest
       po naszej stronie. Sprawdzone: 403, 500 i 503 przechodza nietkniete. */
    json_error('Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 503, [], ['reason' => 'stripe_error']);
}

order_attach_session($dnct, $orderId, $session->id);

json_ok(['url' => $session->url, 'order_id' => $orderId]);
