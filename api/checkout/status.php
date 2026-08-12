<?php
/**
 * GET /api/checkout/status.php?session_id=cs_...
 *
 * Stan zamówienia dla strony powrotu. Serwer powstaje w webhooku, a ten
 * przychodzi WŁASNYM kanałem - klient wraca z Checkoutu zwykle szybciej, niż
 * Stripe zdąży doręczyć zdarzenie. Strona sukcesu odpytuje więc ten endpoint,
 * zamiast cokolwiek tworzyć.
 *
 * Zwraca WYŁĄCZNIE zamówienie należące do zalogowanej osoby - identyfikator
 * sesji Stripe'a bywa w historii przeglądarki i w logach, więc sam w sobie nie
 * jest tajemnicą i nie może wystarczać do odczytania cudzego zamówienia.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

require_method('GET');

$email = (string)($_SESSION['authentik_email'] ?? '');
if ($email === '') {
    json_error('Musisz być zalogowany.', 401, [], ['reason' => 'unauthorized']);
}

$sessionId = trim((string)($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    json_error('Brak identyfikatora sesji płatności.', 422);
}

$order = order_by_session(dnct_db(), $sessionId);

if ($order === null || !hash_equals((string)$order['user_email'], $email)) {
    // Ten sam komunikat w obu przypadkach: "nie ma takiego zamówienia" i "jest,
    // ale cudze" muszą wyglądać identycznie, inaczej różnica odpowiedzi
    // potwierdza istnienie zamówienia.
    json_error('Nie znaleziono zamówienia.', 404, [], ['reason' => 'not_found']);
}

json_ok([
    'status'       => $order['status'],
    'game_type'    => $order['game_type'],
    'period_days'  => (int)$order['period_days'],
    'billing_mode' => $order['billing_mode'],
    'amount'       => pricing_format((int)$order['amount_gross']),
    'expires_at'   => $order['expires_at'],
    'server_ready' => $order['status'] === 'provisioned',
    // Powód niepowodzenia pokazujemy klientowi celowo: "coś poszło nie tak"
    // zmusza go do pisania i pytania, a treść i tak trafia do administracji.
    'failed'       => $order['status'] === 'failed',
]);
