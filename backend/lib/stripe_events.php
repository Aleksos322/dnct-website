<?php
/**
 * Obsluga zdarzen Stripe - wspolna dla webhooka i narzedzia naprawczego.
 *
 * Wyodrebnione z api/stripe/webhook.php, bo TE SAME zdarzenia trzeba umiec
 * przetworzyc dwiema drogami:
 *
 *   1. na biezaco, gdy Stripe doreczy je pod adres webhooka,
 *   2. z opoznieniem, gdy doreczenie sie nie udalo - wtedy pobieramy zdarzenia
 *      z API i puszczamy przez te sama logike (backend/tools/reconcile-stripe.php).
 *
 * Gdyby narzedzie naprawcze mialo wlasna kopie obslugi, po pierwszej poprawce
 * w jednym miejscu obie sciezki zaczelyby robic co innego - a roznice widac
 * dopiero wtedy, gdy komus nie powstaje serwer po zaplacie.
 */

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/provisioning.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/order_mail.php';

/**
 * Rozdzielenie zdarzeń.
 *
 * @return bool czy zdarzenie nas dotyczyło
 */
function webhook_dispatch(PDO $dnct, \Stripe\Event $event): bool
{
    switch ($event->type) {
        /* Płatność kartą kończy sesję od razu. BLIK i Przelewy24 to metody
           z opóźnionym potwierdzeniem — dla nich sesja domyka się dopiero
           zdarzeniem async_payment_succeeded. Obsługa jest ta sama, bo w obu
           przypadkach warunkiem jest payment_status = paid. */
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            return webhook_checkout_completed($dnct, $event->data->object);

        case 'invoice.paid':
            return webhook_invoice_paid($dnct, $event->data->object);

        case 'invoice.payment_failed':
            return webhook_invoice_failed($dnct, $event->data->object);

        case 'customer.subscription.deleted':
            return webhook_subscription_deleted($dnct, $event->data->object);

        default:
            return false;
    }
}

/** Opłacona sesja — oznaczamy zamówienie i zakładamy serwer. */
function webhook_checkout_completed(PDO $dnct, $session): bool
{
    /* Sesja bywa "completed" bez zapłaty (metody z opóźnionym potwierdzeniem).
       Serwer należy się dopiero po pieniądzach, nie po domknięciu formularza. */
    if (($session->payment_status ?? '') !== 'paid') {
        error_log('Stripe webhook: sesja ' . $session->id . ' domknięta bez zapłaty — czekam na potwierdzenie');
        return true;
    }

    $order = order_by_session($dnct, (string)$session->id);

    if ($order === null && !empty($session->metadata->order_id)) {
        // Zapas na wypadek, gdyby dopięcie identyfikatora sesji do zamówienia
        // nie doszło do skutku (np. sieć padła tuż po utworzeniu sesji).
        $order = order_by_id($dnct, (int)$session->metadata->order_id);
    }

    if ($order === null) {
        error_log('Stripe webhook: brak zamówienia dla sesji ' . $session->id);
        return true;
    }

    if (in_array($order['status'], ['provisioned', 'cancelled'], true)) {
        return true;   // już obsłużone
    }

    /* PRZEDŁUŻENIE, nie nowy zakup. Serwer już istnieje — dokładamy dni do
       zamówienia-rodzica i, jeśli trzeba, zdejmujemy zawieszenie. Tworzenie
       serwera tutaj dałoby klientowi drugi serwer zamiast dłuższego czasu na
       tym, który ma. */
    if (!empty($order['extends_order_id'])) {
        return webhook_extension_paid($dnct, $order);
    }

    order_mark_paid(
        $dnct,
        (int)$order['id'],
        isset($session->customer) ? (string)$session->customer : null,
        isset($session->subscription) ? (string)$session->subscription : null,
        (int)$order['period_days']
    );

    // Świeży odczyt: order_mark_paid ustawił daty, a provisioning opisuje nimi
    // serwer.
    $order  = order_by_id($dnct, (int)$order['id']);
    $result = provision_server($order);

    if (!$result['ok']) {
        order_mark_failed($dnct, (int)$order['id'], (string)$result['error']);

        /* Pieniądze są u nas, usługi nie ma. To sytuacja wymagająca człowieka,
           więc powiadamiamy OBIE strony: klienta, żeby nie czekał w
           nieskończoność, i administrację, żeby ktoś to podjął. */
        notify_user(
            $dnct,
            (string)$order['user_name'],
            'error',
            'Serwer nie został jeszcze utworzony',
            'Płatność się powiodła, ale automatyczne zakładanie serwera nie doszło do skutku. '
            . 'Zajmiemy się tym ręcznie — napisz na Discordzie, jeśli chcesz przyspieszyć.'
        );
        notify_admins(
            $dnct,
            'error',
            'Zamówienie #' . $order['id'] . ' opłacone, serwer NIE utworzony',
            (string)$result['error']
        );

        // Zwracamy prawdę, nie wyjątek: powtórka doręczenia niczego nie naprawi,
        // bo problem jest po stronie Pterodactyla, a nie doręczenia zdarzenia.
        return true;
    }

    order_mark_provisioned(
        $dnct,
        (int)$order['id'],
        (int)$result['ptero_user_id'],
        (int)$result['server_id'],
        (string)$result['uuid']
    );

    order_announce_paid($dnct, order_by_id($dnct, (int)$order['id']));

    return true;
}

