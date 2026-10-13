<?php
/* Tłumaczenie poradnika /poradniki/jak-wgrac-swiat-na-serwer-minecraft (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to upload your own world to a Minecraft server',
    'title' => 'How to upload a world to a Minecraft server - step by step | DNCT',
    'opis'  => 'How to move a singleplayer world to a Minecraft server: where saves are, packing, uploading in the panel, level-name, Nether and End on Paper.',
    'tresc' => <<<'HTML'
<p>You can move a singleplayer world (or one from another server) to your server in a few minutes. Just pack the world folder, upload it in the panel and set which world should start.</p>

<h2>1. Find and pack the world</h2>
<ol>
    <li>On your PC open the saves folder: press <strong>Win + R</strong>, type <code>%appdata%\.minecraft\saves</code> and confirm. On macOS it's <code>~/Library/Application Support/minecraft/saves</code>.</li>
    <li>Each folder is one world. Right-click yours → <strong>Send to → Compressed (zipped) folder</strong>.</li>
    <li>Check the <strong>game version</strong> you played this world in. The server needs the same or a newer version - a world from a newer version can't be opened on an older one.</li>
</ol>

<h2>2. Make a backup and stop the server</h2>
<ol>
    <li>In the panel, in the <strong>Backupy</strong> tab (backups), make a backup - in case you need the current world after all.</li>
    <li>Stop the server with the <strong>Stop</strong> button. Swapping the world on a running server ends with a corrupted save.</li>
</ol>

<h2>3. Upload the world</h2>
<ol>
    <li>In the <strong>Pliki</strong> tab (files) delete (or rename) the current <code>world</code>, <code>world_nether</code> and <code>world_the_end</code> folders.</li>
    <li>Click <strong>Upload</strong> and upload the <code>.zip</code> file. Through the browser you can upload files up to <strong>100 MB</strong>.</li>
    <li>Next to the uploaded file choose <strong>Unarchive</strong>.</li>
    <li>Rename the extracted folder to <code>world</code> - or keep your own name and enter it in <code>server.properties</code> on the <code>level-name=</code> line (also in the <strong>Configi</strong> tab).</li>
    <li>Make sure the <code>level.dat</code> file sits directly inside the folder, not another world folder. If there's a double folder, move the contents one level up.</li>
</ol>

<h2>4. Start the server</h2>
<p>Click <strong>Start</strong>. The Paper and Purpur engines keep the Nether and the End in separate folders (<code>world_nether</code>, <code>world_the_end</code>). On the first start they move them out of the singleplayer world on their own - you'll see a migration message in the console. Once <code>Done (...)!</code> appears, you can join.</p>

<h2>World bigger than 100 MB</h2>
<p>Upload big worlds over <strong>SFTP</strong>, e.g. with FileZilla or WinSCP. You'll find the address and username in the panel under <strong>Ustawienia → Połączenie SFTP</strong> (settings, SFTP connection). If you're not sure which password to use, message us on Discord - we'll help.</p>

<h2>Most common problems</h2>
<ul>
    <li><strong>The server created a new, empty world</strong> - the folder name doesn't match <code>level-name</code>, or there's no <code>level.dat</code> in the folder (a double folder after extracting).</li>
    <li><strong>The Nether or the End is missing</strong> - on Paper check the <code>world_nether</code> and <code>world_the_end</code> folders. If you're uploading a world from another Paper server, upload all three folders.</li>
    <li><strong>A modded world</strong> - the server needs the same engine and the same mods (<a href="/poradniki/mody-na-serwer-minecraft">mods and modpacks on a server</a>), otherwise blocks from mods will disappear.</li>
</ul>
<p>Setting up a server for the first time? Start with the guide <a href="/poradniki/jak-postawic-serwer-minecraft">how to set up a Minecraft server</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'So lädst du deine eigene Welt auf einen Minecraft-Server',
    'title' => 'Welt auf einen Minecraft-Server hochladen - Schritt für Schritt | DNCT',
    'opis'  => 'So bringst du eine Einzelspieler-Welt auf einen Minecraft-Server: wo die Spielstände liegen, Packen, Hochladen im Panel, level-name, Nether und End auf Paper.',
    'tresc' => <<<'HTML'
<p>Eine Welt aus dem Einzelspielermodus (oder von einem anderen Server) bringst du in ein paar Minuten auf deinen Server. Welt-Ordner packen, im Panel hochladen und festlegen, welche Welt starten soll.</p>

<h2>1. Welt finden und packen</h2>
<ol>
    <li>Öffne am PC den Spielstand-Ordner: drück <strong>Win + R</strong>, gib <code>%appdata%\.minecraft\saves</code> ein und bestätige. Unter macOS ist es <code>~/Library/Application Support/minecraft/saves</code>.</li>
    <li>Jeder Ordner ist eine Welt. Rechtsklick auf deine → <strong>Senden an → ZIP-komprimierter Ordner</strong>.</li>
    <li>Prüf, in welcher <strong>Spielversion</strong> du die Welt gespielt hast. Der Server braucht dieselbe oder eine neuere Version - eine Welt aus einer neueren Version lässt sich auf einer älteren nicht öffnen.</li>
</ol>

<h2>2. Backup machen und Server stoppen</h2>
<ol>
    <li>Mach im Panel im Tab <strong>Backupy</strong> (Backups) eine Sicherung - falls du die aktuelle Welt doch noch brauchst.</li>
    <li>Stopp den Server mit dem Button <strong>Stop</strong>. Die Welt auf einem laufenden Server zu tauschen, endet mit einem beschädigten Spielstand.</li>
</ol>

<h2>3. Welt hochladen</h2>
<ol>
    <li>Lösche (oder benenne um) im Tab <strong>Pliki</strong> (Dateien) die aktuellen Ordner <code>world</code>, <code>world_nether</code> und <code>world_the_end</code>.</li>
    <li>Klick auf <strong>Upload</strong> und lade die <code>.zip</code>-Datei hoch. Über den Browser gehen Dateien bis <strong>100 MB</strong>.</li>
    <li>Wähl bei der hochgeladenen Datei <strong>Unarchive</strong> (Entpacken).</li>
    <li>Benenne den entpackten Ordner in <code>world</code> um - oder behalte deinen Namen und trag ihn in <code>server.properties</code> in der Zeile <code>level-name=</code> ein (auch im Tab <strong>Configi</strong>).</li>
    <li>Achte darauf, dass die Datei <code>level.dat</code> direkt im Ordner liegt und nicht in einem weiteren Welt-Ordner. Bei einem doppelten Ordner verschieb den Inhalt eine Ebene höher.</li>
</ol>

<h2>4. Server starten</h2>
<p>Klick auf <strong>Start</strong>. Die Engines Paper und Purpur halten Nether und End in eigenen Ordnern (<code>world_nether</code>, <code>world_the_end</code>). Beim ersten Start holen sie sie selbst aus der Einzelspieler-Welt - in der Konsole siehst du eine Migrationsmeldung. Sobald <code>Done (...)!</code> erscheint, kannst du rein.</p>

<h2>Welt größer als 100 MB</h2>
<p>Große Welten lädst du per <strong>SFTP</strong> hoch, z. B. mit FileZilla oder WinSCP. Adresse und Benutzername findest du im Panel unter <strong>Ustawienia → Połączenie SFTP</strong> (Einstellungen, SFTP-Verbindung). Wenn du nicht weißt, welches Passwort du nehmen sollst, schreib uns auf Discord - wir helfen.</p>

<h2>Häufigste Probleme</h2>
<ul>
    <li><strong>Der Server hat eine neue, leere Welt erstellt</strong> - der Ordnername passt nicht zu <code>level-name</code>, oder im Ordner liegt keine <code>level.dat</code> (doppelter Ordner nach dem Entpacken).</li>
    <li><strong>Nether oder End fehlen</strong> - prüf auf Paper die Ordner <code>world_nether</code> und <code>world_the_end</code>. Kommt die Welt von einem anderen Paper-Server, lade alle drei Ordner hoch.</li>
    <li><strong>Welt mit Mods</strong> - der Server braucht dieselbe Engine und dieselben Mods (<a href="/poradniki/mody-na-serwer-minecraft">Mods und Modpacks auf dem Server</a>), sonst verschwinden die Mod-Blöcke.</li>
</ul>
<p>Du richtest zum ersten Mal einen Server ein? Fang mit der Anleitung <a href="/poradniki/jak-postawic-serwer-minecraft">Minecraft-Server einrichten</a> an.</p>
HTML,
],
];
