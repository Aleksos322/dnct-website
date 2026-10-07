<?php
/**
 * Ramka DNCT dla wiadomości bez własnego szablonu.
 *
 * send_mail() owija nią każdy HTML, który nie jest pełnym dokumentem
 * (brak <html>): potwierdzenie umowy, zgłoszenia supportu, abuse, hosting.
 * Wygląd jak w lifecycle.php - fioletowy nagłówek, biała karta, szara stopka -
 * żeby klient nie dostawał od nas raz ładnej wiadomości, raz gołego tekstu.
 *
 * @var string $subtitle  podtytuł w nagłówku (temat bez dopisku "DNCT Hosting")
 * @var string $content   treść wiadomości (HTML, już bezpieczny - od nadawcy)
 */

declare(strict_types=1);

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($subtitle) ?> - DNCT Hosting</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <tr>
                <td align="center" style="background-color:#8b5cf6; padding:32px 24px;">
                    <div style="color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">DNCT Hosting</div>
                    <?php if ($subtitle !== ''): ?>
                    <div style="color:#ede9fe; font-size:14px; margin-top:6px;"><?= $e($subtitle) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td style="padding:32px; font-size:15px; line-height:1.6; color:#444444;">
                    <?= $content ?>
                </td>
            </tr>
            <tr>
                <td align="center" style="background-color:#f9fafb; padding:20px 32px; border-top:1px solid #f0f0f3;">
                    <div style="font-size:12px; color:#9ca3af; line-height:1.6;">
                        DNCT Hosting · <a href="https://game.dnct.pl" style="color:#8b5cf6; text-decoration:none;">game.dnct.pl</a> · support@dnct.pl
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    </table>
</body>
</html>