/**
 * Powiadomienie o gotowym serwerze — na stronie ORAZ mailem z info@dnct.pl.
 *
 * Oba kanały niosą TĘ SAMĄ datę zawieszenia, liczoną w jednym miejscu
 * (order_deadlines). Rozbieżność między mailem a panelem w sprawie terminu
 * płatności to najszybszy sposób na utratę zaufania.
 *
 * Nieudana wysyłka maila NIE wywraca obsługi zdarzenia: serwer już działa,
 * a Stripe ponawiałby doręczenie w nieskończoność, próbując naprawić coś,
 * co jest problemem poczty, a nie płatności.
 */
function order_announce_paid(PDO $dnct, ?array $order): void
{
    if ($order === null) {
        return;
    }

    $d = order_deadlines($order);

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'success',
        'Serwer jest gotowy',
        'Opłacone do ' . $d['expires'] . '. '
        . ($order['billing_mode'] === 'subscription'
            ? 'Abonament odnawia się sam — przy nieudanej płatności serwer zostanie zawieszony ' . $d['suspends'] . '.'
            : 'To płatność jednorazowa — bez przedłużenia serwer zostanie zawieszony ' . $d['suspends'] . '.')
    );

    try {
        $sent = send_order_paid_mail($order);
        if (!$sent['ok']) {
            error_log('Zamówienie #' . $order['id'] . ': mail potwierdzający nie wyszedł — ' . $sent['error']);
        }
    } catch (Throwable $e) {
        error_log('Zamówienie #' . $order['id'] . ': błąd wysyłki potwierdzenia — ' . $e->getMessage());
    }
}

/**
 * Opłacone przedłużenie — dokładamy dni do serwera, który już działa.
 *
 * Samo zamówienie-przedłużenie oznaczamy jako `provisioned`, mimo że niczego
 * nie tworzy. To stan końcowy "zrobione" i dzięki temu strona powrotu ma co
 * pokazać, a lista zamówień nie zostaje z wiecznym „w trakcie".
 */
function webhook_extension_paid(PDO $dnct, array $order): bool
{
    $parent = order_by_id($dnct, (int)$order['extends_order_id']);

    if ($parent === null) {
        error_log('Stripe webhook: przedłużenie #' . $order['id'] . ' bez zamówienia nadrzędnego');
        order_mark_failed($dnct, (int)$order['id'], 'Brak zamówienia nadrzędnego.');
        return true;
    }

    order_extend($dnct, (int)$parent['id'], (int)$order['period_days']);

    if (!empty($parent['ptero_server_id'])) {
        provision_unsuspend((int)$parent['ptero_server_id']);
    }

    $dnct->prepare('UPDATE orders SET status = "provisioned", expires_at = NOW() WHERE id = ?')
         ->execute([(int)$order['id']]);

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'success',
        'Serwer przedłużony',
        'Dopisaliśmy kolejny okres — serwer działa dalej bez przerwy.'
    );

    return true;
}

