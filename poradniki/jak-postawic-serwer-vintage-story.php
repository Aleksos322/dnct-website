<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Vintage Story najlepiej smakuje we wspólnym, stale działającym świecie - z bazą, do której znajomi dochodzą, kiedy chcą. Poniżej wszystko, co trzeba: ile mocy potrzebuje serwer, pierwsze uruchomienie, połączenie, uprawnienia admina i najważniejsze ustawienia.</p>

<h2>1. Ile RAM-u potrzebuje serwer Vintage Story?</h2>
<ul>
    <li><strong>do 5 graczy, bez modów:</strong> 4 GB RAM,</li>
    <li><strong>10-20 graczy:</strong> 4,5-6 GB RAM,</li>
    <li><strong>większy serwer albo dużo modów:</strong> 8 GB i więcej.</li>
</ul>
<p>Pamięć rośnie z liczbą wczytanych obszarów mapy - gracze rozjechani po świecie obciążają serwer bardziej niż ci sami gracze w jednej osadzie.</p>

<h2>2. Zamówienie i pierwsze uruchomienie</h2>
<ol>
    <li>Wybierz liczbę graczy na stronie <a href="/hosting-vintage-story">hostingu Vintage Story</a>, okres i sposób płatności.</li>
    <li>Po płatności serwer zakłada się sam. W panelu (przycisk „Otwórz w panelu" na stronie <a href="/twoje-serwery">Twoje serwery</a>) kliknij <strong>Start</strong>.</li>
    <li>Pierwszy start tworzy świat. Serwer jest gotowy, gdy w konsoli pojawi się <code>Dedicated Server now running on Port ...</code>.</li>
    <li>Tuż po starcie mogą pojawić się ostrzeżenia <code>Server overloaded</code> - to generowanie świata, mijają po chwili.</li>
</ol>

<h2>3. Jak połączyć się z serwerem</h2>
<ol>
    <li>Skopiuj <strong>adres serwera</strong> z panelu - IP z portem, np. <code>123.45.67.89:60001</code>.</li>
    <li>W grze: <strong>Multiplayer → Add Server</strong>, wklej adres razem z portem i zapisz.</li>
    <li>Serwer domyślnie <strong>nie pokazuje się</strong> na publicznej liście serwerów - łączysz się po adresie. Chcesz go upublicznić? Ustaw <code>"AdvertiseServer": true</code> (punkt 5).</li>
</ol>

<h2>4. Zostań administratorem</h2>
<p>W <strong>konsoli panelu</strong> wpisz komendę z nazwą swojego konta Vintage Story:</p>
<pre><code>/op TwojaNazwa                     uprawnienia administratora
/player TwojaNazwa role admin      to samo, nowszy zapis
/whitelist add NazwaGracza         dodanie gracza do białej listy</code></pre>
<p>Serwer sprawdza nazwę w kontach gry, więc musi to być prawdziwa nazwa konta - inaczej odpowie <code>No player with name ... exists</code>.</p>

<h2>5. Najważniejsze ustawienia</h2>
<p>Konfiguracja serwera to plik <code>data/serverconfig.json</code> (zakładka <strong>Pliki</strong>). Edytuj go przy <strong>zatrzymanym</strong> serwerze - działający serwer nadpisuje plik przy zapisie.</p>
<pre><code>"ServerName": "Mój serwer",          nazwa serwera
"WelcomeMessage": "Witaj {0}!",       powitanie ({0} = nick gracza)
"MaxClients": 5,                      limit graczy
"Password": null,                     hasło, np. "tajne"; null = bez hasła
"AdvertiseServer": false,             true = widoczny na publicznej liście
"WhitelistMode": 0,                   biała lista (1 = włączona)
"AllowPvP": true,                     walka między graczami
"AllowFireSpread": true,              rozprzestrzenianie się ognia</code></pre>
<p><strong>MaxClients</strong> ustaw nie wyżej niż liczba slotów w Twoim pakiecie. Uważaj na przecinki i cudzysłowy - błąd składni w JSON-ie zatrzyma start serwera.</p>
<p>Świat zapisuje się w <code>data/Saves/default.vcdbs</code>. Przed większymi zmianami zrób kopię zapasową w zakładce <strong>Backupy</strong>.</p>

<p>Dalej: <a href="/poradniki/mody-na-serwer-vintage-story">jak dodać mody na serwer Vintage Story</a>.</p>
<?php
$poradnik = [
    'slug'  => 'jak-postawic-serwer-vintage-story',
    'tytul' => 'Jak postawić serwer Vintage Story',
    'title' => 'Jak postawić serwer Vintage Story - poradnik | DNCT',
    'opis'  => 'Ile RAM-u potrzebuje serwer Vintage Story, pierwsze uruchomienie, łączenie przez IP, komenda /op, serverconfig.json i najważniejsze ustawienia.',
    'data'  => '2026-10-08',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
