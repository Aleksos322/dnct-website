<?php
/**
 * Prośba o opiekę nad stroną na czacie DNCT (hosting WWW, "Strona pod opieką").
 *
 * Czat żyje na becie, a zamówienia i Stripe na produkcji. Produkcja woła api/internal-care.php (podpis Ed25519,
 * purpose "care") i zakłada rozmowę klient <-> opiekun z wiadomością systemową care_request. Opiekun klika
 * Tak/Nie w czacie (action care_decide), czat woła produkcyjne api/internal/care-decision.php wspólnym sekretem
 * CARE_BRIDGE_SECRET, a produkcja robi start pakietu albo zwrot. Stan trzymamy w payloadzie wiadomości.
 */
declare(strict_types=1);

const CARE_AUTO_REPLY = "Automatyczna odpowiedź: zwykle odpowiadam w ciągu 12 godzin. Jeśli jednak nie dostaniesz odpowiedzi, złóż ticket - przycisk w lewym dolnym rogu czatu.";

function chat_care_text(string $kind, array $p, bool $preview): string
{
    if ($kind === 'care_note') {
        return (string)($p['text'] ?? '');
    }
    $q = 'Cześć, jestem zainteresowany stroną pod twoją opieką, czy wyrażasz na to zgodę?';
    if ($preview) {
        return 'Prośba o opiekę nad stroną';
    }
    $price = (string)($p['price'] ?? '');
    $state = (string)($p['state'] ?? 'pending');
    $tail = match ($state) {
        'accepted' => 'Opiekun zaakceptował prośbę. Pakiet jest uruchamiany.',
        'rejected' => 'Opiekun odrzucił prośbę. Pieniądze wracają na kartę klienta.',
        default    => 'Płatność' . ($price !== '' ? ' (' . $price . ')' : '') . ' jest przyjęta. Czeka na decyzję opiekuna.',
    };
    return $q . "\n" . $tail;
}

/** Dane dla przeglądarki: stan i to, czy ten użytkownik może kliknąć Tak/Nie. */
function chat_care_row(array $system, array $actor, int $conversationId): ?array
{
    if ($system['kind'] !== 'care_request') {
        return null;
    }
    $p = json_decode((string)$system['payload'], true) ?: [];
    $state = (string)($p['state'] ?? 'pending');
    return [
        'order'      => (int)($p['order_id'] ?? 0),
        'conversation' => $conversationId,
        'state'      => $state,
        'can_decide' => $state === 'pending' && (string)($p['opiekun_id'] ?? '') === $actor['id'],
    ];
}

