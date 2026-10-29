<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

ob_start(); ?>
<p><strong>Krótka odpowiedź:</strong> do Minecrafta dla 2-5 znajomych wystarczą 3-4 GB RAM, do modpacka zacznij od 8 GB, a serwer Project Zomboid dla 5 osób potrzebuje około 6 GB. Vintage Story jest lżejsze: 4 GB wystarczy dla pięciu osób. Poniżej tabela i reguły, które pozwalają dobrać pamięć bez przepłacania.</p>

<h2>Ile RAM-u na jaki serwer - tabela</h2>
<div style="overflow-x:auto">
<table>
    <thead>
        <tr><th scope="col">Gra i sposób grania</th><th scope="col">Liczba graczy</th><th scope="col">Zalecany RAM</th></tr>
    </thead>
    <tbody>
        <tr><th scope="row">Minecraft, czysty (vanilla)</th><td>2-5</td><td>3-4 GB</td></tr>
        <tr><th scope="row">Minecraft, Paper z pluginami</th><td>10-20</td><td>6-8 GB</td></tr>
        <tr><th scope="row">Minecraft, modpack (Forge, Fabric, NeoForge)</th><td>kilka osób</td><td>zwykle 8-10 GB, duże paczki więcej</td></tr>
        <tr><th scope="row">Minecraft, serwer publiczny</th><td>kilkadziesiąt</td><td>14 GB i więcej</td></tr>
        <tr><th scope="row">Project Zomboid (Build 42)</th><td>5</td><td>6 GB</td></tr>
        <tr><th scope="row">Project Zomboid (Build 42)</th><td>10</td><td>7 GB</td></tr>
        <tr><th scope="row">Vintage Story</th><td>5</td><td>4 GB</td></tr>
        <tr><th scope="row">Vintage Story</th><td>10</td><td>4,5 GB</td></tr>
    </tbody>
</table>
</div>

<h2>Dlaczego liczba graczy to nie wszystko?</h2>
<p>RAM zależy od tego, co serwer musi trzymać w pamięci: wygenerowane fragmenty świata, pluginy albo mody i zawartość gry. Pięć osób na czystym Minecrafcie to zupełnie inne obciążenie niż pięć osób na ciężkim modpacku. Dlatego zaczynamy od sposobu grania, a dopiero potem liczymy graczy.</p>

<h2>Czy więcej RAM-u znaczy mniej lagów?</h2>
<p>Nie zawsze. Minecraft mocno korzysta z jednego rdzenia procesora, więc gdy serwer „laguje" przy niskim zużyciu pamięci, winne są zwykle zbyt duża odległość renderowania albo ciężkie farmy. Za mało pamięci widać inaczej: przycięcia przy generowaniu terenu, wolne otwieranie skrzyń i komunikat „Out of memory" w konsoli.</p>

<h2>Jak sprawdzić, czy RAM wystarcza?</h2>
<ol>
    <li>Wejdź do panelu i otwórz konsolę serwera.</li>
    <li>Zagrajcie normalnie przez wieczór, z wszystkimi graczami naraz.</li>
    <li>Jeśli zużycie pamięci stale dobija do limitu albo w konsoli pojawia się „Out of memory", weź większy plan.</li>
</ol>
<p>Plan możesz zmienić w trakcie, z dopłatą proporcjonalną, więc nie musisz zgadywać z góry. Przed zmianą zrób kopię zapasową w zakładce <strong>Backupy</strong>.</p>

<p>Gotowe pakiety i aktualne ceny: <a href="/cennik">pełny cennik</a>. Dobór pod konkretną grę: <a href="/poradniki/serwer-minecraft-dla-znajomych">Minecraft dla znajomych</a>, <a href="/poradniki/serwer-project-zomboid-dla-znajomych">Project Zomboid dla znajomych</a>, <a href="/poradniki/serwer-vintage-story-dla-znajomych">Vintage Story dla znajomych</a>.</p>
<?php
$poradnik = [
    'slug'  => 'ile-ram-serwer-gier',
    'tytul' => 'Ile RAM-u potrzebuje serwer gier: Minecraft, Zomboid, Vintage Story',
    'title' => 'Ile RAM-u na serwer Minecraft, Zomboid i Vintage Story - tabela | DNCT',
    'opis'  => 'Ile RAM-u wybrać na serwer Minecraft (vanilla, pluginy, modpack), Project Zomboid i Vintage Story: tabela doboru, objawy braku pamięci i sprawdzenie w konsoli.',
    'data'  => '2026-10-29',
    'tresc' => (string)ob_get_clean(),
];
require dirname(__DIR__) . '/components/poradnik.php';
