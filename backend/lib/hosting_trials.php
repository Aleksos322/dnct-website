<?php
declare(strict_types=1);

/** Jeden test na adres konta, również po anulowaniu subskrypcji.
    Rezerwacja niewykorzystanej sesji wygasa razem z Checkout. */
function hosting_trial_claim(PDO $db, string $accountKey, string $email, int $orderId): bool
{
    $email = mb_strtolower(trim($email));
    $db->prepare('DELETE FROM hosting_trial_claims WHERE (account_key = ? OR user_email = ?) AND started_at IS NULL AND checkout_expires_at < NOW()')->execute([$accountKey, $email]);
    try {
        $db->prepare('INSERT INTO hosting_trial_claims (account_key, user_email, order_id, checkout_expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 31 MINUTE))')->execute([$accountKey, $email, $orderId]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { return false; }
        throw $e;
    }
}

function hosting_trial_by_order(PDO $db, int $id): ?array
{
    $s = $db->prepare('SELECT * FROM hosting_trial_claims WHERE order_id = ?');
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Data opłaconego okresu z pozycji faktury, nie data jej wystawienia. */
function hosting_invoice_period_end($invoice): int
{
    $end = 0;
    foreach (($invoice->lines->data ?? []) as $line) {
        if (($line->type ?? '') === 'subscription' || !empty($line->subscription)
            || !empty($line->parent->subscription_item_details)) {
            $end = max($end, (int)($line->period->end ?? 0));
        }
    }
    return $end ?: (int)($invoice->period_end ?? 0);
}

/** Przypomnienie trzy dni przed pierwszym obciążeniem, także bez osobnego eventu Stripe. */
function hosting_trial_reminders(PDO $db, bool $apply): void
{
    $rows = $db->query("SELECT o.*, t.ends_at AS trial_ends_at FROM hosting_trial_claims t
        JOIN hosting_orders o ON o.id = t.order_id
        WHERE t.started_at IS NOT NULL AND t.reminder_sent_at IS NULL
        AND t.ends_at > NOW() AND t.ends_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND o.billing_mode = 'subscription' AND o.status = 'provisioned'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $o) {
        if (!$apply) { echo 'Trial #' . (int)$o['id'] . ": przypomnienie przed obciążeniem\n"; continue; }
        $s = $db->prepare('UPDATE hosting_trial_claims SET reminder_sent_at = NOW() WHERE order_id = ? AND reminder_sent_at IS NULL');
        $s->execute([(int)$o['id']]);
        if (!$s->rowCount()) { continue; }
        $body = 'Twój 7-dniowy test hostingu kończy się ' . hosting_fmt_date($o['trial_ends_at'])
            . '. Po nim karta zostanie automatycznie obciążona kwotą '
            . pricing_format((int)$o['amount_gross']) . ' zł za kolejne 30 dni. '
            . 'Jeżeli nie chcesz kontynuować, wyłącz odnawianie przed końcem testu: ' . hosting_account_url();
        hosting_mail($o, 'Kończy się test hostingu — sprawdź abonament', $body);
        notify_user($db, (string)$o['user_name'], 'info', 'Kończy się test hostingu', $body);
    }
}
