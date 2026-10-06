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

/** Kod twórcy użyty w tym zamówieniu -> przypisanie klienta (tylko pierwsze się liczy). */
function partner_przypisz(PDO $dnct, string $email, ?string $kod): void
{
    if ($kod === null || $kod === '' || $email === '') {
        return;
    }
    $st = $dnct->prepare(
        'SELECT p.id, p.okres_miesiecy FROM coupons c JOIN partnerzy p ON p.id = c.partner_id
          WHERE c.code = ? AND p.aktywny = 1'
    );
    $st->execute([$kod]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return;
    }
    // INSERT IGNORE: klient należy do pierwszego twórcy, nie do ostatniego.
    $dnct->prepare(
        'INSERT IGNORE INTO partner_klienci (partner_id, klient_email, kod, przypisano, wygasa)
         VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH))'
    )->execute([(int)$p['id'], $email, $kod, (int)$p['okres_miesiecy']]);
}

/** Nalicza prowizję od jednej płatności, jeśli klient ma aktywnego twórcę. */
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
    $prowizja = (int)round($kwota * (float)$p['prowizja_procent'] / 100);
    $dnct->prepare(
        'INSERT IGNORE INTO partner_prowizje (partner_id, order_id, klient_email, stripe_ref, kwota_klienta, prowizja)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([(int)$p['id'], $orderId, $email, $ref, $kwota, $prowizja]);
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
