<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Mody to jedna z największych zalet Vintage Story: od nowych zwierząt i roślin, przez lepsze mapy, po całe nowe systemy rzemiosła. Na serwer wgrywa się je po prostu jako pliki - bez dodatkowych instalatorów.</p>

<h2>1. Skąd brać mody</h2>
<p>Oficjalne źródło to <strong>ModDB</strong>: <code>mods.vintagestory.at</code>. Przy każdym modzie jest lista wersji gry, z którymi działa - pobieraj wydanie zgodne z wersją Twojego serwera. Mod pobierasz jako plik <code>.zip</code> (czasem <code>.cs</code> albo <code>.dll</code>).</p>

<h2>2. Wgrywanie modów</h2>
<ol>
    <li>Zatrzymaj serwer przyciskiem <strong>Stop</strong>.</li>
    <li>W panelu otwórz <strong>Pliki</strong> i wejdź do folderu <code>data/Mods</code>.</li>
    <li>Kliknij <strong>Upload</strong> i wgraj pliki modów. <strong>Nie rozpakowuj</strong> plików <code>.zip</code> - gra czyta je w tej postaci.</li>
    <li>Uruchom serwer. W konsoli zobaczysz listę wczytanych modów, a na końcu <code>Dedicated Server now running</code>.</li>
</ol>

<h2>3. Mody po stronie graczy</h2>
<p>Większość modów musi mieć także każdy gracz. Przy łączeniu z serwerem gra pokazuje, których modów brakuje, i zwykle proponuje ich pobranie. Najprościej podać znajomym listę modów z linkami do ModDB, żeby mieli te same wersje.</p>
<p>Część modów działa tylko po stronie serwera (np. zmiany w zachowaniu świata) - w opisie na ModDB jest zwykle informacja „server-side only".</p>

<h2>4. Wyłączanie i usuwanie modów</h2>
<ul>
    <li>Żeby wyłączyć mod, usuń jego plik z <code>data/Mods</code> (albo przenieś go do innego folderu) i zrestartuj serwer.</li>
    <li>Usunięcie moda, który dodawał bloki albo przedmioty, sprawi, że znikną one ze świata. Zrób wcześniej kopię zapasową w zakładce <strong>Backupy</strong>.</li>
</ul>

<h2>5. Gdy serwer nie startuje po dodaniu modów</h2>
<ul>
    <li><strong>Błąd o brakującej zależności</strong> - mod wymaga innego moda (często biblioteki). Konsola podaje jego nazwę - dograj go do <code>data/Mods</code>.</li>
    <li><strong>Mod dla innej wersji gry</strong> - pobierz wydanie zgodne z wersją serwera albo usuń mod.</li>
    <li><strong>Brak pamięci przy wielu modach</strong> - przejdź na pakiet z większą liczbą slotów (więcej RAM-u) albo ogranicz liczbę modów.</li>
    <li>Dodawaj mody <strong>po kilka naraz</strong> - jeśli coś przestanie działać, od razu wiesz, który jest winny.</li>
</ul>
<p>Pierwszy raz stawiasz serwer? Zacznij od poradnika <a href="/poradniki/jak-postawic-serwer-vintage-story">jak postawić serwer Vintage Story</a>.</p>
<?php
$poradnik = [
    'slug'  => 'mody-na-serwer-vintage-story',
    'tytul' => 'Jak dodać mody na serwer Vintage Story',
    'title' => 'Mody na serwer Vintage Story - jak dodać | DNCT',
    'opis'  => 'Jak dodać mody z ModDB na serwer Vintage Story: folder data/Mods, pliki .zip bez rozpakowywania, mody u graczy, usuwanie i typowe błędy.',
    'data'  => '2026-10-08',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
