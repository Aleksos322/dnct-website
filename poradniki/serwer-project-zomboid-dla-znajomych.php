<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p><strong>Krótka odpowiedź:</strong> prywatny serwer Project Zomboid dla maksymalnie pięciu znajomych zaczyna się w DNCT od 29,99 zł miesięcznie. Dostajesz 6 GB RAM, 3 vCore i 10 GB SSD. To uczciwe minimum dla świata dostępnego całą dobę — nie ma tu pakietu „do 20 zł”, który obiecywałby stabilną grę w Zomboidzie.</p>

<h2>Ile kosztuje serwer dla małej ekipy?</h2>
<ul>
    <li><strong>Do 5 osób:</strong> 6 GB RAM, 3 vCore, 10 GB SSD — 29,99 zł/mies.</li>
    <li><strong>Do 10 osób:</strong> 7 GB RAM, 3 vCore, 15 GB SSD — 34,99 zł/mies.</li>
    <li><strong>Więcej osób, mapy dodatkowe albo dużo modów:</strong> wybierz wyższy próg w konfiguratorze. W Zomboidzie pamięć rośnie także wtedy, gdy gracze rozchodzą się po mapie.</li>
</ul>
<p>Koszt można podzielić między ekipę: przy pięciu osobach pakiet startowy to niecałe 6 zł na osobę miesięcznie. Ważniejsze od samej liczby slotów są mody, dodatkowe mapy, liczba zombie i to, czy gracze przebywają w różnych częściach świata.</p>

<h2>Dlaczego nie hostować po prostu z gry?</h2>
<p>Hostowanie z poziomu gry działa, gdy właściciel ma włączony komputer i jest online. Własny serwer działa niezależnie od niego: znajomi mogą wejść, gdy Ciebie nie ma, a świat ma stały adres oraz kopie zapasowe. To szczególnie istotne w długiej kampanii, w której nie chcesz stracić zapisu po błędzie lub aktualizacji moda.</p>

<h2>Hasło, prywatność i mody</h2>
<p>Dla zamkniętej ekipy ustaw hasło serwera i ogranicz dostęp wyłącznie do znajomych. Mody Steam Workshop można dodać później, ale bezpieczniej ustalić ich listę przed rozpoczęciem świata. Usunięcie moda z już rozegranego zapisu bywa ryzykowne, dlatego przed każdą większą zmianą wykonaj backup.</p>

<h2>Kiedy zwiększyć pakiet?</h2>
<p>Zwiększ pakiet, jeżeli konsola pokazuje brak pamięci, serwer długo generuje teren albo po dodaniu modów zaczyna się restartować. Nie czekaj, aż świat stanie się niestabilny: najpierw wykonaj kopię zapasową, a następnie dobierz większy próg do liczby osób oraz modów.</p>

<p>Dokładne warianty znajdziesz na stronie <a href="/hosting-project-zomboid">hostingu Project Zomboid</a>. Zobacz też <a href="/poradniki/jak-postawic-serwer-project-zomboid">pierwsze uruchomienie serwera</a> i <a href="/poradniki/mody-na-serwer-project-zomboid">instrukcję modów Steam Workshop</a>.</p>
<?php
$poradnik = [
    'slug'  => 'serwer-project-zomboid-dla-znajomych',
    'tytul' => 'Serwer Project Zomboid dla znajomych: koszt i dobór',
    'title' => 'Serwer Project Zomboid dla znajomych: koszt i dobór | DNCT',
    'opis'  => 'Ile kosztuje prywatny serwer Project Zomboid dla znajomych? Konkretne progi RAM-u, koszt dla 5 i 10 osób, mody, backupy oraz moment na zmianę pakietu.',
    'data'  => '2026-10-09',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
