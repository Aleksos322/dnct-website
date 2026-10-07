<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Pluginy dodają serwerowi funkcje bez instalowania czegokolwiek u graczy: ochronę działek, ekonomię, rangi, teleporty, minigry. Działają na silnikach <strong>Paper</strong> i <strong>Purpur</strong> - nasz domyślny silnik to Paper, więc zwykle niczego nie trzeba zmieniać.</p>

<h2>1. Skąd brać pluginy</h2>
<ul>
    <li><strong>Modrinth</strong> (modrinth.com, filtr „Plugins") i <strong>Hangar</strong> (hangar.papermc.io) - nowoczesne, bezpieczne katalogi,</li>
    <li><strong>SpigotMC</strong> (spigotmc.org/resources) - największy wybór, także starsze pluginy.</li>
</ul>
<p>Pobieraj pluginy tylko z tych stron. Plik <code>.jar</code> z nieznanego źródła może przejąć cały serwer.</p>

<h2>2. Sprawdź wersję</h2>
<p>Przy każdym pluginie jest lista obsługiwanych wersji Minecrafta. Wersję serwera zobaczysz w zakładce <strong>Wersje (MC)</strong> albo w konsoli przy starcie. Plugin dla starszej wersji często działa, dla nowszej - prawie nigdy.</p>

<h2>3. Wgrywanie pluginu</h2>
<ol>
    <li>W panelu otwórz <strong>Pliki</strong> i wejdź do folderu <code>plugins</code>.</li>
    <li>Kliknij <strong>Upload</strong> (Prześlij) i wybierz pobrany plik <code>.jar</code>.</li>
    <li>Zrestartuj serwer przyciskiem <strong>Restart</strong>.</li>
    <li>W konsoli wpisz <code>plugins</code> - nowy plugin powinien być na liście na zielono.</li>
</ol>
<p>Nie używaj komendy <code>reload</code> do wczytywania pluginów - wiele z nich psuje się po takim przeładowaniu. Zawsze pełny restart.</p>

<h2>4. Konfiguracja pluginu</h2>
<p>Po pierwszym starcie plugin tworzy swój folder w <code>plugins/NazwaPluginu/</code>, zwykle z plikiem <code>config.yml</code>. Edytujesz go w menedżerze plików i restartujesz serwer.</p>
<p>W plikach <code>.yml</code> liczą się <strong>wcięcia spacjami</strong> (nie tabulatorami). Jedna przesunięta spacja potrafi wyłączyć cały plugin - w konsoli zobaczysz wtedy błąd z numerem linii.</p>

<h2>5. Pluginy, od których warto zacząć</h2>
<ul>
    <li><strong>LuckPerms</strong> - rangi i uprawnienia,</li>
    <li><strong>EssentialsX</strong> - domy, teleporty, kity, podstawowe komendy,</li>
    <li><strong>WorldGuard</strong> + <strong>WorldEdit</strong> - ochrona terenu i budowanie,</li>
    <li><strong>CoreProtect</strong> - historia zmian bloków, cofanie zniszczeń po griefie.</li>
</ul>
<p>Niektóre pluginy wymagają innych (np. WorldGuard wymaga WorldEdit). Brakującą zależność konsola podaje przy starcie w linii z błędem.</p>

<h2>6. Gdy coś nie działa</h2>
<ul>
    <li><strong>Plugin na czerwono w <code>plugins</code></strong> - nie wystartował. Przewiń konsolę do pierwszego błędu z jego nazwą: zwykle zła wersja albo brak zależności.</li>
    <li><strong>Serwer zaczął lagować</strong> - wyłącz pluginy po kolei (przenieś <code>.jar</code> do innego folderu i zrestartuj), żeby znaleźć winny.</li>
    <li><strong>Pluginy nie działają wcale</strong> - sprawdź w <strong>Wersje (MC)</strong>, czy serwer to Paper, a nie Vanilla, Fabric lub Forge.</li>
</ul>
<p>Przed instalacją większej paczki pluginów zrób kopię zapasową w zakładce <strong>Backupy</strong>.</p>
<?php
$poradnik = [
    'slug'  => 'pluginy-na-serwer-minecraft',
    'tytul' => 'Jak dodać pluginy na serwer Minecraft (Paper)',
    'title' => 'Pluginy na serwer Minecraft (Paper) - jak dodać | DNCT',
    'opis'  => 'Skąd pobierać pluginy, jak sprawdzić wersję, wgrać plik .jar do folderu plugins, skonfigurować config.yml i co zrobić, gdy plugin nie działa.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
