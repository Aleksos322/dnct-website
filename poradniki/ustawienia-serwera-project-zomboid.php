<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Serwer Zomboid ma dwa pliki ustawień. Pierwszy decyduje o tym, <strong>kto i jak</strong> może grać (hasło, PvP, limit graczy). Drugi o tym, <strong>jak trudny</strong> jest świat (zombie, łup, prąd i woda). Oba znajdziesz w panelu, w menedżerze plików, w folderze:</p>
<pre><code>.cache/Server/</code></pre>
<p>Edytuj je przy <strong>wyłączonym</strong> serwerze: w konsoli wpisz <code>save</code>, kliknij <strong>Stop</strong>, zmień plik, zapisz i uruchom serwer ponownie.</p>

<h2>ProjectZomboid.ini - zasady serwera</h2>
<p>Najważniejsze linie (każda wygląda jak <code>Nazwa=wartość</code>):</p>
<pre><code>Password=tajnehaslo        hasło do wejścia na serwer (puste = bez hasła)
Open=true                  false = wchodzą tylko gracze dodani przez admina
PVP=true                   czy gracze mogą się ranić nawzajem
SafetySystem=true          gracz sam włącza i wyłącza PvP (ikona czaszki)
MaxPlayers=10              limit graczy online
PauseEmpty=true            czas stoi, gdy nikogo nie ma na serwerze
Public=false               czy serwer widać w publicznej liście w grze
PublicName=Moj serwer      nazwa na liście serwerów
SleepAllowed=false         czy gracze mogą spać
PlayerSafehouse=false      czy gracze mogą zakładać bezpieczne domy
Faction=true               frakcje graczy
SaveWorldEveryMinutes=0    automatyczny zapis co X minut (0 = wyłączony)
BackupsCount=5             ile kopii świata trzyma sam Zomboid
BackupsOnStart=true        kopia przy każdym starcie</code></pre>
<ul>
    <li><strong>MaxPlayers</strong> ustaw nie wyżej niż liczba slotów w Twoim pakiecie - pakiet jest dobrany pod tyle osób, a więcej graczy to więcej zużytej pamięci.</li>
    <li><strong>Open=false</strong> to lista dozwolonych graczy: nowe konta dodajesz komendą <code>adduser "Nick" "haslo"</code> w konsoli.</li>
    <li><strong>SaveWorldEveryMinutes=10</strong> warto włączyć na serwerach, które chodzą całymi dniami - po nagłym wyłączeniu stracisz najwyżej 10 minut gry.</li>
    <li>Wiadomość powitalna to <code>ServerWelcomeMessage=</code>. Nową linię robisz znacznikiem <code>&lt;LINE&gt;</code>.</li>
</ul>

<h2>ProjectZomboid_SandboxVars.lua - trudność świata</h2>
<p>W tym pliku są ustawienia z menu „Sandbox": liczba i szybkość zombie, ilość łupu, kiedy wyłącza się prąd i woda, szybkość zdobywania doświadczenia. Każda opcja ma w pliku komentarz z dozwolonymi wartościami, np.:</p>
<pre><code>-- 1 = Insanely High, 2 = Very High, 3 = High, 4 = Normal, 5 = Low, 6 = None
Zombies = 4,</code></pre>
<p>Zmieniaj tylko liczbę po znaku <code>=</code> i nie usuwaj przecinka na końcu linii - brak przecinka to błąd składni i serwer nie wystartuje.</p>
<p>Część ustawień działa od razu po restarcie (np. szybkość zdobywania doświadczenia). Inne dotyczą tylko <strong>nowych</strong> obszarów mapy albo nowego świata - łupu w już odwiedzonych budynkach nie przybędzie po zmianie ustawienia.</p>

<h2>Kopie zapasowe przed zmianami</h2>
<p>Zanim zmienisz coś poważnego, zrób kopię zapasową w panelu (zakładka z kopiami zapasowymi). Przywrócenie trwa chwilę, a odbudowa świata po zepsutym ustawieniu - tygodnie gry.</p>

<p>Jeszcze nie masz serwera? Zacznij od <a href="/poradniki/jak-postawic-serwer-project-zomboid">pierwszego uruchomienia</a>. Chcesz mody? <a href="/poradniki/mody-na-serwer-project-zomboid">Tak je dodasz</a>.</p>
<?php
$poradnik = [
    'slug'  => 'ustawienia-serwera-project-zomboid',
    'tytul' => 'Ustawienia serwera Zomboid: hasło, PvP, gracze, kopie',
    'title' => 'Ustawienia serwera Project Zomboid (ProjectZomboid.ini) - poradnik | DNCT Hosting',
    'opis'  => 'Jak ustawić hasło, PvP, limit graczy, automatyczny zapis i trudność świata na serwerze Project Zomboid: ProjectZomboid.ini i SandboxVars krok po kroku.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
