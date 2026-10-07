<?php
/**
 * Cotygodniowy raport SEO z Google Search Console (cron w poniedziałek rano).
 *
 * Porównuje ostatnie 7 dni z danymi (GSC ma ~2-3 dni opóźnienia) z tygodniem
 * wcześniej: kliknięcia, wyświetlenia, frazy, strony. Na końcu krótkie
 * podpowiedzi: frazy tuż za pierwszą stroną (warto podbić treść) i frazy
 * z wyświetleniami bez kliknięć (do poprawy tytuł/opis).
 *
 *   php backend/tools/raport-seo.php                  podgląd w terminalu
 *   php backend/tools/raport-seo.php --do=adres@x.pl  wysyła maila
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/gsc.php';
require_once dirname(__DIR__) . '/mail/mail.php';

const RAPORT_WLASCIWOSC = 'sc-domain:dnct.pl';

$opcje = getopt('', ['do:']);
$do    = isset($opcje['do']) ? (string)$opcje['do'] : '';

$tz    = new DateTimeZone('Europe/Warsaw');
$koniec = (new DateTimeImmutable('now', $tz))->modify('-3 days');
$teraz = [$koniec->modify('-6 days'), $koniec];
$przed = [$koniec->modify('-13 days'), $koniec->modify('-7 days')];

/** @return array<string,array{c:int,i:int,p:float}> */
function raport_wiersze(array $okres, string $wymiar): array
{
    $res = gsc_request('POST', GSC_SEARCHANALYTICS_BASE . '/sites/' . rawurlencode(RAPORT_WLASCIWOSC) . '/searchAnalytics/query', [
        'startDate'  => $okres[0]->format('Y-m-d'),
        'endDate'    => $okres[1]->format('Y-m-d'),
        'dimensions' => [$wymiar],
        'rowLimit'   => 250,
    ]);
    if (($res['http_code'] ?? 0) !== 200) {
        throw new RuntimeException('GSC: HTTP ' . ($res['http_code'] ?? 0) . ' ' . ($res['error'] ?? ''));
    }
    $w = [];
    foreach ($res['body']['rows'] ?? [] as $r) {
        $w[(string)$r['keys'][0]] = ['c' => (int)$r['clicks'], 'i' => (int)$r['impressions'], 'p' => (float)$r['position']];
    }
    return $w;
}

$suma = static fn(array $w): array => [
    'c' => array_sum(array_column($w, 'c')),
    'i' => array_sum(array_column($w, 'i')),
];
$zmiana = static fn(int $a, int $b): string => $b === 0 ? ($a > 0 ? 'nowe' : '0') : (($a - $b >= 0 ? '+' : '') . round(($a - $b) / $b * 100) . '%');

