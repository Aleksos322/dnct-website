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
require_once dirname(__DIR__, 2) . '/backend/lib/stripe_events.php';

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
