<?php
/**
 * Potwierdzenie płatności i uruchomienia serwera.
 *
 * @var string $userName
 * @var int    $orderId
 * @var string $gameName
 * @var string $packageName
 * @var int    $ramMb
 * @var string $cpuCores
 * @var int    $ssdMb
 * @var string $amount
 * @var bool   $isSub
 * @var string $expires      data, do kiedy opłacone
 * @var string $suspends     data zawieszenia, gdy nie będzie kolejnej wpłaty
 * @var int    $graceHours
 * @var string $panelUrl
 * @var string $accountUrl
 *
 * Layout na tabelach i style inline - Outlook i Gmail nie obsługują ani
 * zewnętrznych arkuszy, ani nowoczesnego CSS-a.
 *
 * RAMKA Z DATĄ ZAWIESZENIA jest najbardziej wyróżnionym elementem po kwocie.
 * To jedyna informacja z tej wiadomości, do której klient wróci za miesiąc.
 */

declare(strict_types=1);

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serwer gotowy - DNCT Hosting</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333333;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Serwer działa. Opłacone do <?= $e($expires) ?>, zawieszenie <?= $e($suspends) ?>.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

            <tr>
                <td align="center" style="background:linear-gradient(135deg,#8b5cf6 0%,#3b82f6 100%); background-color:#8b5cf6; padding:32px 24px;">
                    <div style="color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">DNCT Hosting</div>
                    <div style="color:#e9d5ff; font-size:14px; margin-top:6px;">Serwer jest gotowy</div>
                </td>
            </tr>

            <tr>
                <td style="padding:32px 32px 8px;">
                    <p style="margin:0 0 16px; font-size:16px;">Cześć <?= $e($userName) ?>!</p>
                    <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#555555;">
                        Płatność <strong><?= $e($amount) ?> zł</strong> została przyjęta,
                        a Twój serwer jest już uruchomiony.
                    </p>
                </td>
            </tr>

            <!-- Co zostało kupione -->
            <tr>
                <td style="padding:0 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb; border-radius:10px;">
                        <tr>
                            <td style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; color:#6b7280;">Zamówienie</td>
                            <td align="right" style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; font-weight:700;">#<?= (int)$orderId ?></td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; color:#6b7280;">Gra</td>
                            <td align="right" style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; font-weight:700;"><?= $e($gameName) ?> · <?= $e($packageName) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; color:#6b7280;">Zasoby</td>
                            <td align="right" style="padding:14px 18px; border-bottom:1px solid #f0f0f3; font-size:14px; font-weight:700;"><?= (int)$ramMb ?> MB RAM · <?= $e($cpuCores) ?> vCore · <?= (int)$ssdMb ?> MB NVMe</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px; font-size:14px; color:#6b7280;">Rozliczenie</td>
                            <td align="right" style="padding:14px 18px; font-size:14px; font-weight:700;"><?= $isSub ? 'abonament (odnawia się sam)' : 'jednorazowo' ?></td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- TERMINY. Najważniejsza część wiadomości. -->
            <tr>
                <td style="padding:24px 32px 8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fffbeb; border:1px solid #fcd34d; border-radius:10px;">
                        <tr>
                            <td style="padding:18px 20px;">
                                <div style="font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#92400e; margin-bottom:12px;">
                                    Terminy - zapisz sobie
                                </div>

                                <div style="font-size:14px; color:#78350f; margin-bottom:8px;">
                                    Opłacone do:
                                    <strong style="font-size:16px; color:#451a03;"><?= $e($expires) ?></strong>
                                </div>

                                <div style="font-size:14px; color:#78350f;">
                                    Serwer zostanie zawieszony:
                                    <strong style="font-size:16px; color:#991b1b;"><?= $e($suspends) ?></strong>
                                </div>

                                <div style="font-size:13px; line-height:1.6; color:#78350f; margin-top:12px; padding-top:12px; border-top:1px solid #fcd34d;">
                                    <?php if ($isSub): ?>
                                        Abonament odnawia się sam. Jeśli kolejna płatność nie przejdzie,
                                        masz <strong><?= (int)$graceHours ?> h</strong> na poprawienie danych karty -
                                        przez ten czas serwer działa normalnie.
                                    <?php else: ?>
                                        To płatność jednorazowa - <strong>serwer nie odnowi się sam</strong>.
                                        Przedłuż go przed tą datą, żeby działał bez przerwy.
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p style="margin:14px 0 0; font-size:13px; line-height:1.6; color:#6b7280;">
                        Zawieszenie nie kasuje danych - świat, pliki i konfiguracja zostają.
                        Po opłaceniu serwer wraca w tym samym stanie.
                    </p>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:26px 32px 8px;">
                    <a href="<?= $e($panelUrl) ?>" style="display:inline-block; padding:14px 30px; background-color:#8b5cf6; color:#ffffff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">
                        Otwórz panel
                    </a>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:8px 32px 30px;">
                    <a href="<?= $e($accountUrl) ?>" style="color:#8b5cf6; font-size:13px; text-decoration:none;">
                        Zobacz swoje serwery i terminy
                    </a>
                </td>
            </tr>

            <tr>
                <td align="center" style="background-color:#f9fafb; padding:20px 32px; border-top:1px solid #f0f0f3;">
                    <div style="font-size:12px; color:#9ca3af; line-height:1.6;">
                        Wiadomość wysłana automatycznie w związku z Twoim zamówieniem.<br>
                        Pytania? Odpisz na tę wiadomość albo napisz na Discordzie.
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    </table>
</body>
</html>
