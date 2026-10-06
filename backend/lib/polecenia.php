<?php
/**
 * Poleć znajomemu.
 *
 * Każdy klient, który za coś zapłacił, dostaje własny kod "na start"
 * (POLEC-NICK): znajomy płaci 10% mniej za pierwszy serwer, a polecający
 * dostaje POLECENIE_DNI dni do swojego serwera, gdy znajomy zapłaci.
 *
 * Kod to zwykły kupon first_order_only (backend/lib/coupons.php), więc
 * "tylko pierwszy zakup" i to, że nie da się polecić samego siebie (masz już
 * zakup), sprawdza ta sama funkcja co przy kodach twórców.
 *
 * Nagroda: serwer polecającego, któremu najszybciej kończy się termin.
 * Abonament dostaje przesunięte obciążenie w Stripe (trial_end), żeby
 * dodatkowe dni nie zostały "zjedzone" przez kolejną fakturę.
 */

declare(strict_types=1);

require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/provisioning.php';

const POLECENIE_RABAT = 10;
const POLECENIE_DNI   = 7;

/** Kod klienta - istniejący albo świeżo założony. null = klient jeszcze nic nie kupił. */
function polecenie_kod(PDO $dnct, string $email, string $username): ?array
{
    $st = $dnct->prepare('SELECT * FROM coupons WHERE polecajacy_email = ? AND active = 1 ORDER BY id DESC LIMIT 1');
    $st->execute([$email]);
    if ($c = $st->fetch(PDO::FETCH_ASSOC)) {
        return $c;
    }
    if (!customer_has_purchase($dnct, $email)) {
        return null;
    }

    $baza = substr(coupon_normalize_code($username), 0, 24) ?: 'KLIENT';
    $wolny = $dnct->prepare('SELECT 1 FROM coupons WHERE code = ?');
    $kod = 'POLEC-' . $baza;
    for ($i = 2; ; $i++) {
        $wolny->execute([$kod]);
        if (!$wolny->fetchColumn()) {
            break;
        }
        $kod = 'POLEC-' . $baza . $i;
    }

    $res = coupon_create($dnct, [
        'code' => $kod, 'percent_off' => POLECENIE_RABAT, 'duration' => 'once',
        'first_order_only' => true, 'polecajacy_email' => $email,
    ], 'polecenia');
    if (!$res['ok']) {
        error_log('Polecenia: nie powstał kod dla ' . $email . ' - ' . $res['error']);
        return null;
    }
    $st->execute([$email]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Ile osób poleciłeś i ile dni dostałeś. */
function polecenie_statystyki(PDO $dnct, string $email): array
{
    $st = $dnct->prepare("SELECT COUNT(*), COALESCE(SUM(IF(status = 'przyznana', nagroda_dni, 0)), 0) FROM polecenia WHERE polecajacy_email = ?");
    $st->execute([$email]);
    [$ile, $dni] = $st->fetch(PDO::FETCH_NUM);
    return ['ile' => (int)$ile, 'dni' => (int)$dni];
}

/** Po opłaconej sesji: jeśli nowy zakup był z kodem polecającym - nagroda. */
function polecenie_po_sesji(PDO $dnct, $session): void
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
        if ($order === null || empty($order['promo_code']) || ($order['kind'] ?? 'new') !== 'new') {
            return;
        }
        $st = $dnct->prepare('SELECT polecajacy_email FROM coupons WHERE code = ? AND polecajacy_email IS NOT NULL');
        $st->execute([(string)$order['promo_code']]);
        $polecajacy = $st->fetchColumn();
        if (!$polecajacy || strcasecmp((string)$polecajacy, (string)$order['user_email']) === 0) {
            return;
        }

        // UNIQUE na znajomym = jedna nagroda na znajomego, także przy powtórzonym zdarzeniu.
        $ins = $dnct->prepare('INSERT IGNORE INTO polecenia (polecajacy_email, kod, znajomy_email, order_id, nagroda_dni) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$polecajacy, $order['promo_code'], $order['user_email'], (int)$order['id'], POLECENIE_DNI]);
        if ($ins->rowCount() === 0) {
            return;
        }
        polecenie_przyznaj($dnct, (int)$dnct->lastInsertId());
    } catch (Throwable $e) {
        error_log('Polecenia: sesja ' . ($session->id ?? '?') . ' - ' . $e->getMessage());
        try {
            notify_admins($dnct, 'error', 'Nagroda za polecenie nie przyznana', ($session->id ?? '?') . ': ' . $e->getMessage());
        } catch (Throwable) {
        }
    }
}

/** Dopisuje dni do serwera polecającego. Wołane z webhooka i (ręcznie) z panelu admina. */
function polecenie_przyznaj(PDO $dnct, int $id): bool
{
    $st = $dnct->prepare("SELECT * FROM polecenia WHERE id = ? AND status <> 'przyznana'");
    $st->execute([$id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return false;
    }
    $dni = (int)$p['nagroda_dni'];
    $oznacz = $dnct->prepare('UPDATE polecenia SET status = ?, nagroda_order_id = ?, opis = ?, przyznano = IF(? = "przyznana", NOW(), NULL) WHERE id = ?');

    $s = $dnct->prepare(
        'SELECT * FROM orders WHERE user_email = ? AND kind = "new" AND status = "provisioned"
            AND deleted_at IS NULL AND ptero_server_id IS NOT NULL
          ORDER BY expires_at ASC LIMIT 1'
    );
    $s->execute([$p['polecajacy_email']]);
    $order = $s->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        $oznacz->execute(['oczekuje', null, 'brak aktywnego serwera', 'oczekuje', $id]);
        notify_admins($dnct, 'warning', 'Polecenie bez serwera do przedłużenia',
            $p['polecajacy_email'] . ' polecił ' . $p['znajomy_email'] . ' - przyznaj ' . $dni . ' dni ręcznie w admin -> Partnerzy.');
        return false;
    }

    try {
        if ($order['billing_mode'] === 'subscription' && !empty($order['stripe_subscription_id'])) {
            stripe_init();
            $sub = \Stripe\Subscription::retrieve((string)$order['stripe_subscription_id']);
            $koniec = (int)($sub->trial_end ?? 0) ?: (int)($sub->current_period_end ?? $sub->items->data[0]->current_period_end ?? 0);
            if ($koniec <= time()) {
                throw new RuntimeException('subskrypcja bez przyszłego terminu');
            }
            \Stripe\Subscription::update($sub->id, ['trial_end' => $koniec + $dni * 86400, 'proration_behavior' => 'none']);
        }
        $byloZawieszone = !empty($order['suspended_at']);
        order_extend($dnct, (int)$order['id'], $dni);
        if ($byloZawieszone) {
            provision_unsuspend((int)$order['ptero_server_id']);
        }
    } catch (Throwable $e) {
        $oznacz->execute(['blad', (int)$order['id'], mb_substr($e->getMessage(), 0, 250), 'blad', $id]);
        notify_admins($dnct, 'error', 'Nagroda za polecenie: błąd', $p['polecajacy_email'] . ': ' . $e->getMessage());
        return false;
    }

    $oznacz->execute(['przyznana', (int)$order['id'], null, 'przyznana', $id]);
    notify_user($dnct, (string)$order['user_name'], 'success', 'Znajomy dołączył z Twoim kodem',
        'Dziękujemy za polecenie! Dopisaliśmy +' . $dni . ' dni do serwera ' . ucfirst((string)$order['game_type']) . '.');
    return true;
}
