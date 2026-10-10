<?php
/**
 * Wysyłka poczty przez SMTP (Mailcow).
 *
 * Moduł wielokrotnego użytku - sam nic nie wypisuje i niczego nie renderuje.
 * Cała konfiguracja pochodzi z .env (SMTP_*), więc w kodzie nie ma już
 * zaszytego hosta ani hasła.
 *
 * Użycie:
 *     require_once .../backend/mail/mail.php;
 *     send_verification_code_mail('jan@example.com', '123456', 'Jan');
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/lib/env.php';

/**
 * Skonfigurowana instancja PHPMailer gotowa do wysyłki.
 *
 * @throws Exception gdy konfiguracja SMTP jest niekompletna
 */
function mailer_factory(string $profile = 'default'): PHPMailer
{
    $mail = new PHPMailer(true);

    // Debug włączamy wyłącznie zmienną z .env (SMTP_DEBUG=1) - nigdy na stałe,
    // bo wypisuje surowy dialog SMTP wprost do odpowiedzi HTTP.
    $mail->SMTPDebug = env('SMTP_DEBUG') === '1' ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
    $mail->Debugoutput = static function (string $message): void {
        error_log('SMTP: ' . $message);
    };

    $host = (string)env('SMTP_HOST', 'localhost');
    $encryption = strtolower((string)env('SMTP_ENCRYPTION', 'tls'));

    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = env_int('SMTP_PORT', $encryption === 'ssl' ? 465 : 587);

    /* LOGOWANIE A NADAWCA TO DWIE RÓŻNE RZECZY.
       Skrzynka `info@` używa własnych danych logowania TYLKO wtedy, gdy ma
       ustawione OBA: login i hasło. Sam login z hasłem konta głównego to
       gwarantowane "SMTP Error: Could not authenticate" - i dokładnie tak
       padła pierwsza wysyłka potwierdzenia zamówienia.

       Bez własnego hasła logujemy się kontem głównym i zmieniamy wyłącznie
       nagłówek From. Mailcow na to pozwala, o ile konto ma nadane prawo
       wysyłki w imieniu tego adresu; jeśli nie ma, odrzuci wiadomość z błędem
       o nadawcy, który zobaczymy w logu. */
    $infoUser = (string)env('SMTP_INFO_USER', '');
    $infoPass = (string)env('SMTP_INFO_PASSWORD', '');
    $useInfoCredentials = $profile === 'info' && $infoUser !== '' && $infoPass !== '';

    $user     = $useInfoCredentials ? $infoUser : (string)env('SMTP_USER', '');
    $password = $useInfoCredentials ? $infoPass : (string)env('SMTP_PASSWORD', '');

    $mail->SMTPAuth = $user !== '';
    if ($mail->SMTPAuth) {
        $mail->Username = $user;
        $mail->Password = $password;
    }

    // 'ssl' => SMTPS (jawny SSL od początku połączenia, zwykle port 465)
    // 'tls' => STARTTLS (szyfrowanie negocjowane po połączeniu, port 587)
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'none' || $encryption === '') {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    // Gdy łączymy się po adresie IP (np. do Mailcowa w LAN), certyfikat nigdy
    // nie będzie pasował do nazwy - tylko wtedy luzujemy weryfikację.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    $mail->Timeout = 15;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;

    /* Nadawca zależy od PROFILU. `register@` wysyła kody weryfikacyjne,
       `info@` - sprawy zamówień i płatności. Rozdzielenie nie jest kosmetyką:
       gdyby wszystko szło z jednego adresu, filtr antyspamowy uczyłby się na
       kodach jednorazowych i potrafił wrzucić tam potwierdzenie zapłaty. */
    if ($profile === 'info') {
        $mail->setFrom(
            (string)env('SMTP_INFO_FROM_EMAIL', 'info@dnct.pl'),
            (string)env('SMTP_INFO_FROM_NAME', 'DNCT Hosting')
        );
    } else {
        $mail->setFrom(
            (string)env('SMTP_FROM_EMAIL', 'noreply@dnct.pl'),
            (string)env('SMTP_FROM_NAME', 'DNCT Hosting')
        );
    }

    return $mail;
}

/**
 * Wysyła pojedynczą wiadomość.
 *
 * @return array{ok:bool, error:string} - błędy raportujemy, nie rzucamy,
 *         bo warstwa API sama decyduje, co pokazać użytkownikowi.
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody, string $profile = 'default'): array
{
    // Ramka DNCT dokładana tutaj, żeby dziennik (mail_log) pokazywał dokładnie to, co poszło.
    if (stripos($htmlBody, '<html') === false) {
        $htmlBody = mail_ramka($subject, $htmlBody);
    }
    $wynik = send_mail_wykonaj($to, $subject, $htmlBody, $textBody, $profile);
    mail_dziennik($to, $subject, $profile, $wynik, $htmlBody);
    return $wynik;
}

/**
 * Zapis próby wysyłki do mail_log (admin -> Maile). Nigdy nie przerywa wysyłki:
 * brak tabeli albo bazy kończy się wpisem w error_log.
 */
