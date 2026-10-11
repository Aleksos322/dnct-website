<?php
/**
 * Dekoracje boczne cennika - po jednym zestawie na grę.
 *
 * DWA ŹRÓDŁA GRAFIKI, JEDEN KOD
 *
 * MINECRAFT i PROJECT ZOMBOID mają prawdziwe grafiki: Minecraft rendery
 * (assets/media/minecraft) - statek z End City, geodę ametystową, komnatę
 * portalu, Creaking i spawner blaze'ów; Zomboid moodle ze stanu postaci
 * (assets/media/zomboid) - te same ikonki, które gracz zna z paska HUD-u.
 * Biją każdą wektorową imitację i były pod ręką, więc nie ma powodu ich udawać.
 *
 * VINTAGE STORY nie ma jeszcze takich materiałów, więc zostaje zestaw
 * RYSOWANY - lepiej to niż puste marginesy albo cudze grafiki wzięte z sieci,
 * do których nie mamy praw. Rysunki powstają dwiema technikami:
 *
 * 1. IZOMETRYCZNE BLOKI (decor_cube) - trzy równoległoboki: góra, lewy i prawy
 *    bok. Czytają się jako "klocek" bez ani jednego pliku graficznego.
 * 2. PIXEL ART (decor_pixels) - siatka kwadratów opisana tablicą napisów, gdzie
 *    znak wskazuje kolor z palety. Obrazek widać wprost w źródle i poprawia się
 *    go, zmieniając litery.
 *
 * Wybór jest DANYMI, nie rozgałęzieniem kodu: element zestawu z kluczem `img`
 * renderuje się jako obrazek (z folderu gry, patrz render_game_decor()),
 * napis - jako rysunek.
 *
 * ROZMIARY. Źródła Minecrafta miały po 2000 px i do megabajta; przeskalowane
 * do realnych rozmiarów wyświetlania cały zestaw waży 110 KB zamiast 3,4 MB.
 * Dekoracja, która kosztuje więcej niż reszta strony, przestaje być dekoracją.
 * Moodle Zomboida są małe z natury (126x126 w źródle) i nie potrzebują tego
 * zabiegu - dlatego nie dostają też powiększonych wymiarów jak Minecraft
 * w cennik.css (`[data-decor-for="game-minecraft"]`): to skalowanie było
 * pomyślane pod rozległe rendery, nie pod ikonki HUD-u.
 *
 * DOSTĘPNOŚĆ: cała warstwa jest aria-hidden i pointer-events:none. To czysta
 * ozdoba - nie może przechwytywać kliknięć ani trafiać do czytników ekranu.
 */

declare(strict_types=1);

if (!function_exists('render_game_decor')) {

/**
 * Izometryczny klocek w kwadracie 100x100.
 *
 * Wierzchołki są stałe - zmieniają się wyłącznie kolory ścianek. Górna ścianka
 * jest najjaśniejsza, prawa najciemniejsza; to wystarcza, żeby płaskie
 * wielokąty odczytać jako bryłę.
 */
function decor_cube(string $top, string $left, string $right): string
{
    return '<polygon points="50,8 92,32 50,56 8,32" fill="' . $top . '"/>'
         . '<polygon points="8,32 50,56 50,96 8,72" fill="' . $left . '"/>'
         . '<polygon points="92,32 50,56 50,96 92,72" fill="' . $right . '"/>';
}

/**
 * Pixel art z tablicy napisów.
 *
 * @param array<int,string>    $rows    Wiersze; jeden znak = jeden piksel, kropka = przezroczysty
 * @param array<string,string> $palette Znak => kolor
 */
function decor_pixels(array $rows, array $palette): string
{
    $size = 100 / max(count($rows), 1);
    $out  = '';

    foreach ($rows as $y => $row) {
        foreach (str_split($row) as $x => $char) {
            if (!isset($palette[$char])) {
                continue;
            }
            // +0.5 na wymiarze zamyka włoskowate szpary między kwadratami,
            // które przeglądarki potrafią zostawić przy ułamkowych pozycjach.
            $out .= sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s"/>',
                $x * $size,
                $y * $size,
                $size + 0.5,
                $size + 0.5,
                $palette[$char]
            );
        }
    }

    return $out;
}

