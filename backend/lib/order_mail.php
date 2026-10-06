<?php
/**
 * Wiadomości o zamówieniach i płatnościach.
 *
 * Wysyłane z `info@dnct.pl` (profil `info` w backend/mail/mail.php), osobno od
 * `register@`, która obsługuje kody weryfikacyjne.
 *
 * NAJWAŻNIEJSZA RZECZ W TYCH WIADOMOŚCIACH TO DATA ZAWIESZENIA.
 *
 * Klient ma wiedzieć DOKŁADNIE, do kiedy serwer działa i co się stanie, jeśli
 * kolejna płatność nie dojdzie. "Pamiętaj o przedłużeniu" nie jest informacją -
 * człowiek, który to czyta, nie wie, czy ma czas do jutra, czy do przyszłego
 * miesiąca, i dowiaduje się dopiero wtedy, gdy serwer przestaje odpowiadać.
 *
 * Daty liczymy TU, w jednym miejscu, i te same wartości trafiają do wiadomości
 * i na stronę - rozbieżność między mailem a panelem to najszybszy sposób na
 * utratę zaufania w sprawach pieniężnych.
 */

declare(strict_types=1);

require_once __DIR__ . '/../mail/mail.php';
require_once __DIR__ . '/provisioning.php';

/**
 * Terminy dla zamówienia.
 *
 * @return array{expires:string,suspends:string,expires_raw:?string,suspends_raw:?string,grace_hours:int}
 */
function order_deadlines(array $order): array
{
    $expiresRaw = $order['expires_at'] ?? null;

    if ($expiresRaw === null) {
        return [
            'expires' => 'nieustalone', 'suspends' => 'nieustalone',
            'expires_raw' => null, 'suspends_raw' => null,
            'grace_hours' => PROVISION_GRACE_HOURS,
        ];
    }

    $expires = new DateTimeImmutable((string)$expiresRaw);

    /* KIEDY SERWER ZOSTANIE ZAWIESZONY.
       - Płatność jednorazowa: z chwilą wygaśnięcia. Nikt nie obciąży karty,
         więc nie ma na co czekać.
       - Abonament: Stripe obciąża w dniu wygaśnięcia, a przy odmowie klient
         dostaje dobę na poprawienie karty. Zabranie serwera w sekundzie po
         odmowie banku karałoby go za rzecz, która zwykle naprawia się sama. */
    $suspends = $order['billing_mode'] === 'subscription'
        ? $expires->modify('+' . PROVISION_GRACE_HOURS . ' hours')
        : $expires;

    return [
        'expires'      => $expires->setTimezone(new DateTimeZone(DNCT_TZ_USER))->format('d.m.Y \o H:i'),
        'suspends'     => $suspends->setTimezone(new DateTimeZone(DNCT_TZ_USER))->format('d.m.Y \o H:i'),
        'expires_raw'  => $expires->format('Y-m-d H:i:s'),
        'suspends_raw' => $suspends->format('Y-m-d H:i:s'),
        'grace_hours'  => PROVISION_GRACE_HOURS,
    ];
}

/**
 * Potwierdzenie płatności i uruchomienia serwera.
 *
 * $adminUser/$adminPassword: login i hasło administratora W GRZE (nie do
 * panelu ani strony) - tylko gry, których egg ma takie zmienne startowe
 * (dziś: Project Zomboid). NIE zapisujemy hasła nigdzie w bazie, więc ten
 * mail (i towarzyszące mu powiadomienie na stronie) to JEDYNE miejsce,
 * w którym klient może je zobaczyć.
 */
function send_order_paid_mail(array $order, ?string $adminUser = null, ?string $adminPassword = null): array
{
    $d = order_deadlines($order);

    $vars = [
        'userName'    => (string)$order['user_name'],
        'orderId'     => (int)$order['id'],
        'gameName'    => ucfirst((string)$order['game_type']),
        'packageName' => $order['slots'] !== null
            ? (int)$order['slots'] . ' slotów'
            : 'plan #' . (int)$order['plan_id'],
        'ramMb'       => (int)$order['ram_mb'],
        'cpuCores'    => rtrim(rtrim(number_format((float)$order['cpu_cores'], 1, '.', ''), '0'), '.'),
        'ssdMb'       => (int)$order['ssd_mb'],
        'amount'      => pricing_format((int)$order['amount_gross']),
        'isSub'       => $order['billing_mode'] === 'subscription',
        'expires'     => $d['expires'],
        'suspends'    => $d['suspends'],
        'graceHours'  => $d['grace_hours'],
        'panelUrl'    => 'https://panel.dnct.pl',
        'accountUrl'  => rtrim((string)env('BASE_URL', 'https://game.dnct.pl'), '/') . '/twoje-serwery',
        'adminUser'     => $adminUser,
        'adminPassword' => $adminPassword,
    ];

    $html = render_mail_template('order-paid', $vars);
    $text = order_paid_text($vars);

    return send_mail(
        (string)$order['user_email'],
        'Serwer gotowy - zamówienie #' . (int)$order['id'],
        $html,
        $text,
        'info'
    );
}

