<?php
/**
 * Prośba o opinię w Google - raz na klienta, 3 dni po starcie pierwszego serwera.
 *
 * Bez --apply tylko pokazuje, do kogo by poszło (jak lifecycle.php).
 * Cron codziennie (crontab deployera). Klient, który już dostał prośbę przy
 * jakimkolwiek zamówieniu, nie dostaje drugiej.
 *
 *   php backend/tools/prosba-o-opinie.php            podgląd
 *   php backend/tools/prosba-o-opinie.php --apply    wysyłka
 *   php backend/tools/prosba-o-opinie.php --test=adres@x.pl   próbka na podany adres
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/mail/mail.php';

const OPINIA_LINK = 'https://g.page/r/CRwe2fxVy-gHEBM/review';
const OPINIA_PO_DNIACH = 3;

$apply = in_array('--apply', $argv, true);
$test  = (string)(getopt('', ['test:'])['test'] ?? '');
$db = dnct_db();

$kandydaci = $db->query(
    "SELECT o.* FROM orders o
      WHERE o.kind = 'new' AND o.status = 'provisioned' AND o.deleted_at IS NULL
        AND o.review_asked_at IS NULL
        AND COALESCE(o.service_start_at, o.created_at) <= NOW() - INTERVAL " . OPINIA_PO_DNIACH . " DAY
        AND NOT EXISTS (SELECT 1 FROM orders x WHERE x.user_email = o.user_email AND x.review_asked_at IS NOT NULL)
      ORDER BY o.id"
)->fetchAll(PDO::FETCH_ASSOC);

$wyslane = [];
foreach ($kandydaci as $o) {
    $email = (string)$o['user_email'];
    if (isset($wyslane[$email])) {
        continue; // kilka serwerów jednego klienta - jeden mail
    }
    $wyslane[$email] = true;
    $gra = ucfirst((string)$o['game_type']);
    if (!$apply && $test === '') {
        echo "[podgląd] {$email} - {$gra}, zamówienie #{$o['id']}\n";
        continue;
    }
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $html = '<p style="margin:0 0 14px;">Cześć ' . $e((string)$o['user_name']) . ',</p>'
        . '<p style="margin:0 0 14px;">Twój serwer <strong>' . $e($gra) . '</strong> działa od kilku dni. Jak się na nim gra?</p>'
        . '<p style="margin:0 0 20px;">Jeśli wszystko jest w porządku, będziemy wdzięczni za krótką opinię w Google. To 30 sekund, a dla małego polskiego hostingu znaczy naprawdę dużo.</p>'
        . '<p style="margin:0 0 20px;text-align:center;"><a href="' . OPINIA_LINK . '" style="display:inline-block;padding:14px 30px;background-color:#8b5cf6;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:700;">Oceń DNCT w Google</a></p>'
        . '<p style="margin:0;color:#6b7280;font-size:13px;">Coś nie działa? Nie wystawiaj od razu złej oceny - odpisz na tego maila albo napisz na czacie na stronie, a naprawimy to.</p>';
    $tekst = "Cześć {$o['user_name']},\n\nTwój serwer {$gra} działa od kilku dni. Jeśli wszystko gra, będziemy wdzięczni za krótką opinię w Google:\n" . OPINIA_LINK . "\n\nCoś nie działa? Odpisz na tego maila, a naprawimy to.\n\nDNCT Hosting";
    if ($test !== '') {
        $r = send_mail($test, '[PRÓBKA] Jak się gra na serwerze ' . $gra . '? - DNCT Hosting', $html, $tekst, 'info');
        echo $r['ok'] ? "[próbka] wysłano do {$test}\n" : "[błąd] {$r['error']}\n";
        exit(0);
    }
    $r = send_mail($email, 'Jak się gra na serwerze ' . $gra . '? - DNCT Hosting', $html, $tekst, 'info');
    if ($r['ok']) {
        $db->prepare('UPDATE orders SET review_asked_at = NOW() WHERE user_email = ? AND review_asked_at IS NULL')->execute([$email]);
        echo "[wysłano] {$email}\n";
    } else {
        echo "[błąd] {$email}: {$r['error']}\n";
    }
}
echo ($apply ? 'Wysłano' : 'Do wysłania') . ': ' . count($wyslane) . PHP_EOL;