/** Wiadomość systemowa w rozmowie; autor musi być członkiem. Wywołuj w transakcji z zablokowaną rozmową. */
function chat_care_insert_system(PDO $db, int $conv, string $authorId, string $kind, array $payload): int
{
    $db->prepare('INSERT INTO chat_messages(conversation_id,author_id,body,client_key) VALUES(?,?,?,?)')
       ->execute([$conv, $authorId, '', bin2hex(random_bytes(32))]);
    $mid = (int)$db->lastInsertId();
    $db->prepare('INSERT INTO chat_system_messages(message_id,kind,payload) VALUES(?,?,?)')
       ->execute([$mid, $kind, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    return $mid;
}

/** Prośba klienta + automatyczna odpowiedź opiekuna. Idempotentna po numerze zamówienia. */
function chat_care_create_request(PDO $db, array $client, array $opiekun, int $orderId, string $price): array
{
    $conv = chat_start($db, $client, $opiekun);
    $db->beginTransaction();
    try {
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$conv]);
        $q = $db->prepare("SELECT m.id FROM chat_system_messages s JOIN chat_messages m ON m.id=s.message_id
                            WHERE m.conversation_id=? AND s.kind='care_request' AND JSON_EXTRACT(s.payload,'$.order_id')=?");
        $q->execute([$conv, $orderId]);
        if ($old = $q->fetchColumn()) {
            $db->commit();
            return ['conversation_id' => $conv, 'message_id' => (int)$old];
        }
        chat_request_on_send($db, $conv, $client['id']);
        $mid = chat_care_insert_system($db, $conv, $client['id'], 'care_request', [
            'order_id' => $orderId, 'state' => 'pending', 'price' => $price,
            'opiekun_id' => $opiekun['id'], 'client_id' => $client['id'],
        ]);
        // Odpowiedź opiekuna: zwykła wiadomość, więc rozmowa przechodzi w "accepted" i obie strony mogą pisać.
        chat_request_on_send($db, $conv, $opiekun['id']);
        $db->prepare('INSERT INTO chat_messages(conversation_id,author_id,body,client_key) VALUES(?,?,?,?)')
           ->execute([$conv, $opiekun['id'], CARE_AUTO_REPLY, bin2hex(random_bytes(32))]);
        $db->prepare('UPDATE chat_preferences SET archived=0,hidden=0 WHERE conversation_id=?')->execute([$conv]);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$conv]);
        chat_emit($db, $conv);
        $db->commit();
        return ['conversation_id' => $conv, 'message_id' => $mid];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/** Klik opiekuna. Decyzję podejmuje produkcja; tu tylko pośredniczymy i zapisujemy wynik w rozmowie. */
function chat_care_decide(PDO $db, int $convId, int $messageId, array $actor, string $decision): void
{
    if (!in_array($decision, ['accept', 'reject'], true)) {
        throw new InvalidArgumentException('Nieznana decyzja.');
    }
    chat_member($db, $convId, $actor['id']);
    $q = $db->prepare("SELECT s.payload FROM chat_system_messages s JOIN chat_messages m ON m.id=s.message_id
                        WHERE m.id=? AND m.conversation_id=? AND s.kind='care_request'");
    $q->execute([$messageId, $convId]);
    $p = json_decode((string)$q->fetchColumn(), true);
    if (!is_array($p) || (string)($p['opiekun_id'] ?? '') !== $actor['id']) {
        throw new DomainException('Tylko wskazany opiekun może zdecydować.');
    }
    if (($p['state'] ?? '') !== 'pending') {
        throw new DomainException('Ta prośba jest już rozstrzygnięta.');
    }

    $secret = (string)env('CARE_BRIDGE_SECRET', '');
    $url = (string)env('CARE_DECISION_URL', 'https://dnct.pl/api/internal/care-decision.php');
    if ($secret === '') {
        throw new RuntimeException('Decyzje opieki są chwilowo niedostępne.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['order_id' => (int)$p['order_id'], 'decision' => $decision, 'actor' => $actor['username']]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Internal-Secret: ' . $secret],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 40,
    ]);
    $out = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $j = is_string($out) ? json_decode($out, true) : null;
    if ($status !== 200 || !is_array($j) || empty($j['ok'])) {
        throw new DomainException((string)($j['message'] ?? 'Nie udało się zapisać decyzji. Spróbuj ponownie za chwilę.'));
    }

    $state = $decision === 'accept' ? 'accepted' : 'rejected';
    $db->beginTransaction();
    try {
        $db->prepare('SELECT id FROM chat_conversations WHERE id=? FOR UPDATE')->execute([$convId]);
        $p['state'] = $state;
        $db->prepare('UPDATE chat_system_messages SET payload=? WHERE message_id=?')->execute([json_encode($p, JSON_UNESCAPED_UNICODE), $messageId]);
        chat_care_insert_system($db, $convId, $actor['id'], 'care_note', ['text' => $state === 'accepted'
            ? 'Opiekun przyjął prośbę. Możecie tu rozmawiać o stronie.'
            : 'Opiekun nie przyjął prośby. Pełna kwota wraca na kartę klienta (zwykle 5-10 dni roboczych), subskrypcja została anulowana.']);
        $db->prepare('UPDATE chat_conversations SET updated_at=NOW() WHERE id=?')->execute([$convId]);
        chat_emit($db, $convId);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
