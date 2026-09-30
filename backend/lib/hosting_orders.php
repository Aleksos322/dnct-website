<?php
/**
 * Sprzedaż hostingu WWW - kopia przebiegu serwerów gier (orders.php +
 * stripe_events.php), tylko że zamiast serwera w Pterodactylu zmienia się
 * PAKIET konta w panelu hostingu.
 *
 * Konto hostingu istnieje już od rejestracji albo logowania (jedno konto DNCT,
 * pakiet `dnct-konto` bez zasobów - patrz hosting.php). Zakup je tylko
 * "odblokowuje": webhook ustawia płatny pakiet, cykl życia po terminie
 * zawiesza konto.
 *
 * Te same trzy zasady co przy grach (api/stripe/webhook.php):
 *  - kwotę liczymy tu, z HOSTING_PLANS, nigdy z formularza,
 *  - pakiet zmienia WYŁĄCZNIE webhook (nie strona powrotu),
 *  - GET_LOCK na zamówieniu, bo BLIK/P24 potwierdzają dwoma zdarzeniami.
 *
 * Czego świadomie NIE ma: automatycznego kasowania. Konto hostingu to strony,
 * poczta i bazy klienta - po dłuższym zawieszeniu admin dostaje sygnał
 * "do decyzji", a nie automat z `v-delete-user`.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/provisioning.php';
require_once __DIR__ . '/hosting.php';
require_once __DIR__ . '/../mail/mail.php';

/** Cennik - jedyne źródło kwot. Kwoty brutto w groszach za 30 dni. */
const HOSTING_PLANS = [
    'dnct-start'  => ['name' => 'Start',  'price' => 900,  'sites' => 1,  'disk_gb' => 5,  'mailboxes' => 5,   'databases' => 2],
    'dnct-pro'    => ['name' => 'Pro',    'price' => 1900, 'sites' => 5,  'disk_gb' => 20, 'mailboxes' => 25,  'databases' => 10],
    'dnct-biznes' => ['name' => 'Biznes', 'price' => 3900, 'sites' => 20, 'disk_gb' => 50, 'mailboxes' => 100, 'databases' => 50],
];

const HOSTING_PERIOD_DAYS = 30;

/** Po tylu dniach zawieszenia admin dostaje "do decyzji" (i anulujemy abonament). */
const HOSTING_STALE_DAYS = 14;

// ---------------------------------------------------------------------------
// Zamówienia
// ---------------------------------------------------------------------------

