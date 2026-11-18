<?php
/**
 * Program partnerski dla twórców.
 *
 * Zasada: widz wpisuje kod twórcy przy PIERWSZYM zakupie (kupon "na start",
 * backend/lib/coupons.php), dostaje zniżkę, a od tej chwili przez
 * `okres_miesiecy` (domyślnie 6) każda jego płatność - zakup, przedłużenie,
 * odnowienie abonamentu - nalicza twórcy `prowizja_procent` (domyślnie 15%)
 * od kwoty, którą klient faktycznie zapłacił.
 *
 * Tabele (migracja 018_partnerzy.sql):
 *   partnerzy         - twórcy,
 *   partner_klienci   - który klient należy do którego twórcy i do kiedy,
 *   partner_prowizje  - naliczenia, jedno na płatność (UNIQUE stripe_ref),
 *   partner_zgloszenia- formularz z /partnerzy.
 *
 * Wszystko tu jest DODATKIEM do obsługi płatności. Błąd przy prowizji nie
 * może cofnąć serwera ani kazać Stripe'owi ponawiać zdarzenia - stąd
 * partner_po_* łapią każdy wyjątek, logują i zgłaszają adminom.
 *
 * Idempotencja: stripe_ref = faktura (abonament) albo payment_intent
 * (jednorazowa). Pierwsza faktura abonamentu przychodzi i jako sesja,
 * i jako invoice.paid - wspólny identyfikator faktury nalicza ją raz.
 */

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/notifications.php';

/**
 * Stawka VAT (w %) odejmowana przy liczeniu prowizji netto. 0 = sprzedawca
 * nie jest płatnikiem VAT, więc netto = brutto. Po rejestracji VAT wystarczy
 * ustawić VAT_RATE_PROCENT w .env - kod nie wymaga zmian.
 */
function partner_vat_procent(): float
{
    $v = (float)str_replace(',', '.', (string)env('VAT_RATE_PROCENT', '0'));
    return $v > 0 && $v < 100 ? $v : 0.0;
}

/** Kwota netto w groszach z kwoty brutto zapłaconej przez klienta. */
function partner_netto(int $brutto): int
{
    $vat = partner_vat_procent();
    return $vat > 0 ? (int)round($brutto / (1 + $vat / 100)) : $brutto;
}