try {
    $fT = raport_wiersze($teraz, 'query');
    $fP = raport_wiersze($przed, 'query');
    $sT = raport_wiersze($teraz, 'page');
    $sP = raport_wiersze($przed, 'page');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$t = $suma($fT);
$p = $suma($fP);
uasort($fT, static fn($a, $b) => $b['i'] <=> $a['i']);
uasort($sT, static fn($a, $b) => $b['i'] <=> $a['i']);

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$okresTxt = $teraz[0]->format('d.m') . '-' . $teraz[1]->format('d.m.Y');

$wiersz = static function (string $nazwa, array $v, ?array $stare) use ($e): string {
    $poz = number_format($v['p'], 1, ',', '');
    $ruch = $stare ? number_format($stare['p'] - $v['p'], 1, ',', '') : 'nowa';
    $ruch = is_numeric(str_replace(',', '.', $ruch)) && (float)str_replace(',', '.', $ruch) > 0 ? '+' . $ruch : $ruch;
    return '<tr><td style="padding:4px 8px;border-bottom:1px solid #f0f0f3;">' . $e($nazwa) . '</td>'
        . '<td style="padding:4px 8px;border-bottom:1px solid #f0f0f3;text-align:right;">' . $v['i'] . '</td>'
        . '<td style="padding:4px 8px;border-bottom:1px solid #f0f0f3;text-align:right;">' . $v['c'] . '</td>'
        . '<td style="padding:4px 8px;border-bottom:1px solid #f0f0f3;text-align:right;">' . $poz . ' <small style="color:#9ca3af;">(' . $e($ruch) . ')</small></td></tr>';
};
$tabela = static function (string $tytul, array $wiersze, array $stare, int $n) use ($wiersz, $e): string {
    $h = '<p style="margin:22px 0 6px;font-weight:700;color:#333;">' . $e($tytul) . '</p>';
    if (!$wiersze) {
        return $h . '<p style="margin:0;color:#9ca3af;">Brak danych w tym tygodniu.</p>';
    }
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px;"><tr style="color:#6b7280;text-align:left;">'
        . '<th style="padding:4px 8px;">Fraza / strona</th><th style="padding:4px 8px;text-align:right;">Wyśw.</th>'
        . '<th style="padding:4px 8px;text-align:right;">Klik.</th><th style="padding:4px 8px;text-align:right;">Pozycja (zmiana)</th></tr>';
    foreach (array_slice($wiersze, 0, $n, true) as $k => $v) {
        $h .= $wiersz(str_replace('https://', '', (string)$k), $v, $stare[$k] ?? null);
    }
    return $h . '</table>';
};

// Podpowiedzi
$blisko = array_filter($fT, static fn($v) => $v['p'] > 8 && $v['p'] <= 20 && $v['i'] >= 5);
$bezKlikow = array_filter($fT, static fn($v) => $v['p'] <= 8 && $v['i'] >= 20 && $v['c'] === 0);
$nowe = array_diff_key($fT, $fP);

$html = '<p style="margin:0 0 14px;">Tydzień ' . $e($okresTxt) . ' w Google (dnct.pl i subdomeny):</p>'
    . '<table style="width:100%;border-collapse:collapse;text-align:center;"><tr>'
    . '<td style="padding:12px;background:#f5f3ff;border-radius:8px;"><div style="font-size:22px;font-weight:700;color:#5b21b6;">' . $t['c'] . '</div><div style="font-size:12px;color:#6b7280;">kliknięć (' . $zmiana($t['c'], $p['c']) . ')</div></td>'
    . '<td style="width:10px;"></td>'
    . '<td style="padding:12px;background:#f5f3ff;border-radius:8px;"><div style="font-size:22px;font-weight:700;color:#5b21b6;">' . $t['i'] . '</div><div style="font-size:12px;color:#6b7280;">wyświetleń (' . $zmiana($t['i'], $p['i']) . ')</div></td>'
    . '<td style="width:10px;"></td>'
    . '<td style="padding:12px;background:#f5f3ff;border-radius:8px;"><div style="font-size:22px;font-weight:700;color:#5b21b6;">' . count($fT) . '</div><div style="font-size:12px;color:#6b7280;">fraz (' . count($nowe) . ' nowych)</div></td>'
    . '</tr></table>'
    . $tabela('Najczęściej wyświetlane frazy', $fT, $fP, 12)
    . $tabela('Strony', $sT, $sP, 8);

$rady = [];
foreach (array_slice($blisko, 0, 4, true) as $k => $v) {
    $rady[] = '„' . $k . '" jest na pozycji ' . number_format($v['p'], 1, ',', '') . ' - tuż za pierwszą stroną. Warto dopisać o tym akapit albo poradnik.';
}
foreach (array_slice($bezKlikow, 0, 3, true) as $k => $v) {
    $rady[] = '„' . $k . '" ma ' . $v['i'] . ' wyświetleń i 0 kliknięć - do poprawy tytuł lub opis strony.';
}
if (!$rady) {
    $rady[] = $t['i'] < 50 ? 'Mało danych - nowe strony dopiero się indeksują. Najwięcej dadzą teraz linki z innych stron i opinie w Google.' : 'Bez pilnych rzeczy - trzymaj kurs.';
}
$html .= '<p style="margin:22px 0 6px;font-weight:700;color:#333;">Co zrobić</p><ul style="margin:0;padding-left:20px;">'
    . implode('', array_map(static fn($r) => '<li style="margin:4px 0;">' . $e($r) . '</li>', $rady)) . '</ul>';

$tekst = "Raport SEO {$okresTxt}: {$t['c']} kliknięć (" . $zmiana($t['c'], $p['c']) . "), {$t['i']} wyświetleń (" . $zmiana($t['i'], $p['i']) . '), ' . count($fT) . " fraz.\n\n" . implode("\n", $rady);

if ($do === '') {
    echo $tekst, PHP_EOL, PHP_EOL;
    foreach (array_slice($fT, 0, 12, true) as $k => $v) {
        printf("%-45s wysw %4d  klik %3d  poz %5.1f\n", mb_strimwidth($k, 0, 45), $v['i'], $v['c'], $v['p']);
    }
    exit(0);
}

$r = send_mail($do, 'Raport SEO - tydzień ' . $okresTxt, $html, $tekst, 'info');
echo $r['ok'] ? "Wysłano do {$do}\n" : "Błąd wysyłki: {$r['error']}\n";
exit($r['ok'] ? 0 : 1);