function hosting_order_create(PDO $dnct, string $email, string $name, string $package, string $billingMode, ?int $extendsId = null): int
{
    $plan = HOSTING_PLANS[$package] ?? null;
    if ($plan === null) {
        throw new RuntimeException('Nieznany pakiet hostingu.');
    }
    $stmt = $dnct->prepare(
        'INSERT INTO hosting_orders (user_email, user_name, package, period_days, billing_mode, amount_gross, extends_order_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$email, $name, $package, HOSTING_PERIOD_DAYS, $billingMode, $plan['price'], $extendsId]);
    return (int)$dnct->lastInsertId();
}

function hosting_order_by(PDO $dnct, string $column, string|int $value): ?array
{
    if (!in_array($column, ['id', 'stripe_session_id', 'stripe_subscription_id'], true)) {
        throw new InvalidArgumentException('hosting_order_by: niedozwolona kolumna');
    }
    $stmt = $dnct->prepare("SELECT * FROM hosting_orders WHERE {$column} = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$value]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/**
 * Bieżący hosting klienta: ostatnie opłacone zamówienie główne (nie
 * przedłużenie), które nie zostało zamknięte.
 */
function hosting_current_order(PDO $dnct, string $email): ?array
{
    $stmt = $dnct->prepare(
        'SELECT * FROM hosting_orders
          WHERE user_email = ? AND extends_order_id IS NULL AND status IN ("paid", "provisioned")
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/** Ostatnie zamówienie klienta w dowolnym stanie - do strony powrotu z płatności. */
function hosting_last_order(PDO $dnct, string $email): ?array
{
    $stmt = $dnct->prepare('SELECT * FROM hosting_orders WHERE user_email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false ? $row : null;
}

/** Przedłuża o dni i zatrzymuje zegar zawieszenia (jak order_extend). */
function hosting_order_extend(PDO $dnct, int $id, ?int $days = null, ?string $until = null): void
{
    if ($until !== null) {
        $dnct->prepare(
            'UPDATE hosting_orders
                SET expires_at = GREATEST(COALESCE(expires_at, ?), ?),
                    suspended_at = NULL, grace_until = NULL, failure_reason = NULL,
                    warn_7d_at = NULL, warn_24h_at = NULL, stale_notified_at = NULL,
                    status = IF(status = "failed", status, "provisioned")
              WHERE id = ?'
        )->execute([$until, $until, $id]);
        return;
    }
    $dnct->prepare(
        'UPDATE hosting_orders
            SET expires_at = DATE_ADD(GREATEST(COALESCE(expires_at, NOW()), NOW()), INTERVAL ? DAY),
                suspended_at = NULL, grace_until = NULL, failure_reason = NULL,
                warn_7d_at = NULL, warn_24h_at = NULL, stale_notified_at = NULL,
                status = IF(status = "failed", status, "provisioned")
          WHERE id = ?'
    )->execute([$days ?? HOSTING_PERIOD_DAYS, $id]);
}

function hosting_order_fail(PDO $dnct, int $id, string $reason): void
{
    $dnct->prepare('UPDATE hosting_orders SET status = "failed", failure_reason = ? WHERE id = ?')
         ->execute([mb_substr($reason, 0, 500), $id]);
}

/** Kiedy konto zostanie zawieszone (jak order_deadlines). */
function hosting_suspends_at(array $order): ?DateTimeImmutable
{
    if (empty($order['expires_at'])) {
        return null;
    }
    if (!empty($order['grace_until'])) {
        return new DateTimeImmutable((string)$order['grace_until']);
    }
    $e = new DateTimeImmutable((string)$order['expires_at']);
    return $order['billing_mode'] === 'subscription' ? $e->modify('+' . PROVISION_GRACE_HOURS . ' hours') : $e;
}

function hosting_fmt_date(?string $raw): string
{
    return $raw ? (new DateTimeImmutable($raw))->format('d.m.Y \o H:i') : 'nieustalone';
}

// ---------------------------------------------------------------------------
// Webhook - wołane z stripe_events.php
// ---------------------------------------------------------------------------

/** Czy zdarzenie Stripe dotyczy hostingu (metadane sesji albo subskrypcji). */
function hosting_is_hosting_session($session): bool
{
    return (string)($session->metadata->product ?? '') === 'hosting';
}

function hosting_webhook_checkout_completed(PDO $dnct, $session): bool
{
    $paid = (string)($session->payment_status ?? '');
    if ($paid !== 'paid' && $paid !== 'no_payment_required') {
        return true; // BLIK/P24 - czekamy na async_payment_succeeded
    }

    $order = hosting_order_by($dnct, 'stripe_session_id', (string)$session->id);
    if ($order === null && !empty($session->metadata->order_id)) {
        $order = hosting_order_by($dnct, 'id', (int)$session->metadata->order_id);
    }
    if ($order === null) {
        error_log('Stripe webhook: brak zamówienia hostingu dla sesji ' . $session->id);
        return true;
    }

    $id   = (int)$order['id'];
    $lock = 'dnct_hosting_order_' . $id;
    $stmt = $dnct->prepare('SELECT GET_LOCK(?, 15)');
    $stmt->execute([$lock]);
    if ((int)$stmt->fetchColumn() !== 1) {
        return false;
    }

    try {
        $order = hosting_order_by($dnct, 'id', $id);
        if ($order === null || in_array($order['status'], ['provisioned', 'cancelled'], true)) {
            return true;
        }

        $customer     = isset($session->customer) ? (string)$session->customer : null;
        $subscription = isset($session->subscription) ? (string)$session->subscription : null;

        // --- Przedłużenie: dni do zamówienia głównego i odwieszenie ---------
        if (!empty($order['extends_order_id'])) {
            $parent = hosting_order_by($dnct, 'id', (int)$order['extends_order_id']);
            if ($parent === null) {
                hosting_order_fail($dnct, $id, 'Brak zamówienia nadrzędnego.');
                notify_admins($dnct, 'error', 'Hosting #' . $id . ': opłacone przedłużenie bez zamówienia nadrzędnego', (string)$order['user_email']);
                return true;
            }
            hosting_order_extend($dnct, (int)$parent['id'], (int)$order['period_days']);
            if ($order['billing_mode'] === 'subscription' && $subscription !== null) {
                $dnct->prepare('UPDATE hosting_orders SET billing_mode = "subscription", stripe_subscription_id = ?, stripe_customer_id = COALESCE(?, stripe_customer_id) WHERE id = ?')
                     ->execute([$subscription, $customer, (int)$parent['id']]);
            }
            $ok = empty($parent['hosting_user']) || hosting_call('unsuspend', ['user' => (string)$parent['hosting_user']]) !== null;
            $dnct->prepare('UPDATE hosting_orders SET status = "provisioned", expires_at = NOW() WHERE id = ?')->execute([$id]);
            notify_user($dnct, (string)$order['user_name'], $ok ? 'success' : 'warning',
                'Hosting przedłużony',
                $ok ? 'Dopisaliśmy kolejny okres - strony i poczta działają dalej.'
                    : 'Zapłata i dni się zgadzają, ale panel nie potwierdził zdjęcia zawieszenia. Sprawdzimy to ręcznie.');
            if (!$ok) {
                notify_admins($dnct, 'error', 'Nie udało się odwiesić hostingu po przedłużeniu', 'Zamówienie #' . (int)$parent['id'] . ', konto ' . (string)$parent['hosting_user']);
            }
            return true;
        }

        // --- Nowy zakup: płatny pakiet na koncie DNCT ------------------------
        $dnct->prepare(
            'UPDATE hosting_orders
                SET status = "paid", stripe_customer_id = COALESCE(?, stripe_customer_id),
                    stripe_subscription_id = COALESCE(?, stripe_subscription_id),
                    expires_at = DATE_ADD(NOW(), INTERVAL period_days DAY), grace_until = NULL, failure_reason = NULL
              WHERE id = ?'
        )->execute([$customer, $subscription, $id]);

        $res = hosting_call('create', [
            'email'   => (string)$order['user_email'],
            'name'    => (string)$order['user_name'],
            'package' => (string)$order['package'],
        ]);

        if ($res === null || empty($res['user'])) {
            hosting_order_fail($dnct, $id, 'Panel hostingu nie przyjął zmiany pakietu.');
            notify_user($dnct, (string)$order['user_name'], 'error', 'Hosting nie został jeszcze włączony',
                'Płatność się powiodła, ale automatyczne włączenie pakietu nie doszło do skutku. Zajmiemy się tym ręcznie.');
            notify_admins($dnct, 'error', 'Hosting #' . $id . ' opłacony, pakiet NIE ustawiony', (string)$order['user_email'] . ' - ' . (string)$order['package']);
            return true; // powtórka doręczenia nic nie naprawi
        }

        $dnct->prepare('UPDATE hosting_orders SET status = "provisioned", hosting_user = ? WHERE id = ?')
             ->execute([(string)$res['user'], $id]);

        hosting_announce_paid($dnct, hosting_order_by($dnct, 'id', $id));
        return true;
    } finally {
        $dnct->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}

function hosting_webhook_invoice_paid(PDO $dnct, array $order, $invoice): bool
{
    if (($invoice->billing_reason ?? '') === 'subscription_create') {
        return true; // pierwszy okres zaliczył już checkout.session.completed
    }
    $end = isset($invoice->period_end) ? (int)$invoice->period_end : 0;
    $end > 0
        ? hosting_order_extend($dnct, (int)$order['id'], null, gmdate('Y-m-d H:i:s', $end))
        : hosting_order_extend($dnct, (int)$order['id'], (int)$order['period_days']);

    if (!empty($order['suspended_at']) && !empty($order['hosting_user'])) {
        if (hosting_call('unsuspend', ['user' => (string)$order['hosting_user']]) === null) {
            notify_admins($dnct, 'error', 'Nie udało się odwiesić hostingu po opłaconej fakturze', 'Zamówienie #' . (int)$order['id']);
        }
    }
    notify_user($dnct, (string)$order['user_name'], 'success', 'Abonament hostingu przedłużony',
        'Płatność się powiodła - strony i poczta działają przez kolejny okres.');
    return true;
}

function hosting_webhook_invoice_failed(PDO $dnct, array $order, $invoice): bool
{
    $dnct->prepare('UPDATE hosting_orders SET grace_until = DATE_ADD(NOW(), INTERVAL ? HOUR), failure_reason = ? WHERE id = ?')
         ->execute([PROVISION_GRACE_HOURS, 'Nieudana płatność cykliczna (faktura ' . ($invoice->id ?? '?') . ')', (int)$order['id']]);
    $order = hosting_order_by($dnct, 'id', (int)$order['id']);
    $when  = hosting_fmt_date((string)$order['grace_until']);

    notify_user($dnct, (string)$order['user_name'], 'warning', 'Nie udało się pobrać opłaty za hosting',
        'Bank odrzucił płatność. Hosting zostanie zawieszony ' . $when . ' - do tego czasu działa normalnie. Popraw dane karty albo przedłuż jednorazowo.');
    notify_admins($dnct, 'warning', 'Nieudana płatność cykliczna (hosting)',
        'Zamówienie hostingu #' . (int)$order['id'] . ' (' . (string)$order['user_email'] . '), zawieszenie ' . $when . '.');
    hosting_mail($order, 'Nie udało się pobrać opłaty - hosting zostanie zawieszony ' . $when,
        "nie udało się pobrać opłaty za hosting {$order['package']}.\n\nHOSTING ZOSTANIE ZAWIESZONY: {$when}\n\n"
        . "Do tego czasu strony i poczta działają normalnie. Popraw dane karty albo przedłuż jednorazowo:\n" . hosting_account_url() . "\n\n"
        . 'Zawieszenie nie kasuje danych - po opłaceniu wszystko wraca w tym samym stanie.');
    return true;
}

function hosting_webhook_subscription_deleted(PDO $dnct, array $order): bool
{
    /* Jak przy grach: NIE zawieszamy od razu. Klient ma opłacony okres,
       zawieszeniem po expires_at zajmuje się cykl życia. Zmieniamy tylko
       tryb, żeby cykl życia traktował to jak płatność jednorazową. */
    $dnct->prepare('UPDATE hosting_orders SET billing_mode = "payment" WHERE id = ?')->execute([(int)$order['id']]);
    notify_user($dnct, (string)$order['user_name'], 'info', 'Abonament hostingu nie będzie odnawiany',
        'Strony działają do końca opłaconego okresu (' . hosting_fmt_date((string)$order['expires_at']) . '). Możesz przedłużyć w każdej chwili.');
    return true;
}

// ---------------------------------------------------------------------------
// Powiadomienia
// ---------------------------------------------------------------------------

function hosting_account_url(): string
{
    return hosting_site_url() . '/';
}

function hosting_announce_paid(PDO $dnct, ?array $order): void
{
    if ($order === null) {
        return;
    }
    $plan    = HOSTING_PLANS[$order['package']] ?? ['name' => $order['package']];
    $expires = hosting_fmt_date((string)$order['expires_at']);
    $renew   = $order['billing_mode'] === 'subscription'
        ? 'Abonament odnawia się sam co miesiąc.'
        : 'To płatność jednorazowa - przedłużysz ją na stronie hostingu.';

    notify_user($dnct, (string)$order['user_name'], 'success', 'Hosting ' . $plan['name'] . ' jest gotowy',
        'Opłacone do ' . $expires . '. ' . $renew . ' Panel otworzysz tym samym kontem DNCT.');

    hosting_mail($order, 'Hosting ' . $plan['name'] . ' gotowy - zamówienie #' . (int)$order['id'],
        "pakiet hostingu {$plan['name']} jest włączony.\n\n"
        . "Opłacone do: {$expires}\n{$renew}\n\n"
        . 'Panel hostingu: ' . hosting_public_url() . "\n"
        . "Logujesz się tym samym kontem DNCT co na stronie i w panelu gier.\n\n"
        . 'Twój hosting: ' . hosting_account_url());
}

/** Prosta wiadomość z info@ - błąd poczty nie wywraca webhooka. */
function hosting_mail(array $order, string $subject, string $body): void
{
    $text = 'Cześć ' . $order['user_name'] . ",\n\n" . $body . "\n\nDNCT Hosting";
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111">'
        . nl2br(htmlspecialchars($text, ENT_QUOTES)) . '</div>';
    try {
        $res = send_mail((string)$order['user_email'], $subject, $html, $text, 'info');
        if (empty($res['ok'])) {
            error_log('Hosting #' . $order['id'] . ': mail nie wyszedł - ' . ($res['error'] ?? '?'));
        }
    } catch (Throwable $e) {
        error_log('Hosting #' . $order['id'] . ': błąd wysyłki - ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Cykl życia - wołany na końcu backend/tools/lifecycle.php
// ---------------------------------------------------------------------------

function hosting_lifecycle_run(PDO $dnct, bool $apply): void
{
    echo "\n-- hosting WWW --\n";
    $done = 0;

    // 1. Ostrzeżenia - tylko płatność jednorazowa (abonament odnawia się sam).
    foreach ([['warn_7d_at', '7 DAY', 'za tydzień'], ['warn_24h_at', '24 HOUR', 'jutro']] as [$col, $interval, $opis]) {
        $rows = $dnct->query(
            "SELECT * FROM hosting_orders
              WHERE status = 'provisioned' AND extends_order_id IS NULL AND billing_mode = 'payment'
                AND suspended_at IS NULL AND expires_at > NOW()
                AND expires_at <= DATE_ADD(NOW(), INTERVAL {$interval}) AND {$col} IS NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $o) {
            $done++;
            printf("  #%-4d %-28s wygasa %s -> ostrzeżenie (%s)\n", $o['id'], $o['user_name'], $o['expires_at'], $opis);
            if (!$apply) {
                continue;
            }
            $dnct->prepare("UPDATE hosting_orders SET {$col} = NOW() WHERE id = ?")->execute([(int)$o['id']]);
            $when = hosting_fmt_date((string)$o['expires_at']);
            notify_user($dnct, (string)$o['user_name'], 'warning', 'Hosting wygasa ' . $opis,
                'Opłacony okres kończy się ' . $when . '. To płatność jednorazowa - hosting nie odnowi się sam.');
            hosting_mail($o, 'Hosting wygasa ' . $opis . ' (' . $when . ')',
                "opłacony okres hostingu kończy się {$when}. Potem strony i poczta zostaną zawieszone.\n\nPrzedłużysz tutaj: " . hosting_account_url());
        }
    }

    // 2. Zawieszenie po terminie (abonament: po karencji).
    $rows = $dnct->query(
        "SELECT * FROM hosting_orders
          WHERE status = 'provisioned' AND extends_order_id IS NULL AND suspended_at IS NULL
            AND hosting_user IS NOT NULL AND expires_at IS NOT NULL
            AND COALESCE(grace_until,
                  IF(billing_mode = 'subscription', DATE_ADD(expires_at, INTERVAL " . PROVISION_GRACE_HOURS . " HOUR), expires_at)) < NOW()"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $o) {
        $done++;
        printf("  #%-4d %-28s wygasło %s -> zawieszenie konta %s\n", $o['id'], $o['user_name'], $o['expires_at'], $o['hosting_user']);
        if (!$apply) {
            continue;
        }
        if (hosting_call('suspend', ['user' => (string)$o['hosting_user']]) === null) {
            echo "      panel nie potwierdził zawieszenia - spróbuję w następnym przebiegu\n";
            continue;
        }
        $dnct->prepare('UPDATE hosting_orders SET suspended_at = NOW() WHERE id = ?')->execute([(int)$o['id']]);
        notify_user($dnct, (string)$o['user_name'], 'error', 'Hosting zawieszony',
            'Opłacony okres minął, więc strony i poczta są wstrzymane. Nic nie zostało skasowane - po opłaceniu wszystko wraca od razu.');
        hosting_mail($o, 'Hosting zawieszony - nic nie zostało skasowane',
            "opłacony okres hostingu minął, więc strony i poczta są wstrzymane.\n\nNic nie zostało skasowane. Po przedłużeniu wszystko wraca od razu:\n" . hosting_account_url());
    }

    // 3. Długo zawieszone - do decyzji admina, bez automatycznego kasowania.
    $rows = $dnct->query(
        "SELECT * FROM hosting_orders
          WHERE status = 'provisioned' AND suspended_at IS NOT NULL AND stale_notified_at IS NULL
            AND suspended_at < DATE_SUB(NOW(), INTERVAL " . HOSTING_STALE_DAYS . " DAY)"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $o) {
        $done++;
        printf("  #%-4d %-28s zawieszone od %s -> do decyzji admina\n", $o['id'], $o['user_name'], $o['suspended_at']);
        if (!$apply) {
            continue;
        }
        $cancelled = lifecycle_cancel_subscription($o);
        $dnct->prepare('UPDATE hosting_orders SET stale_notified_at = NOW() WHERE id = ?')->execute([(int)$o['id']]);
        notify_admins($dnct, 'warning', 'Hosting zawieszony od ' . HOSTING_STALE_DAYS . ' dni - do decyzji',
            'Zamówienie #' . (int)$o['id'] . ' (' . (string)$o['user_email'] . '), konto ' . (string)$o['hosting_user']
            . '. Automat niczego nie kasuje.' . ($cancelled ? '' : ' UWAGA: nie udało się anulować subskrypcji Stripe.'));
    }

    if ($done === 0) {
        echo "  nic\n";
    }
}
