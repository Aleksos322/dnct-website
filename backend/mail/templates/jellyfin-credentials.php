<?php
/**
 * Szablon wiadomości z danymi logowania do nowo założonego konta Jellyfin.
 *
 * Zmienne dostarcza render_mail_template():
 * @var string $greetingName imię odbiorcy (może być puste)
 * @var string $jfUsername   login do Jellyfina
 * @var string $jfPassword   wygenerowane hasło - pokazujemy je TYLKO tutaj,
 *                           nigdzie w bazie ani w odpowiedzi API go nie ma
 *
 * Layout na tabelach + style inline - jak verification-code.php.
 */

declare(strict_types=1);

$safeName     = htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8');
$safeUsername = htmlspecialchars($jfUsername, ENT_QUOTES, 'UTF-8');
$safePassword = htmlspecialchars($jfPassword, ENT_QUOTES, 'UTF-8');
$greeting     = $safeName !== '' ? 'Cześć ' . $safeName . '!' : 'Cześć!';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twoje konto Jellyfin - DNCT Hosting</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333333;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Twoje konto Jellyfin jest gotowe. Login: <?= $safeUsername ?>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

                    <tr>
                        <td align="center" style="background:linear-gradient(135deg,#8b5cf6 0%,#3b82f6 100%); background-color:#8b5cf6; padding:32px 24px;">
                            <h1 style="margin:0; font-size:24px; font-weight:600; color:#ffffff; letter-spacing:-0.5px;">
                                DNCT HOSTING
                            </h1>
                            <p style="margin:8px 0 0; font-size:14px; color:rgba(255,255,255,0.85);">
                                Twoje konto Jellyfin jest gotowe
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 8px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.6; color:#333333;">
                                <?= $greeting ?>
                            </p>
                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#555555;">
                                Razem z kontem DNCT założyliśmy dla Ciebie konto na naszym serwerze mediów Jellyfin.
                                Poniższe hasło pokazujemy tylko raz, w tej wiadomości - zapisz je w menedżerze haseł.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:0 32px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                <tr>
                                    <td style="background-color:#f5f3ff; border:2px dashed #8b5cf6; border-radius:12px; padding:20px 24px;">
                                        <p style="margin:0 0 8px; font-size:13px; color:#5b21b6;">Login</p>
                                        <p style="margin:0 0 16px; font-family:'Courier New',Courier,monospace; font-size:18px; font-weight:700; color:#5b21b6;"><?= $safeUsername ?></p>
                                        <p style="margin:0 0 8px; font-size:13px; color:#5b21b6;">Hasło</p>
                                        <p style="margin:0; font-family:'Courier New',Courier,monospace; font-size:18px; font-weight:700; color:#5b21b6;"><?= $safePassword ?></p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fffbeb; border-left:4px solid #f59e0b; border-radius:6px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:13px; line-height:1.6; color:#92400e;">
                                        Zmień to hasło po pierwszym zalogowaniu. Jeśli masz już inne konto Jellyfin
                                        (np. sprzed rejestracji na dnct.pl), możesz je podłączyć zamiast tego na
                                        stronie account.dnct.pl.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="background-color:#fafafa; border-top:1px solid #eeeeee; padding:20px 32px;">
                            <p style="margin:0 0 6px; font-size:12px; color:#999999;">
                                Wiadomość wysłana automatycznie - prosimy na nią nie odpowiadać.
                            </p>
                            <p style="margin:0; font-size:12px; color:#999999;">
                                &copy; <?= date('Y') ?> DNCT Hosting &middot;
                                <a href="https://dnct.pl" style="color:#8b5cf6; text-decoration:none;">dnct.pl</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
