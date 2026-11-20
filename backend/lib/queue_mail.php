<?php
/**
 * backend/lib/queue_mail.php
 *
 * Trzy wiadomości kolejki przedłużeń (patrz extension_queue.php): klient wszedł do
 * kolejki, serwer został uruchomiony, zwrot po braku miejsca. Wspólny szablon
 * `lifecycle`, z info@dnct.pl.
 */

declare(strict_types=1);

require_once __DIR__ . '/lifecycle_mail.php';
require_once __DIR__ . '/extension_queue.php';

function queue_mail_vars(array $order, string $header, string $pre, string $lead, string $label, string $value, string $note, array $kolor, array $bullets, string $cta, string $footer): array
{
    return [
        'accent' => $kolor[0], 'accentSoft' => $kolor[1], 'boxBg' => $kolor[2], 'boxBorder' => $kolor[3], 'boxFg' => $kolor[4],
        'headerNote' => $header, 'preheader' => $pre, 'userName' => (string)$order['user_name'], 'lead' => $lead,
        'boxLabel' => $label, 'boxValue' => $value, 'boxNote' => $note, 'bullets' => $bullets,
        'ctaUrl' => lifecycle_account_url(), 'ctaLabel' => $cta, 'footerNote' => $footer,
    ];
}

/** Płatność dotarła, ale na węźle nie ma miejsca: klient czeka w kolejce. */
function send_queued_mail(array $order, array $parent, int $position): array
{
    $game = htmlspecialchars(ucfirst((string)($parent['game_type'] ?? 'serwer')), ENT_QUOTES, 'UTF-8');
    $vars = queue_mail_vars(
        $order, 'Jesteś w kolejce', 'Uruchomimy serwer, gdy tylko zwolni się miejsce.',
        'dziękujemy za płatność. Niestety zostaliśmy wyprzedani i na węźle nie ma teraz miejsca dla serwera <strong>' . $game
        . '</strong>. Uruchomimy go <strong>od razu, gdy tylko zwolni się miejsce</strong>.',
        'Pozycja w kolejce', (string)$position, 'Kolejka działa wg kolejności wpłat.',
        ['#f59e0b', '#fef3c7', '#fffbeb', '#fcd34d', '#92400e'],
        [
            'Opłacony czas zacznie biec dopiero od uruchomienia serwera - nic Ci nie przepada.',
            'Dostaniesz mail i powiadomienie na stronie w chwili uruchomienia.',
            'Jeśli miejsce nie zwolni się w ciągu ' . QUEUE_REFUND_DAYS . ' dni, zwrócimy całą kwotę automatycznie.',
        ],
        'Zobacz stan', 'Twoje dane na serwerze są nietknięte.'
    );
    $text = "Cześć {$order['user_name']},\n\ndziękujemy za płatność. Niestety zostaliśmy wyprzedani - uruchomimy Twój serwer od razu, gdy zwolni się miejsce.\n"
        . "Pozycja w kolejce: {$position}\nOpłacony czas liczy się od uruchomienia. Po " . QUEUE_REFUND_DAYS . " dniach bez miejsca zwracamy pieniądze.\n\n"
        . lifecycle_account_url() . "\n\nDNCT Hosting";
    return send_mail((string)$order['user_email'], 'Jesteś w kolejce na uruchomienie serwera', render_mail_template('lifecycle', $vars), $text, 'info');
}

/** Miejsce się zwolniło: serwer uruchomiony, dni doliczone. */
function send_queue_started_mail(array $order, array $parent, string $until): array
{
    $game = htmlspecialchars(ucfirst((string)($parent['game_type'] ?? 'serwer')), ENT_QUOTES, 'UTF-8');
    $when = lifecycle_when($until);
    $vars = queue_mail_vars(
        $order, 'Serwer uruchomiony', 'Zwolniło się miejsce - Twój serwer już działa.',
        'dobra wiadomość: zwolniło się miejsce i serwer <strong>' . $game . '</strong> został uruchomiony. Dziękujemy za cierpliwość.',
        'Serwer działa do', $when, 'Opłacony okres liczymy od chwili uruchomienia.',
        ['#16a34a', '#dcfce7', '#f0fdf4', '#86efac', '#166534'],
        ['Wszystkie pliki i ustawienia są w tym samym stanie co przed zawieszeniem.', 'Możesz od razu wejść do panelu i włączyć serwer.'],
        'Otwórz serwery', 'Miłej gry!'
    );
    $text = "Cześć {$order['user_name']},\n\nzwolniło się miejsce - serwer został uruchomiony i działa do {$when}.\n\n" . lifecycle_account_url() . "\n\nDNCT Hosting";
    return send_mail((string)$order['user_email'], 'Twój serwer został uruchomiony', render_mail_template('lifecycle', $vars), $text, 'info');
}

/** Po QUEUE_REFUND_DAYS dniach bez miejsca: zwrot. */
function send_queue_refund_mail(array $order): array
{
    $vars = queue_mail_vars(
        $order, 'Zwrot pieniędzy', 'Nie udało się zwolnić miejsca - zwracamy płatność.',
        'przez ' . QUEUE_REFUND_DAYS . ' dni nie zwolniło się miejsce na uruchomienie Twojego serwera, więc <strong>zwróciliśmy całą kwotę</strong> za przedłużenie. Przepraszamy za kłopot.',
        'Zwrot', pricing_format((int)$order['amount_gross']) . ' zł', 'Pieniądze wrócą na kartę w ciągu kilku dni roboczych (zależy od banku).',
        ['#dc2626', '#fee2e2', '#fef2f2', '#fca5a5', '#991b1b'],
        ['Serwer i jego pliki zostają do terminu usunięcia - jeśli miejsce się zwolni, możesz przedłużyć ponownie.'],
        'Twoje serwery', 'Przepraszamy i dziękujemy za cierpliwość.'
    );
    $text = "Cześć {$order['user_name']},\n\nnie zwolniło się miejsce na Twój serwer, więc zwróciliśmy całą kwotę za przedłużenie. Przepraszamy.\n\nDNCT Hosting";
    return send_mail((string)$order['user_email'], 'Zwrot za przedłużenie serwera', render_mail_template('lifecycle', $vars), $text, 'info');
}