/**
 * Potwierdzenie zawarcia umowy wysyłamy zaraz po otrzymaniu płatności, zanim
 * uruchomimy usługę. Dzięki temu klient dostaje trwały zapis wersji warunków
 * i wyboru startu także wtedy, gdy wybrał uruchomienie po 14 dniach.
 */
function send_order_contract_confirmation_mail(array $order): array
{
    $legal = order_contract_confirmation_text($order);
    if ($legal === '') {
        return ['ok' => true, 'error' => ''];
    }

    $text = 'Cześć ' . (string)$order['user_name'] . ",\n\n"
        . 'Potwierdzamy zawarcie umowy i przyjęcie płatności za zamówienie #'
        . (int)$order['id'] . ".\n\n"
        . $legal
        . "\nDNCT Hosting";
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111">'
        . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>';

    return send_mail(
        (string)$order['user_email'],
        'Potwierdzenie umowy - zamówienie #' . (int)$order['id'],
        $html,
        $text,
        'info'
    );
}

/** Ostrzeżenie po nieudanej płatności cyklicznej. */
function send_payment_failed_mail(array $order): array
{
    $d = order_deadlines($order);

    $vars = [
        'userName'   => (string)$order['user_name'],
        'orderId'    => (int)$order['id'],
        'gameName'   => ucfirst((string)$order['game_type']),
        'amount'     => pricing_format((int)$order['amount_gross']),
        'suspends'   => $order['grace_until'] !== null
            ? czas_fmt((string)$order['grace_until'], 'd.m.Y \o H:i')
            : $d['suspends'],
        'graceHours' => $d['grace_hours'],
        'accountUrl' => rtrim((string)env('BASE_URL', 'https://game.dnct.pl'), '/') . '/twoje-serwery',
    ];

    $html = render_mail_template('payment-failed', $vars);
    $text = "Cześć {$vars['userName']},\n\n"
        . "nie udało się pobrać opłaty za serwer {$vars['gameName']} (zamówienie #{$vars['orderId']}, "
        . "{$vars['amount']} zł).\n\n"
        . "SERWER ZOSTANIE ZAWIESZONY: {$vars['suspends']}\n\n"
        . "Do tego czasu działa normalnie. Popraw dane karty albo przedłuż jednorazowo: {$vars['accountUrl']}\n\n"
        . "Zawieszenie nie kasuje danych - po opłaceniu serwer wraca w tym samym stanie.\n\n"
        . "DNCT Hosting";

    return send_mail(
        (string)$order['user_email'],
        'Nie udało się pobrać opłaty - serwer zostanie zawieszony ' . $vars['suspends'],
        $html,
        $text,
        'info'
    );
}

/** Wersja tekstowa potwierdzenia - czytana przez klientów bez HTML-a i przez filtry. */
function order_paid_text(array $v): string
{
    $renew = $v['isSub']
        ? "Abonament odnawia się sam. Jeśli kolejna płatność nie przejdzie, masz {$v['graceHours']} h na poprawienie karty,\n"
          . "a serwer zostanie zawieszony {$v['suspends']}."
        : "To płatność jednorazowa - serwer NIE odnowi się sam.\n"
          . "Jeśli nie przedłużysz, zostanie zawieszony {$v['suspends']}.";

    $credentials = ($v['adminUser'] !== null && $v['adminPassword'] !== null)
        ? "\nLOGIN ADMINISTRATORA W GRZE: {$v['adminUser']}\n"
          . "HASŁO: {$v['adminPassword']}\n"
          . "(zapisz to - nie pokażemy go drugi raz)\n"
        : '';

    return "Cześć {$v['userName']},\n\n"
        . "płatność {$v['amount']} zł została przyjęta, a serwer jest już uruchomiony.\n\n"
        . "Zamówienie #{$v['orderId']}\n"
        . "Gra: {$v['gameName']} ({$v['packageName']})\n"
        . "Zasoby: {$v['ramMb']} MB RAM, {$v['cpuCores']} vCore, {$v['ssdMb']} MB SSD\n"
        . $credentials . "\n"
        . "OPŁACONE DO: {$v['expires']}\n"
        . "ZAWIESZENIE: {$v['suspends']}\n\n"
        . $renew . "\n\n"
        . "Panel: {$v['panelUrl']}\n"
        . "Twoje serwery: {$v['accountUrl']}\n\n"
        . "DNCT Hosting";
}