/** Kod -> aktywny partner: własny kod partnera (partnerzy.kod) albo kupon powiązany z partnerem (twórcy gier). */
function partner_po_kodzie(PDO $dnct, string $kod): ?array
{
    $kod = strtoupper(trim($kod));
    if ($kod === '') {
        return null;
    }
    $st = $dnct->prepare(
        'SELECT id, okres_miesiecy, prowizja_procent, konto_email FROM partnerzy WHERE aktywny = 1 AND kod = ?
         UNION ALL
         SELECT p.id, p.okres_miesiecy, p.prowizja_procent, p.konto_email FROM coupons c JOIN partnerzy p ON p.id = c.partner_id
          WHERE c.code = ? AND p.aktywny = 1
         LIMIT 1'
    );
    $st->execute([$kod, $kod]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Czy to konto samego partnera (partner nie może przypisać siebie jako klienta własnym kodem). */
function partner_to_jego_konto(array $partner, string $email): bool
{
    $konto = trim((string)($partner['konto_email'] ?? ''));
    return $konto !== '' && strcasecmp($konto, trim($email)) === 0;
}

/**
 * Kod partnera użyty w tym zamówieniu -> przypisanie klienta (tylko pierwsze się liczy).
 * okres_miesiecy = 0 oznacza brak limitu (wygasa 9999-12-31).
 */
function partner_przypisz(PDO $dnct, string $email, ?string $kod): void
{
    if ($kod === null || $kod === '' || $email === '') {
        return;
    }
    $p = partner_po_kodzie($dnct, $kod);
    if (!$p || partner_to_jego_konto($p, $email)) {
        return;
    }
    $okres = (int)$p['okres_miesiecy'];
    // INSERT IGNORE: klient należy do pierwszego partnera, nie do ostatniego.
    $dnct->prepare(
        "INSERT IGNORE INTO partner_klienci (partner_id, klient_email, kod, przypisano, wygasa)
         VALUES (?, ?, ?, NOW(), IF(? = 0, '9999-12-31 23:59:59', DATE_ADD(NOW(), INTERVAL ? MONTH)))"
    )->execute([(int)$p['id'], $email, strtoupper(trim($kod)), $okres, $okres]);
}

/** Nalicza prowizję od jednej płatności (od kwoty netto), jeśli klient ma aktywnego partnera. */
function partner_nalicz(PDO $dnct, string $email, int $kwota, string $ref, ?int $orderId): void
{
    if ($email === '' || $kwota <= 0 || $ref === '') {
        return;
    }
    $st = $dnct->prepare(
        'SELECT p.id, p.prowizja_procent FROM partner_klienci k JOIN partnerzy p ON p.id = k.partner_id
          WHERE k.klient_email = ? AND k.wygasa > NOW() AND p.aktywny = 1'
    );
    $st->execute([$email]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return;
    }
    $netto = partner_netto($kwota);
    $prowizja = (int)round($netto * (float)$p['prowizja_procent'] / 100);
    $dnct->prepare(
        'INSERT IGNORE INTO partner_prowizje (partner_id, order_id, klient_email, stripe_ref, kwota_klienta, kwota_netto, prowizja)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([(int)$p['id'], $orderId, $email, $ref, $kwota, $netto, $prowizja]);
}

/** Po obsłużonej sesji Checkout (zakup gry, przedłużenie, zmiana pakietu). */
function partner_po_sesji(PDO $dnct, $session): void
{
    try {
        $paid = (string)($session->payment_status ?? '');
        if ($paid !== 'paid' && $paid !== 'no_payment_required') {
            return;
        }
        $order = order_by_session($dnct, (string)$session->id);
        if ($order === null && !empty($session->metadata->order_id)) {
            $order = order_by_id($dnct, (int)$session->metadata->order_id);
        }
        if ($order === null) {
            return;
        }
        $email = (string)$order['user_email'];
        partner_przypisz($dnct, $email, $order['promo_code'] ?? null);

        $ref = (string)($session->invoice ?? '') ?: (string)($session->payment_intent ?? '') ?: (string)$session->id;
        partner_nalicz($dnct, $email, (int)($session->amount_total ?? 0), $ref, (int)$order['id']);
    } catch (Throwable $e) {
        partner_blad('sesja ' . ($session->id ?? '?'), $e);
    }
}

/** Po opłaconej fakturze abonamentu (odnowienia; pierwsza dubluje się z sesją i odpada na UNIQUE). */
function partner_po_fakturze(PDO $dnct, $invoice): void
{
    try {
        $sub = (string)($invoice->subscription ?? '');
        $order = $sub !== '' ? order_by_subscription($dnct, $sub) : null;
        if ($order === null) {
            return;
        }
        partner_nalicz($dnct, (string)$order['user_email'], (int)($invoice->amount_paid ?? 0), (string)$invoice->id, (int)$order['id']);
    } catch (Throwable $e) {
        partner_blad('faktura ' . ($invoice->id ?? '?'), $e);
    }
}

/** Po obsłużonej sesji Checkout hostingu WWW (zakup albo przedłużenie): przypisanie partnera i prowizja. */
function partner_po_sesji_hostingu(PDO $dnct, $session): void
{
    try {
        $paid = (string)($session->payment_status ?? '');
        if ($paid !== 'paid' && $paid !== 'no_payment_required') {
            return;
        }
        $order = hosting_order_by($dnct, 'stripe_session_id', (string)$session->id);
        if ($order === null && hosting_session_order_id($session) > 0) {
            $order = hosting_order_by($dnct, 'id', hosting_session_order_id($session));
        }
        if ($order === null) {
            return;
        }
        $email = (string)$order['user_email'];
        partner_przypisz($dnct, $email, $order['promo_code'] ?? null);

        // Pierwsza faktura abonamentu ma ten sam numer co w invoice.paid - zapis jest raz (UNIQUE stripe_ref).
        $ref = (string)($session->invoice ?? '') ?: (string)($session->payment_intent ?? '') ?: (string)$session->id;
        partner_nalicz($dnct, $email, (int)($session->amount_total ?? 0), $ref, (int)$order['id']);
    } catch (Throwable $e) {
        partner_blad('sesja hostingu ' . ($session->id ?? '?'), $e);
    }
}

/** Po opłaconej fakturze abonamentu hostingu WWW (odnowienia, także roczne). */
function partner_po_fakturze_hostingu(PDO $dnct, array $order, $invoice): void
{
    try {
        partner_nalicz($dnct, (string)$order['user_email'], (int)($invoice->amount_paid ?? 0), (string)$invoice->id, (int)$order['id']);
    } catch (Throwable $e) {
        partner_blad('faktura hostingu ' . ($invoice->id ?? '?'), $e);
    }
}

/**
 * Zwrot pieniędzy - prowizja maleje proporcjonalnie do zwróconej części.
 * Wypłaconej już prowizji nie ruszamy po cichu - admin dostaje powiadomienie.
 */
function partner_po_zwrocie(PDO $dnct, $charge): void
{
    try {
        $refs = array_filter([(string)($charge->invoice ?? ''), (string)($charge->payment_intent ?? '')]);
        if (!$refs) {
            return;
        }
        $in = implode(',', array_fill(0, count($refs), '?'));
        $st = $dnct->prepare("SELECT * FROM partner_prowizje WHERE stripe_ref IN ($in)");
        $st->execute(array_values($refs));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $zaplacono = (int)($charge->amount ?? 0);
            $zwrot = (int)($charge->amount_refunded ?? 0);
            $zostaje = $zaplacono > 0 ? max(0, $zaplacono - $zwrot) / $zaplacono : 0;
            $nowa = (int)round((int)$p['prowizja'] * $zostaje);

            if ($p['status'] === 'wyplacona') {
                notify_admins($dnct, 'warning', 'Zwrot od klienta twórcy - prowizja już wypłacona',
                    $p['klient_email'] . ', ' . $p['stripe_ref'] . ': do potrącenia ' . pricing_format((int)$p['prowizja'] - $nowa) . ' zł przy następnej wypłacie.');
                continue;
            }
            $dnct->prepare('UPDATE partner_prowizje SET prowizja = ?, status = ? WHERE id = ?')
                ->execute([$nowa, $nowa === 0 ? 'cofnieta' : $p['status'], (int)$p['id']]);
        }
    } catch (Throwable $e) {
        partner_blad('zwrot ' . ($charge->id ?? '?'), $e);
    }
}

function partner_blad(string $co, Throwable $e): void
{
    error_log('Partnerzy: ' . $co . ' - ' . $e->getMessage());
    try {
        notify_admins(dnct_db(), 'error', 'Prowizja twórcy nie naliczona', $co . ': ' . $e->getMessage());
    } catch (Throwable) {
    }
}

/** Zestawienie dla panelu admina: twórcy z liczbą klientów i sumami. */
function partnerzy_lista(PDO $dnct): array
{
    return $dnct->query(
        "SELECT p.*,
                (SELECT GROUP_CONCAT(c.code) FROM coupons c WHERE c.partner_id = p.id) AS kody,
                (SELECT COUNT(*) FROM partner_klienci k WHERE k.partner_id = p.id) AS klienci,
                (SELECT COUNT(*) FROM partner_klienci k WHERE k.partner_id = p.id AND k.wygasa > NOW()) AS klienci_aktywni,
                COALESCE((SELECT SUM(kwota_klienta) FROM partner_prowizje r WHERE r.partner_id = p.id AND r.status <> 'cofnieta'), 0) AS obrot,
                COALESCE((SELECT SUM(prowizja) FROM partner_prowizje r WHERE r.partner_id = p.id AND r.status = 'naliczona'), 0) AS do_wyplaty,
                COALESCE((SELECT SUM(prowizja) FROM partner_prowizje r WHERE r.partner_id = p.id AND r.status = 'wyplacona'), 0) AS wyplacono
           FROM partnerzy p ORDER BY p.aktywny DESC, p.id DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Opiekunowie stron: aktywni partnerzy z kontem DNCT i zaznaczonym "przyjmuje opieke".
 * Zwraca tylko to, co wolno pokazac klientowi (id, nazwa, avatar). Przed migracja 059 - pusta lista.
 *
 * @return list<array{id:int,nazwa:string,avatar:?string,login:string}>
 */
function partner_opiekunowie(PDO $db): array
{
    try {
        $st = $db->query("SELECT id, nazwa, konto_login FROM partnerzy
                           WHERE opieka = 1 AND aktywny = 1 AND konto_login IS NOT NULL AND konto_login <> '' AND kod IS NOT NULL AND kod <> ''
                           ORDER BY nazwa");
        $wiersze = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $avatary = [];
    $loginy = array_values(array_unique(array_map(static fn($w) => (string)$w['konto_login'], $wiersze)));
    if ($loginy) {
        try {
            $in = implode(',', array_fill(0, count($loginy), '?'));
            $q = $db->prepare("SELECT authentik_username, avatar_file FROM user_profiles WHERE authentik_username IN ({$in}) AND avatar_file IS NOT NULL AND avatar_file <> ''");
            $q->execute($loginy);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $avatary[mb_strtolower((string)$r['authentik_username'])] = (string)$r['avatar_file'];
            }
        } catch (Throwable $e) {
            // avatar jest dodatkiem
        }
    }
    $out = [];
    foreach ($wiersze as $w) {
        $f = $avatary[mb_strtolower((string)$w['konto_login'])] ?? '';
        $out[] = [
            'id' => (int)$w['id'],
            'nazwa' => (string)$w['nazwa'],
            'login' => (string)$w['konto_login'],
            'avatar' => preg_match('/^[a-f0-9]{16}-avatar-[a-f0-9]{8}\.webp$/', $f) ? 'https://account.dnct.pl/m/' . $f : null,
        ];
    }
    return $out;
}
