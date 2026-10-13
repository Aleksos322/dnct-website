<?php
/* Tłumaczenie poradnika /poradniki/jak-postawic-serwer-minecraft (components/poradnik.php).
   Nazwy zakładek panelu zostają po polsku - tak je widać w panelu. */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to set up a Minecraft server',
    'title' => 'How to set up a Minecraft server - step-by-step guide | DNCT',
    'opis'  => 'How much RAM a Minecraft server needs, Paper or Forge, first start, connecting by IP, server.properties and admin commands.',
    'tresc' => <<<'HTML'
<p>Your own Minecraft server is a world that keeps running even when your PC is off, and your friends can join whenever they want. Below is the whole path: choosing the server size and engine, the first start, connecting and the basic settings.</p>

<h2>1. How much RAM does a Minecraft server need?</h2>
<ul>
    <li><strong>2-5 players, vanilla Minecraft:</strong> 3-4 GB RAM,</li>
    <li><strong>10-20 players with plugins:</strong> 6-8 GB RAM,</li>
    <li><strong>modpack (Forge, Fabric, NeoForge):</strong> usually 8-10 GB, big packs need more,</li>
    <li><strong>public server for dozens of players:</strong> 14 GB and up.</li>
</ul>
<p>More RAM doesn't always mean faster - Minecraft leans heavily on a single CPU core. When the server lags while memory use is low, the usual culprits are a view distance that's too high or heavy farms, not RAM.</p>

<h2>2. Paper, Fabric or Forge?</h2>
<ul>
    <li><strong>Paper</strong> (our default) - faster than the regular server and supports <strong>plugins</strong> (land claims, economy, permissions). Players join with plain Minecraft.</li>
    <li><strong>Fabric</strong> and <strong>Forge / NeoForge</strong> - support <strong>mods</strong>. Players need the same mods and the same game version installed.</li>
    <li><strong>Vanilla</strong> - the regular server from Mojang, no plugins or mods.</li>
</ul>
<p>You change the engine and game version in the panel, in the <strong>Wersje (MC)</strong> tab (versions). Make a backup in the <strong>Backupy</strong> tab before switching.</p>

<h2>3. Ordering and the first start</h2>
<ol>
    <li>Pick a plan on the <a href="/hosting-minecraft">Minecraft hosting</a> page, then the period and payment method.</li>
    <li>After payment the server sets itself up. Open the panel (the "Open in the panel" button on the <a href="/twoje-serwery">Your servers</a> page) and click <strong>Start</strong>.</li>
    <li>The first start downloads the server files and generates the world. The server is ready when the console shows the line <code>Done (...)! For help, type "help"</code>.</li>
</ol>

<h2>4. How to connect to the server</h2>
<ol>
    <li>Copy the <strong>server address</strong> from the panel or from the "Your servers" page - the IP with the port, e.g. <code>123.45.67.89:60002</code>.</li>
    <li>In the game: <strong>Multiplayer → Add Server</strong> and paste the address together with the port.</li>
    <li>Want a shorter address? In the <strong>Subdomena</strong> tab (subdomain) you can set a name that's easier to share with friends.</li>
</ol>

<h2>5. First settings and commands</h2>
<p>The key options (server name, player limit, game mode, difficulty, PvP, whitelist) are in the <strong>Configi</strong> tab (configs) or in the <code>server.properties</code> file in the file manager. Restart the server after changing them.</p>
<pre><code>motd=My server            description shown in the server list
max-players=10            player limit
difficulty=normal         peaceful / easy / normal / hard
gamemode=survival         survival / creative / adventure
pvp=true                  whether players can attack each other
white-list=false          true = only listed players can join
view-distance=10          lower value = less lag</code></pre>
<p>Type commands in the <strong>panel console</strong> without the slash:</p>
<pre><code>op YourNick               admin permissions
whitelist add Nick        adds a player to the whitelist
save-all                  saves the world
stop                      saves and shuts the server down</code></pre>
<p>You can also manage permissions, the whitelist and bans without commands - in the <strong>Gracze (MC)</strong> tab (players).</p>

<h2>6. Most common problems</h2>
<ul>
    <li><strong>"Outdated client" / "Outdated server"</strong> - the player's game version differs from the server's. Set the version in <strong>Wersje (MC)</strong> or change it in the launcher.</li>
    <li><strong>Players without a licensed account can't join</strong> - by default the server checks Mojang accounts (<code>online-mode=true</code>). Turning this off lets anyone in under any nickname, so in that case you must add a login plugin.</li>
    <li><strong>The server shuts down by itself</strong> - check the end of the console. "Out of memory" means the plan is too small for that modpack or that many plugins.</li>
</ul>
<p>Next: <a href="/poradniki/pluginy-na-serwer-minecraft">add plugins</a> or <a href="/poradniki/mody-na-serwer-minecraft">install mods and modpacks</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'So richtest du einen Minecraft-Server ein',
    'title' => 'Minecraft-Server einrichten - Schritt-für-Schritt-Anleitung | DNCT',
    'opis'  => 'Wie viel RAM ein Minecraft-Server braucht, Paper oder Forge, erster Start, Verbindung per IP, server.properties und Admin-Befehle.',
    'tresc' => <<<'HTML'
<p>Ein eigener Minecraft-Server ist eine Welt, die auch dann läuft, wenn dein PC aus ist, und in die deine Freunde jederzeit reinkommen. Hier der ganze Weg: Servergröße und Engine wählen, erster Start, Verbindung und Grundeinstellungen.</p>

