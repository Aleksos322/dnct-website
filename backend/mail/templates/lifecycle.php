<?php
/**
 * Wspólny szablon wiadomości o cyklu życia serwera.
 *
 * Cztery wiadomości - ostrzeżenie o wygaśnięciu, zawieszenie, gotowa paczka
 * z plikami, usunięcie - różnią się kolorem, nagłówkiem i jedną ramką z datą.
 * Reszta jest identyczna, więc są to NIE cztery pliki, tylko cztery zestawy
 * zmiennych. Cztery kopie tego samego HTML-a rozjechałyby się przy pierwszej
 * poprawce stopki, i to dokładnie w tych wiadomościach, których nikt nie
 * ogląda na co dzień.
 *
 * @var string      $accent      kolor nagłówka (#rrggbb)
 * @var string      $accentSoft  kolor podtytułu w nagłówku
 * @var string      $headerNote  podtytuł w nagłówku
 * @var string      $preheader   tekst widoczny na liście wiadomości
 * @var string      $userName
 * @var string      $lead        pierwszy akapit (HTML dozwolony: <strong>)
 * @var string      $boxLabel    napis nad datą w ramce
 * @var string      $boxValue    sama data albo kwota
 * @var string      $boxNote     zdanie pod datą
 * @var string      $boxBg       tło ramki
 * @var string      $boxBorder   obramowanie ramki
 * @var string      $boxFg       kolor tekstu w ramce
 * @var array       $bullets     lista punktów (może być pusta)
 * @var string      $ctaUrl
 * @var string      $ctaLabel
 * @var string      $footerNote
 */

declare(strict_types=1);

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($headerNote) ?> - DNCT Hosting</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333333;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        <?= $e($preheader) ?>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7; padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">

            <tr>
                <td align="center" style="background-color:<?= $e($accent) ?>; padding:32px 24px;">
                    <div style="color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">DNCT Hosting</div>
                    <div style="color:<?= $e($accentSoft) ?>; font-size:14px; margin-top:6px;"><?= $e($headerNote) ?></div>
                </td>
            </tr>

            <tr>
                <td style="padding:32px 32px 8px;">
                    <p style="margin:0 0 16px; font-size:16px;">Cześć <?= $e($userName) ?>,</p>
                    <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#555555;"><?= $lead ?></p>
                </td>
            </tr>

            <?php if ($boxValue !== ''): ?>
            <tr>
                <td style="padding:0 32px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:<?= $e($boxBg) ?>; border:1px solid <?= $e($boxBorder) ?>; border-radius:10px;">
                        <tr>
                            <td align="center" style="padding:20px;">
                                <div style="font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:<?= $e($boxFg) ?>;">
                                    <?= $e($boxLabel) ?>
                                </div>
                                <div style="font-size:22px; font-weight:700; color:<?= $e($boxFg) ?>; margin-top:8px;">
                                    <?= $e($boxValue) ?>
                                </div>
                                <?php if ($boxNote !== ''): ?>
                                <div style="font-size:13px; color:<?= $e($boxFg) ?>; margin-top:10px; line-height:1.6; opacity:0.85;">
                                    <?= $e($boxNote) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($bullets): ?>
            <tr>
                <td style="padding:22px 32px 8px;">
                    <ul style="margin:0; padding-left:20px; font-size:14px; line-height:1.8; color:#555555;">
                        <?php foreach ($bullets as $b): ?>
                        <li><?= $e((string)$b) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($ctaUrl !== ''): ?>
            <tr>
                <td align="center" style="padding:24px 32px 32px;">
                    <a href="<?= $e($ctaUrl) ?>" style="display:inline-block; padding:14px 30px; background-color:#8b5cf6; color:#ffffff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">
                        <?= $e($ctaLabel) ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <td align="center" style="background-color:#f9fafb; padding:20px 32px; border-top:1px solid #f0f0f3;">
                    <div style="font-size:12px; color:#9ca3af; line-height:1.6;">
                        <?= $e($footerNote) ?>
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    </table>
</body>
</html>
