<?php
/**
 * POST /api/plan/change.php - przejście na inny pakiet.
 *
 * Wejście:  { order_id, plan_id, csrf_token }
 * Wyjście:  { ok:true, direction:"down", applied:true, bonus_days:N }   - zejście niżej
 *           { ok:true, direction:"up", url:"https://checkout.stripe..." } - dopłata
 *
 * DWIE RÓŻNE ŚCIEŻKI, bo to dwie różne operacje:
 *
 *   W DÓŁ  - nic nie kosztuje, więc wykonujemy od ręki. Przepuszczanie tego
 *            przez bramkę płatności na kwotę 0 zł byłoby absurdem.
 *   W GÓRĘ - jest dopłata, więc zmiana zasobów następuje DOPIERO po zapłacie,
 *            w webhooku. Podniesienie pakietu "na kredyt" oznaczałoby oddanie
 *            zasobów komuś, kto może nigdy nie dokończyć płatności.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/stripe.php';
require_once dirname(__DIR__, 2) . '/backend/lib/plan_change.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'checkout',
    RATE_LIMIT_CHECKOUT,
    'Zbyt wiele operacji z tego adresu IP. Odczekaj kilkanaście minut.'
);

if (!stripe_sales_enabled()) {
    json_error('Zmiana pakietu jest chwilowo wyłączona - trwa przerwa techniczna.', 503, [], ['reason' => 'sales_disabled']);
}

$email = (string)($_SESSION['authentik_email'] ?? '');
$name  = (string)($_SESSION['authentik_username'] ?? '');

if ($email === '' || $name === '') {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$dnct   = dnct_db();
$order  = order_by_id($dnct, (int)request_string('order_id', '0'));

if ($order === null || !hash_equals((string)$order['user_email'], $email)) {
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}
if ($order['status'] !== 'provisioned' || empty($order['ptero_server_id'])) {
    json_error('Ten serwer nie jest gotowy - nie ma czego zmieniać.', 409, [], ['reason' => 'not_ready']);
}
if ($order['slots'] !== null) {
    // Gry rozliczane za sloty mają własny konfigurator; mieszanie tych dwóch
    // sposobów doboru zasobów w jednym przepływie skończyłoby się zamówieniem,
    // które ma jednocześnie plan i liczbę slotów.
    json_error('Ta gra jest rozliczana za sloty - napisz do nas, przestawimy ręcznie.', 409, [], ['reason' => 'slot_based']);
}

$panel = new PDO(
    'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
    (string)env('DB_USERNAME'),
    (string)env('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

try {
    $quote = plan_change_quote($panel, $order, (int)request_string('plan_id', '0'));
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 422, [], ['reason' => 'invalid_plan']);
}

/* ---------------------------------------------------------------------------
   ZEJŚCIE NIŻEJ - od ręki, bez płatności.
   --------------------------------------------------------------------------- */
if ($quote['direction'] === 'down') {
    $applied = plan_change_apply_limits((int)$order['ptero_server_id'], $quote['plan']);

    if (!$applied['ok']) {
        error_log('Zmiana pakietu #' . $order['id'] . ': ' . $applied['error']);
        json_error('Nie udało się przestawić zasobów serwera. Napisz do nas - zrobimy to ręcznie.', 502, [], ['reason' => 'panel_error']);
    }

    plan_change_store($dnct, (int)$order['id'], $quote['plan'], (int)$quote['bonus_days']);

    $msg = 'Pakiet zmieniony na ' . $quote['plan']['name'] . '.';
    if ($quote['bonus_days'] > 0) {
        $msg .= ' Niewykorzystana różnica wróciła jako ' . $quote['bonus_days'] . ' dni.';
    }

    notify_user($dnct, $name, 'success', 'Pakiet zmieniony', $msg);

    json_ok([
        'direction'  => 'down',
        'applied'    => true,
        'bonus_days' => (int)$quote['bonus_days'],
        'message'    => $msg,
    ]);
}

/* ---------------------------------------------------------------------------
   PRZEJŚCIE WYŻEJ - najpierw dopłata, zasoby zmienia webhook.
   --------------------------------------------------------------------------- */
$amount = (int)round($quote['price'] * 100);

$orderId = order_create($dnct, [
    'game_type'     => (string)$order['game_type'],
    'plan_id'       => (int)$quote['plan']['id'],
    'slots'         => null,
    'ram_mb'        => (int)$quote['plan']['ram_mb'],
    'cpu_cores'     => (float)$quote['plan']['cpu_cores'],
    'ssd_mb'        => (int)$quote['plan']['ssd_mb'],
    'backups_limit' => (int)$quote['plan']['backups_limit'],
    'period_days'   => (int)$order['period_days'],
    'billing_mode'  => 'payment',
    'amount_gross'  => $amount,
    'currency'      => 'pln',
], $email, $name);

$dnct->prepare('UPDATE orders SET kind = "plan_change", extends_order_id = ? WHERE id = ?')
     ->execute([(int)$order['id'], $orderId]);

stripe_init();
$base = stripe_base_url();

try {
    $session = \Stripe\Checkout\Session::create([
        'mode'                 => 'payment',
        'payment_method_types' => ['card', 'blik', 'p24'],
        'line_items' => [[
            'price_data' => [
                'currency'     => 'pln',
                'unit_amount'  => $amount,
                'product_data' => [
                    'name'        => 'DNCT Hosting - zmiana pakietu na ' . $quote['plan']['name'],
                    'description' => sprintf(
                        'Dopłata za pozostałe %d%% opłaconego okresu (zamówienie #%d)',
                        (int)round($quote['ratio'] * 100),
                        (int)$order['id']
                    ),
                ],
            ],
            'quantity' => 1,
        ]],
        'allow_promotion_codes' => true,
        'customer_email'        => $email,
        'client_reference_id'   => (string)$orderId,
        'metadata'              => ['order_id' => (string)$orderId, 'changes' => (string)$order['id']],
        'success_url'           => $base . '/sukces?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'            => $base . '/twoje-serwery',
    ]);
} catch (Throwable $e) {
    error_log('Zmiana pakietu: sesja nieutworzona dla zamówienia #' . $orderId . ' - ' . $e->getMessage());
    json_error('Nie udało się rozpocząć płatności. Spróbuj ponownie za chwilę.', 502, [], ['reason' => 'stripe_error']);
}

order_attach_session($dnct, $orderId, $session->id);

json_ok(['direction' => 'up', 'url' => $session->url, 'order_id' => $orderId]);
