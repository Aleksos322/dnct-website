<?php
/**
 * Test regresji: sesja Stripe za hosting niesie numer zamówienia pod "hosting_order_id", a nie "order_id"
 * (ten klucz czyta webhook sklepu SG na tym samym koncie Stripe i szukałby po nim cudzych zamówień).
 * Woła prawdziwy endpoint POST /api/checkout/hosting jako zalogowany klient i sprawdza metadane sesji.
 * Tylko beta: klucz sk_test_, baza bety. Nic nie jest płatne; po teście sesja jest wygaszana, a zamówienie usuwane.
 *
 * Uruchom: DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env php backend/tests/hosting_metadata_key.php "$(pwd)"
 */
$root = rtrim($argv[1] ?? '', '/');
if ($root === '' || !is_file($root . '/api/checkout/hosting.php')) {
    fwrite(STDERR, "Podaj katalog kodu strony.\n");
    exit(2);
}
if (getenv('DNCT_ENV_FILE') !== '/var/www/dnct.pl/config/beta.env') {
    fwrite(STDERR, "Odmawiam: test tylko z DNCT_ENV_FILE=beta.env (inaczej ruszylby produkcyjny Stripe).\n");
    exit(2);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'beta.dnct.pl';
$_SERVER['REQUEST_URI'] = '/api/checkout/hosting';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

ob_start();
require $root . '/backend/bootstrap.php';
require_once $root . '/backend/lib/hosting_orders.php';
ob_end_clean();

if (!str_starts_with((string)env('STRIPE_SECRET_KEY', ''), 'sk_test_')) {
    fwrite(STDERR, "Odmawiam: klucz nie jest testowy.\n");
    exit(2);
}

// TEST_PARTNER_KOD=1: dodatkowo kod partnera (tymczasowy partner w bazie bety) - sprawdza promo_code i metadata.partner_kod.
$kodTest = '';
if (getenv('TEST_PARTNER_KOD') === '1') {
    $kodTest = 'E2EKOD' . strtoupper(bin2hex(random_bytes(3)));
    dnct_db()->prepare('INSERT INTO partnerzy (nazwa, kod, prowizja_procent, okres_miesiecy) VALUES (?, ?, 20, 0)')->execute(['TEST e2e kod', $kodTest]);
}

$email = 'e2e-test+' . bin2hex(random_bytes(3)) . '@dnct.invalid';
$_SESSION['authentik_email'] = $email;
$_SESSION['authentik_username'] = 'e2etest';
$_POST = [
    'csrf_token' => csrf_token(),
    'package' => 'dnct-web-start',
    'billing_mode' => 'payment',
    'terms_accepted' => '1',
    'immediate_service_start' => '1',
];
if ($kodTest !== '') {
    $_POST['promo_code'] = strtolower($kodTest);
}

$fail = 0;
$check = function (string $what, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK    ' : 'BLAD  '), $what, $extra !== '' ? "  ($extra)" : '', "\n";
    if (!$ok) {
        $fail++;
    }
};

register_shutdown_function(function () use ($check, &$fail, $email, $kodTest) {
    $out = (string)ob_get_clean();
    $res = json_decode($out, true);
    if (!is_array($res) || empty($res['ok'])) {
        echo "ENDPOINT ZWROCIL BLAD: ", substr($out, 0, 300), "\n";
        exit(1);
    }
    $orderId = (int)$res['order_id'];
    preg_match('#(cs_test_[A-Za-z0-9]+)#', (string)$res['url'], $m);
    $sid = $m[1] ?? '';
    $check('endpoint utworzyl sesje Stripe (tryb testowy)', $sid !== '', $sid);
    $dnct = dnct_db();
    try {
        $s = \Stripe\Checkout\Session::retrieve($sid);
        $md = $s->metadata;
        $check('metadata.hosting_order_id == numer zamowienia', (string)($md->hosting_order_id ?? '') === (string)$orderId, (string)($md->hosting_order_id ?? '-'));
        $check('metadata.product == hosting', (string)($md->product ?? '') === 'hosting');
        $check('brak metadata.order_id (webhook SG nie ma po czym szukac)', !isset($md->order_id) && empty($md->order_id));
        $check('hosting_session_order_id() odczytuje nowy klucz', hosting_session_order_id($s) === $orderId);
        $check('hosting_is_hosting_session() rozpoznaje sesje', hosting_is_hosting_session($s));
        $legacy = (object)['metadata' => (object)['order_id' => '7', 'product' => 'hosting']];
        $check('fallback: stara sesja z order_id=7 dalej dziala', hosting_session_order_id($legacy) === 7);
        $none = (object)['metadata' => (object)['product' => 'hosting']];
        $check('brak numeru -> 0', hosting_session_order_id($none) === 0);
        $row = hosting_order_by($dnct, 'id', $orderId);
        $check('zamowienie w bazie bety, status pending', $row !== null && $row['status'] === 'pending', $row['status'] ?? 'brak');
        $check('kwota jednorazowa Start = 10,99 zl', (int)($row['amount_gross'] ?? 0) === 1099, (string)($row['amount_gross'] ?? ''));
        if ($kodTest !== '') {
            $check('metadata.partner_kod == kod partnera', (string)($md->partner_kod ?? '') === $kodTest, (string)($md->partner_kod ?? '-'));
            $check('hosting_orders.promo_code zapisany (wielkie litery)', ($row['promo_code'] ?? '') === $kodTest, (string)($row['promo_code'] ?? '-'));
        } else {
            $check('bez kodu: brak metadata.partner_kod', !isset($md->partner_kod));
        }
        try { \Stripe\Checkout\Session::retrieve($sid)->expire(); echo "sprzatanie: sesja testowa wygaszona\n"; } catch (Throwable $e) { echo "sprzatanie sesji: ", $e->getMessage(), "\n"; }
    } catch (Throwable $e) {
        $check('retrieve sesji', false, $e->getMessage());
    }
    $del = $dnct->prepare('DELETE FROM hosting_orders WHERE id = ? AND user_email = ?');
    $del->execute([$orderId, $email]);
    echo "sprzatanie: usunieto wierszy z bety: ", $del->rowCount(), "\n";
    if ($kodTest !== '') {
        $dnct->prepare('DELETE FROM partnerzy WHERE kod = ?')->execute([$kodTest]);
        echo "sprzatanie: usunieto tymczasowego partnera\n";
    }
    echo $fail === 0 ? "\nWSZYSTKO OK\n" : "\nBLEDOW: $fail\n";
    exit($fail === 0 ? 0 : 1);
});

ob_start();
require $root . '/api/checkout/hosting.php';