/**
 * Kolejna opłacona faktura abonamentu — przedłużenie.
 *
 * PIERWSZĄ fakturę pomijamy. Powstaje razem z subskrypcją i dotyczy okresu,
 * który zaliczyło już checkout.session.completed — bez tego warunku pierwszy
 * zakup dawałby klientowi podwójny okres.
 */
function webhook_invoice_paid(PDO $dnct, $invoice): bool
{
    if (($invoice->billing_reason ?? '') === 'subscription_create') {
        return true;
    }

    $subscriptionId = (string)($invoice->subscription ?? '');
    if ($subscriptionId === '') {
        return true;
    }

    $order = order_by_subscription($dnct, $subscriptionId);
    if ($order === null) {
        error_log('Stripe webhook: brak zamówienia dla subskrypcji ' . $subscriptionId);
        return true;
    }

    order_extend($dnct, (int)$order['id'], (int)$order['period_days']);

    /* Serwer mógł zostać zawieszony po nieudanej płatności. Skoro zaległość
       jest opłacona, przywracamy go od razu — czekanie na ręczne odwieszenie
       oznaczałoby, że klient zapłacił i nadal nie gra. */
    if (!empty($order['ptero_server_id'])) {
        provision_unsuspend((int)$order['ptero_server_id']);
    }

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'success',
        'Abonament przedłużony',
        'Płatność się powiodła — serwer działa dalej przez kolejny okres.'
    );

    return true;
}

/** Nieudane obciążenie — karencja zamiast natychmiastowego wyłączenia. */
function webhook_invoice_failed(PDO $dnct, $invoice): bool
{
    $subscriptionId = (string)($invoice->subscription ?? '');
    if ($subscriptionId === '') {
        return true;
    }

    $order = order_by_subscription($dnct, $subscriptionId);
    if ($order === null) {
        return true;
    }

    order_set_grace(
        $dnct,
        (int)$order['id'],
        PROVISION_GRACE_DAYS,
        'Nieudana płatność cykliczna (faktura ' . ($invoice->id ?? '?') . ')'
    );

    /* Świeży odczyt: order_set_grace dopiero co ustawiło grace_until, a to
       z niego liczy się data zawieszenia podawana klientowi. */
    $order = order_by_id($dnct, (int)$order['id']);
    $when  = $order['grace_until'] !== null
        ? (new DateTimeImmutable((string)$order['grace_until']))->format('d.m.Y \o H:i')
        : order_deadlines($order)['suspends'];

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'warning',
        'Nie udało się pobrać opłaty',
        'Bank odrzucił płatność za serwer. Serwer zostanie zawieszony ' . $when
        . ' — do tego czasu działa normalnie. Popraw dane karty albo przedłuż jednorazowo.'
    );

    try {
        $sent = send_payment_failed_mail($order);
        if (!$sent['ok']) {
            error_log('Zamówienie #' . $order['id'] . ': ostrzeżenie o płatności nie wyszło — ' . $sent['error']);
        }
    } catch (Throwable $e) {
        error_log('Zamówienie #' . $order['id'] . ': błąd wysyłki ostrzeżenia — ' . $e->getMessage());
    }

    return true;
}

/**
 * Koniec subskrypcji.
 *
 * NIE wyłączamy serwera. Klient zapłacił za bieżący okres i ma go dostać
 * w całości — wygaszaniem po `expires_at` zajmuje się osobny proces, który
 * patrzy na daty, a nie na to zdarzenie.
 */
function webhook_subscription_deleted(PDO $dnct, $subscription): bool
{
    $order = order_by_subscription($dnct, (string)$subscription->id);
    if ($order === null) {
        return true;
    }

    order_cancel_renewals($dnct, (int)$order['id']);

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'info',
        'Abonament nie będzie odnawiany',
        'Serwer działa do końca opłaconego okresu. Możesz go wznowić w każdej chwili.'
    );

    return true;
}
