<?php
/* Tłumaczenie poradnika /poradniki/mody-na-serwer-project-zomboid (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to add Steam Workshop mods to a Zomboid server',
    'title' => 'Project Zomboid server mods from the Steam Workshop | DNCT',
    'opis'  => 'How to add Steam Workshop mods to a Project Zomboid server (Build 42): ProjectZomboid.ini, Workshop ID, Mod ID and what to do when the server won\'t start.',
    'tresc' => <<<'HTML'
<p>Steam Workshop mods don't need any file uploads. The server downloads them on startup, and players get them automatically when they join. You just tell the server which mods to use - in the panel or in the config file.</p>

<h2>The easiest way: the Mody (PZ) tab</h2>
<ol>
    <li>In the server panel open the <strong>Mody (PZ)</strong> tab (mods).</li>
    <li>Paste the <strong>link to the mod</strong> from the Steam Workshop (e.g. <code>steamcommunity.com/sharedfiles/filedetails/?id=...</code>) or just the ID number and click add.</li>
    <li>The panel reads the name and the <strong>Mod ID</strong> from the Workshop by itself. If the item contains several mods or a map, it asks which ones to enable.</li>
    <li>Add the mods this mod requires separately (the "Required items" section on its Steam page).</li>
    <li>Restart the server. You can pause or remove the mod later in the same tab.</li>
</ol>
<p>The tab writes everything to the config file for you. Below is the manual method - handy when you want to set the mod order or fix something.</p>

<h2>1. Manually: where the config file is</h2>
<p>In the panel open the <strong>file manager</strong> and go to:</p>
<pre><code>.cache/Server/ProjectZomboid.ini</code></pre>
<p>The <code>.cache</code> folder starts with a dot, so it's at the very top of the file list. The name <code>ProjectZomboid.ini</code> comes from the server name (<code>ProjectZomboid</code> by default).</p>

<h2>2. Two lines to fill in</h2>
<p>Every mod needs two things: the <strong>Workshop ID</strong> (the item number in the Workshop) and the <strong>Mod ID</strong> (the mod's name in the game). Enter them on two separate lines, separating values with a semicolon:</p>
<pre><code>WorkshopItems=1111111111;2222222222
Mods=LibraryName;ModName</code></pre>
<p>(The numbers and names above are an example - enter the ones from your mods' descriptions.)</p>
<ul>
    <li>You'll find the <strong>Workshop ID</strong> in the address of the mod's Steam page: <code>steamcommunity.com/sharedfiles/filedetails/?id=<strong>1111111111</strong></code>.</li>
    <li>The author usually gives the <strong>Mod ID</strong> at the very bottom of the mod description, on the <code>Mod ID: ...</code> line. One Workshop item can contain several mods - then enter every Mod ID you want to use.</li>
    <li>In Build 42 some mods list a Mod ID starting with a backslash (e.g. <code>\ModName</code>). Copy it <strong>exactly as the author gives it</strong>.</li>
</ul>

<h2>3. Order matters</h2>
<p>Put libraries (mods others depend on - often with <em>lib</em>, <em>Framework</em> or <em>API</em> in the name) on the <code>Mods=</code> line <strong>before</strong> the mods that use them. The "Required items" section in the mod's Steam description shows what it needs - those items also go into <code>WorkshopItems=</code>.</p>

<h2>4. Save and restart</h2>
<ol>
    <li>Type <code>save</code> in the console, then stop the server with the <strong>Stop</strong> button.</li>
    <li>Save the file in the file manager.</li>
    <li>Start the server. The console shows the mods downloading, then <code>*** SERVER STARTED ****</code>.</li>
</ol>
<p>Edit the file while the server is <strong>stopped</strong>. When shutting down, Zomboid can overwrite the config file, and changes made while it was running disappear.</p>

<h2>5. When the server won't start after adding mods</h2>
<ul>
    <li><strong>"Out of memory" in the console</strong> - mods (especially vehicles and maps) eat RAM. Reduce the number of mods or move to a bigger plan.</li>
    <li><strong>An error about a missing mod</strong> - a dependency is missing. Check "Required items" on Steam and add the missing Workshop ID and Mod ID.</li>
    <li><strong>The mod only works on Build 41</strong> - check the description to see if it supports Build 42. Old mods can stop the server from starting.</li>
    <li>Add mods <strong>a few at a time</strong>. If something breaks, you know right away which mod is to blame.</li>
</ul>
<p>Make a backup in the panel before a big mod change - adding mods to an existing world usually works, but removing a mod with items can corrupt the save.</p>
HTML,
],
'de' => [
    'tytul' => 'So fügst du Mods aus dem Steam Workshop zum Zomboid-Server hinzu',
    'title' => 'Mods für Project-Zomboid-Server aus dem Steam Workshop | DNCT',
    'opis'  => 'So fügst du Mods aus dem Steam Workshop zum Project-Zomboid-Server hinzu (Build 42): ProjectZomboid.ini, Workshop ID, Mod ID und was tun, wenn der Server nicht startet.',
    'tresc' => <<<'HTML'
<p>Mods aus dem Steam Workshop musst du nicht hochladen. Der Server lädt sie beim Start selbst, und Spieler bekommen sie automatisch beim Verbinden. Du sagst dem Server nur, welche Mods er nutzen soll - im Panel oder in der Konfigurationsdatei.</p>

<h2>Am einfachsten: der Tab Mody (PZ)</h2>
<ol>
    <li>Öffne im Server-Panel den Tab <strong>Mody (PZ)</strong> (Mods).</li>
    <li>Füg den <strong>Link zum Mod</strong> aus dem Steam Workshop ein (z. B. <code>steamcommunity.com/sharedfiles/filedetails/?id=...</code>) oder nur die ID-Nummer und klick auf Hinzufügen.</li>
    <li>Das Panel liest Namen und <strong>Mod ID</strong> selbst aus dem Workshop. Enthält das Item mehrere Mods oder eine Karte, fragt es, welche aktiviert werden sollen.</li>
    <li>Füg die Mods, die dieser Mod braucht, separat hinzu (Abschnitt „Required items“ auf seiner Steam-Seite).</li>
    <li>Starte den Server neu. Den Mod kannst du später im selben Tab pausieren oder entfernen.</li>
</ol>
<p>Der Tab schreibt alles für dich in die Konfigurationsdatei. Unten steht die manuelle Methode - praktisch, wenn du die Mod-Reihenfolge festlegen oder etwas korrigieren willst.</p>

<h2>1. Manuell: wo die Konfigurationsdatei liegt</h2>
<p>Öffne im Panel den <strong>Dateimanager</strong> und geh zu:</p>
<pre><code>.cache/Server/ProjectZomboid.ini</code></pre>
<p>Der Ordner <code>.cache</code> beginnt mit einem Punkt und steht deshalb ganz oben in der Dateiliste. Der Name <code>ProjectZomboid.ini</code> kommt vom Servernamen (standardmäßig <code>ProjectZomboid</code>).</p>

<h2>2. Zwei Zeilen zum Ausfüllen</h2>
<p>Jeder Mod braucht zwei Dinge: die <strong>Workshop ID</strong> (Item-Nummer im Workshop) und die <strong>Mod ID</strong> (Name des Mods im Spiel). Du trägst sie in zwei getrennte Zeilen ein, mehrere Werte trennst du mit Semikolon:</p>
<pre><code>WorkshopItems=1111111111;2222222222
Mods=BibliotheksName;ModName</code></pre>
<p>(Die Nummern und Namen oben sind ein Beispiel - trag die aus der Beschreibung deiner Mods ein.)</p>
<ul>
    <li>Die <strong>Workshop ID</strong> steht in der Adresse der Steam-Seite des Mods: <code>steamcommunity.com/sharedfiles/filedetails/?id=<strong>1111111111</strong></code>.</li>
    <li>Die <strong>Mod ID</strong> nennt der Autor meist ganz unten in der Beschreibung, in der Zeile <code>Mod ID: ...</code>. Ein Workshop-Item kann mehrere Mods enthalten - dann trag alle Mod IDs ein, die du nutzen willst.</li>
    <li>In Build 42 haben manche Mods eine Mod ID mit Backslash am Anfang (z. B. <code>\ModName</code>). Übernimm sie <strong>genau so, wie der Autor sie angibt</strong>.</li>
</ul>

<h2>3. Die Reihenfolge zählt</h2>
<p>Bibliotheken (Mods, von denen andere abhängen - im Namen oft <em>lib</em>, <em>Framework</em>, <em>API</em>) gehören in der Zeile <code>Mods=</code> <strong>vor</strong> die Mods, die sie nutzen. Im Abschnitt „Required items“ der Steam-Beschreibung siehst du, was ein Mod braucht - diese Items müssen auch in <code>WorkshopItems=</code>.</p>

<h2>4. Speichern und neu starten</h2>
<ol>
    <li>Gib in der Konsole <code>save</code> ein und stopp den Server dann mit dem Button <strong>Stop</strong>.</li>
    <li>Speicher die Datei im Dateimanager.</li>
    <li>Starte den Server. In der Konsole siehst du den Download der Mods und dann <code>*** SERVER STARTED ****</code>.</li>
</ol>
<p>Bearbeite die Datei bei <strong>gestopptem</strong> Server. Zomboid kann die Konfigurationsdatei beim Herunterfahren überschreiben, und Änderungen aus der Laufzeit sind dann weg.</p>

<h2>5. Wenn der Server nach dem Hinzufügen von Mods nicht startet</h2>
<ul>
    <li><strong>„Out of memory“ in der Konsole</strong> - Mods (vor allem Fahrzeuge und Karten) fressen RAM. Weniger Mods oder ein größeres Paket.</li>
    <li><strong>Fehler wegen eines fehlenden Mods</strong> - eine Abhängigkeit fehlt. Prüf „Required items“ in Steam und ergänze die fehlende Workshop ID und Mod ID.</li>
    <li><strong>Der Mod läuft nur auf Build 41</strong> - prüf in der Beschreibung, ob er Build 42 unterstützt. Alte Mods können den Serverstart verhindern.</li>
    <li>Füg Mods <strong>immer nur ein paar auf einmal</strong> hinzu. Geht etwas kaputt, weißt du sofort, welcher Mod schuld ist.</li>
</ul>
<p>Mach vor größeren Mod-Änderungen ein Backup im Panel - Mods zu einer bestehenden Welt hinzufügen klappt meist, aber das Entfernen eines Mods mit Gegenständen kann den Spielstand beschädigen.</p>
HTML,
],
];