/**
 * Zestawy dekoracji. Klucz to `type` z tabeli hosting_games.
 *
 * Każdy zestaw to sześć elementów - tyle wypełnia obie kolumny marginesu bez
 * robienia z nich tapety. Pozycje i rozmiary siedzą w CSS (klasy d1..d6), tutaj
 * jest wyłącznie treść graficzna.
 *
 * @return array<string,array<int,string|array<string,mixed>>>
 */
function decor_sets(): array
{
    /* --- Minecraft: End ------------------------------------------------- */
    $purpur    = decor_cube('#b07ab8', '#7d4f86', '#683f70');
    $endStone  = decor_cube('#e2e4a8', '#b9bb7e', '#9ea067');
    $obsidian  = decor_cube('#3b2f5f', '#241c3e', '#181231');

    $chorus = decor_pixels([
        '........',
        '..cbbc..',
        '.cbaabc.',
        '.baaaab.',
        '.baaaab.',
        '.cbaabc.',
        '..cbbc..',
        '........',
    ], ['a' => '#a06fa8', 'b' => '#7d4f86', 'c' => '#c9a2d1']);

    $crystal = decor_pixels([
        '...aa...',
        '..abba..',
        '.abccba.',
        'abccccba',
        '.abccba.',
        '..abba..',
        '...aa...',
        '.dddddd.',
    ], ['a' => '#e9b6f2', 'b' => '#c07ad0', 'c' => '#f6e6fb', 'd' => '#2a2140']);

    $pearl = decor_pixels([
        '..bbbb..',
        '.bcccbb.',
        'bccddcbb',
        'bcddddcb',
        'bcddddcb',
        'bccddcbb',
        '.bbccbb.',
        '..bbbb..',
    ], ['b' => '#0d5f52', 'c' => '#149c84', 'd' => '#3fe0c0']);

    /* --- Vintage Story: epoka miedzi ------------------------------------ */
    $copper = decor_cube('#d18a4a', '#9c6130', '#7d4c25');
    $clay   = decor_cube('#b8795c', '#8a563e', '#6f4531');

    $ingot = decor_pixels([
        '........',
        '..cccc..',
        '.cddddc.',
        'cdddddda',
        'cdddddda',
        '.cddddc.',
        '..cccc..',
        '........',
    ], ['c' => '#e0a76a', 'd' => '#c07f3f', 'a' => '#8a5526']);

    $pot = decor_pixels([
        '..tttt..',
        '.tuuuut.',
        'tuuuuuut',
        'tuuuuuut',
        'tuuuuuut',
        '.tuuuut.',
        '..tttt..',
        '........',
    ], ['t' => '#8a563e', 'u' => '#b8795c']);

    $resin = decor_pixels([
        '...aa...',
        '..aabb..',
        '.aabbbb.',
        'aabbbbcc',
        'aabbbbcc',
        '.abbbbc.',
        '..abbc..',
        '...ac...',
    ], ['a' => '#e8c169', 'b' => '#d9a441', 'c' => '#a8761f']);

    /* MINECRAFT I ZOMBOID MAJĄ PRAWDZIWE GRAFIKI, Vintage Story - rysowane
       klocki. Zomboid dostał komplet moodli (assets/media/zomboid) - te same
       ikonki stanu postaci, które gracz zna z paska HUD-u w grze; sześć z ośmiu,
       bo tyle mieści się w marginesie bez zamieniania go w tapetę (patrz
       docblock pliku). Wybrane są te najbardziej "grywalne" - zombified i dead
       czytelnie sygnalizują, o jaką grę chodzi, zanim ktoś przeczyta nazwę.

       Rozdzielenie jest DANYMI, nie osobnym kodem: element z kluczem `img`
       renderuje się jako obrazek, z kluczem `svg` jako rysunek. */
    return [
        'minecraft' => [
            ['img' => 'End_City_Ship',                'alt' => 'Statek z End City'],
            ['img' => 'Amethyst_Geode_3',             'alt' => 'Geoda ametystowa'],
            ['img' => 'Stronghold_Portal_Room',       'alt' => 'Komnata portalu'],
            ['img' => 'Creaking',                     'alt' => 'Creaking'],
            ['img' => 'Nether_Fortress_Blaze_Spawner','alt' => 'Spawner blaze’ów'],
            ['puffer' => true,                        'alt' => 'Rozdymka'],
        ],
        'zomboid' => [
            ['img' => 'Mood_Zombified', 'alt' => 'Moodle: zombified'],
            ['img' => 'Mood_Dead',      'alt' => 'Moodle: dead'],
            ['img' => 'Mood_Ill',       'alt' => 'Moodle: ill'],
            ['img' => 'Mood_Nauseous',  'alt' => 'Moodle: nauseous'],
            ['img' => 'Mood_Stressed',  'alt' => 'Moodle: stressed'],
            ['img' => 'Mood_Drunk',     'alt' => 'Moodle: drunk'],
        ],
        'vintagestory' => [$copper, $ingot, $clay, $pot, $copper, $resin],
    ];
}

