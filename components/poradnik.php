<?php
/**
 * Wspólny układ poradników (/poradniki/...).
 *
 * Strona poradnika ustawia $poradnik i dołącza ten plik zamiast clear.php:
 *   slug, tytul (H1), title (<title>), opis (meta), data (Y-m-d), tresc (HTML)
 * Tu dochodzą: okruszki, JSON-LD Article + BreadcrumbList, ramka z ofertą
 * i lista pozostałych poradników - żeby każdy poradnik prowadził do strony
 * hostingu i do siebie nawzajem (linkowanie wewnętrzne).
 *
 * Wszystkie instrukcje sprawdzone na prawdziwym serwerze Zomboida w panelu
 * DNCT (ścieżki plików, nazwy ustawień) - nie piszemy tu rzeczy "z pamięci".
 */

declare(strict_types=1);

const PORADNIKI = [
    'jak-postawic-serwer-minecraft'       => ['minecraft', 'Jak postawić serwer Minecraft'],
    'pluginy-na-serwer-minecraft'         => ['minecraft', 'Jak dodać pluginy na serwer Minecraft (Paper)'],
    'mody-na-serwer-minecraft'            => ['minecraft', 'Mody i modpacki na serwer Minecraft (Fabric, Forge)'],
    'jak-wgrac-swiat-na-serwer-minecraft' => ['minecraft', 'Jak wgrać własny świat na serwer Minecraft'],
    'serwer-minecraft-dla-znajomych'      => ['minecraft', 'Serwer Minecraft dla znajomych: jaki plan wybrać?'],
    'jak-postawic-serwer-project-zomboid' => ['zomboid', 'Jak postawić serwer Project Zomboid (Build 42)'],
    'mody-na-serwer-project-zomboid'      => ['zomboid', 'Jak dodać mody ze Steam Workshop na serwer Zomboid'],
    'ustawienia-serwera-project-zomboid'  => ['zomboid', 'Ustawienia serwera Zomboid: hasło, PvP, gracze, kopie'],
    'zapis-zomboid-na-serwer'             => ['zomboid', 'Jak przenieść zapis Project Zomboid na serwer'],
    'serwer-project-zomboid-dla-znajomych' => ['zomboid', 'Serwer Project Zomboid dla znajomych: koszt i dobór'],
    'jak-postawic-serwer-vintage-story'   => ['vintagestory', 'Jak postawić serwer Vintage Story'],
    'mody-na-serwer-vintage-story'        => ['vintagestory', 'Jak dodać mody na serwer Vintage Story'],
    'serwer-vintage-story-dla-znajomych'  => ['vintagestory', 'Serwer Vintage Story dla znajomych: jaki plan wybrać?'],
];

/* Ramka z ofertą pod poradnikiem - per gra. */
const PORADNIKI_OFERTA = [
    'zomboid'   => ['Serwer Project Zomboid od 29,99 zł miesięcznie', 'Od 5 do 100 graczy, stały RAM i CPU, dyski SSD, serwery w Polsce. Zakładamy go automatycznie zaraz po płatności kartą lub BLIK-iem.', '/hosting-project-zomboid', 'Project Zomboid Build 42'],
    'vintagestory' => ['Serwer Vintage Story od 19,99 zł miesięcznie', 'Od 5 do 100 graczy, aktualna wersja gry, stały RAM i CPU, dyski SSD, serwery w Polsce. Zakładamy go automatycznie zaraz po płatności kartą lub BLIK-iem.', '/hosting-vintage-story', 'Vintage Story'],
    'minecraft' => ['Serwer Minecraft od 6,99 zł miesięcznie', 'Paper, Fabric albo Forge, od 1,5 do 32 GB RAM, dyski SSD, serwery w Polsce. Zakładamy go automatycznie zaraz po płatności kartą lub BLIK-iem.', '/hosting-minecraft', 'Minecraft Java'],
];

/** @var array{slug:string,tytul:string,title:string,opis:string,data:string,tresc:string} $poradnik */
$gra    = PORADNIKI[$poradnik['slug']][0] ?? 'zomboid';
$oferta = PORADNIKI_OFERTA[$gra];
$url = 'https://game.dnct.pl/poradniki/' . $poradnik['slug'];

