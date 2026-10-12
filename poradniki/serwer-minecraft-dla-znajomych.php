<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p><strong>Krótka odpowiedź:</strong> dla 2-5 znajomych grających w zwykły survival lub z lekkimi pluginami sensownym startem jest BASIC: 3 GB RAM, 2 vCore i 5 GB SSD za 9,99 zł miesięcznie. Jeżeli świat będzie większy albo dojdzie więcej pluginów, wybierz Starter: 4 GB RAM i 15 GB SSD za 15,99 zł miesięcznie.</p>

<h2>Najpierw ustal, jak będziecie grać</h2>
<p>W Minecraftcie liczba graczy nie jest jedynym parametrem. Ten sam serwer dla pięciu osób może działać lekko na vanilla, a mocno obciążyć procesor i pamięć, gdy każdy odkrywa nowe chunki albo używa modów.</p>
<ul>
    <li><strong>2-5 osób, vanilla albo Paper z kilkoma pluginami:</strong> BASIC — 3 GB RAM, 2 vCore, 5 GB SSD, 9,99 zł/mies.</li>
    <li><strong>5-10 osób, większy świat albo więcej pluginów:</strong> Starter — 4 GB RAM, 2 vCore, 15 GB SSD, 15,99 zł/mies.</li>
    <li><strong>Modpack Forge, Fabric lub NeoForge:</strong> zacznij od sprawdzenia wymagań paczki. Pełne modpacki zwykle potrzebują co najmniej 8 GB RAM, czyli planu Standard za 36,99 zł/mies.</li>
</ul>

<h2>Czego nie wybierać tylko dlatego, że jest najtańsze?</h2>
<p>Plan Podstawowy z 1,5 GB RAM ma sens do krótkiego testu małego, prostego świata. Nie jest dobrym domyślnym wyborem dla ekipy, która chce budować przez dłuższy czas. Za mało pamięci najczęściej widać jako przycięcia podczas generowania terenu, wolne otwieranie skrzyń i spadki TPS.</p>

<h2>Vanilla, pluginy czy mody?</h2>
<p><strong>Vanilla</strong> jest najlżejsza. <strong>Pluginy</strong> działają zwykle na Paperze lub Purpurze i obciążają serwer zależnie od ich liczby oraz funkcji. <strong>Mody</strong> wymagają Fabric, Forge albo NeoForge, a ciężar paczki często ma większe znaczenie niż sama liczba osób online.</p>
<p>Jeżeli nie masz jeszcze wybranej paczki, zacznij od vanilla lub lekkich pluginów. Gdy wybierzecie modpack, porównaj jego zalecenia z liczbą RAM-u w planie. Przed zmianą silnika lub większą aktualizacją zrób kopię zapasową świata.</p>

<h2>Co, jeśli po czasie potrzeba więcej mocy?</h2>
<p>Nie musisz od początku kupować największego pakietu. Zacznij od planu pasującego do faktycznej rozgrywki, obserwuj konsolę i zużycie zasobów, a gdy świat oraz liczba graczy urosną — zmień pakiet w panelu. Zanim zmienisz wersję gry, silnik lub plan, wykonaj backup.</p>

<p>Po wyborze planu zobacz <a href="/hosting-minecraft">hosting Minecraft</a>. Jeśli potrzebujesz pomocy z konfiguracją, przeczytaj poradnik <a href="/poradniki/mody-na-serwer-minecraft">o modach i modpackach</a> albo <a href="/poradniki/pluginy-na-serwer-minecraft">o pluginach na Paperze</a>.</p>
<?php
$poradnik = [
    'slug'  => 'serwer-minecraft-dla-znajomych',
    'tytul' => 'Serwer Minecraft dla znajomych: jaki plan wybrać?',
    'title' => 'Serwer Minecraft dla znajomych: jaki plan wybrać? | DNCT',
    'opis'  => 'Jaki serwer Minecraft wybrać dla znajomych? Porównanie planów dla vanilla, pluginów i modpacków: ile RAM-u potrzeba, ile kosztuje i kiedy zwiększyć pakiet.',
    'data'  => '2026-10-09',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
