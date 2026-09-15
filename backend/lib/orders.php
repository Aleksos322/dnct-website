<?php
/**
 * Zamówienia - zapis i zmiany stanu.
 *
 * Zamówienie żyje w bazie `dnct` (własnej bazie strony), a nie w bazie panelu.
 * Jedno zamówienie = jedna próba zakupu, także nieudana: wiersz powstaje ZANIM
 * klient zobaczy Stripe'a. Dzięki temu po nieopłaconej sesji zostaje ślad,
 * z którego widać, co ktoś próbował kupić i na czym się zatrzymał.
 *
 * ŚCIEŻKA STANÓW:
 *
 *   pending ──(checkout.session.completed)──> paid ──(serwer utworzony)──> provisioned
 *      │                                       │
 *      └──(porzucona sesja, zostaje)           └──(błąd Pterodactyla)──> failed
 *
 *   cancelled ustawia customer.subscription.deleted - abonament wygasa
 *   z końcem opłaconego okresu, więc to NIE jest natychmiastowe wyłączenie.
 *
 * Serwer zakłada WYŁĄCZNIE webhook. Strona powrotu tylko odpytuje o stan -
 * inaczej wystarczyłoby odświeżenie strony sukcesu, żeby zamówić drugi serwer
 * za tę samą płatność. Stara wersja robiła to w success.php i broniła się
 * przed tym komentarzem, a nie kodem.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/notifications.php';

/**
 * Zakłada zamówienie w stanie `pending`.
 *
 * @param array<string,mixed> $quote Wynik pricing_quote()
 * @return int Identyfikator zamówienia
 */
