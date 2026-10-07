<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p>Mody ze Steam Workshop nie wymagają wgrywania plików. Serwer sam pobiera je przy starcie, a gracze dostają je automatycznie przy wejściu. Wystarczy powiedzieć serwerowi, których modów używać - w panelu albo w pliku konfiguracyjnym.</p>

<h2>Najprościej: zakładka Mody (PZ)</h2>
<ol>
    <li>W panelu serwera otwórz zakładkę <strong>Mody (PZ)</strong>.</li>
    <li>Wklej <strong>link do moda</strong> ze Steam Workshop (np. <code>steamcommunity.com/sharedfiles/filedetails/?id=...</code>) albo sam numer ID i kliknij dodaj.</li>
    <li>Panel sam odczyta z Workshopu nazwę i <strong>Mod ID</strong>. Jeśli przedmiot zawiera kilka modów albo mapę, zapyta, które włączyć.</li>
    <li>Dodaj osobno mody wymagane przez ten mod (sekcja „Required items" na jego stronie w Steamie).</li>
    <li>Zrestartuj serwer. Mod możesz później wstrzymać albo usunąć w tej samej zakładce.</li>
</ol>
<p>Zakładka wpisuje wszystko do pliku konfiguracyjnego za Ciebie. Poniżej opis ręcznej metody - przydaje się, gdy chcesz ustawić kolejność modów albo coś poprawić.</p>

<h2>1. Ręcznie: gdzie jest plik konfiguracyjny</h2>
<p>W panelu otwórz <strong>Menedżer plików</strong> i przejdź do:</p>
<pre><code>.cache/Server/ProjectZomboid.ini</code></pre>
<p>Folder <code>.cache</code> zaczyna się od kropki, więc na liście plików stoi na samej górze. Nazwa <code>ProjectZomboid.ini</code> bierze się od nazwy serwera (domyślnie <code>ProjectZomboid</code>).</p>

<h2>2. Dwie linie, które trzeba uzupełnić</h2>
<p>Każdy mod potrzebuje dwóch rzeczy: <strong>Workshop ID</strong> (numer przedmiotu w Workshopie) i <strong>Mod ID</strong> (nazwa moda w grze). Wpisujesz je w dwóch osobnych liniach, kolejne wartości oddzielasz średnikiem:</p>
<pre><code>WorkshopItems=1111111111;2222222222
Mods=NazwaBiblioteki;NazwaModa</code></pre>
<p>(Numery i nazwy powyżej to przykład - wpisz te z opisu swoich modów.)</p>
<ul>
    <li><strong>Workshop ID</strong> znajdziesz w adresie strony moda w Steamie: <code>steamcommunity.com/sharedfiles/filedetails/?id=<strong>1111111111</strong></code>.</li>
    <li><strong>Mod ID</strong> autor podaje zwykle na samym dole opisu moda, w linii <code>Mod ID: ...</code>. Jeden przedmiot Workshopu może zawierać kilka modów - wtedy wpisz wszystkie Mod ID, których chcesz używać.</li>
    <li>W Build 42 część modów ma w opisie Mod ID zaczynające się od ukośnika (np. <code>\NazwaModa</code>). Przepisz go <strong>dokładnie tak, jak podaje autor</strong>.</li>
</ul>

<h2>3. Kolejność ma znaczenie</h2>
<p>Biblioteki (mody, od których zależą inne - w nazwie często <em>lib</em>, <em>Framework</em>, <em>API</em>) wpisz w linii <code>Mods=</code> <strong>przed</strong> modami, które ich używają. W opisie moda w Steamie, w sekcji „Required items", widać, czego potrzebuje - te przedmioty też trzeba dodać do <code>WorkshopItems=</code>.</p>

<h2>4. Zapisz i zrestartuj</h2>
<ol>
    <li>W konsoli wpisz <code>save</code>, a potem zatrzymaj serwer przyciskiem <strong>Stop</strong>.</li>
    <li>Zapisz plik w menedżerze plików.</li>
    <li>Uruchom serwer. W konsoli zobaczysz pobieranie modów, a potem <code>*** SERVER STARTED ****</code>.</li>
</ol>
<p>Edytuj plik przy <strong>wyłączonym</strong> serwerze. Zomboid przy wyłączaniu potrafi nadpisać plik konfiguracyjny i zmiany zrobione w trakcie działania znikają.</p>

<h2>5. Gdy serwer nie startuje po dodaniu modów</h2>
<ul>
    <li><strong>„Out of memory" w konsoli</strong> - mody (zwłaszcza pojazdy i mapy) zjadają RAM. Zmniejsz liczbę modów albo przejdź na większy pakiet.</li>
    <li><strong>Błąd o brakującym modzie</strong> - brakuje zależności. Sprawdź „Required items" w Steamie i dopisz brakujący Workshop ID i Mod ID.</li>
    <li><strong>Mod działa tylko na Build 41</strong> - sprawdź w opisie, czy mod wspiera Build 42. Stare mody potrafią zatrzymać start serwera.</li>
    <li>Dodawaj mody <strong>po kilka naraz</strong>. Jeśli coś przestanie działać, od razu wiesz, który mod jest winny.</li>
</ul>
<p>Przed dużą zmianą modów zrób kopię zapasową w panelu - dodawanie modów do istniejącego świata zwykle działa, ale usunięcie moda z przedmiotami potrafi uszkodzić zapis.</p>
<?php
$poradnik = [
    'slug'  => 'mody-na-serwer-project-zomboid',
    'tytul' => 'Jak dodać mody ze Steam Workshop na serwer Zomboid',
    'title' => 'Mody na serwer Project Zomboid ze Steam Workshop | DNCT',
    'opis'  => 'Jak dodać mody ze Steam Workshop na serwer Project Zomboid (Build 42): ProjectZomboid.ini, Workshop ID, Mod ID i co zrobić, gdy serwer nie startuje.',
    'data'  => '2026-10-07',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
