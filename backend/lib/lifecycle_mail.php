<?php
/**
 * backend/lib/lifecycle_mail.php
 *
 * Cztery wiadomości z cyklu życia serwera: ostrzeżenie o wygaśnięciu,
 * zawieszenie, gotowa paczka z plikami, usunięcie.
 *
 * ZASADA: każda z nich podaje DOKŁADNĄ datę następnego kroku. Klient ma po
 * przeczytaniu wiedzieć, co się stanie i kiedy - "wkrótce" albo "niebawem"
 * nie jest informacją, tylko sposobem na uniknięcie odpowiedzialności za
 * termin. Skoro automat zna tę datę co do minuty, nie ma powodu jej ukrywać.
 *
 * Wszystkie idą z `info@dnct.pl` i korzystają ze wspólnego szablonu
 * `lifecycle` - różnią się zestawem zmiennych, nie osobnym HTML-em.
 */

declare(strict_types=1);

require_once __DIR__ . '/../mail/mail.php';
require_once __DIR__ . '/server_archive.php';

/** Data w formie, w jakiej czyta ją człowiek. */
function lifecycle_when(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return 'nieustalone';
    }

    return czas_fmt($raw, 'd.m.Y \o H:i');
}

function lifecycle_account_url(): string
{
    return rtrim((string)env('BASE_URL', 'https://dnct.pl'), '/') . '/twoje-serwery';
}

/**
 * Ostrzeżenie przed wygaśnięciem - 7 dni i 24 godziny przed terminem.
 *
 * Tylko dla płatności jednorazowej. Abonament odnawia się sam, więc takie
 * ostrzeżenie byłoby straszeniem klienta czymś, czego nie musi robić.
 */
function send_expiry_warning_mail(array $order, bool $isFinalCall): array
{
    $when = lifecycle_when($order['expires_at'] ?? null);
    $game = ucfirst((string)$order['game_type']);

    $vars = [
        'accent'     => $isFinalCall ? '#f59e0b' : '#8b5cf6',
        'accentSoft' => $isFinalCall ? '#fef3c7' : '#ede9fe',
        'headerNote' => $isFinalCall ? 'Zostały 24 godziny' : 'Serwer wygasa za tydzień',
        'preheader'  => 'Serwer ' . $game . ' działa do ' . $when . '.',
        'userName'   => (string)$order['user_name'],
        'lead'       => $isFinalCall
            ? 'przypominamy, że opłacony okres serwera <strong>' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8')
              . '</strong> kończy się jutro. To płatność jednorazowa, więc serwer <strong>nie odnowi się sam</strong>.'
            : 'opłacony okres serwera <strong>' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8')
              . '</strong> kończy się za tydzień. Piszemy z wyprzedzeniem, żebyś nie musiał tego robić w pośpiechu.',
        'boxLabel'   => 'Serwer działa do',
        'boxValue'   => $when,
        'boxNote'    => 'Po tym terminie zostanie zawieszony. Dane pozostaną nietknięte.',
        'boxBg'      => $isFinalCall ? '#fffbeb' : '#f5f3ff',
        'boxBorder'  => $isFinalCall ? '#fcd34d' : '#c4b5fd',
        'boxFg'      => $isFinalCall ? '#92400e' : '#5b21b6',
        'bullets'    => [
            'Przedłużenie zajmuje chwilę - wybierasz okres i płacisz.',
            'Możesz przy okazji przejść na abonament, żeby nie pamiętać o tym co miesiąc.',
        ],
        'ctaUrl'     => lifecycle_account_url(),
        'ctaLabel'   => 'Przedłuż serwer',
        'footerNote' => 'Nie musisz nic robić, jeśli nie chcesz kontynuować - serwer wygaśnie sam.',
    ];

    $text = "Cześć {$vars['userName']},\n\n"
        . ($isFinalCall
            ? "opłacony okres serwera {$game} kończy się jutro.\n"
            : "opłacony okres serwera {$game} kończy się za tydzień.\n")
        . "To płatność jednorazowa - serwer NIE odnowi się sam.\n\n"
        . "SERWER DZIAŁA DO: {$when}\n\n"
        . "Przedłużenie: " . lifecycle_account_url() . "\n\n"
        . "DNCT Hosting";

    return send_mail(
        (string)$order['user_email'],
        $isFinalCall
            ? 'Serwer wygasa jutro - ' . $when
            : 'Serwer wygasa za tydzień - ' . $when,
        render_mail_template('lifecycle', $vars),
        $text,
        'info'
    );
}

/**
 * Serwer właśnie został zawieszony - i to jest wiadomość, która niesie paczkę
 * z plikami.
 *
 * Jedna przesyłka, nie dwie. Zawieszenie i link do plików to dla klienta jedna
 * sprawa; rozbicie jej na dwa maile znaczyłoby, że dostaje je w losowej
 * kolejności i musi sam się domyślić, że dotyczą tego samego serwera.
 *
 * Link zamiast załącznika, bo paczki ważą setki megabajtów - żaden serwer
 * pocztowy takiej wiadomości nie przepuści. Patrz backend/lib/server_archive.php.
 */