function order_create(PDO $dnct, array $quote, string $userEmail, string $userName): int
{
    $stmt = $dnct->prepare(
        'INSERT INTO orders
            (user_email, user_name, game_type, plan_id, slots, ram_mb, cpu_cores, ssd_mb,
             backups_limit, period_days, billing_mode, amount_gross, currency, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
    );

    $stmt->execute([
        $userEmail,
        $userName,
        $quote['game_type'],
        $quote['plan_id'],
        $quote['slots'],
        $quote['ram_mb'],
        $quote['cpu_cores'],
        $quote['ssd_mb'],
        $quote['backups_limit'],
        $quote['period_days'],
        $quote['billing_mode'],
        $quote['amount_gross'],
        $quote['currency'],
    ]);

    return (int)$dnct->lastInsertId();
}

/** Dopina identyfikator sesji Stripe'a po jej utworzeniu. */
function order_attach_session(PDO $dnct, int $orderId, string $sessionId): void
{
    $stmt = $dnct->prepare('UPDATE orders SET stripe_session_id = ? WHERE id = ?');
    $stmt->execute([$sessionId, $orderId]);
}

function order_by_id(PDO $dnct, int $id): ?array
{
    $stmt = $dnct->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function order_by_session(PDO $dnct, string $sessionId): ?array
{
    $stmt = $dnct->prepare('SELECT * FROM orders WHERE stripe_session_id = ?');
    $stmt->execute([$sessionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Zamówienie po identyfikatorze subskrypcji.
 *
 * Bierzemy NAJSTARSZE pasujące - subskrypcja rodzi kolejne faktury, ale serwer
 * i data wygaśnięcia należą do zamówienia, które ją założyło.
 */
function order_by_subscription(PDO $dnct, string $subscriptionId): ?array
{
    $stmt = $dnct->prepare('SELECT * FROM orders WHERE stripe_subscription_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$subscriptionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Zamówienia jednego klienta, od najnowszego. */
function orders_for_user(PDO $dnct, string $userEmail, int $limit = 50): array
{
    $stmt = $dnct->prepare(
        'SELECT * FROM orders WHERE user_email = ? ORDER BY id DESC LIMIT ' . max(1, min($limit, 200))
    );
    $stmt->execute([$userEmail]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Oznacza zamówienie jako opłacone i zapisuje, co przyszło ze Stripe'a.
 *
 * Data wygaśnięcia liczona jest OD TERAZ, a nie od utworzenia zamówienia -
 * między kliknięciem "kupuję" a zapłatą klient może siedzieć w Checkoucie
 * dowolnie długo, a płaci za pełny okres.
 */
function order_mark_paid(
    PDO $dnct,
    int $orderId,
    ?string $customerId,
    ?string $subscriptionId,
    int $periodDays
): void {
    $stmt = $dnct->prepare(
        'UPDATE orders
            SET status = "paid",
                stripe_customer_id = COALESCE(?, stripe_customer_id),
                stripe_subscription_id = COALESCE(?, stripe_subscription_id),
                expires_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                grace_until = NULL,
                failure_reason = NULL
          WHERE id = ?'
    );
    $stmt->execute([$customerId, $subscriptionId, $periodDays, $orderId]);
}

function order_mark_provisioned(PDO $dnct, int $orderId, int $pteroUserId, int $serverId, string $serverUuid): void
{
    $stmt = $dnct->prepare(
        'UPDATE orders
            SET status = "provisioned", ptero_user_id = ?, ptero_server_id = ?, ptero_server_uuid = ?
          WHERE id = ?'
    );
    $stmt->execute([$pteroUserId, $serverId, $serverUuid, $orderId]);
}

/**
 * Zamówienie opłacone, ale serwera nie udało się założyć.
 *
 * To jest stan wymagający RĘCZNEJ reakcji: pieniądze są u nas, usługi nie ma.
 * Powód zapisujemy w wierszu, żeby nie trzeba było go szukać po logach.
 */
function order_mark_failed(PDO $dnct, int $orderId, string $reason): void
{
    $stmt = $dnct->prepare('UPDATE orders SET status = "failed", failure_reason = ? WHERE id = ?');
    $stmt->execute([mb_substr($reason, 0, 500), $orderId]);
}

/**
 * Przedłużenie po kolejnej opłaconej fakturze abonamentu.
 *
 * Liczymy od WIĘKSZEJ z dwóch dat: dotychczasowego wygaśnięcia albo teraz.
 * Przy płatności przed terminem klient nie może stracić dni, które ma
 * wykupione; przy płatności po karencji nie dostaje wstecz czasu, w którym
 * serwer i tak nie działał.
 */
function order_extend(PDO $dnct, int $orderId, int $days): void
{
    /* `suspended_at = NULL` JEST TU CELOWO. Bez tego opłacone przedłużenie
       zostawiało stary znacznik zawieszenia w bazie: serwer wracał do życia
       w Pterodactylu (provision_unsuspend() u wywołującego), ale
       lifecycle_delete() patrzy WYŁĄCZNIE na suspended_at + upływ czasu, nie
       na expires_at - więc automat kasował, po cichu, działający, opłacony
       serwer przy najbliższym przebiegu crona, bo jego zegar kasowania nigdy
       nie został zatrzymany. Ten sam powód co zerowanie grace_until obok. */
    $stmt = $dnct->prepare(
        'UPDATE orders
            SET expires_at = DATE_ADD(GREATEST(COALESCE(expires_at, NOW()), NOW()), INTERVAL ? DAY),
                suspended_at = NULL,
                grace_until = NULL,
                failure_reason = NULL,
                status = IF(status = "failed", status, "provisioned")
          WHERE id = ?'
    );
    $stmt->execute([$days, $orderId]);
}

/**
 * Nieudana płatność cykliczna - karencja zamiast natychmiastowego wyłączenia.
 *
 * Stripe sam ponawia obciążenie przez kilka dni. Zabranie serwera po pierwszej
 * odmowie banku ukarałoby klienta za rzecz, która najczęściej naprawia się
 * sama, a dane serwera znikają nieodwracalnie.
 */
function order_set_grace(PDO $dnct, int $orderId, int $graceHours, string $reason): void
{
    /* Karencję liczymy w GODZINACH od nieudanej płatności, nie w dniach od
       wygaśnięcia. Klientowi obiecujemy "masz 24 h na reakcję" i ta doba ma
       biec od chwili, w której dostał wiadomość - a nie od północy. */
    $stmt = $dnct->prepare(
        'UPDATE orders
            SET grace_until = DATE_ADD(NOW(), INTERVAL ? HOUR), failure_reason = ?
          WHERE id = ?'
    );
    $stmt->execute([$graceHours, mb_substr($reason, 0, 500), $orderId]);
}

/**
 * Koniec odnowień.
 *
 * NIE kasuje serwera i nie skraca `expires_at`: klient zapłacił za bieżący
 * okres i ma go dostać w całości. Wygaszaniem po terminie zajmuje się osobny
 * proces, nie webhook.
 */
function order_cancel_renewals(PDO $dnct, int $orderId): void
{
    $stmt = $dnct->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
    $stmt->execute([$orderId]);
}

/**
 * Zamyka zamówienie(a) po tym, jak serwer w Pterodactylu przestał istnieć -
 * bez względu na to, SKĄD przyszła wiadomość o skasowaniu (cykl życia co
 * godzinę wywołuje to w pętli dla wszystkich martwych ID, a webhook
 * api/internal/ptero-server-deleted.php - od razu, dla jednego).
 *
 * Jedna funkcja, dwa wywołania - inaczej reguła "kasowanie w Pterodactylu =
 * zamknij zamówienie i anuluj Stripe'a" rozjechałaby się w dwóch miejscach
 * przy pierwszej zmianie.
 *
 * CELOWO BEZ notify_user(): klient (albo admin, który skasował serwer) już
 * wie, że go nie ma. `notify_admins()` zostaje - to sygnał, że ktoś ominął
 * normalny cykl życia, więc subskrypcja mogła NIE zostać anulowana wcześniej.
 *
 * @return array{found:bool,order_id:?int,subscription_cancelled:?bool}
 */
function server_forget_by_ptero_id(PDO $dnct, int $pteroServerId): array
{
    $stmt = $dnct->prepare(
        'SELECT * FROM orders
          WHERE status = "provisioned"
            AND ptero_server_id = ?
            AND deleted_at IS NULL
          LIMIT 1'
    );
    $stmt->execute([$pteroServerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order === false) {
        return ['found' => false, 'order_id' => null, 'subscription_cancelled' => null];
    }

    $cancelled = lifecycle_cancel_subscription($order);

    $dnct->prepare('UPDATE orders SET deleted_at = NOW(), status = "cancelled" WHERE id = ?')
         ->execute([(int)$order['id']]);

    notify_admins($dnct, 'warning', 'Serwer zniknął z panelu poza cyklem życia',
        'Zamówienie #' . (int)$order['id'] . ' (' . (string)$order['user_email'] . '), serwer '
        . $pteroServerId . ' - ktoś skasował go ręcznie w Pterodactylu. '
        . 'Zamówienie oznaczone jako zakończone, klient NIE dostał o tym maila.'
        . ($cancelled ? '' : ' UWAGA: nie udało się potwierdzić anulowania subskrypcji Stripe - sprawdź ręcznie.'));

    return ['found' => true, 'order_id' => (int)$order['id'], 'subscription_cancelled' => $cancelled];
}
