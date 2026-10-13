<?php
/* Tłumaczenie poradnika /poradniki/jak-postawic-serwer-vintage-story (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to set up a Vintage Story server',
    'title' => 'How to set up a Vintage Story server - guide | DNCT',
    'opis'  => 'How much RAM a Vintage Story server needs, the first start, connecting by IP, the /op command, serverconfig.json and the key settings.',
    'tresc' => <<<'HTML'
<p>Vintage Story is at its best in a shared, always-on world - with a base your friends can reach whenever they want. Below is everything you need: how much power the server needs, the first start, connecting, admin rights and the key settings.</p>

<h2>1. How much RAM does a Vintage Story server need?</h2>
<ul>
    <li><strong>up to 5 players, no mods:</strong> 4 GB RAM,</li>
    <li><strong>10-20 players:</strong> 4.5-6 GB RAM,</li>
    <li><strong>a bigger server or lots of mods:</strong> 8 GB and up.</li>
</ul>
<p>Memory grows with the number of loaded map areas - players spread across the world load the server more than the same players in one settlement.</p>

<h2>2. Ordering and the first start</h2>
<ol>
    <li>Choose the number of players on the <a href="/hosting-vintage-story">Vintage Story hosting</a> page, then the period and payment method.</li>
    <li>After payment the server sets itself up. In the panel (the "Open in the panel" button on the <a href="/twoje-serwery">Your servers</a> page) click <strong>Start</strong>.</li>
    <li>The first start creates the world. The server is ready when the console shows <code>Dedicated Server now running on Port ...</code>.</li>
    <li>Right after the start you may see <code>Server overloaded</code> warnings - that's world generation, they pass after a moment.</li>
</ol>

<h2>3. How to connect to the server</h2>
<ol>
    <li>Copy the <strong>server address</strong> from the panel - the IP with the port, e.g. <code>123.45.67.89:60001</code>.</li>
    <li>In the game: <strong>Multiplayer → Add Server</strong>, paste the address together with the port and save.</li>
    <li>By default the server <strong>doesn't show up</strong> in the public server list - you connect by address. Want it public? Set <code>"AdvertiseServer": true</code> (step 5).</li>
</ol>

<h2>4. Become an admin</h2>
<p>In the <strong>panel console</strong> type the command with your Vintage Story account name:</p>
<pre><code>/op YourName                       admin permissions
/player YourName role admin        the same, newer syntax
/whitelist add PlayerName          adds a player to the whitelist</code></pre>
<p>The server checks the name against game accounts, so it has to be your real account name - otherwise it replies <code>No player with name ... exists</code>.</p>

<h2>5. Key settings</h2>
<p>The server configuration is the <code>data/serverconfig.json</code> file (the <strong>Pliki</strong> tab, files). Edit it with the server <strong>stopped</strong> - a running server overwrites the file when it saves.</p>
<pre><code>"ServerName": "My server",            server name
"WelcomeMessage": "Welcome {0}!",     greeting ({0} = player nick)
"MaxClients": 5,                      player limit
"Password": null,                     password, e.g. "secret"; null = no password
"AdvertiseServer": false,             true = visible in the public list
"WhitelistMode": 0,                   whitelist (1 = on)
"AllowPvP": true,                     player vs player combat
"AllowFireSpread": true,              fire spreading</code></pre>
<p>Set <strong>MaxClients</strong> no higher than the number of slots in your plan. Watch the commas and quotes - a JSON syntax error stops the server from starting.</p>
<p>The world is saved in <code>data/Saves/default.vcdbs</code>. Before bigger changes, make a backup in the <strong>Backupy</strong> tab (backups).</p>

<p>Next: <a href="/poradniki/mody-na-serwer-vintage-story">how to add mods to a Vintage Story server</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'So richtest du einen Vintage-Story-Server ein',
    'title' => 'Vintage-Story-Server einrichten - Anleitung | DNCT',
    'opis'  => 'Wie viel RAM ein Vintage-Story-Server braucht, erster Start, Verbindung per IP, der Befehl /op, serverconfig.json und die wichtigsten Einstellungen.',
    'tresc' => <<<'HTML'
<p>Vintage Story macht am meisten Spaß in einer gemeinsamen, ständig laufenden Welt - mit einer Basis, zu der Freunde kommen, wann sie wollen. Hier alles, was du brauchst: wie viel Leistung der Server braucht, erster Start, Verbindung, Admin-Rechte und die wichtigsten Einstellungen.</p>

<h2>1. Wie viel RAM braucht ein Vintage-Story-Server?</h2>
<ul>
    <li><strong>bis 5 Spieler, ohne Mods:</strong> 4 GB RAM,</li>
    <li><strong>10-20 Spieler:</strong> 4,5-6 GB RAM,</li>
    <li><strong>größerer Server oder viele Mods:</strong> 8 GB und mehr.</li>
</ul>
<p>Der Speicher wächst mit der Zahl geladener Kartenbereiche - über die Welt verteilte Spieler belasten den Server stärker als dieselben Spieler in einer Siedlung.</p>

<h2>2. Bestellung und erster Start</h2>
<ol>
    <li>Wähl auf der Seite <a href="/hosting-vintage-story">Vintage-Story-Hosting</a> die Spielerzahl, den Zeitraum und die Zahlungsart.</li>
    <li>Nach der Zahlung richtet sich der Server selbst ein. Klick im Panel (Button „Im Panel öffnen“ auf der Seite <a href="/twoje-serwery">Deine Server</a>) auf <strong>Start</strong>.</li>
    <li>Der erste Start erstellt die Welt. Der Server ist bereit, wenn in der Konsole <code>Dedicated Server now running on Port ...</code> erscheint.</li>
    <li>Direkt nach dem Start können Warnungen <code>Server overloaded</code> auftauchen - das ist die Weltgenerierung, sie verschwinden nach kurzer Zeit.</li>
</ol>

<h2>3. So verbindest du dich mit dem Server</h2>
<ol>
    <li>Kopiere die <strong>Serveradresse</strong> aus dem Panel - IP mit Port, z. B. <code>123.45.67.89:60001</code>.</li>
    <li>Im Spiel: <strong>Multiplayer → Add Server</strong>, Adresse samt Port einfügen und speichern.</li>
    <li>Standardmäßig <strong>erscheint</strong> der Server <strong>nicht</strong> in der öffentlichen Serverliste - du verbindest dich per Adresse. Soll er öffentlich sein? Setz <code>"AdvertiseServer": true</code> (Punkt 5).</li>
</ol>

<h2>4. Admin werden</h2>
<p>Gib in der <strong>Panel-Konsole</strong> den Befehl mit dem Namen deines Vintage-Story-Kontos ein:</p>
<pre><code>/op DeinName                       Admin-Rechte
/player DeinName role admin        dasselbe, neuere Schreibweise
/whitelist add SpielerName         Spieler zur Whitelist hinzufügen</code></pre>
<p>Der Server prüft den Namen gegen die Spielkonten, es muss also dein echter Kontoname sein - sonst antwortet er <code>No player with name ... exists</code>.</p>

<h2>5. Die wichtigsten Einstellungen</h2>
<p>Die Serverkonfiguration ist die Datei <code>data/serverconfig.json</code> (Tab <strong>Pliki</strong>, Dateien). Bearbeite sie bei <strong>gestopptem</strong> Server - ein laufender Server überschreibt die Datei beim Speichern.</p>
<pre><code>"ServerName": "Mein Server",          Servername
"WelcomeMessage": "Hallo {0}!",       Begrüßung ({0} = Nick des Spielers)
"MaxClients": 5,                      Spielerlimit
"Password": null,                     Passwort, z. B. "geheim"; null = ohne Passwort
"AdvertiseServer": false,             true = in der öffentlichen Liste sichtbar
"WhitelistMode": 0,                   Whitelist (1 = an)
"AllowPvP": true,                     Kämpfe zwischen Spielern
"AllowFireSpread": true,              Ausbreitung von Feuer</code></pre>
<p>Stell <strong>MaxClients</strong> nicht höher als die Slotzahl deines Pakets. Achte auf Kommas und Anführungszeichen - ein Syntaxfehler im JSON verhindert den Serverstart.</p>
<p>Die Welt wird in <code>data/Saves/default.vcdbs</code> gespeichert. Mach vor größeren Änderungen ein Backup im Tab <strong>Backupy</strong> (Backups).</p>

<p>Weiter: <a href="/poradniki/mody-na-serwer-vintage-story">Mods zum Vintage-Story-Server hinzufügen</a>.</p>
HTML,
],
];
