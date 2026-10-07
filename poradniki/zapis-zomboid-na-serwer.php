<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Grałeś sam albo ze znajomymi na hoście w grze i chcecie kontynuować ten świat na serwerze, który działa całą dobę? Świat z zapisu da się przenieść na serwer Project Zomboid. Poniżej jak to zrobić i co przejdzie, a co nie.</p>

<h2>Co przechodzi, a co nie</h2>
<ul>
    <li><strong>Przechodzi:</strong> mapa ze wszystkimi zmianami - zbudowane bazy, przedmioty w kontenerach, zniszczenia, pojazdy.</li>
    <li><strong>Nie przechodzi automatycznie:</strong> postać z gry jednoosobowej. Na serwerze postacie są przypisane do kont graczy, więc zaczniesz nową postacią w tym samym świecie (z zachowanymi bazami i zapasami).</li>
    <li><strong>Wersja gry musi się zgadzać.</strong> Zapisu z Build 41 nie uruchomisz na serwerze Build 42.</li>
</ul>

<h2>1. Znajdź zapis na komputerze</h2>
<p>Zapisy leżą w folderze <code>Zomboid</code> w katalogu użytkownika:</p>
<pre><code>C:\Users\TwojaNazwa\Zomboid\Saves\</code></pre>
<p>W środku są foldery trybów (np. <code>Survivor</code>, <code>Sandbox</code>), a w nich foldery poszczególnych zapisów. Świat hostowany w grze dla znajomych leży w <code>Saves\Multiplayer\</code>. Spakuj folder wybranego zapisu do <code>.zip</code>.</p>

<h2>2. Przygotuj serwer</h2>
<ol>
    <li>Zrób kopię zapasową w zakładce <strong>Backupy</strong>.</li>
    <li>Dodaj na serwerze <strong>te same mody</strong>, z którymi grałeś (zakładka <strong>Mody (PZ)</strong>, opis w <a href="/poradniki/mody-na-serwer-project-zomboid">poradniku o modach</a>). Bez nich przedmioty i pojazdy z modów znikną.</li>
    <li>Ustaw podobne zasady świata w <code>ProjectZomboid_SandboxVars.lua</code> (<a href="/poradniki/ustawienia-serwera-project-zomboid">ustawienia serwera</a>) - serwer używa swoich ustawień, a nie tych z zapisu.</li>
    <li>Zatrzymaj serwer przyciskiem <strong>Stop</strong>.</li>
</ol>

<h2>3. Podmień zapis na serwerze</h2>
<ol>
    <li>W zakładce <strong>Pliki</strong> wejdź do folderu <code>.cache/Saves/Multiplayer/ProjectZomboid</code> - to świat serwera.</li>
    <li>Usuń jego zawartość (kopię masz w Backupach).</li>
    <li>Wgraj swój <code>.zip</code> do tego folderu i wybierz <strong>Unarchive</strong> (Rozpakuj).</li>
    <li>Sprawdź, że pliki zapisu (np. <code>map_t.bin</code>, <code>map_meta.bin</code>) leżą <strong>bezpośrednio</strong> w <code>ProjectZomboid</code>, a nie w dodatkowym podfolderze. Jeśli jest podfolder, przenieś pliki piętro wyżej.</li>
    <li>Uruchom serwer i poczekaj na <code>*** SERVER STARTED ****</code> w konsoli.</li>
</ol>
<p>Przez przeglądarkę wgrasz plik do <strong>100 MB</strong>. Większe zapisy (duże, długo grane światy) wgraj przez <strong>SFTP</strong> - dane znajdziesz w zakładce <strong>Ustawienia → Połączenie SFTP</strong>, a w razie kłopotu z hasłem napisz do nas na Discordzie.</p>

<h2>Gdy coś nie działa</h2>
<ul>
    <li><strong>Serwer wygenerował nowy świat</strong> - pliki leżą w podfolderze albo w złym miejscu. Mają być bezpośrednio w <code>.cache/Saves/Multiplayer/ProjectZomboid</code>.</li>
    <li><strong>Błędy o brakujących przedmiotach</strong> - na serwerze brakuje któregoś moda z gry jednoosobowej.</li>
    <li><strong>Serwer pada przy wczytywaniu</strong> - sprawdź koniec konsoli. „Out of memory" przy dużym świecie z modami oznacza, że potrzeba więcej RAM-u.</li>
</ul>
<?php
$poradnik = [
    'slug'  => 'zapis-zomboid-na-serwer',
    'tytul' => 'Jak przenieść zapis Project Zomboid na serwer',
    'title' => 'Przeniesienie zapisu Project Zomboid na serwer | DNCT',
    'opis'  => 'Jak przenieść świat z gry jednoosobowej Project Zomboid na serwer: gdzie są zapisy, co przechodzi, mody, podmiana zapisu w panelu i typowe błędy.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
