<?php
/* Tłumaczenie poradnika /poradniki/zapis-zomboid-na-serwer (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to move a Project Zomboid save to a server',
    'title' => 'Moving a Project Zomboid save to a server | DNCT',
    'opis'  => 'How to move a singleplayer Project Zomboid world to a server: where saves are, what carries over, mods, swapping the save in the panel and common errors.',
    'tresc' => <<<'HTML'
<p>Played alone or with friends on an in-game host and want to continue that world on a server that runs around the clock? You can move the world from a save to a Project Zomboid server. Below is how to do it and what carries over and what doesn't.</p>

<h2>What carries over and what doesn't</h2>
<ul>
    <li><strong>Carries over:</strong> the map with all changes - built bases, items in containers, damage, vehicles.</li>
    <li><strong>Doesn't carry over automatically:</strong> your singleplayer character. On a server, characters belong to player accounts, so you'll start with a new character in the same world (with your bases and supplies kept).</li>
    <li><strong>The game version has to match.</strong> You can't run a Build 41 save on a Build 42 server.</li>
</ul>

<h2>1. Find the save on your PC</h2>
<p>Saves live in the <code>Zomboid</code> folder in your user directory:</p>
<pre><code>C:\Users\YourName\Zomboid\Saves\</code></pre>
<p>Inside are folders for the game modes (e.g. <code>Survivor</code>, <code>Sandbox</code>), and in them the folders of individual saves. A world hosted in-game for friends is in <code>Saves\Multiplayer\</code>. Pack the folder of the save you want into a <code>.zip</code>.</p>

<h2>2. Prepare the server</h2>
<ol>
    <li>Make a backup in the <strong>Backupy</strong> tab (backups).</li>
    <li>Add <strong>the same mods</strong> you played with to the server (the <strong>Mody (PZ)</strong> tab, see the <a href="/poradniki/mody-na-serwer-project-zomboid">mods guide</a>). Without them, items and vehicles from mods will disappear.</li>
    <li>Set similar world rules in <code>ProjectZomboid_SandboxVars.lua</code> (<a href="/poradniki/ustawienia-serwera-project-zomboid">server settings</a>) - the server uses its own settings, not the ones from the save.</li>
    <li>Stop the server with the <strong>Stop</strong> button.</li>
</ol>

<h2>3. Swap the save on the server</h2>
<ol>
    <li>In the <strong>Pliki</strong> tab (files) go to the <code>.cache/Saves/Multiplayer/ProjectZomboid</code> folder - that's the server's world.</li>
    <li>Delete its contents (you have a copy in Backups).</li>
    <li>Upload your <code>.zip</code> to that folder and choose <strong>Unarchive</strong>.</li>
    <li>Check that the save files (e.g. <code>map_t.bin</code>, <code>map_meta.bin</code>) sit <strong>directly</strong> in <code>ProjectZomboid</code>, not in an extra subfolder. If there's a subfolder, move the files one level up.</li>
    <li>Start the server and wait for <code>*** SERVER STARTED ****</code> in the console.</li>
</ol>
<p>Through the browser you can upload files up to <strong>100 MB</strong>. Upload bigger saves (large, long-played worlds) over <strong>SFTP</strong> - you'll find the details under <strong>Ustawienia → Połączenie SFTP</strong> (settings, SFTP connection), and if the password gives you trouble, message us on Discord.</p>

<h2>When something doesn't work</h2>
<ul>
    <li><strong>The server generated a new world</strong> - the files are in a subfolder or in the wrong place. They need to be directly in <code>.cache/Saves/Multiplayer/ProjectZomboid</code>.</li>
    <li><strong>Errors about missing items</strong> - the server is missing one of the mods from singleplayer.</li>
    <li><strong>The server crashes while loading</strong> - check the end of the console. "Out of memory" with a big modded world means you need more RAM.</li>
</ul>
HTML,
],
'de' => [
    'tytul' => 'So überträgst du einen Project-Zomboid-Spielstand auf den Server',
    'title' => 'Project-Zomboid-Spielstand auf den Server übertragen | DNCT',
    'opis'  => 'So bringst du eine Einzelspieler-Welt aus Project Zomboid auf einen Server: wo die Spielstände liegen, was übernommen wird, Mods, Spielstand im Panel tauschen und typische Fehler.',
    'tresc' => <<<'HTML'
<p>Du hast allein oder mit Freunden über den Host im Spiel gespielt und wollt die Welt auf einem Server weiterspielen, der rund um die Uhr läuft? Die Welt aus einem Spielstand lässt sich auf einen Project-Zomboid-Server übertragen. Hier steht, wie das geht und was übernommen wird und was nicht.</p>

<h2>Was übernommen wird und was nicht</h2>
<ul>
    <li><strong>Wird übernommen:</strong> die Karte mit allen Änderungen - gebaute Basen, Gegenstände in Containern, Zerstörungen, Fahrzeuge.</li>
    <li><strong>Wird nicht automatisch übernommen:</strong> deine Einzelspieler-Figur. Auf dem Server gehören Figuren zu Spielerkonten, du startest also mit einer neuen Figur in derselben Welt (Basen und Vorräte bleiben).</li>
    <li><strong>Die Spielversion muss passen.</strong> Einen Spielstand aus Build 41 startest du nicht auf einem Build-42-Server.</li>
</ul>

<h2>1. Spielstand am PC finden</h2>
<p>Spielstände liegen im Ordner <code>Zomboid</code> in deinem Benutzerverzeichnis:</p>
<pre><code>C:\Users\DeinName\Zomboid\Saves\</code></pre>
<p>Darin sind Ordner für die Spielmodi (z. B. <code>Survivor</code>, <code>Sandbox</code>) und darin die Ordner der einzelnen Spielstände. Eine im Spiel für Freunde gehostete Welt liegt in <code>Saves\Multiplayer\</code>. Pack den Ordner des gewünschten Spielstands in eine <code>.zip</code>.</p>

<h2>2. Server vorbereiten</h2>
<ol>
    <li>Mach ein Backup im Tab <strong>Backupy</strong> (Backups).</li>
    <li>Füg auf dem Server <strong>dieselben Mods</strong> hinzu, mit denen du gespielt hast (Tab <strong>Mody (PZ)</strong>, siehe <a href="/poradniki/mody-na-serwer-project-zomboid">Mod-Anleitung</a>). Ohne sie verschwinden Gegenstände und Fahrzeuge aus Mods.</li>
    <li>Stell in <code>ProjectZomboid_SandboxVars.lua</code> ähnliche Weltregeln ein (<a href="/poradniki/ustawienia-serwera-project-zomboid">Servereinstellungen</a>) - der Server nutzt seine eigenen Einstellungen, nicht die aus dem Spielstand.</li>
    <li>Stopp den Server mit dem Button <strong>Stop</strong>.</li>
</ol>

<h2>3. Spielstand auf dem Server tauschen</h2>
<ol>
    <li>Geh im Tab <strong>Pliki</strong> (Dateien) in den Ordner <code>.cache/Saves/Multiplayer/ProjectZomboid</code> - das ist die Welt des Servers.</li>
    <li>Lösch seinen Inhalt (die Kopie hast du in den Backups).</li>
    <li>Lade deine <code>.zip</code> in diesen Ordner hoch und wähl <strong>Unarchive</strong> (Entpacken).</li>
    <li>Prüf, dass die Spielstand-Dateien (z. B. <code>map_t.bin</code>, <code>map_meta.bin</code>) <strong>direkt</strong> in <code>ProjectZomboid</code> liegen und nicht in einem zusätzlichen Unterordner. Gibt es einen Unterordner, verschieb die Dateien eine Ebene höher.</li>
    <li>Starte den Server und warte in der Konsole auf <code>*** SERVER STARTED ****</code>.</li>
</ol>
<p>Über den Browser gehen Dateien bis <strong>100 MB</strong>. Größere Spielstände (große, lange gespielte Welten) lädst du per <strong>SFTP</strong> hoch - die Daten stehen unter <strong>Ustawienia → Połączenie SFTP</strong> (Einstellungen, SFTP-Verbindung). Bei Problemen mit dem Passwort schreib uns auf Discord.</p>

<h2>Wenn etwas nicht klappt</h2>
<ul>
    <li><strong>Der Server hat eine neue Welt erzeugt</strong> - die Dateien liegen in einem Unterordner oder am falschen Ort. Sie müssen direkt in <code>.cache/Saves/Multiplayer/ProjectZomboid</code> liegen.</li>
    <li><strong>Fehler wegen fehlender Gegenstände</strong> - auf dem Server fehlt einer der Mods aus dem Einzelspielermodus.</li>
    <li><strong>Der Server stürzt beim Laden ab</strong> - schau ans Ende der Konsole. „Out of memory“ bei einer großen Welt mit Mods heißt: mehr RAM nötig.</li>
</ul>
HTML,
],
];
