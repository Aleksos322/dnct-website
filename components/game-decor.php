<?php
/**
 * Dekoracje boczne cennika — po jednym zestawie na grę.
 *
 * DLACZEGO RYSOWANE, A NIE WGRANE
 *
 * W assets/media nie ma żadnych grafik poza ikonami gier i tłami kart. Zamiast
 * ściągać cudze rendery (prawa autorskie do materiałów z gier są tu realnym
 * problemem — Mojang i The Indie Stone mają własne wytyczne), zestawy są
 * rysowane wektorowo w motywie, nie z materiałów wydawcy. Efekt uboczny jest
 * korzystny: cały zestaw waży kilka kilobajtów tekstu, skaluje się bez
 * rozmycia i przyjmuje kolory z CSS-a.
 *
 * DWIE TECHNIKI
 *
 * 1. IZOMETRYCZNE BLOKI (decor_cube) — trzy równoległoboki: góra, lewy i prawy
 *    bok. Czytają się jako "klocek" bez ani jednego pliku graficznego.
 * 2. PIXEL ART (decor_pixels) — siatka kwadratów opisana tablicą napisów, gdzie
 *    znak wskazuje kolor z palety. Zapis jest czytelny w kodzie: obrazek widać
 *    wprost w źródle i poprawia się go, zmieniając litery.
 *
 * MOTYW
 *
 * Minecraft dostaje zestaw z Endu — purpur, kamień Kresu, kwiat chorusa,
 * kryształ. To nawiązanie do tego, jak strona i tak wygląda: ciemny fiolet
 * z poświatą. Pozostałe gry mają własne palety, żeby przełączenie zakładki
 * było widoczne kątem oka, a nie tylko w treści.
 *
 * DOSTĘPNOŚĆ: cała warstwa jest aria-hidden i pointer-events:none. To czysta
 * ozdoba — nie może przechwytywać kliknięć ani trafiać do czytników ekranu.
 */

declare(strict_types=1);

if (!function_exists('render_game_decor')) {

/**
 * Izometryczny klocek w kwadracie 100x100.
 *
 * Wierzchołki są stałe — zmieniają się wyłącznie kolory ścianek. Górna ścianka
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
 * Każdy zestaw to sześć elementów — tyle wypełnia obie kolumny marginesu bez
 * robienia z nich tapety. Pozycje i rozmiary siedzą w CSS (klasy d1..d6), tutaj
 * jest wyłącznie treść graficzna.
 *
 * @return array<string,array<int,string>>
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

    /* --- Project Zomboid: zapuszczone przedmieścia ---------------------- */
    $plank   = decor_cube('#9c7a52', '#6d5335', '#57422a');
    $asphalt = decor_cube('#4a4f4a', '#31352f', '#242721');

    $medkit = decor_pixels([
        '.rrrrrr.',
        'rrrrrrrr',
        'rrrwwrrr',
        'rrwwwwrr',
        'rrwwwwrr',
        'rrrwwrrr',
        'rrrrrrrr',
        '.rrrrrr.',
    ], ['r' => '#9b2f2f', 'w' => '#f2f2f2']);

    $hazard = decor_pixels([
        '...yy...',
        '...yy...',
        '..yyyy..',
        '..ykky..',
        '.yykkyy.',
        '.yyykyy.',
        'yyyyyyyy',
        '........',
    ], ['y' => '#c9a227', 'k' => '#1c1a12']);

    $barrel = decor_pixels([
        '.oooooo.',
        'obbbbbbo',
        'obbbbbbo',
        'ooooooooo',
        'obbbbbbo',
        'obbbbbbo',
        'ooooooooo',
        '.oooooo.',
    ], ['o' => '#7a5b2e', 'b' => '#a8813f']);

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

    return [
        'minecraft'    => [$purpur, $chorus, $endStone, $crystal, $obsidian, $pearl],
        'zomboid'      => [$plank, $medkit, $asphalt, $hazard, $plank, $barrel],
        'vintagestory' => [$copper, $ingot, $clay, $pot, $copper, $resin],
    ];
}

/**
 * Warstwa dekoracji dla jednej gry.
 *
 * Zestawy dla WSZYSTKICH gier trafiają do HTML-a naraz i przełączają się klasą
 * — dzięki temu zmiana zakładki nie czeka na nic i nie miga. Sześć drobnych
 * SVG na grę to nadal mniej bajtów niż jedna ikona PNG.
 */
function render_game_decor(string $type): void
{
    $set = decor_sets()[$type] ?? null;
    if ($set === null) {
        return;
    }
    ?>
    <div class="cnk-decor" data-decor="<?php echo htmlspecialchars($type); ?>" aria-hidden="true">
        <?php foreach ($set as $i => $svg): ?>
            <span class="cnk-decor-item d<?php echo $i + 1; ?>">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><?php echo $svg; ?></svg>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
}

}
