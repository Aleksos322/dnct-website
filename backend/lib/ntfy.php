<?php
/**
 * Powiadomienia na telefon admina przez własny serwer ntfy (ntfy.dnct.pl, CT 7510).
 *
 * Trzy kanały, żeby na telefonie dało się wyciszyć mniej ważne:
 *   awarie   - coś nie działa i dotyczy klienta (priorytet 4-5, dzwoni)
 *   sprzedaz - zakup, przedłużenie, zmiana pakietu, anulowanie, zgłoszenie
 *   raporty  - VPN, SEO, podsumowania (po cichu)
 *
 * Wysyłka NIGDY nie przerywa wywołującego - telefon jest dodatkiem do dzwonka
 * na stronie, a webhook Stripe'a nie może dostać 500 dlatego, że ntfy leży.
 * Bez NTFY_TOKEN w .env funkcja nic nie robi.
 *
 * Beta ma w beta.env NTFY_LABEL=BETA - tytuł dostaje przedrostek [BETA],
 * żeby testowy zakup nie wyglądał na prawdziwe pieniądze.
 */

declare(strict_types=1);

const NTFY_TOPICS = ['awarie' => 'dnct-awarie', 'sprzedaz' => 'dnct-sprzedaz', 'raporty' => 'dnct-raporty'];

/**
 * @param string $channel  awarie | sprzedaz | raporty
 * @param int    $priority 1 (min) .. 5 (max)
 * @param string $tags     emoji ntfy po przecinku, np. "moneybag" albo "rotating_light"
 * @param string $click    adres otwierany po stuknięciu w powiadomienie
 */
function ntfy_send(string $channel, string $title, string $message, int $priority = 3, string $tags = '', string $click = ''): bool
{
    $token = (string)env('NTFY_TOKEN', '');
    $topic = NTFY_TOPICS[$channel] ?? null;
    if ($token === '' || $topic === null) {
        return false;
    }

    $label = (string)env('NTFY_LABEL', '');
    if ($label !== '') {
        $title = '[' . $label . '] ' . $title;
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        // Nagłówki HTTP są ASCII - polskie znaki w tytule przechodzą jako RFC 2047.
        'Title: =?UTF-8?B?' . base64_encode($title) . '?=',
        'Priority: ' . max(1, min(5, $priority)),
    ];
    if ($tags !== '') {
        $headers[] = 'Tags: ' . $tags;
    }
    if ($click !== '') {
        $headers[] = 'Click: ' . $click;
    }

    $ch = curl_init(rtrim((string)env('NTFY_URL', 'http://127.0.0.1:2586'), '/') . '/' . $topic);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => mb_substr($message, 0, 3000),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 4,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log('ntfy: ' . $topic . ' HTTP ' . $code . ' ' . mb_substr((string)$body, 0, 200));
        return false;
    }
    return true;
}

/** Dzwonek adminów na stronie -> telefon. Typ powiadomienia wyznacza kanał i głośność. */
function ntfy_from_admin_notification(string $type, string $title, string $message, array $opts = []): void
{
    $map = [
        'error'   => ['awarie', 5, 'rotating_light'],
        'warning' => ['awarie', 4, 'warning'],
    ];
    [$channel, $priority, $tags] = $opts['ntfy'] ?? $map[$type] ?? ['sprzedaz', 3, 'information_source'];
    ntfy_send($channel, $title, $message, $priority, $tags, (string)($opts['link'] ?? ''));
}
