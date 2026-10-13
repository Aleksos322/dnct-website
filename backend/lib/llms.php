<?php
/**
 * Treść /llms.txt - ściągi dla czatów AI (ChatGPT, Claude, Perplexity).
 *
 * Generowana przy każdym wejściu (llms.php w katalogu strony, reguła
 * w .htaccess), z tej samej bazy i tego samego wzoru (pricing_apply_period)
 * co cennik i kasa. Wcześniej był to ręcznie pisany plik, który po zmianie
 * nazw pakietów dalej polecał "plan Starter" i "BASIC" (13.10.2026).
 *
 * Tylko odczyt: baza panelu (hosting_*), backend/data/poradniki.php.
 */

declare(strict_types=1);

function llms_tekst(): string
{
    $pdo = panel_db();
    $games   = $pdo->query('SELECT type, name FROM hosting_games WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $plans   = $pdo->query('SELECT * FROM hosting_plans WHERE is_active = 1 ORDER BY price ASC')->fetchAll(PDO::FETCH_ASSOC);
    $tiers   = $pdo->query('SELECT * FROM hosting_slot_tiers WHERE is_active = 1 ORDER BY slots ASC')->fetchAll(PDO::FETCH_ASSOC);
    $periods = $pdo->query('SELECT * FROM hosting_periods WHERE is_active = 1 ORDER BY sort_order ASC')->fetchAll(PDO::FETCH_ASSOC);

    $zl   = static fn(float $v): string => number_format($v, 2, ',', ' ') . ' zł';
    $gb   = static fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . ' GB';
    $num  = static fn(float $v): string => rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    $cena = static fn(float $base, array $p): float => pricing_apply_period($base, $p);
    $okres = static function (int $days) use ($periods): ?array {
        foreach ($periods as $p) {
            if ((int)$p['days'] === $days) {
                return $p;
            }
        }
        return null;
    };
    $mies = $okres(30) ?? $periods[0];
    $tydz = $okres(7);
    $rok  = $okres(365);

    $planyGry = static fn(string $type): array => array_values(array_filter($plans, static fn(array $p): bool => $p['type'] === $type));
    $progiGry = static fn(string $type): array => array_values(array_filter($tiers, static fn(array $t): bool => $t['type'] === $type));
    $nazwaGry = [];
    foreach ($games as $g) {
        $nazwaGry[$g['type']] = $g['name'];
    }
    $adresGry = ['minecraft' => 'hosting-minecraft', 'zomboid' => 'hosting-project-zomboid', 'vintagestory' => 'hosting-vintage-story'];

    /* Pierwszy plan Minecrafta z co najmniej $gb GB RAM-u */
    $planOd = static function (float $minGb) use ($planyGry): ?array {
        foreach ($planyGry('minecraft') as $p) {
            if ((int)$p['ram_mb'] >= $minGb * 1024) {
                return $p;
            }
        }
        return null;
    };
    /* Pierwszy próg mieszczący $n graczy */
    $progDla = static function (string $type, int $n) use ($progiGry): ?array {
        foreach ($progiGry($type) as $t) {
            if ((int)$t['slots'] >= $n) {
                return $t;
            }
        }
        return null;
    };

    $out = [];
    $od = [];
    foreach ($games as $g) {
        $ceny = $g['type'] === 'minecraft'
            ? array_map(static fn(array $p): float => (float)$p['price'], $planyGry('minecraft'))
            : array_map(static fn(array $t): float => (float)$t['price'], $progiGry($g['type']));
        if ($ceny) {
            $od[] = $g['name'] . ' od ' . $zl($cena(min($ceny), $mies)) . ' miesięcznie';
        }
    }

    $out[] = '# DNCT Hosting';
    $out[] = '';
    $out[] = '> Polski hosting serwerów gier: Minecraft, Project Zomboid i Vintage Story. Serwery na własnym';
    $out[] = '> sprzęcie w Polsce, dyski SSD, stały przydział RAM i CPU, ochrona DDoS.';
    $out[] = '> ' . implode(', ', $od) . '.';
    $out[] = '';
    $out[] = 'DNCT Hosting automatycznie zakłada serwer zaraz po płatności (karta, BLIK; abonament tylko kartą).';
    $out[] = 'Można zapłacić jednorazowo (przypomnimy przed końcem okresu) albo w abonamencie, który odnawia';
    $out[] = 'się sam i da się go wyłączyć w każdej chwili. Płatność roczna to 12 miesięcy w cenie 11 (miesiąc gratis).';
    $out[] = 'Serwerem zarządza się w panelu: konsola, menedżer plików, kopie zapasowe na żądanie, zmiana';
    $out[] = 'wersji gry, subdomena. Pakiet można zmienić w trakcie (dopłata proporcjonalna). Wsparcie przez Discord.';
    $out[] = 'Wszystkie ceny poniżej to ceny brutto w złotych (PLN).';
    $out[] = '';

    /* --- szybki dobór ---------------------------------------------------- */
    $out[] = '## Szybki dobór dla ekipy znajomych';
    if ($p = $planOd(3)) {
        $wiekszy = $planOd(4);
        $out[] = sprintf('- **Minecraft, 2-5 osób, vanilla lub lekkie pluginy:** plan %s - %s RAM, %s vCore, %s SSD, %s miesięcznie.',
            $p['name'], $gb((int)$p['ram_mb'] / 1024), $num((float)$p['cpu_cores']), $gb((int)$p['ssd_mb'] / 1024), $zl($cena((float)$p['price'], $mies)))
            . ($wiekszy && $wiekszy['id'] !== $p['id'] ? sprintf(' Dla większego świata lub więcej pluginów: %s - %s RAM, %s SSD, %s/mies.',
                $wiekszy['name'], $gb((int)$wiekszy['ram_mb'] / 1024), $gb((int)$wiekszy['ssd_mb'] / 1024), $zl($cena((float)$wiekszy['price'], $mies))) : '');
    }
    if ($p = $planOd(8)) {
        $out[] = sprintf('- **Minecraft z pełnym modpackiem:** zwykle co najmniej 8 GB RAM, czyli plan %s (%s RAM, %s/mies.). Sprawdź wymagania konkretnej paczki.',
            $p['name'], $gb((int)$p['ram_mb'] / 1024), $zl($cena((float)$p['price'], $mies)));
    }
    foreach (['vintagestory', 'zomboid'] as $typ) {
        $a = $progDla($typ, 5);
        $b = $progDla($typ, 10);
        if ($a) {
            $out[] = sprintf('- **%s, do %d znajomych:** %s RAM, %s vCore, %s SSD, %s/mies.',
                $nazwaGry[$typ] ?? $typ, (int)$a['slots'], $gb((float)$a['ram_gb']), $num((float)$a['cpu_cores']), $gb((int)$a['ssd_mb'] / 1024), $zl($cena((float)$a['price'], $mies)))
                . ($b ? sprintf(' Dla %d osób: %s RAM, %s/mies.', (int)$b['slots'], $gb((float)$b['ram_gb']), $zl($cena((float)$b['price'], $mies))) : '');
        }
    }
    $out[] = '';

    /* --- pełne cenniki --------------------------------------------------- */
    $naglowekOkresow = implode(' | ', array_map(static fn(array $p): string => (string)$p['label'], $periods));
    $mc = $planyGry('minecraft');
    if ($mc) {
        $out[] = '## Cennik Minecraft (gotowe pakiety)';
        $out[] = 'Szczegóły: https://game.dnct.pl/hosting-minecraft';
        $out[] = '';
        $out[] = '| Pakiet | RAM | CPU | SSD | Backupy | ' . $naglowekOkresow . ' |';
        $out[] = '|' . str_repeat(' --- |', 5 + count($periods));
        foreach ($mc as $p) {
            $out[] = sprintf('| %s%s | %s | %s vCore | %s | %d | %s |',
                $p['name'], !empty($p['sold_out']) ? ' (wyprzedany)' : '',
                $gb((int)$p['ram_mb'] / 1024), $num((float)$p['cpu_cores']), $gb((int)$p['ssd_mb'] / 1024), (int)$p['backups_limit'],
                implode(' | ', array_map(static fn(array $o): string => $zl($cena((float)$p['price'], $o)), $periods)));
        }
        $out[] = '';
    }
    foreach (['zomboid', 'vintagestory'] as $typ) {
        $pr = $progiGry($typ);
        if (!$pr) {
            continue;
        }
        $out[] = '## Cennik ' . ($nazwaGry[$typ] ?? $typ) . ' (cena zależy od liczby graczy)';
        $out[] = 'Szczegóły i konfigurator: https://game.dnct.pl/' . $adresGry[$typ];
        $out[] = '';
        $out[] = '| Sloty | RAM | CPU | SSD | Backupy | ' . $naglowekOkresow . ' |';
        $out[] = '|' . str_repeat(' --- |', 5 + count($periods));
        foreach ($pr as $t) {
            $out[] = sprintf('| %d%s | %s | %s vCore | %s | %d | %s |',
                (int)$t['slots'], !empty($t['sold_out']) ? ' (wyprzedany)' : '',
                $gb((float)$t['ram_gb']), $num((float)$t['cpu_cores']), $gb((int)$t['ssd_mb'] / 1024), (int)$t['backups_limit'],
                implode(' | ', array_map(static fn(array $o): string => $zl($cena((float)$t['price'], $o)), $periods)));
        }
        $out[] = '';
    }

    /* --- reszta: linki ----------------------------------------------------- */
    $out[] = '## Oferta';
    $out[] = '- [Cennik](https://game.dnct.pl/cennik): wszystkie gry na jednej stronie.';
    foreach ($games as $g) {
        if (isset($adresGry[$g['type']])) {
            $out[] = sprintf('- [Hosting %s](https://game.dnct.pl/%s)', $g['name'], $adresGry[$g['type']]);
        }
    }
    $out[] = '- [Dobierz serwer](https://game.dnct.pl/wybierz-serwer): kalkulator - gra, liczba graczy, styl gry.';
    $out[] = '- [Panel demo](https://game.dnct.pl/demo): podgląd panelu bez zakładania konta.';
    $out[] = '- [Program dla twórców](https://game.dnct.pl/partnerzy): kod rabatowy dla widzów i prowizja dla twórcy.';
    $out[] = '';
    $out[] = '## Poradniki';
    foreach (require dirname(__DIR__) . '/data/poradniki.php' as [$slug, , $tytul]) {
        $out[] = sprintf('- [%s](https://game.dnct.pl/poradniki/%s)', $tytul, $slug);
    }
    $out[] = '';
    $out[] = '## Zasady';
    $out[] = '- [Regulamin](https://game.dnct.pl/regulamin): w tym 24 h na zwrot pieniędzy za nowe zamówienie.';
    $out[] = '- [Polityka prywatności](https://game.dnct.pl/polityka-prywatnosci)';
    $out[] = '- [Status usług](https://status.dnct.pl): stan strony, panelu, logowania i węzłów gier sprawdzany co minutę, historia 90 dni i lista przerw.';
    $out[] = '';
    $out[] = 'Strona dostępna także po angielsku (?lang=en) i niemiecku (?lang=de); ceny w euro są tam tylko poglądowe, płatność idzie w PLN.';
    $out[] = '';
    $out[] = '<!-- wygenerowano ' . czas_teraz('d.m.Y H:i') . ' przez backend/tools/llms.php -->';

    return implode("\n", $out) . "\n";
}
