<?php
/**
 * Dekoracje boczne cennika Vintage Story - zazębione koła zębate
 * ("temporal gear" z gry) w różnych grupach po obu stronach strony.
 *
 * Każde koło kręci się powoli w pętli (vs-spin) i dodatkowo razem
 * z przewijaniem (--vs-turn z assets/js/cennik-vs.js). Koła w parze są
 * zazębione: obracają się w przeciwne strony, a mniejsze szybciej -
 * w proporcji liczby zębów, jak prawdziwa przekładnia.
 *
 * Rysowane tutaj (SVG), bez grafik z gry - nie mamy do nich praw.
 * Warstwa jest aria-hidden i poniżej 900 px wyłączona (cennik.css).
 */

declare(strict_types=1);

if (!function_exists('render_vs_world')) {

/** Koło zębate 200x200: zęby-trapezy, obręcz, szprychy, piasta i nity. */
function vs_gear_svg(int $teeth): string
{
    $c = 100; $R = 96; $r = 82;
    $base = M_PI / $teeth * 0.56;
    $tip  = M_PI / $teeth * 0.32;
    $pts  = [];
    for ($k = 0; $k < $teeth; $k++) {
        $a = 2 * M_PI * $k / $teeth;
        foreach ([[$r, -$base], [$R, -$tip], [$R, $tip], [$r, $base]] as [$rad, $off]) {
            $pts[] = sprintf('%.1f,%.1f', $c + $rad * cos($a + $off), $c + $rad * sin($a + $off));
        }
    }
    $spokes = '';
    for ($k = 0; $k < 5; $k++) {
        $spokes .= sprintf('<rect x="94" y="30" width="12" height="44" rx="4" fill="url(#vsRust)" transform="rotate(%d 100 100)"/>', $k * 72);
    }
    $rivets = '';
    for ($k = 0; $k < 10; $k++) {
        $a = 2 * M_PI * $k / 10;
        $rivets .= sprintf('<circle cx="%.1f" cy="%.1f" r="2.6" fill="#3b2314"/>', $c + 70 * cos($a), $c + 70 * sin($a));
    }
    return '<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><g class="vs-spin">'
         . '<polygon points="' . implode(' ', $pts) . '" fill="url(#vsRust)" stroke="#3b2314" stroke-width="2"/>'
         . '<circle cx="100" cy="100" r="76" fill="none" stroke="#3b2314" stroke-width="3" opacity="0.6"/>'
         . '<circle cx="100" cy="100" r="64" fill="#2a1a10"/>'
         . $spokes . $rivets
         . '<circle cx="100" cy="100" r="26" fill="url(#vsRust)" stroke="#3b2314" stroke-width="3"/>'
         . '<circle cx="100" cy="100" r="11" fill="#1a100a"/>'
         . '<circle cx="62" cy="40" r="9" fill="#7c3a12" opacity="0.5"/><circle cx="150" cy="128" r="12" fill="#7c3a12" opacity="0.45"/>'
         . '</g></svg>';
}

function render_vs_world(): void
{
    /* GRUPY, NIE KOPIA. Każda grupa to inna przekładnia: łańcuch trzech kół,
       para, samotne koło. Koła w grupie stykają się zębami - odstęp środków
       to suma promieni tocznych (0.89 promienia zewnętrznego). Rozmiar idzie
       z liczby zębów (wspólny moduł w grupie), więc zęby pasują wielkością.

       Grupa: [strona, x środka 1. koła od krawędzi (px), góra (CSS),
               skala (głębia), krycie, [[zęby, kąt do poprzedniego koła], ...]]
       Kąt: 0 = w stronę treści, 90 = w dół, -90 = w górę. */
    $groups = [
        ['left',  -40, '2%',  1.0,  0.55, [[16, 0], [9, 32], [12, 96]]],
        ['right', -40, '9%',  1.15, 0.4,  [[19, 0]]],
        ['left',   70, '25%', 0.8,  0.45, [[8, 0], [5, 58]]],
        ['right',  70, '33%', 0.95, 0.5,  [[10, 0], [14, 64], [7, 128]]],
        ['left',   10, '48%', 1.0,  0.42, [[12, 0], [6, -38]]],
        ['right',  80, '60%', 0.85, 0.46, [[9, 0], [9, 105]]],
        ['left',  -80, '69%', 1.1,  0.5,  [[20, 0], [8, 14], [11, 82]]],
        ['right',  20, '86%', 0.9,  0.38, [[11, 0]]],
    ];
    $module = 17.5;   // px średnicy na ząb przy skali 1

    $gears = [];
    foreach ($groups as [$side, $x0, $top, $scale, $opacity, $chain]) {
        $cx = $x0; $cy = 0; $prev = null; $dir = 1;
        foreach ($chain as [$teeth, $angle]) {
            $size = $teeth * $module * $scale;
            if ($prev !== null) {
                $dist = ($prev + $size) / 2 * 0.89;
                $cx += cos(deg2rad($angle)) * $dist;
                $cy += sin(deg2rad($angle)) * $dist;
                $dir = -$dir;   // zazębione koło kręci się w drugą stronę
            }
            $gears[] = [$side, $cx - $size / 2, $top, $cy - $size / 2, $size, $teeth, $dir, $opacity];
            $prev = $size;
        }
    }
?>
<div class="vs-scene" data-vs-scene>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <linearGradient id="vsRust" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#c2783f"/><stop offset="0.55" stop-color="#8f4b22"/><stop offset="1" stop-color="#5c2e14"/>
            </linearGradient>
        </defs>
    </svg>

    <?php foreach ($gears as [$side, $x, $top, $y, $size, $teeth, $dir, $opacity]): ?>
        <span class="cnk-decor-item vs-gear is-<?php echo $side; ?>"
              style="--x:<?php echo round($x); ?>;--y:<?php echo round($y); ?>;--top:<?php echo $top; ?>;--size:<?php echo round($size); ?>;--o:<?php echo $opacity; ?>;--k:<?php echo round($dir * 16 / $teeth, 3); ?>;--dur:<?php echo round(70 * $teeth / 16, 1); ?>s;--dir:<?php echo $dir > 0 ? 'normal' : 'reverse'; ?>">
            <?php echo vs_gear_svg($teeth); ?>
        </span>
    <?php endforeach; ?>
</div>
<?php
}

}
