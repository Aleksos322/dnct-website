<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Mody, w odróżnieniu od pluginów, zmieniają grę także po stronie gracza: nowe bloki, moby, maszyny, wymiary. Dlatego <strong>serwer i każdy gracz muszą mieć ten sam zestaw modów</strong> i tę samą wersję gry.</p>

<h2>1. Wybierz silnik pod mody</h2>
<p>Mod jest pisany pod konkretny silnik - informację znajdziesz na jego stronie (Modrinth, CurseForge):</p>
<ul>
    <li><strong>Fabric</strong> - lekkie mody, szybkie aktualizacje, większość modów wymaga dodatkowo <strong>Fabric API</strong>,</li>
    <li><strong>Forge</strong> / <strong>NeoForge</strong> - duże mody i większość klasycznych modpacków.</li>
</ul>
<p>Mody Fabrica nie działają na Forge i odwrotnie. Pluginy Paper nie działają na żadnym z nich.</p>

<h2>2. Zmiana silnika na serwerze</h2>
<ol>
    <li>Zrób kopię zapasową w zakładce <strong>Backupy</strong>.</li>
    <li>Zatrzymaj serwer.</li>
    <li>Otwórz zakładkę <strong>Wersje (MC)</strong>, wybierz silnik (Fabric, Forge albo NeoForge) i <strong>dokładnie tę wersję Minecrafta</strong>, pod którą są Twoje mody.</li>
    <li>Uruchom serwer raz bez modów - powinien wystartować i utworzyć folder <code>mods</code>.</li>
</ol>

<h2>3. Wgrywanie modów</h2>
<ol>
    <li>Pobierz mody w wersji <strong>dla serwera</strong> albo uniwersalnej. Mody wyłącznie klienckie (np. minimapy, shadery, ulepszenia grafiki) zostaw graczom - na serwerze potrafią go zatrzymać.</li>
    <li>W panelu otwórz <strong>Pliki → mods</strong> i prześlij pliki <code>.jar</code>.</li>
    <li>Na Fabricu dodaj też <strong>Fabric API</strong> w tej samej wersji gry.</li>
    <li>Zrestartuj serwer i obserwuj konsolę do linii <code>Done (...)!</code>.</li>
</ol>

<h2>4. Modpacki (CurseForge, Modrinth)</h2>
<ol>
    <li>Na stronie modpacka pobierz paczkę <strong>Server Pack</strong> (Server Files) - to wersja przygotowana pod serwer.</li>
    <li>Ustaw w <strong>Wersje (MC)</strong> silnik i wersję, które podaje modpack.</li>
    <li>Prześlij paczkę <code>.zip</code> w menedżerze plików i <strong>rozpakuj</strong> ją w głównym folderze serwera (opcja „Unarchive" przy pliku).</li>
    <li>Przekopiuj z paczki foldery <code>mods</code> i <code>config</code> (jeśli są w podfolderze, przenieś je wyżej).</li>
    <li>Uruchom serwer. Pierwszy start modpacka trwa dłużej.</li>
</ol>
<p>Część paczek na nowym Forge ma zamiast pliku <code>.jar</code> tylko skrypt <code>run.sh</code>. Jeśli tak jest w Twojej paczce, napisz do nas na Discordzie - ustawimy start serwera.</p>

<h2>5. Ile RAM-u na mody</h2>
<p>Kilka lekkich modów Fabrica zmieści się w 4 GB. Typowy modpack na Forge potrzebuje <strong>8-10 GB</strong>, a duże paczki z setkami modów - jeszcze więcej. Jeśli w konsoli widzisz „Out of memory", serwer potrzebuje większego planu.</p>

<h2>6. Gdy serwer nie startuje</h2>
<ul>
    <li><strong>„Missing mods" / „requires ..."</strong> - brakuje zależności. Konsola podaje nazwę brakującego moda.</li>
    <li><strong>„Mod ... is not compatible with this version"</strong> - mod jest pod inną wersję Minecrafta albo inny silnik.</li>
    <li><strong>Gracz wylatuje przy łączeniu z listą modów</strong> - u gracza brakuje moda albo ma inną wersję. Najprościej dać graczom ten sam modpack w launcherze (CurseForge, Modrinth, Prism).</li>
</ul>
<?php
$poradnik = [
    'slug'  => 'mody-na-serwer-minecraft',
    'tytul' => 'Mody i modpacki na serwer Minecraft (Fabric, Forge)',
    'title' => 'Mody i modpacki na serwer Minecraft - Fabric, Forge | DNCT',
    'opis'  => 'Jak zmienić silnik serwera na Fabric lub Forge, wgrać mody do folderu mods, zainstalować modpack z CurseForge lub Modrinth i ile RAM-u potrzeba.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
