<?php
/* Tłumaczenie poradnika /poradniki/mody-na-serwer-vintage-story (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to add mods to a Vintage Story server',
    'title' => 'Vintage Story server mods - how to add them | DNCT',
    'opis'  => 'How to add ModDB mods to a Vintage Story server: the data/Mods folder, .zip files without extracting, mods on the players\' side, removing mods and common errors.',
    'tresc' => <<<'HTML'
<p>Mods are one of Vintage Story's biggest strengths: from new animals and plants, through better maps, to whole new crafting systems. You put them on the server simply as files - no extra installers.</p>

<h2>1. Where to get mods</h2>
<p>The official source is <strong>ModDB</strong>: <code>mods.vintagestory.at</code>. Every mod lists the game versions it works with - download the release that matches your server's version. You download a mod as a <code>.zip</code> file (sometimes <code>.cs</code> or <code>.dll</code>).</p>

<h2>2. Uploading mods</h2>
<ol>
    <li>Stop the server with the <strong>Stop</strong> button.</li>
    <li>In the panel open <strong>Pliki</strong> (files) and go to the <code>data/Mods</code> folder.</li>
    <li>Click <strong>Upload</strong> and upload the mod files. <strong>Don't extract</strong> the <code>.zip</code> files - the game reads them as they are.</li>
    <li>Start the server. The console shows the list of loaded mods, ending with <code>Dedicated Server now running</code>.</li>
</ol>

<h2>3. Mods on the players' side</h2>
<p>Most mods also need to be installed by every player. When connecting to the server, the game shows which mods are missing and usually offers to download them. The simplest way is to give your friends a list of mods with ModDB links so they have the same versions.</p>
<p>Some mods only run on the server (e.g. changes to how the world behaves) - the ModDB description usually says "server-side only".</p>

<h2>4. Disabling and removing mods</h2>
<ul>
    <li>To disable a mod, delete its file from <code>data/Mods</code> (or move it to another folder) and restart the server.</li>
    <li>Removing a mod that added blocks or items makes them disappear from the world. Make a backup in the <strong>Backupy</strong> tab (backups) first.</li>
</ul>

<h2>5. When the server won't start after adding mods</h2>
<ul>
    <li><strong>An error about a missing dependency</strong> - the mod needs another mod (often a library). The console names it - add it to <code>data/Mods</code>.</li>
    <li><strong>A mod for another game version</strong> - download the release that matches the server version, or remove the mod.</li>
    <li><strong>Running out of memory with many mods</strong> - move to a plan with more slots (more RAM) or cut down the number of mods.</li>
    <li>Add mods <strong>a few at a time</strong> - if something breaks, you know right away which one is to blame.</li>
</ul>
<p>Setting up a server for the first time? Start with the guide <a href="/poradniki/jak-postawic-serwer-vintage-story">how to set up a Vintage Story server</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'So fügst du Mods zum Vintage-Story-Server hinzu',
    'title' => 'Mods für Vintage-Story-Server - so geht’s | DNCT',
    'opis'  => 'So fügst du Mods aus der ModDB zum Vintage-Story-Server hinzu: der Ordner data/Mods, .zip-Dateien ohne Entpacken, Mods bei den Spielern, Entfernen und typische Fehler.',
    'tresc' => <<<'HTML'
<p>Mods gehören zu den größten Stärken von Vintage Story: von neuen Tieren und Pflanzen über bessere Karten bis zu ganz neuen Handwerkssystemen. Auf den Server kommen sie einfach als Dateien - ohne zusätzliche Installer.</p>

<h2>1. Woher du Mods bekommst</h2>
<p>Die offizielle Quelle ist die <strong>ModDB</strong>: <code>mods.vintagestory.at</code>. Bei jedem Mod steht, mit welchen Spielversionen er läuft - lade die Version, die zu deinem Server passt. Ein Mod kommt als <code>.zip</code>-Datei (manchmal <code>.cs</code> oder <code>.dll</code>).</p>

<h2>2. Mods hochladen</h2>
<ol>
    <li>Stopp den Server mit dem Button <strong>Stop</strong>.</li>
    <li>Öffne im Panel <strong>Pliki</strong> (Dateien) und geh in den Ordner <code>data/Mods</code>.</li>
    <li>Klick auf <strong>Upload</strong> und lade die Mod-Dateien hoch. <strong>Entpacke</strong> die <code>.zip</code>-Dateien <strong>nicht</strong> - das Spiel liest sie so, wie sie sind.</li>
    <li>Starte den Server. In der Konsole siehst du die Liste der geladenen Mods und am Ende <code>Dedicated Server now running</code>.</li>
</ol>

<h2>3. Mods bei den Spielern</h2>
<p>Die meisten Mods braucht auch jeder Spieler. Beim Verbinden zeigt das Spiel, welche Mods fehlen, und bietet meist den Download an. Am einfachsten gibst du deinen Freunden eine Mod-Liste mit Links zur ModDB, damit alle dieselben Versionen haben.</p>
<p>Manche Mods laufen nur auf dem Server (z. B. Änderungen am Verhalten der Welt) - in der ModDB-Beschreibung steht dann meist „server-side only“.</p>

<h2>4. Mods deaktivieren und entfernen</h2>
<ul>
    <li>Um einen Mod zu deaktivieren, lösch seine Datei aus <code>data/Mods</code> (oder verschieb sie in einen anderen Ordner) und starte den Server neu.</li>
    <li>Wenn du einen Mod entfernst, der Blöcke oder Gegenstände hinzugefügt hat, verschwinden sie aus der Welt. Mach vorher ein Backup im Tab <strong>Backupy</strong> (Backups).</li>
</ul>

<h2>5. Wenn der Server nach dem Hinzufügen von Mods nicht startet</h2>
<ul>
    <li><strong>Fehler wegen fehlender Abhängigkeit</strong> - der Mod braucht einen anderen Mod (oft eine Bibliothek). Die Konsole nennt seinen Namen - lade ihn in <code>data/Mods</code> nach.</li>
    <li><strong>Mod für eine andere Spielversion</strong> - lade die zur Serverversion passende Version oder entferne den Mod.</li>
    <li><strong>Speichermangel bei vielen Mods</strong> - wechsle auf ein Paket mit mehr Slots (mehr RAM) oder reduziere die Mods.</li>
    <li>Füg Mods <strong>immer nur ein paar auf einmal</strong> hinzu - geht etwas kaputt, weißt du sofort, welcher schuld ist.</li>
</ul>
<p>Du richtest zum ersten Mal einen Server ein? Fang mit der Anleitung <a href="/poradniki/jak-postawic-serwer-vintage-story">Vintage-Story-Server einrichten</a> an.</p>
HTML,
],
];