$pageTitle       = $poradnik['title'];
$metaDescription = $poradnik['opis'];
$canonicalUrl    = $url;
$centerContent   = false;

$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'         => 'Article',
            'headline'      => $poradnik['tytul'],
            'description'   => $poradnik['opis'],
            'datePublished' => $poradnik['data'],
            'dateModified'  => $poradnik['data'],
            'inLanguage'    => 'pl',
            'mainEntityOfPage' => $url,
            'author'        => ['@type' => 'Organization', 'name' => 'DNCT Hosting', 'url' => 'https://game.dnct.pl'],
            'publisher'     => ['@type' => 'Organization', 'name' => 'DNCT Hosting',
                                'logo' => ['@type' => 'ImageObject', 'url' => 'https://game.dnct.pl/assets/media/icons/logo_s.png']],
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'DNCT Hosting', 'item' => 'https://game.dnct.pl/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Poradniki', 'item' => 'https://game.dnct.pl/poradniki/'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $poradnik['tytul'], 'item' => $url],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">
<style>
    .pr-okruszki { font-size: .85rem; color: #94a3b8; margin-bottom: 1rem; }
    .pr-okruszki a { color: #c4b5fd; text-decoration: none; }
    .legal pre { background: #0f0c18; border: 1px solid #34303e; border-radius: .6rem; padding: .9rem 1rem; overflow-x: auto; font-size: .85rem; line-height: 1.55; color: #e2e8f0; }
    .legal code { background: rgba(139,92,246,.15); padding: .1rem .35rem; border-radius: .3rem; font-size: .9em; color: #e9d5ff; }
    .legal pre code { background: none; padding: 0; color: inherit; }
    .pr-oferta { margin: 2.5rem 0 1rem; padding: 1.25rem 1.4rem; border: 1px solid rgba(139,92,246,.45); border-radius: 1rem; background: rgba(139,92,246,.08); }
    .pr-oferta strong { color: #f1f5f9; font-size: 1.05rem; }
    .pr-oferta p { margin: .4rem 0 .9rem; }
    .pr-oferta a.pr-btn { display: inline-block; padding: .65rem 1.2rem; border-radius: .6rem; background: #8b5cf6; color: #fff; font-weight: 700; text-decoration: none; }
    .pr-inne li { margin: .35rem 0; }
    .pr-data { font-size: .85rem; color: #94a3b8; margin: -1rem 0 1.5rem; }
    .legal ol { list-style: decimal; padding-left: 1.5rem; }
    .legal ul { list-style: disc; padding-left: 1.5rem; }
    .legal li::marker { color: #a78bfa; }
</style>
<article class="legal glass glass-content" style="border-radius:22px;padding:clamp(1.25rem,4vw,3rem);max-width:900px;margin:0 auto 4rem;">
    <nav class="pr-okruszki" aria-label="Okruszki">
        <a href="/">DNCT Hosting</a> › <a href="/poradniki/">Poradniki</a> › <?php echo htmlspecialchars($poradnik['tytul']); ?>
    </nav>
    <h1><?php echo htmlspecialchars($poradnik['tytul']); ?></h1>
    <p class="pr-data">Aktualizacja: <?php echo htmlspecialchars(date('d.m.Y', strtotime($poradnik['data']))); ?> · <?php echo htmlspecialchars($oferta[3]); ?></p>

    <?php echo $poradnik['tresc']; ?>

    <div class="pr-oferta">
        <strong><?php echo htmlspecialchars($oferta[0]); ?></strong>
        <p><?php echo htmlspecialchars($oferta[1]); ?></p>
        <a class="pr-btn" href="<?php echo htmlspecialchars($oferta[2]); ?>">Zobacz pakiety</a>
    </div>

    <h2>Inne poradniki</h2>
    <ul class="pr-inne">
        <?php foreach (PORADNIKI as $s => [$g, $t]): if ($s === $poradnik['slug'] || $g !== $gra) continue; ?>
            <li><a href="/poradniki/<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($t); ?></a></li>
        <?php endforeach; ?>
    </ul>
</article>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/assets/clear/clear.php';
