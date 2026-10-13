<?php
/* Tłumaczenie poradnika /poradniki/serwer-project-zomboid-dla-znajomych (components/poradnik.php).
   Kwoty w złotych z groszami - na euro przelicza je filtr i18n. Kwot bez
   groszy ("6 zł na osobę") filtr nie łapie, więc tu ich nie ma. */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'Project Zomboid server for friends: cost and sizing',
    'title' => 'Project Zomboid server for friends: cost and sizing | DNCT',
    'opis'  => 'How much does a private Project Zomboid server for friends cost? Specific RAM tiers, the cost for 5 and 10 players, mods, backups and when to change plans.',
    'tresc' => <<<'HTML'
<p><strong>Short answer:</strong> at DNCT a private Project Zomboid server for up to five friends starts at 29,99 zł per month. You get 6 GB RAM, 3 vCore and 10 GB SSD. That's an honest minimum for a world that's online around the clock - there's no bargain plan here that promises stable Zomboid on less.</p>

<h2>How much does a server for a small group cost?</h2>
<ul>
    <li><strong>Up to 5 players:</strong> 6 GB RAM, 3 vCore, 10 GB SSD - 29,99 zł/mies.</li>
    <li><strong>Up to 10 players:</strong> 7 GB RAM, 3 vCore, 15 GB SSD - 34,99 zł/mies.</li>
    <li><strong>More players, extra maps or lots of mods:</strong> pick a higher tier in the configurator. In Zomboid memory also grows when players spread out across the map.</li>
</ul>
<p>You can split the cost across the group: with five people, each pays a fifth of the starter plan per month. Mods, extra maps, the zombie count and whether players are in different parts of the world matter more than the slot count itself.</p>

<h2>Why not just host from the game?</h2>
<p>Hosting from inside the game works while the owner's PC is on and they're online. Your own server runs independently: friends can join when you're not around, and the world has a fixed address and backups. That matters most in a long campaign where you don't want to lose the save after an error or a mod update.</p>

<h2>Password, privacy and mods</h2>
<p>For a closed group, set a server password and limit access to your friends only. You can add Steam Workshop mods later, but it's safer to settle the list before starting the world. Removing a mod from a save you've already played can be risky, so make a backup before every bigger change.</p>

<h2>When to upgrade the plan?</h2>
<p>Upgrade if the console shows memory running out, terrain takes long to generate, or the server starts restarting after you add mods. Don't wait until the world becomes unstable: make a backup first, then pick a higher tier for your number of players and mods.</p>

<p>You'll find the exact options on the <a href="/hosting-project-zomboid">Project Zomboid hosting</a> page. See also the <a href="/poradniki/jak-postawic-serwer-project-zomboid">first server start</a> and the <a href="/poradniki/mody-na-serwer-project-zomboid">Steam Workshop mods guide</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'Project-Zomboid-Server für Freunde: Kosten und Auswahl',
    'title' => 'Project-Zomboid-Server für Freunde: Kosten und Auswahl | DNCT',
    'opis'  => 'Was kostet ein privater Project-Zomboid-Server für Freunde? Konkrete RAM-Stufen, Kosten für 5 und 10 Spieler, Mods, Backups und wann du das Paket wechseln solltest.',
    'tresc' => <<<'HTML'
<p><strong>Kurz gesagt:</strong> ein privater Project-Zomboid-Server für bis zu fünf Freunde startet bei DNCT ab 29,99 zł im Monat. Du bekommst 6 GB RAM, 3 vCore und 10 GB SSD. Das ist ein ehrliches Minimum für eine Welt, die rund um die Uhr läuft - ein Billigpaket, das mit weniger stabiles Zomboid verspricht, gibt es bei uns nicht.</p>

<h2>Was kostet ein Server für eine kleine Gruppe?</h2>
<ul>
    <li><strong>Bis 5 Spieler:</strong> 6 GB RAM, 3 vCore, 10 GB SSD - 29,99 zł/mies.</li>
    <li><strong>Bis 10 Spieler:</strong> 7 GB RAM, 3 vCore, 15 GB SSD - 34,99 zł/mies.</li>
    <li><strong>Mehr Spieler, zusätzliche Karten oder viele Mods:</strong> wähl eine höhere Stufe im Konfigurator. In Zomboid wächst der Speicherbedarf auch, wenn sich die Spieler über die Karte verteilen.</li>
</ul>
<p>Die Kosten lassen sich in der Gruppe teilen: bei fünf Leuten zahlt jeder ein Fünftel des Startpakets im Monat. Wichtiger als die Slotzahl sind Mods, zusätzliche Karten, die Zombie-Anzahl und ob die Spieler in verschiedenen Teilen der Welt unterwegs sind.</p>

<h2>Warum nicht einfach aus dem Spiel hosten?</h2>
<p>Hosten aus dem Spiel funktioniert, solange der PC des Besitzers läuft und er online ist. Ein eigener Server läuft unabhängig davon: Freunde kommen rein, auch wenn du nicht da bist, und die Welt hat eine feste Adresse und Backups. Das zählt besonders in einer langen Kampagne, in der du den Spielstand nicht nach einem Fehler oder Mod-Update verlieren willst.</p>

<h2>Passwort, Privatsphäre und Mods</h2>
<p>Für eine geschlossene Gruppe setzt du ein Serverpasswort und lässt nur deine Freunde rein. Mods aus dem Steam Workshop kannst du später hinzufügen, aber sicherer ist es, die Liste vor dem Start der Welt festzulegen. Einen Mod aus einem schon gespielten Spielstand zu entfernen, kann riskant sein - mach deshalb vor jeder größeren Änderung ein Backup.</p>

<h2>Wann solltest du das Paket vergrößern?</h2>
<p>Vergrößere das Paket, wenn die Konsole Speichermangel meldet, das Gelände lange generiert wird oder der Server nach neuen Mods neu startet. Warte nicht, bis die Welt instabil wird: erst ein Backup machen, dann eine höhere Stufe passend zu Spielerzahl und Mods wählen.</p>

<p>Die genauen Varianten findest du auf der Seite <a href="/hosting-project-zomboid">Project-Zomboid-Hosting</a>. Siehe auch den <a href="/poradniki/jak-postawic-serwer-project-zomboid">ersten Serverstart</a> und die <a href="/poradniki/mody-na-serwer-project-zomboid">Anleitung zu Steam-Workshop-Mods</a>.</p>
HTML,
],
];
