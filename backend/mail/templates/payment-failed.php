<?php
/**
 * Ostrzeżenie po nieudanej płatności cyklicznej.
 *
 * @var string $userName
 * @var int    $orderId
 * @var string $gameName
 * @var string $amount
 * @var string $suspends    dokładna data zawieszenia
 * @var int    $graceDays
 * @var string $accountUrl
 *
 * Ton jest rzeczowy, nie straszący. Najczęstsza przyczyna odmowy to wygasła
 * karta albo limit - nie złe intencje. Wiadomość ma dać KONKRETNĄ datę i jedno
 * kliknięcie do naprawy, a nie budować napięcie.
 */

declare(strict_types=1);

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nie udało się pobrać opłaty - DNCT Hosting</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333333;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Serwer działa do <?= $e($suspends) ?>. Popraw dane karty albo przedłuż jednorazowo.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

            <tr>
                <td align="center" style="background-color:#f59e0b; padding:32px 24px;">
                    <div style="color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">DNCT Hosting</div>
                    <div style="color:#fef3c7; font-size:14px; margin-top:6px;">Płatność nie przeszła</div>
                </td>
            </tr>

            <tr>
                <td style="padding:32px 32px 8px;">
                    <p style="margin:0 0 16px; font-size:16px;">Cześć <?= $e($userName) ?>,</p>
                    <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#555555;">
                        nie udało się pobrać opłaty <strong><?= $e($amount) ?> zł</strong> za serwer
                        <strong><?= $e($gameName) ?></strong> (zamówienie #<?= (int)$orderId ?>).
                        Najczęściej to wygasła karta albo limit transakcji - nic poważnego.
                    </p>
                </td>
            </tr>

            <tr>
                <td style="padding:0 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fef2f2; border:1px solid #fca5a5; border-radius:10px;">
                        <tr>
                            <td align="center" style="padding:20px;">
                                <div style="font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#991b1b;">
                                    Serwer zostanie zawieszony
                                </div>
                                <div style="font-size:22px; font-weight:700; color:#991b1b; margin-top:8px;">
                                    <?= $e($suspends) ?>
                                </div>
                                <div style="font-size:13px; color:#7f1d1d; margin-top:10px; line-height:1.6;">
                                    Do tego czasu działa normalnie - masz <?= (int)$graceDays ?> dni na reakcję.
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td style="padding:22px 32px 8px;">
                    <p style="margin:0; font-size:14px; line-height:1.7; color:#555555;">
                        Co możesz zrobić:
                    </p>
                    <ul style="margin:10px 0 0; padding-left:20px; font-size:14px; line-height:1.8; color:#555555;">
                        <li>poprawić dane karty - kolejne obciążenie spróbuje samo,</li>
                        <li>albo przedłużyć serwer jednorazowo, bez abonamentu.</li>
                    </ul>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:24px 32px 32px;">
                    <a href="<?= $e($accountUrl) ?>" style="display:inline-block; padding:14px 30px; background-color:#8b5cf6; color:#ffffff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">
                        Przejdź do swoich serwerów
                    </a>
                </td>
            </tr>

            <tr>
                <td align="center" style="background-color:#f9fafb; padding:20px 32px; border-top:1px solid #f0f0f3;">
                    <div style="font-size:12px; color:#9ca3af; line-height:1.6;">
                        Zawieszenie nie kasuje danych - po opłaceniu serwer wraca w tym samym stanie.
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    </table>
</body>
</html>