<h2>1. Wie viel RAM braucht ein Minecraft-Server?</h2>
<ul>
    <li><strong>2-5 Spieler, Vanilla-Minecraft:</strong> 3-4 GB RAM,</li>
    <li><strong>10-20 Spieler mit Plugins:</strong> 6-8 GB RAM,</li>
    <li><strong>Modpack (Forge, Fabric, NeoForge):</strong> meist 8-10 GB, große Packs mehr,</li>
    <li><strong>öffentlicher Server für Dutzende Spieler:</strong> 14 GB und mehr.</li>
</ul>
<p>Mehr RAM heißt nicht immer schneller - Minecraft nutzt vor allem einen CPU-Kern. Wenn der Server bei geringem Speicherverbrauch laggt, liegt es meist an zu hoher Sichtweite oder schweren Farmen, nicht am RAM.</p>

<h2>2. Paper, Fabric oder Forge?</h2>
<ul>
    <li><strong>Paper</strong> (bei uns Standard) - schneller als der normale Server und unterstützt <strong>Plugins</strong> (Grundstücksschutz, Wirtschaft, Rechte). Spieler verbinden sich mit normalem Minecraft.</li>
    <li><strong>Fabric</strong> und <strong>Forge / NeoForge</strong> - unterstützen <strong>Mods</strong>. Spieler brauchen dieselben Mods und dieselbe Spielversion.</li>
    <li><strong>Vanilla</strong> - der normale Server von Mojang, ohne Plugins und Mods.</li>
</ul>
<p>Engine und Spielversion änderst du im Panel im Tab <strong>Wersje (MC)</strong> (Versionen). Mach vorher ein Backup im Tab <strong>Backupy</strong>.</p>

<h2>3. Bestellung und erster Start</h2>
<ol>
    <li>Wähle auf der Seite <a href="/hosting-minecraft">Minecraft-Hosting</a> ein Paket, den Zeitraum und die Zahlungsart.</li>
    <li>Nach der Zahlung richtet sich der Server selbst ein. Öffne das Panel (Button „Im Panel öffnen“ auf der Seite <a href="/twoje-serwery">Deine Server</a>) und klick auf <strong>Start</strong>.</li>
    <li>Der erste Start lädt die Serverdateien und erzeugt die Welt. Der Server ist bereit, wenn in der Konsole die Zeile <code>Done (...)! For help, type "help"</code> erscheint.</li>
</ol>

<h2>4. So verbindest du dich mit dem Server</h2>
<ol>
    <li>Kopiere die <strong>Serveradresse</strong> aus dem Panel oder von der Seite „Deine Server“ - IP mit Port, z. B. <code>123.45.67.89:60002</code>.</li>
    <li>Im Spiel: <strong>Mehrspieler → Server hinzufügen</strong> und die Adresse samt Port einfügen.</li>
    <li>Lieber eine kürzere Adresse? Im Tab <strong>Subdomena</strong> (Subdomain) legst du einen Namen fest, den du Freunden leichter geben kannst.</li>
</ol>

<h2>5. Erste Einstellungen und Befehle</h2>
<p>Die wichtigsten Optionen (Servername, Spielerlimit, Spielmodus, Schwierigkeit, PvP, Whitelist) findest du im Tab <strong>Configi</strong> (Configs) oder in der Datei <code>server.properties</code> im Dateimanager. Starte den Server danach neu.</p>
<pre><code>motd=Mein Server          Beschreibung in der Serverliste
max-players=10            Spielerlimit
difficulty=normal         peaceful / easy / normal / hard
gamemode=survival         survival / creative / adventure
pvp=true                  ob Spieler sich angreifen können
white-list=false          true = nur Spieler auf der Liste
view-distance=10          kleinerer Wert = weniger Lag</code></pre>
<p>Befehle gibst du in der <strong>Panel-Konsole</strong> ohne Schrägstrich ein:</p>
<pre><code>op DeinNick               Admin-Rechte
whitelist add Nick        fügt einen Spieler zur Whitelist hinzu
save-all                  speichert die Welt
stop                      speichert und fährt den Server herunter</code></pre>
<p>Rechte, Whitelist und Banns verwaltest du auch ohne Befehle - im Tab <strong>Gracze (MC)</strong> (Spieler).</p>

<h2>6. Häufigste Probleme</h2>
<ul>
    <li><strong>„Outdated client“ / „Outdated server“</strong> - die Spielversion des Spielers weicht von der des Servers ab. Stell die Version in <strong>Wersje (MC)</strong> ein oder ändere sie im Launcher.</li>
    <li><strong>Spieler ohne gekauften Account kommen nicht rein</strong> - der Server prüft standardmäßig Mojang-Accounts (<code>online-mode=true</code>). Wenn du das abschaltest, kommt jeder mit beliebigem Nick rein - dann unbedingt ein Login-Plugin installieren.</li>
    <li><strong>Der Server schaltet sich selbst ab</strong> - schau ans Ende der Konsole. „Out of memory“ heißt, das Paket ist zu klein für dieses Modpack oder so viele Plugins.</li>
</ul>
<p>Weiter: <a href="/poradniki/pluginy-na-serwer-minecraft">Plugins hinzufügen</a> oder <a href="/poradniki/mody-na-serwer-minecraft">Mods und Modpacks installieren</a>.</p>
HTML,
],
];