function send_suspended_mail(array $order, string $deleteAtRaw, ?array $archive = null): array
{
    $when = lifecycle_when($deleteAtRaw);
    $game = ucfirst((string)$order['game_type']);

    $hasFiles = $archive !== null;
    $size     = $hasFiles ? archive_human_size((int)$archive['bytes']) : '';
    $linkTill = $hasFiles ? lifecycle_when((string)$archive['expires_at']) : '';

    $bullets = $hasFiles
        ? [
            'Paczka z plikami serwera (' . $size . ') czeka pod przyciskiem - świat, konfiguracja i wtyczki.',
            'Link działa do ' . $linkTill . '. Format tar.gz, rozpakujesz go 7-Zipem albo WinRAR-em.',
            'Opłacenie przedłużenia przed terminem usunięcia cofa zawieszenie - nic nie tracisz.',
        ]
        : [
            'Opłacenie przedłużenia przed tym terminem cofa zawieszenie, nic nie tracisz.',
            'Jeśli potrzebujesz kopii plików, napisz do nas - przygotujemy ją ręcznie.',
        ];

    $vars = [
        'accent'     => '#dc2626',
        'accentSoft' => '#fee2e2',
        'headerNote' => 'Serwer zawieszony',
        'preheader'  => 'Masz czas do ' . $when . ' na przywrócenie serwera.',
        'userName'   => (string)$order['user_name'],
        'lead'       => 'opłacony okres serwera <strong>' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8')
            . '</strong> dobiegł końca, więc serwer został zawieszony. Dziękujemy za czas, przez który'
            . ' nam zaufałeś - i za to, że trzymałeś go właśnie u nas.'
            . ($hasFiles ? ' Pliki spakowaliśmy, żebyś nie stracił niczego, co na nim zbudowałeś.' : ''),
        'boxLabel'   => 'Serwer zostanie usunięty',
        'boxValue'   => $when,
        'boxNote'    => 'Do tego momentu wystarczy opłacić przedłużenie, żeby wrócił w tym samym stanie.',
        'boxBg'      => '#fef2f2',
        'boxBorder'  => '#fca5a5',
        'boxFg'      => '#991b1b',
        'bullets'    => $bullets,
        'ctaUrl'     => $hasFiles ? archive_download_url($archive) : lifecycle_account_url(),
        'ctaLabel'   => $hasFiles ? 'Pobierz pliki serwera' : 'Przywróć serwer',
        'footerNote' => $hasFiles
            ? 'Jeśli zdecydujesz się wrócić, wgramy tę paczkę na nowy serwer - napisz do nas.'
            : 'Po usunięciu serwera odzyskanie danych nie będzie już możliwe.',
    ];

    $text = "Cześć {$vars['userName']},\n\n"
        . "opłacony okres serwera {$game} dobiegł końca - serwer został zawieszony.\n"
        . "Dziękujemy za zaufanie.\n\n"
        . "SERWER ZOSTANIE USUNIĘTY: {$when}\n\n"
        . ($hasFiles
            ? "PLIKI SERWERA ({$size}): " . archive_download_url($archive) . "\n"
              . "Link działa do: {$linkTill}\n\n"
            : "")
        . "Do momentu usunięcia przedłużenie przywraca serwer w tym samym stanie:\n"
        . lifecycle_account_url() . "\n\n"
        . "DNCT Hosting";

    return send_mail(
        (string)$order['user_email'],
        'Serwer zawieszony - usunięcie ' . $when,
        render_mail_template('lifecycle', $vars),
        $text,
        'info'
    );
}

/** Serwer został usunięty z panelu. Ostatnia wiadomość w tej sprawie. */
function send_deleted_mail(array $order, ?array $archive): array
{
    $game = ucfirst((string)$order['game_type']);
    $url  = $archive !== null ? archive_download_url($archive) : lifecycle_account_url();

    $vars = [
        'accent'     => '#4b5563',
        'accentSoft' => '#e5e7eb',
        'headerNote' => 'Serwer usunięty',
        'preheader'  => 'Maszyna zniknęła z panelu. Paczka z plikami jest nadal do pobrania.',
        'userName'   => (string)$order['user_name'],
        'lead'       => 'serwer <strong>' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8')
            . '</strong> został usunięty z panelu, zgodnie z terminem podanym w poprzedniej wiadomości.'
            . ' Nie pobieramy już za niego żadnych opłat.',
        'boxLabel'   => $archive !== null ? 'Paczka z plikami' : 'Zamówienie',
        'boxValue'   => $archive !== null ? 'nadal do pobrania' : '#' . (int)$order['id'],
        'boxNote'    => $archive !== null
            ? 'Link działa do ' . lifecycle_when((string)$archive['expires_at']) . '.'
            : '',
        'boxBg'      => '#f9fafb',
        'boxBorder'  => '#d1d5db',
        'boxFg'      => '#374151',
        'bullets'    => [
            'Konto w panelu zostaje - możesz w każdej chwili zamówić nowy serwer.',
            'Jeśli wrócisz, wgramy Twoją paczkę na nową maszynę.',
        ],
        'ctaUrl'     => $url,
        'ctaLabel'   => $archive !== null ? 'Pobierz pliki serwera' : 'Zamów nowy serwer',
        'footerNote' => 'Dziękujemy za czas spędzony z DNCT. Drzwi zostają otwarte.',
    ];

    $text = "Cześć {$vars['userName']},\n\n"
        . "serwer {$game} został usunięty z panelu zgodnie z podanym terminem.\n"
        . "Nie pobieramy już za niego żadnych opłat.\n\n"
        . ($archive !== null
            ? "Paczka z plikami jest nadal do pobrania:\n{$url}\n(link działa do "
              . lifecycle_when((string)$archive['expires_at']) . ")\n\n"
            : "")
        . "Dziękujemy za zaufanie.\n\n"
        . "DNCT Hosting";

    return send_mail(
        (string)$order['user_email'],
        'Serwer usunięty - zamówienie #' . (int)$order['id'],
        render_mail_template('lifecycle', $vars),
        $text,
        'info'
    );
}
