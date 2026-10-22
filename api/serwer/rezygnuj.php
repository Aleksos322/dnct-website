<?php
/**
 * POST /api/serwer/rezygnuj.php - klient rezygnuje z WYGASŁEGO serwera.
 *
 * Wejście:  { order_id, csrf_token }
 * Wyjście:  { ok:true }
 *
 * Ustawia orders.hidden_at: zamówienie znika z /twoje-serwery. Niczego nie
 * kasujemy i nie przyspieszamy - cykl życia (backend/tools/lifecycle.php)
 * robi archiwum, zawieszenie i usunięcie tak samo jak bez rezygnacji, bo
 * skasowanie bez kopii to dane klienta nie do odzyskania.
 *
 * Tylko serwer PO TERMINIE i tylko własny. Działającego nie da się tu
 * "schować" - zniknąłby z listy, a dalej by działał i się odnawiał.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

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

$dnct  = dnct_db();
$order = order_by_id($dnct, (int)request_string('order_id', '0'));

if ($order === null || !hash_equals((string)$order['user_email'], $email)) {
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}

if (!empty($order['hidden_at'])) {
    json_ok(['already' => true]);
}

$poTerminie = $order['expires_at'] !== null && strtotime((string)$order['expires_at']) < time();
if (!$poTerminie || $order['status'] === 'failed') {
    json_error('Zrezygnować można tylko z serwera po terminie.', 409, [], ['reason' => 'not_expired']);
}

$dnct->prepare('UPDATE orders SET hidden_at = NOW() WHERE id = ? AND hidden_at IS NULL')
     ->execute([(int)$order['id']]);

json_ok();