function mail_dziennik(string $to, string $subject, string $profile, array $wynik, string $html): void
{
    try {
        // Skąd poszedł mail: plik i funkcja, które wywołały send_mail().
        $slad = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        $zrodlo = null;
        foreach ($slad as $i => $k) {
            if (($k['function'] ?? '') === 'send_mail') {
                $fn = $slad[$i + 1]['function'] ?? '';
                $zrodlo = ($fn !== '' && !in_array($fn, ['require', 'require_once', 'include', 'include_once'], true) ? $fn . ' @ ' : '')
                    . basename((string)($k['file'] ?? ''));
                break;
            }
        }
        $zrodlo = $zrodlo ?: basename((string)($_SERVER['SCRIPT_FILENAME'] ?? 'cli'));
        dnct_db()->prepare('INSERT INTO mail_log (to_email, subject, profile, ok, error, html, zrodlo) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([mb_substr($to, 0, 254), mb_substr($subject, 0, 255), $profile, !empty($wynik['ok']) ? 1 : 0,
                       !empty($wynik['ok']) ? null : mb_substr((string)($wynik['error'] ?? ''), 0, 500), $html, mb_substr($zrodlo, 0, 120)]);
    } catch (Throwable $e) {
        error_log('mail_dziennik: ' . $e->getMessage());
    }
}

/** Właściwa wysyłka (dawne send_mail) - bez dziennika. */
function send_mail_wykonaj(string $to, string $subject, string $htmlBody, string $textBody, string $profile = 'default'): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Nieprawidłowy adres odbiorcy.'];
    }

    try {
        return mail_deliver($to, $subject, $htmlBody, $textBody, $profile);
    } catch (Exception $e) {
        /* AWARYJNY NADAWCA.
           Serwer poczty odrzuca nadawcę, na którego konto logujące się nie ma
           uprawnienia ("Sender address rejected: not owned by user ..."). Gdyby
           to kończyło wysyłkę, klient po zapłaceniu nie dostałby ŻADNEGO
           potwierdzenia - z powodu ustawienia skrzynki, nie płatności.

           Wysyłamy więc ponownie z nadawcą domyślnym i głośno logujemy, co
           trzeba naprawić. Lepiej dostarczyć wiadomość z innego adresu niż
           nie dostarczyć jej wcale. */
        $rejected = str_contains($e->getMessage(), 'Sender address rejected')
            || str_contains($e->getMessage(), 'not owned by user');

        if ($profile !== 'default' && $rejected) {
            error_log(
                'Mail: nadawca profilu "' . $profile . '" odrzucony przez serwer - '
                . 'wysyłam z adresu domyślnego. NAPRAW: ustaw SMTP_' . strtoupper($profile)
                . '_PASSWORD albo nadaj kontu ' . env('SMTP_USER', '?')
                . ' prawo wysyłki w imieniu tego adresu.'
            );

            try {
                return mail_deliver($to, $subject, $htmlBody, $textBody, 'default');
            } catch (Throwable $inner) {
                error_log('Mail: awaryjna wysyłka do ' . $to . ' też się nie udała: ' . $inner->getMessage());
                return ['ok' => false, 'error' => $inner->getMessage()];
            }
        }

        error_log('Mail: nie udało się wysłać wiadomości do ' . $to . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Mail: nieoczekiwany błąd wysyłki do ' . $to . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Nieoczekiwany błąd wysyłki.'];
    }
}

/**
 * Jedno doręczenie, bez obsługi błędów - te są warstwę wyżej, w send_mail().
 *
 * @return array{ok:bool, error:string}
 * @throws Exception gdy serwer poczty odmówi
 */
function mail_deliver(string $to, string $subject, string $htmlBody, string $textBody, string $profile): array
{
    $mail = mailer_factory($profile);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody;
    $mail->send();

    return ['ok' => true, 'error' => ''];
}

/** Owija gołą treść w ramkę DNCT; podtytuł to temat bez dopisku z nazwą firmy. */
function mail_ramka(string $subject, string $content): string
{
    $subtitle = trim((string)preg_replace('/\s*[-—–]\s*DNCT Hosting\s*$/u', '', $subject));
    try {
        return render_mail_template('ramka', ['subtitle' => $subtitle, 'content' => $content]);
    } catch (Throwable $e) {
        error_log('Mail: ramka niedostępna - ' . $e->getMessage());
        return $content;
    }
}

/**
 * Renderuje szablon wiadomości z katalogu templates/.
 *
 * @param array<string,mixed> $vars
 */
function render_mail_template(string $template, array $vars): string
{
    $path = __DIR__ . '/templates/' . $template . '.php';
    if (!is_file($path)) {
        throw new RuntimeException('Brak szablonu wiadomości: ' . $template);
    }

    extract($vars, EXTR_SKIP);
    ob_start();
    require $path;
    return (string)ob_get_clean();
}

/**
 * Wiadomość z 6-cyfrowym kodem weryfikacyjnym (krok 2 rejestracji).
 *
 * @return array{ok:bool, error:string}
 */
function send_verification_code_mail(string $to, string $code, string $firstName = '', int $ttlMinutes = 10): array
{
    $greetingName = trim($firstName);

    $html = render_mail_template('verification-code', [
        'code'         => $code,
        'greetingName' => $greetingName,
        'ttlMinutes'   => $ttlMinutes,
    ]);

    $text = ($greetingName !== '' ? "Cześć {$greetingName}!\n\n" : "Cześć!\n\n")
        . "Twój kod weryfikacyjny do rejestracji w DNCT Hosting:\n\n"
        . "    {$code}\n\n"
        . "Kod jest ważny przez {$ttlMinutes} minut.\n\n"
        . "Jeśli to nie Ty zakładasz konto, po prostu zignoruj tę wiadomość.\n\n"
        . "-- \nDNCT Hosting\nhttps://game.dnct.pl";

    return send_mail($to, 'Kod weryfikacyjny: ' . $code . ' - DNCT Hosting', $html, $text);
}