/**
 * Warstwa dekoracji dla jednej gry.
 *
 * Zestawy dla WSZYSTKICH gier trafiają do HTML-a naraz i przełączają się klasą
 * - dzięki temu zmiana zakładki nie czeka na nic i nie miga. Sześć drobnych
 * SVG na grę to nadal mniej bajtów niż jedna ikona PNG.
 */
function render_game_decor(string $type): void
{
    /* Vintage Story ma osobną, dużą scenę zamiast sześciu drobiazgów -
       krajobraz z porami roku, burza czasowa, kuźnia (components/vs-world.php). */
    if ($type === 'vintagestory') {
        require_once __DIR__ . '/vs-world.php';
        render_vs_world();
        return;
    }

    $set = decor_sets()[$type] ?? null;
    if ($set === null) {
        return;
    }
    ?>
    <div class="cnk-decor" data-decor="<?php echo htmlspecialchars($type); ?>" aria-hidden="true">
        <?php foreach ($set as $i => $item): ?>
            <span class="cnk-decor-item d<?php echo $i + 1; ?><?php echo is_array($item) ? ' is-photo' : ''; ?>">
                <?php if (is_array($item) && !empty($item['puffer'])): ?>
                    <?php /* ROZDYMKA SIĘ NAPRAWDĘ ROZDYMA.
                             Trzy warstwy jedna na drugiej; kursor przełącza, która
                             jest widoczna. To nie ozdobnik - dokładnie tak działa
                             ta ryba w grze, więc żart trafia tylko do tych, którzy
                             grali, i nie przeszkadza reszcie. */ ?>
                    <span class="cnk-puffer">
                        <img src="/assets/media/minecraft/Pufferfish_Small.webp"  alt="" class="p1" loading="lazy" decoding="async">
                        <img src="/assets/media/minecraft/Pufferfish_Medium.webp" alt="" class="p2" loading="lazy" decoding="async">
                        <img src="/assets/media/minecraft/Pufferfish_Large.webp"  alt="" class="p3" loading="lazy" decoding="async">
                    </span>
                <?php elseif (is_array($item)): ?>
                    <?php /* Folder gry = $type, nie sztywno "minecraft" - dzięki temu
                             ten sam blok obsługuje też prawdziwe grafiki Zomboida
                             (assets/media/zomboid), bez rozgałęzienia w kodzie. */ ?>
                    <img src="/assets/media/<?php echo htmlspecialchars($type); ?>/<?php echo htmlspecialchars($item['img']); ?>.webp"
                         alt="" loading="lazy" decoding="async">
                <?php else: ?>
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><?php echo $item; ?></svg>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
}

}
