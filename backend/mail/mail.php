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
function mailer_factory(): PHPMailer
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

    $user = (string)env('SMTP_USER', '');
    $password = (string)env('SMTP_PASSWORD', '');
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

    $mail->setFrom(
        (string)env('SMTP_FROM_EMAIL', 'noreply@dnct.pl'),
        (string)env('SMTP_FROM_NAME', 'DNCT Hosting')
    );

    return $mail;
}

/**
 * Wysyła pojedynczą wiadomość.
 *
 * @return array{ok:bool, error:string} - błędy raportujemy, nie rzucamy,
 *         bo warstwa API sama decyduje, co pokazać użytkownikowi.
 */
function send_mail(string $to, string $subject, string $htmlBody, string $textBody): array
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Nieprawidłowy adres odbiorcy.'];
    }

    try {
        $mail = mailer_factory();
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return ['ok' => true, 'error' => ''];
    } catch (Exception $e) {
        error_log('Mail: nie udało się wysłać wiadomości do ' . $to . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Mail: nieoczekiwany błąd wysyłki do ' . $to . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Nieoczekiwany błąd wysyłki.'];
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
        . "-- \nDNCT Hosting\nhttps://dnct.pl";

    return send_mail($to, 'Kod weryfikacyjny: ' . $code . ' - DNCT Hosting', $html, $text);
}
