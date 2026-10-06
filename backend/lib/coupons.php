<?php
/**
 * Kupony promocyjne.
 *
 * ŹRÓDŁEM PRAWDY JEST STRIPE. Zniżkę nalicza Checkout, kod sprawdza Stripe,
 * limity i termin ważności pilnuje Stripe. Nasza tabela `coupons` to odbicie,
 * dzięki któremu lista w panelu nie wymaga odpytywania API przy każdym
 * odświeżeniu strony.
 *
 * Alternatywa - własne liczenie rabatu i podawanie Stripe'owi gotowej,
 * obniżonej kwoty - wygląda prościej i jest gorsza pod każdym względem:
 * pomyłka w mnożeniu staje się realną stratą, limit użyć trzeba by pilnować
 * samemu z ryzykiem wyścigu przy dwóch równoczesnych zakupach, a Checkout
 * i tak nie pokazałby klientowi, ile zaoszczędził.
 *
 * DWA OBIEKTY NA JEDEN KUPON. Stripe rozdziela:
 *   - Coupon         - sama zniżka (ile procent / ile złotych, jak długo),
 *   - PromotionCode  - kod, który klient wpisuje, wraz z limitami użycia.
 * Robimy oba naraz, bo osobno nie mają sensu dla nikogo poza Stripe'em.
 *
 * WYJĄTEK: kupon "na start" (first_order_only). Stripe nie umie sam sprawdzić
 * "pierwszy zakup w DNCT" (jego restrictions.first_time_transaction patrzy na
 * klienta Stripe'a, a my zakładamy nowego przy każdej sesji). Taki kupon NIE
 * ma PromotionCode - w Checkoucie nie da się go wpisać. Klient podaje kod
 * w naszym formularzu zamówienia, my sprawdzamy historię zakupów
 * (coupon_for_checkout) i dopiero wtedy doklejamy zniżkę do sesji.
 * Limit użyć i termin pilnuje dalej Stripe - na samym Couponie.
 */

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/db.php';

/** Kody piszemy wielkimi literami - Stripe rozróżnia wielkość znaków. */
function coupon_normalize_code(string $code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?? '');
}

/**
 * Zakłada kupon w Stripe i zapisuje odbicie u nas.
 *
 * @param array{code:string,percent_off?:float,amount_off?:int,duration:string,
 *              duration_months?:int,max_redemptions?:int,redeem_by?:string,
 *              first_order_only?:bool,partner_id?:int} $in
 * @return array{ok:bool,error?:string,code?:string}
 */
