<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Własny serwer Minecraft to świat, który działa także wtedy, gdy Twój komputer jest wyłączony, i do którego znajomi wchodzą, kiedy chcą. Poniżej cała droga: wybór wielkości serwera, silnika, pierwsze uruchomienie, połączenie i podstawowe ustawienia.</p>

<h2>1. Ile RAM-u potrzebuje serwer Minecraft?</h2>
<ul>
    <li><strong>2-5 osób, czysty Minecraft:</strong> 3-4 GB RAM,</li>
    <li><strong>10-20 osób z pluginami:</strong> 6-8 GB RAM,</li>
    <li><strong>modpack (Forge, Fabric, NeoForge):</strong> zwykle 8-10 GB, duże paczki więcej,</li>
    <li><strong>serwer publiczny dla kilkudziesięciu graczy:</strong> 14 GB i więcej.</li>
</ul>
<p>Więcej RAM-u nie zawsze znaczy szybciej - Minecraft mocno korzysta z jednego rdzenia procesora. Gdy serwer „laguje" przy małym zużyciu pamięci, winne są zwykle zbyt duża odległość renderowania albo ciężkie farmy, a nie RAM.</p>

<h2>2. Paper, Fabric czy Forge?</h2>
<ul>
    <li><strong>Paper</strong> (domyślny u nas) - szybszy od zwykłego serwera, obsługuje <strong>pluginy</strong> (np. ochrona działek, ekonomia, uprawnienia). Gracze wchodzą czystym Minecraftem.</li>
    <li><strong>Fabric</strong> i <strong>Forge / NeoForge</strong> - obsługują <strong>mody</strong>. Gracze muszą mieć u siebie te same mody i tę samą wersję gry.</li>
    <li><strong>Vanilla</strong> - zwykły serwer od Mojangu, bez pluginów i modów.</li>
</ul>
<p>Silnik i wersję gry zmieniasz w panelu, w zakładce <strong>Wersje (MC)</strong>. Przed zmianą zrób kopię zapasową w zakładce <strong>Backupy</strong>.</p>

<h2>3. Zamówienie i pierwsze uruchomienie</h2>
<ol>
    <li>Wybierz plan na stronie <a href="/hosting-minecraft">hostingu Minecraft</a>, okres i sposób płatności.</li>
    <li>Po płatności serwer zakłada się sam. Wejdź do panelu (przycisk „Otwórz w panelu" na stronie <a href="/twoje-serwery">Twoje serwery</a>) i kliknij <strong>Start</strong>.</li>
    <li>Pierwszy start pobiera pliki serwera i generuje świat. Serwer jest gotowy, gdy w konsoli pojawi się linia <code>Done (...)! For help, type "help"</code>.</li>
</ol>

<h2>4. Jak połączyć się z serwerem</h2>
<ol>
    <li>Skopiuj <strong>adres serwera</strong> z panelu albo ze strony „Twoje serwery" - IP z portem, np. <code>123.45.67.89:60002</code>.</li>
    <li>W grze: <strong>Tryb wieloosobowy → Dodaj serwer</strong> i wklej adres razem z portem.</li>
    <li>Chcesz krótszy adres? W zakładce <strong>Subdomena</strong> ustawisz nazwę, którą łatwiej podać znajomym.</li>
</ol>

<h2>5. Pierwsze ustawienia i komendy</h2>
<p>Najważniejsze opcje (nazwa serwera, limit graczy, tryb gry, trudność, PvP, whitelista) zmienisz w zakładce <strong>Configi</strong> albo w pliku <code>server.properties</code> w menedżerze plików. Po zmianie zrestartuj serwer.</p>
<pre><code>motd=Mój serwer           opis widoczny na liście serwerów
max-players=10            limit graczy
difficulty=normal         peaceful / easy / normal / hard
gamemode=survival         survival / creative / adventure
pvp=true                  czy gracze mogą się atakować
white-list=false          true = wchodzą tylko gracze z listy
view-distance=10          mniejsza wartość = mniej lagów</code></pre>
<p>Komendy wpisujesz w <strong>konsoli panelu</strong> bez ukośnika:</p>
<pre><code>op TwojNick               uprawnienia administratora
whitelist add Nick        dodaje gracza do whitelisty
save-all                  zapisuje świat
stop                      zapisuje i wyłącza serwer</code></pre>
<p>Uprawnienia, whitelistę i bany ustawisz też bez komend - w zakładce <strong>Gracze (MC)</strong>.</p>

<h2>6. Najczęstsze problemy</h2>
<ul>
    <li><strong>„Outdated client" / „Outdated server"</strong> - wersja gry u gracza jest inna niż na serwerze. Ustaw wersję w <strong>Wersje (MC)</strong> albo zmień wersję w launcherze.</li>
    <li><strong>Gracze z kontem bez licencji nie mogą wejść</strong> - serwer domyślnie sprawdza konta Mojangu (<code>online-mode=true</code>). Wyłączenie tej opcji wpuszcza każdego pod dowolnym nickiem, więc wtedy koniecznie dodaj plugin do logowania.</li>
    <li><strong>Serwer wyłącza się sam</strong> - sprawdź koniec konsoli. „Out of memory" oznacza za mały plan na ten modpack albo tyle pluginów.</li>
</ul>
<p>Dalej: <a href="/poradniki/pluginy-na-serwer-minecraft">dodaj pluginy</a> albo <a href="/poradniki/mody-na-serwer-minecraft">zainstaluj mody i modpacki</a>.</p>
<?php
$poradnik = [
    'slug'  => 'jak-postawic-serwer-minecraft',
    'tytul' => 'Jak postawić serwer Minecraft',
    'title' => 'Jak postawić serwer Minecraft - poradnik krok po kroku | DNCT',
    'opis'  => 'Ile RAM-u potrzebuje serwer Minecraft, Paper czy Forge, pierwsze uruchomienie, łączenie przez IP, server.properties i komendy administratora.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
