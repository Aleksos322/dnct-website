<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p><strong>Krótka odpowiedź:</strong> dla małej osady do pięciu osób wybierz pakiet Vintage Story z 4 GB RAM, 3 vCore i 5 GB SSD za 19,99 zł miesięcznie. Dla dziesięciu osób jest dostępny próg 4,5 GB RAM, 3 vCore i 10 GB SSD za 22,99 zł miesięcznie.</p>

<h2>Dobór pakietu do ekipy</h2>
<ul>
    <li><strong>Do 5 znajomych, podstawowa rozgrywka:</strong> 4 GB RAM, 3 vCore, 5 GB SSD — 19,99 zł/mies.</li>
    <li><strong>Do 10 znajomych:</strong> 4,5 GB RAM, 3 vCore, 10 GB SSD — 22,99 zł/mies.</li>
    <li><strong>Większa osada, rozbudowany świat albo więcej modów:</strong> wybierz wyższy próg. Dla 20 osób to 6 GB RAM, 3 vCore i 15 GB SSD za 30,99 zł/mies.</li>
</ul>

<h2>Co obciąża serwer Vintage Story?</h2>
<p>Vintage Story nie potrzebuje ogromnej liczby slotów, ale obciążenie rośnie przy generowaniu nowego terenu, większej liczbie aktywnych obszarów oraz modach. Dlatego patrz jednocześnie na RAM, procesor i wolne miejsce na świecie — nie wybieraj pakietu tylko po liczbie znajomych na Discordzie.</p>

<h2>Mody i długi świat</h2>
<p>Mody dodają funkcje, ale mogą też zwiększyć zużycie pamięci oraz rozmiar zapisu. Jeśli zaczynacie nowy świat z większą paczką modów, wybierz wyższy próg od razu albo zostaw sobie zapas na późniejszą zmianę. Przed aktualizacją gry, moda albo konfiguracji wykonaj backup.</p>

<h2>Jak zachować prywatność serwera?</h2>
<p>Ustaw hasło świata i udostępnij adres tylko swojej ekipie. Własny serwer pozwala grać, gdy założyciel nie ma uruchomionego komputera, a konfigurację świata, mody i pliki kontrolujesz z panelu oraz przez SFTP.</p>

<p>Wybierz dokładny próg na stronie <a href="/hosting-vintage-story">hostingu Vintage Story</a>. Przed startem przeczytaj też <a href="/poradniki/jak-postawic-serwer-vintage-story">poradnik pierwszego uruchomienia</a> oraz <a href="/poradniki/mody-na-serwer-vintage-story">instrukcję instalacji modów</a>.</p>
<?php
$poradnik = [
    'slug'  => 'serwer-vintage-story-dla-znajomych',
    'tytul' => 'Serwer Vintage Story dla znajomych: jaki plan wybrać?',
    'title' => 'Serwer Vintage Story dla znajomych: jaki plan wybrać? | DNCT',
    'opis'  => 'Jaki serwer Vintage Story wybrać dla znajomych w Polsce? Konkretne pakiety dla 5, 10 i 20 osób, mody, backupy oraz dobór RAM-u i CPU.',
    'data'  => '2026-10-09',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