function coupon_create(PDO $dnct, array $in, string $createdBy): array
{
    $code = coupon_normalize_code((string)($in['code'] ?? ''));

    if (strlen($code) < 3) {
        return ['ok' => false, 'error' => 'Kod musi mieć co najmniej 3 znaki (litery, cyfry, - i _).'];
    }

    $percent = isset($in['percent_off']) && $in['percent_off'] !== '' ? (float)$in['percent_off'] : null;
    $amount  = isset($in['amount_off'])  && $in['amount_off']  !== '' ? (int)round((float)$in['amount_off'] * 100) : null;

    /* Dokładnie jedna forma zniżki. Stripe odrzuca kupon z obiema naraz,
       ale komunikat z API jest po angielsku i mówi o polach, których nie ma
       w naszym formularzu - lepiej powiedzieć to po swojemu i od razu. */
    if (($percent === null) === ($amount === null)) {
        return ['ok' => false, 'error' => 'Podaj albo procent, albo kwotę zniżki - dokładnie jedno z dwóch.'];
    }
    if ($percent !== null && ($percent <= 0 || $percent > 100)) {
        return ['ok' => false, 'error' => 'Procent zniżki musi mieścić się w zakresie 1–100.'];
    }
    if ($amount !== null && $amount <= 0) {
        return ['ok' => false, 'error' => 'Kwota zniżki musi być dodatnia.'];
    }

    $duration = (string)($in['duration'] ?? 'once');
    if (!in_array($duration, ['once', 'repeating', 'forever'], true)) {
        return ['ok' => false, 'error' => 'Nieznany czas obowiązywania.'];
    }

    $months = $duration === 'repeating' ? max(1, (int)($in['duration_months'] ?? 1)) : null;
    $firstOnly = !empty($in['first_order_only']);
    $partnerId = !empty($in['partner_id']) ? (int)$in['partner_id'] : null;
    $maxUse = !empty($in['max_redemptions']) ? max(1, (int)$in['max_redemptions']) : null;

    $redeemBy = null;
    if (!empty($in['redeem_by'])) {
        $ts = strtotime((string)$in['redeem_by']);
        if ($ts === false) {
            return ['ok' => false, 'error' => 'Nie rozumiem daty ważności.'];
        }
        if ($ts <= time()) {
            return ['ok' => false, 'error' => 'Data ważności musi być w przyszłości.'];
        }
        $redeemBy = $ts;
    }

    // Kod zajęty u nas sprawdzamy PRZED wołaniem Stripe'a - inaczej zostawałby
    // tam osierocony kupon po każdej nieudanej próbie.
    $exists = $dnct->prepare('SELECT 1 FROM coupons WHERE code = ?');
    $exists->execute([$code]);
    if ($exists->fetchColumn()) {
        return ['ok' => false, 'error' => 'Kupon o takim kodzie już istnieje.'];
    }

    try {
        stripe_init();

        $couponParams = [
            'duration' => $duration,
            'name'     => $code,
        ];
        if ($percent !== null) {
            $couponParams['percent_off'] = $percent;
        } else {
            $couponParams['amount_off'] = $amount;
            $couponParams['currency']   = 'pln';
        }
        if ($months !== null) {
            $couponParams['duration_in_months'] = $months;
        }

        $promo = null;
        if ($firstOnly) {
            // Limity na samym Couponie - PromotionCode go nie owija (patrz nagłówek).
            if ($maxUse !== null)   { $couponParams['max_redemptions'] = $maxUse; }
            if ($redeemBy !== null) { $couponParams['redeem_by'] = $redeemBy; }
            $coupon = \Stripe\Coupon::create($couponParams);
        } else {
            $coupon = \Stripe\Coupon::create($couponParams);

            $promoParams = ['coupon' => $coupon->id, 'code' => $code];
            if ($maxUse !== null)   { $promoParams['max_redemptions'] = $maxUse; }
            if ($redeemBy !== null) { $promoParams['expires_at'] = $redeemBy; }

            $promo = \Stripe\PromotionCode::create($promoParams);
        }
    } catch (Throwable $e) {
        error_log('Kupony: Stripe odmówił - ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Stripe odrzucił kupon: ' . $e->getMessage()];
    }

    $stmt = $dnct->prepare(
        'INSERT INTO coupons
            (code, stripe_coupon_id, stripe_promo_id, percent_off, amount_off, currency,
             duration, duration_months, max_redemptions, redeem_by, active, first_order_only, partner_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
    );
    $stmt->execute([
        $code,
        $coupon->id,
        $promo?->id,
        $percent,
        $amount,
        $amount !== null ? 'pln' : null,
        $duration,
        $months,
        $maxUse,
        $redeemBy !== null ? date('Y-m-d H:i:s', $redeemBy) : null,
        $firstOnly ? 1 : 0,
        $partnerId,
        $createdBy,
    ]);

    return ['ok' => true, 'code' => $code];
}

/** Lista kuponów, od najnowszego. */
function coupons_list(PDO $dnct): array
{
    return $dnct->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Odświeża licznik użyć ze Stripe'a.
 *
 * Licznik rośnie po ICH stronie, przy każdym udanym zakupie z kodem, i nie ma
 * zdarzenia webhooka, które mówiłoby wprost "kod użyty". Zamiast zgadywać
 * z zamówień, pytamy o prawdę tam, gdzie ona jest - przy otwarciu listy.
 */
function coupons_sync(PDO $dnct): void
{
    $rows = $dnct->query('SELECT id, stripe_coupon_id, stripe_promo_id FROM coupons WHERE active = 1')->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return;
    }

    try {
        stripe_init();
    } catch (Throwable $e) {
        return;
    }

    $update = $dnct->prepare('UPDATE coupons SET times_redeemed = ?, active = ? WHERE id = ?');

    foreach ($rows as $row) {
        try {
            if ($row['stripe_promo_id'] === null) {
                // Kupon "na start": licznik i ważność są na samym Couponie.
                $c = \Stripe\Coupon::retrieve((string)$row['stripe_coupon_id']);
                $update->execute([(int)$c->times_redeemed, $c->valid ? 1 : 0, (int)$row['id']]);
                continue;
            }
            $promo = \Stripe\PromotionCode::retrieve((string)$row['stripe_promo_id']);
            $update->execute([(int)$promo->times_redeemed, $promo->active ? 1 : 0, (int)$row['id']]);
        } catch (Throwable $e) {
            // Kupon skasowany ręcznie w panelu Stripe'a albo chwilowy błąd API.
            // Jedno i drugie nie może wywrócić listy pozostałych kuponów.
            error_log('Kupony: nie odświeżono ' . ($row['stripe_promo_id'] ?? $row['stripe_coupon_id']) . ' - ' . $e->getMessage());
        }
    }
}

/**
 * Wyłącza kupon.
 *
 * Wyłączamy KOD (PromotionCode), nie kasujemy zniżki. Skasowanie kuponu
 * w Stripe zabiera zniżkę także trwającym abonamentom, które ją mają
 * przypisaną - czyli podniosłoby rachunek ludziom, którzy nic nie zrobili.
 */
function coupon_deactivate(PDO $dnct, int $id): array
{
    $stmt = $dnct->prepare('SELECT stripe_promo_id FROM coupons WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'error' => 'Nie ma takiego kuponu.'];
    }

    // Kupon "na start" nie ma kodu w Stripe - wystarczy wyłączyć go u nas,
    // bo tylko nasz formularz zamówienia umie go doczepić do sesji.
    if ($row['stripe_promo_id'] !== null) {
        try {
            stripe_init();
            \Stripe\PromotionCode::update((string)$row['stripe_promo_id'], ['active' => false]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Stripe odmówił: ' . $e->getMessage()];
        }
    }

    $dnct->prepare('UPDATE coupons SET active = 0 WHERE id = ?')->execute([$id]);

    return ['ok' => true];
}

/** Czytelny opis zniżki na listę. */
function coupon_describe(array $c): string
{
    $what = $c['percent_off'] !== null
        ? rtrim(rtrim(number_format((float)$c['percent_off'], 2, ',', ''), '0'), ',') . '%'
        : pricing_format((int)$c['amount_off']) . ' zł';

    $how = match ($c['duration']) {
        'forever'   => 'na zawsze',
        'repeating' => 'przez ' . (int)$c['duration_months'] . ' mies.',
        default     => 'jednorazowo',
    };

    return $what . ' - ' . $how;
}

/**
 * Czy ten e-mail kiedykolwiek za coś zapłacił (gry albo hosting WWW).
 * `expires_at` ustawia dopiero opłata, więc łapie też zamówienia, które
 * później zostały anulowane albo skasowane po wygaśnięciu.
 */
function customer_has_purchase(PDO $dnct, string $email): bool
{
    foreach (['orders', 'hosting_orders'] as $t) {
        $st = $dnct->prepare("SELECT 1 FROM {$t} WHERE user_email = ?
                                AND (status IN ('paid', 'provisioned') OR expires_at IS NOT NULL) LIMIT 1");
        $st->execute([$email]);
        if ($st->fetchColumn()) {
            return true;
        }
    }
    return false;
}

/**
 * Kod z formularza zamówienia -> zniżka do sesji Checkout.
 * Limity użyć i daty sprawdzi jeszcze Stripe; tu odsiewamy to, czego on nie
 * wie: wyłączony kupon i "tylko pierwszy zakup".
 *
 * @return array{ok:bool,error?:string,code?:string,discount?:array<string,string>}
 */
function coupon_for_checkout(PDO $dnct, string $rawCode, string $email): array
{
    $code = coupon_normalize_code($rawCode);
    $st = $dnct->prepare('SELECT * FROM coupons WHERE code = ?');
    $st->execute([$code]);
    $c = $st->fetch(PDO::FETCH_ASSOC);

    if (!$c || (int)$c['active'] !== 1 || ($c['redeem_by'] !== null && strtotime((string)$c['redeem_by']) <= time())) {
        return ['ok' => false, 'error' => 'Ten kod nie istnieje albo już wygasł.'];
    }
    if ($c['max_redemptions'] !== null && (int)$c['times_redeemed'] >= (int)$c['max_redemptions']) {
        return ['ok' => false, 'error' => 'Ten kod został już wykorzystany.'];
    }
    if ((int)$c['first_order_only'] === 1 && customer_has_purchase($dnct, $email)) {
        return ['ok' => false, 'error' => 'Ten kod działa tylko przy pierwszym zakupie.'];
    }

    return [
        'ok'       => true,
        'code'     => $code,
        'discount' => $c['stripe_promo_id'] !== null
            ? ['promotion_code' => (string)$c['stripe_promo_id']]
            : ['coupon' => (string)$c['stripe_coupon_id']],
    ];
}
