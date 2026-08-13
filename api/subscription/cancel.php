<?php
/**
 * POST /api/subscription/cancel.php - rezygnacja z odnawiania.
 *
 * Wejście:  { order_id, csrf_token }
 * Wyjście:  { ok:true, works_until: "2026-09-11 17:20:00" }
 *
 * ANULUJEMY NA KONIEC OPŁACONEGO OKRESU, nie natychmiast.
 *
 * Stripe potrafi jedno i drugie. Natychmiastowe zakończenie zabrałoby klientowi
 * czas, za który już zapłacił - i wymagałoby zwrotu części kwoty, żeby było
 * uczciwe. `cancel_at_period_end` daje ten sam efekt bez zabierania czegokolwiek:
 * serwer działa do końca, a kolejne obciążenie się nie odbędzie.
 *
 * Serwera NIE zawieszamy tutaj. Zajmie się nim proces od dat
 * (backend/tools/suspend-expired.php), gdy termin faktycznie minie.
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
    'Zbyt wiele operacji z tego adresu IP. Odczekaj kilkanaście minut.'
);

$email = (string)($_SESSION['authentik_email'] ?? '');
$name  = (string)($_SESSION['authentik_username'] ?? '');

if ($email === '' || $name === '') {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$dnct  = dnct_db();
$order = order_by_id($dnct, (int)request_string('order_id', '0'));

if ($order === null || !hash_equals((string)$order['user_email'], $email)) {
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}

if ($order['billing_mode'] !== 'subscription' || empty($order['stripe_subscription_id'])) {
    json_error('To zamówienie nie jest abonamentem - nie ma z czego rezygnować.', 409, [], ['reason' => 'not_subscription']);
}

if ($order['status'] === 'cancelled') {
    // Powtórne kliknięcie nie jest błędem - odpowiadamy tak, jakby się udało.
    json_ok(['works_until' => $order['expires_at'], 'already' => true]);
}

try {
    stripe_init();
    \Stripe\Subscription::update((string)$order['stripe_subscription_id'], [
        'cancel_at_period_end' => true,
    ]);
} catch (Throwable $e) {
    error_log('Rezygnacja z abonamentu #' . $order['id'] . ' - ' . $e->getMessage());
    json_error('Nie udało się zapisać rezygnacji. Spróbuj ponownie albo napisz do nas.', 503, [], ['reason' => 'stripe_error']);
}

/* Stan zmieniamy OD RAZU, nie czekając na zdarzenie
   `customer.subscription.deleted` - to przyjdzie dopiero z końcem okresu,
   czyli za tydzień albo za rok. Klient ma zobaczyć skutek swojego kliknięcia
   teraz, a nie po odświeżeniu za miesiąc. */
order_cancel_renewals($dnct, (int)$order['id']);

notify_user(
    $dnct,
    $name,
    'info',
    'Abonament nie będzie odnawiany',
    'Serwer działa do końca opłaconego okresu. Możesz wznowić w każdej chwili - wystarczy przedłużyć.'
);

json_ok(['works_until' => $order['expires_at']]);
