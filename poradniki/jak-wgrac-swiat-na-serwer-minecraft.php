<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Świat z gry jednoosobowej (albo z innego serwera) przeniesiesz na swój serwer w kilka minut. Wystarczy spakować folder świata, wgrać go w panelu i ustawić, który świat ma się uruchamiać.</p>

<h2>1. Znajdź i spakuj świat</h2>
<ol>
    <li>Na komputerze otwórz folder zapisów: wciśnij <strong>Win + R</strong>, wpisz <code>%appdata%\.minecraft\saves</code> i zatwierdź. Na macOS to <code>~/Library/Application Support/minecraft/saves</code>.</li>
    <li>Każdy folder to jeden świat. Kliknij swój prawym przyciskiem → <strong>Wyślij do → Folder skompresowany (zip)</strong>.</li>
    <li>Sprawdź <strong>wersję gry</strong>, w której grałeś na tym świecie. Serwer musi mieć tę samą albo nowszą wersję - świata z nowszej wersji nie da się otworzyć na starszej.</li>
</ol>

<h2>2. Zrób kopię i zatrzymaj serwer</h2>
<ol>
    <li>W panelu, w zakładce <strong>Backupy</strong>, zrób kopię zapasową - na wypadek, gdyby obecny świat jednak był potrzebny.</li>
    <li>Zatrzymaj serwer przyciskiem <strong>Stop</strong>. Podmiana świata na działającym serwerze kończy się uszkodzonym zapisem.</li>
</ol>

<h2>3. Wgraj świat</h2>
<ol>
    <li>W zakładce <strong>Pliki</strong> usuń (albo zmień nazwę) obecne foldery <code>world</code>, <code>world_nether</code> i <code>world_the_end</code>.</li>
    <li>Kliknij <strong>Upload</strong> i wgraj plik <code>.zip</code>. Przez przeglądarkę wgrasz plik do <strong>100 MB</strong>.</li>
    <li>Przy wgranym pliku wybierz <strong>Unarchive</strong> (Rozpakuj).</li>
    <li>Zmień nazwę rozpakowanego folderu na <code>world</code> - albo zostaw własną nazwę i wpisz ją w <code>server.properties</code> w linii <code>level-name=</code> (także w zakładce <strong>Configi</strong>).</li>
    <li>Upewnij się, że w środku folderu od razu leży plik <code>level.dat</code>, a nie kolejny folder ze światem. Jeśli jest podwójny folder, przenieś zawartość piętro wyżej.</li>
</ol>

<h2>4. Uruchom serwer</h2>
<p>Kliknij <strong>Start</strong>. Silniki Paper i Purpur trzymają Nether i End w osobnych folderach (<code>world_nether</code>, <code>world_the_end</code>). Przy pierwszym starcie same przenoszą je ze świata z gry jednoosobowej - w konsoli zobaczysz komunikat o migracji. Gdy pojawi się <code>Done (...)!</code>, możesz wchodzić.</p>

<h2>Świat większy niż 100 MB</h2>
<p>Duże światy wgrasz przez <strong>SFTP</strong>, np. programem FileZilla albo WinSCP. Adres i nazwę użytkownika znajdziesz w panelu w zakładce <strong>Ustawienia → Połączenie SFTP</strong>. Jeśli nie wiesz, jakiego hasła użyć, napisz do nas na Discordzie - pomożemy.</p>

<h2>Najczęstsze problemy</h2>
<ul>
    <li><strong>Serwer stworzył nowy, pusty świat</strong> - nazwa folderu nie zgadza się z <code>level-name</code> albo w folderze nie ma <code>level.dat</code> (podwójny folder po rozpakowaniu).</li>
    <li><strong>Brakuje Netheru lub Endu</strong> - na Paperze sprawdź foldery <code>world_nether</code> i <code>world_the_end</code>. Jeśli wgrywasz świat z innego serwera Paper, wgraj wszystkie trzy foldery.</li>
    <li><strong>Świat z modami</strong> - serwer musi mieć ten sam silnik i te same mody (<a href="/poradniki/mody-na-serwer-minecraft">mody i modpacki na serwer</a>), inaczej bloki z modów znikną.</li>
</ul>
<p>Pierwszy raz stawiasz serwer? Zacznij od poradnika <a href="/poradniki/jak-postawic-serwer-minecraft">jak postawić serwer Minecraft</a>.</p>
<?php
$poradnik = [
    'slug'  => 'jak-wgrac-swiat-na-serwer-minecraft',
    'tytul' => 'Jak wgrać własny świat na serwer Minecraft',
    'title' => 'Jak wgrać świat na serwer Minecraft - krok po kroku | DNCT',
    'opis'  => 'Jak przenieść świat z gry jednoosobowej na serwer Minecraft: gdzie są zapisy, pakowanie, wgrywanie w panelu, level-name, Nether i End na Paperze.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
