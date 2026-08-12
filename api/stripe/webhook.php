<?php
/**
 * POST /api/stripe/webhook.php — jedyne miejsce, w którym powstaje serwer.
 *
 * TRZY RZECZY, KTÓRYCH STARA WERSJA NIE MIAŁA, A BEZ KTÓRYCH TO NIE DZIAŁA:
 *
 * 1. WERYFIKACJA PODPISU. beta/webhook.php robiło `json_decode` na treści
 *    żądania i ufało jej. Adres webhooka jest publiczny, więc dowolna osoba
 *    mogła wysłać własne "zdarzenie" i dostać serwer za darmo. Tutaj treść
 *    przechodzi przez \Stripe\Webhook::constructEvent z sekretem podpisu —
 *    bez poprawnego nagłówka Stripe-Signature nie dzieje się nic.
 *
 * 2. IDEMPOTENCJA. Stripe POWTARZA doręczenia: po każdym błędzie, po każdym
 *    przekroczeniu czasu odpowiedzi, czasem bez powodu. Bez zabezpieczenia
 *    jedna płatność zakłada trzy serwery. Wiersz w `stripe_events` zakładamy
 *    PRZED obsługą, więc powtórka odbija się o klucz główny nawet wtedy, gdy
 *    pierwsze doręczenie jeszcze się liczy.
 *
 * 3. TWORZENIE SERWERA PO STRONIE SERWERA. Stara wersja robiła to w success.php,
 *    czyli przy WYŚWIETLENIU strony powrotu — kto zamknął kartę po zapłaceniu,
 *    nie dostawał nic, a kto odświeżył, mógł dostać drugi serwer.
 *
 * ODPOWIEDZI: 200 znaczy "przyjąłem, nie ponawiaj". 500 znaczy "spróbuj
 * jeszcze raz". Dlatego błąd bazy oddaje 500 (chcemy powtórki), a zdarzenie,
 * którym się nie zajmujemy — 200 (powtórka niczego nie zmieni).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/stripe.php';
require_once dirname(__DIR__, 2) . '/backend/lib/provisioning.php';
require_once dirname(__DIR__, 2) . '/backend/lib/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('{"error":"method_not_allowed"}');
}

/* Surowa treść żądania, nie request_payload(). Podpis liczony jest z BAJTÓW —
   każde przepuszczenie ich przez dekodowanie i ponowne zakodowanie zmienia
   choćby kolejność kluczy i podpis przestaje się zgadzać. */
$payload   = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    stripe_init();
    $event = \Stripe\Webhook::constructEvent($payload, $signature, stripe_webhook_secret());
} catch (\UnexpectedValueException $e) {
    error_log('Stripe webhook: nieczytelna treść — ' . $e->getMessage());
    http_response_code(400);
    exit('{"error":"invalid_payload"}');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Najczęstsza przyczyna to zły sekret w .env, ale to samo widać przy
    // próbie podszycia się. W obu przypadkach: nie ruszamy niczego.
    error_log('Stripe webhook: podpis się nie zgadza — ' . $e->getMessage());
    http_response_code(400);
    exit('{"error":"invalid_signature"}');
} catch (Throwable $e) {
    error_log('Stripe webhook: konfiguracja — ' . $e->getMessage());
    http_response_code(500);
    exit('{"error":"misconfigured"}');
}

$dnct = dnct_db();

/* Zamek idempotencji. INSERT, a nie SELECT-potem-INSERT: sprawdzenie i zapis
   w dwóch krokach zostawia okno, w które trafiają dwa równoległe doręczenia
   tego samego zdarzenia — a Stripe potrafi je wysłać dokładnie tak. */
try {
    $lock = $dnct->prepare('INSERT INTO stripe_events (event_id, type) VALUES (?, ?)');
    $lock->execute([$event->id, $event->type]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // Już widzieliśmy to zdarzenie. 200, żeby Stripe przestał ponawiać.
        echo '{"ok":true,"duplicate":true}';
        exit;
    }
    error_log('Stripe webhook: baza niedostępna — ' . $e->getMessage());
    http_response_code(500);
    exit('{"error":"database"}');
}

try {
    $handled = webhook_dispatch($dnct, $event);

    $done = $dnct->prepare('UPDATE stripe_events SET status = "done", handled_at = NOW() WHERE event_id = ?');
    $done->execute([$event->id]);

    echo json_encode(['ok' => true, 'handled' => $handled]);
} catch (Throwable $e) {
    error_log('Stripe webhook: błąd obsługi ' . $event->type . ' (' . $event->id . ') — ' . $e->getMessage());

    /* Zdarzenie zostaje oznaczone jako błędne, ale WIERSZ ZOSTAJE — inaczej
       powtórka trafiłaby na czysto i mogła powtórzyć część, która się udała.
       Zwracamy 500, więc Stripe spróbuje ponownie; przy powtórce zadziała
       blokada powyżej, a ślad błędu zostaje do ręcznego rozpatrzenia. */
    $fail = $dnct->prepare('UPDATE stripe_events SET status = "error", error = ?, handled_at = NOW() WHERE event_id = ?');
    $fail->execute([mb_substr($e->getMessage(), 0, 500), $event->id]);

    http_response_code(500);
    echo '{"error":"handler_failed"}';
}

exit;


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

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'success',
        'Serwer jest gotowy',
        'Twój serwer został utworzony i czeka w panelu. Miłej gry!'
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

    notify_user(
        $dnct,
        (string)$order['user_name'],
        'warning',
        'Nie udało się pobrać opłaty',
        'Bank odrzucił płatność za serwer. Masz ' . PROVISION_GRACE_DAYS
        . ' dni na poprawienie danych karty — przez ten czas serwer działa normalnie.'
    );

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
