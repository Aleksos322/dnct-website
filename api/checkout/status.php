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
require_once dirname(__DIR__, 2) . '/backend/lib/stripe.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

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

/* DATA WAŻNOŚCI PRZY PRZEDŁUŻENIU I ZMIANIE PAKIETU.
   Wiersz przedłużenia dostaje `expires_at = NOW()` jako znacznik "załatwione"
   (patrz webhook_extension_paid) - prawdziwy termin siedzi w zamówieniu
   NADRZĘDNYM. Bez tego klient po zapłaceniu za 30 dni czytał "opłacone do
   dzisiaj", czyli dokładnie odwrotność tego, co właśnie kupił. */
$expiresAt = $order['expires_at'];

if (in_array($order['kind'], ['extension', 'plan_change'], true) && !empty($order['extends_order_id'])) {
    $parent = order_by_id(dnct_db(), (int)$order['extends_order_id']);
    if ($parent !== null) {
        $expiresAt = $parent['expires_at'];
    }
}

/**
 * Diagnostyka WYŁĄCZNIE dla admina - klient jej nie widzi i nie potrzebuje.
 *
 * Strona sukcesu odpytuje TYLKO naszą bazę, więc "pending" samo z siebie nie
 * mówi, czy to Stripe jeszcze nie potwierdził zapłaty, czy webhook w ogóle
 * nie dotarł (np. Basic Auth na proxy blokujący Stripe'a - dokładnie ten
 * scenariusz kosztował już kilka godzin diagnozowania ręcznie przez SSH).
 * Realny `payment_status` z API Stripe'a rozstrzyga to jednoznacznie: jeśli
 * Stripe mówi "paid", a u nas nadal "pending", to nie jest kwestia czasu -
 * webhook nie dotarł i trzeba go ręcznie ponowić (reconcile-stripe.php albo
 * przycisk "spróbuj ponownie" w /zamowienia).
 */
$debug = null;
if (dnct_is_admin()) {
    $debug = [
        'order_status'    => $order['status'],
        'created_at'      => $order['created_at'],
        'updated_at'      => $order['updated_at'],
        'failure_reason'  => $order['failure_reason'] ?: null,
        'stripe_status'   => null,
        'stripe_payment'  => null,
        'diagnoza'        => null,
    ];

    try {
        stripe_init();
        $liveSession = \Stripe\Checkout\Session::retrieve($sessionId);
        $debug['stripe_status']  = (string)$liveSession->status;
        $debug['stripe_payment'] = (string)$liveSession->payment_status;
    } catch (Throwable $e) {
        $debug['stripe_error'] = $e->getMessage();
    }

    $paidAtStripe = in_array($debug['stripe_payment'] ?? '', ['paid', 'no_payment_required'], true);

    if ($order['status'] === 'pending' && $paidAtStripe) {
        $debug['diagnoza'] = 'Stripe potwierdza opłacenie, a u nas zamówienie nadal "pending" - webhook nie dotarł albo padł, zanim cokolwiek zapisał. Sprawdź dostarczanie webhooka (Basic Auth na proxy?) i/lub użyj "spróbuj ponownie" w /zamowienia.';
    } elseif ($order['status'] === 'pending') {
        $debug['diagnoza'] = 'Stripe jeszcze nie potwierdza zapłaty (status sesji: ' . ($debug['stripe_status'] ?? '?') . ') - to może być zwykłe oczekiwanie na metodę z opóźnionym potwierdzeniem (BLIK/Przelewy24).';
    } elseif ($order['status'] === 'failed') {
        $debug['diagnoza'] = 'Płatność przeszła, ale zakładanie serwera się nie powiodło - patrz failure_reason.';
    } elseif ($order['status'] === 'provisioned') {
        $debug['diagnoza'] = 'Zakończone poprawnie.';
    }
}

json_ok([
    'status'       => $order['status'],
    'kind'         => $order['kind'],
    'game_type'    => $order['game_type'],
    'period_days'  => (int)$order['period_days'],
    'billing_mode' => $order['billing_mode'],
    'amount'       => pricing_format((int)$order['amount_gross']),
    'expires_at'   => $expiresAt,
    'server_ready' => $order['status'] === 'provisioned',
    // Powód niepowodzenia pokazujemy klientowi celowo: "coś poszło nie tak"
    // zmusza go do pisania i pytania, a treść i tak trafia do administracji.
    'failed'       => $order['status'] === 'failed',
    'debug'        => $debug,
]);
