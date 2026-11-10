<?php
/**
 * POST /api/serwer/start-teraz.php - klient żąda startu usługi PRZED upływem
 * 14 dni na odstąpienie.
 *
 * Wejście:  { order_id, csrf_token }
 * Wyjście:  { ok:true, status:"provisioned"|"failed"|... }
 *
 * Zamówienie bez zgody na natychmiastowy start czeka do `service_start_at`
 * (14 dni od zakupu), a potem uruchamia je backend/tools/deferred-provision.php.
 * Ten endpoint jest ręcznym skrótem: zapisuje żądanie (immediate_service_requested_at
 * to nasz dowód wyraźnego żądania) i zakłada serwer od razu, tą samą funkcją co
 * webhook i cron, pod tym samym zamkiem MySQL - dwa kliknięcia ani kliknięcie
 * równolegle z cronem nie zrobią drugiego serwera.
 *
 * Okres opłacony liczymy od faktycznego startu, tak jak przy zakupie z natychmiastowym
 * startem: expires_at = teraz + period_days (wcześniej było start_planowany + okres).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/stripe_events.php';
require_once dirname(__DIR__, 2) . '/backend/lib/order_mail.php';

require_method('POST');
csrf_guard();
rate_limit_guard(
    'checkout',
    RATE_LIMIT_CHECKOUT,
    'Zbyt wiele operacji z tego adresu IP. Odczekaj kilkanaście minut.'
);

$email = (string)($_SESSION['authentik_email'] ?? '');
if ($email === '' || empty($_SESSION['authentik_username'])) {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$dnct    = dnct_db();
$orderId = (int)request_string('order_id', '0');
$order   = order_by_id($dnct, $orderId);

if ($order === null || !hash_equals((string)$order['user_email'], $email)) {
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}

$lock = 'dnct_order_' . $orderId;
$stmt = $dnct->prepare('SELECT GET_LOCK(?, 15)');
$stmt->execute([$lock]);
if ((int)$stmt->fetchColumn() !== 1) {
    json_error('Zamówienie jest właśnie obsługiwane. Spróbuj za chwilę.', 409, [], ['reason' => 'busy']);
}

try {
    $fresh = order_by_id($dnct, $orderId);

    if ($fresh === null
        || $fresh['status'] !== 'paid'
        || $fresh['kind'] !== 'new'
        || !empty($fresh['ptero_server_id'])
        || !empty($fresh['immediate_service_requested_at'])
        || empty($fresh['service_start_at'])
    ) {
        json_error('Ta usługa nie czeka już na uruchomienie.', 409, [], ['reason' => 'not_deferred']);
    }

    $upd = $dnct->prepare(
        'UPDATE orders
            SET immediate_service_requested_at = NOW(),
                service_start_at = NOW(),
                expires_at = DATE_ADD(NOW(), INTERVAL period_days DAY)
          WHERE id = ? AND status = "paid" AND immediate_service_requested_at IS NULL'
    );
    $upd->execute([$orderId]);
    if ($upd->rowCount() !== 1) {
        json_error('Ta usługa nie czeka już na uruchomienie.', 409, [], ['reason' => 'not_deferred']);
    }

    $fresh = order_by_id($dnct, $orderId);

    try {
        send_order_contract_confirmation_mail($fresh, true);
    } catch (Throwable $e) {
        error_log('Zamówienie #' . $orderId . ': mail z potwierdzeniem żądania startu - ' . $e->getMessage());
    }

    webhook_provision_paid_order($dnct, $fresh);
} finally {
    $dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
}

$after = order_by_id($dnct, $orderId);
json_ok(['status' => (string)($after['status'] ?? '')]);
