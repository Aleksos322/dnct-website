<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Własny serwer Project Zomboid to najprostszy sposób, żeby grać ze znajomymi w jednym świecie, który nie znika, gdy host wyjdzie z gry. Poniżej cała droga: od wyboru wielkości serwera, przez pierwsze uruchomienie, po pierwsze wejście do gry jako administrator.</p>

<h2>1. Ile RAM-u potrzebuje serwer Zomboid?</h2>
<p>Zomboid trzyma w pamięci każdy wczytany kawałek mapy, a każdy gracz w innym miejscu wczytuje własny. Dlatego pamięć rośnie z liczbą graczy, a jeszcze mocniej z modami.</p>
<ul>
    <li><strong>do 5 graczy, bez modów lub z kilkoma:</strong> 6 GB RAM,</li>
    <li><strong>10-20 graczy:</strong> 7-9 GB RAM,</li>
    <li><strong>duży serwer z dziesiątkami modów (pojazdy, mapy):</strong> zacznij od 10 GB i patrz na zużycie w panelu.</li>
</ul>
<p>Serwer, któremu brakuje pamięci, nie zwalnia, tylko się wyłącza. W konsoli zobaczysz wtedy <code>Out of memory: true</code> i kod wyjścia 137. To znak, że trzeba większego pakietu albo mniej modów.</p>

<h2>2. Zamówienie i pierwsze uruchomienie</h2>
<ol>
    <li>Wybierz liczbę graczy na stronie <a href="/hosting-project-zomboid">hostingu Project Zomboid</a>, okres i sposób płatności.</li>
    <li>Po płatności serwer zakłada się sam. Mail z potwierdzeniem zawiera <strong>login i hasło administratora w grze</strong> - zapisz je, nie pokażemy ich drugi raz.</li>
    <li>Wejdź do panelu (przycisk „Otwórz w panelu" na stronie <a href="/twoje-serwery">Twoje serwery</a>) i kliknij <strong>Start</strong>.</li>
    <li>Pierwszy start pobiera grę ze Steama - to około <strong>7 GB</strong>, więc trwa kilka minut. Postęp widać w konsoli.</li>
    <li>Serwer jest gotowy, gdy w konsoli pojawi się <code>*** SERVER STARTED ****</code>. Pierwsze uruchomienie generuje świat, dlatego chwilę trwa.</li>
</ol>

<h2>3. Jak połączyć się z serwerem</h2>
<ol>
    <li>W panelu (albo na stronie „Twoje serwery") skopiuj <strong>adres serwera</strong>: IP i port, np. <code>123.45.67.89:60001</code>.</li>
    <li>W grze wybierz <strong>Dołącz</strong> (Join), a w polach wpisz osobno <strong>IP</strong> i <strong>port</strong>.</li>
    <li>Jako nazwę użytkownika wpisz <strong>login administratora z maila</strong> i jego hasło - tak wejdziesz z uprawnieniami admina.</li>
    <li>Znajomi wchodzą tak samo, tylko z własnym nickiem. Konto w grze zakłada się przy pierwszym wejściu.</li>
</ol>

<h2>4. Pierwsze komendy administratora</h2>
<p>Komendy wpisujesz w <strong>konsoli panelu</strong> (bez ukośnika) albo na czacie w grze (z ukośnikiem, np. <code>/save</code>):</p>
<pre><code>setaccesslevel "NickGracza" admin      nadaje uprawnienia admina
servermsg "Restart za 5 minut"         wiadomość do wszystkich graczy
save                                   zapisuje świat
players                                lista graczy online
quit                                   zapisuje i wyłącza serwer</code></pre>
<p>Przed restartem lub zmianą ustawień zawsze wpisz <code>save</code> - Zomboid zapisuje świat okresowo, a nie przy każdym ruchu gracza.</p>

<h2>5. Najczęstsze problemy</h2>
<ul>
    <li><strong>Serwer w kółko się restartuje, w konsoli „Out of memory"</strong> - za mało RAM-u na tyle modów lub graczy. Usuń część modów albo zmień pakiet na większy.</li>
    <li><strong>„Server version mismatch" przy łączeniu</strong> - gra u gracza i na serwerze to różne wersje. Zaktualizuj grę w Steamie; serwer sam sprawdza aktualizacje przy każdym starcie.</li>
    <li><strong>Nie widać serwera na liście</strong> - serwery prywatne nie pokazują się w publicznej przeglądarce. Łącz się przez IP i port (punkt 3).</li>
</ul>

<p>Dalej: <a href="/poradniki/ustawienia-serwera-project-zomboid">ustaw hasło, PvP i limit graczy</a> albo <a href="/poradniki/mody-na-serwer-project-zomboid">dodaj mody ze Steam Workshop</a>.</p>
<?php
$poradnik = [
    'slug'  => 'jak-postawic-serwer-project-zomboid',
    'tytul' => 'Jak postawić serwer Project Zomboid (Build 42)',
    'title' => 'Jak postawić serwer Project Zomboid (Build 42) - poradnik | DNCT Hosting',
    'opis'  => 'Krok po kroku: ile RAM-u potrzebuje serwer Project Zomboid, pierwsze uruchomienie, łączenie przez IP i port, komendy administratora i najczęstsze błędy.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
