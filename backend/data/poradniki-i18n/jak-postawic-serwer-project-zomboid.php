<?php
/* Tłumaczenie poradnika /poradniki/jak-postawic-serwer-project-zomboid (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to set up a Project Zomboid server (Build 42)',
    'title' => 'How to set up a Project Zomboid server (Build 42) | DNCT',
    'opis'  => 'Step by step: how much RAM a Project Zomboid server needs, the first start, connecting by IP and port, admin commands and the most common errors.',
    'tresc' => <<<'HTML'
<p>Your own Project Zomboid server is the simplest way to play with friends in one world that doesn't disappear when the host leaves the game. Below is the whole path: from choosing the server size, through the first start, to joining the game as an admin for the first time.</p>

<h2>1. How much RAM does a Zomboid server need?</h2>
<p>Zomboid keeps every loaded piece of the map in memory, and every player in a different spot loads their own. That's why memory grows with the number of players, and even more with mods.</p>
<ul>
    <li><strong>up to 5 players, no mods or just a few:</strong> 6 GB RAM,</li>
    <li><strong>10-20 players:</strong> 7-9 GB RAM,</li>
    <li><strong>big server with dozens of mods (vehicles, maps):</strong> start at 10 GB and watch usage in the panel.</li>
</ul>
<p>A server that runs out of memory doesn't slow down - it shuts off. The console then shows <code>Out of memory: true</code> and exit code 137. That's the sign you need a bigger plan or fewer mods.</p>

<h2>2. Ordering and the first start</h2>
<ol>
    <li>Choose the number of players on the <a href="/hosting-project-zomboid">Project Zomboid hosting</a> page, then the period and payment method.</li>
    <li>After payment the server sets itself up. The confirmation email contains the <strong>in-game admin login and password</strong> - save them, we won't show them a second time.</li>
    <li>Open the panel (the "Open in the panel" button on the <a href="/twoje-serwery">Your servers</a> page) and click <strong>Start</strong>.</li>
    <li>The first start downloads the game from Steam - around <strong>7 GB</strong>, so it takes a few minutes. You can follow the progress in the console.</li>
    <li>The server is ready when the console shows <code>*** SERVER STARTED ****</code>. The first start generates the world, so it takes a moment.</li>
</ol>

<h2>3. How to connect to the server</h2>
<ol>
    <li>In the panel (or on the "Your servers" page) copy the <strong>server address</strong>: IP and port, e.g. <code>123.45.67.89:60001</code>.</li>
    <li>In the game choose <strong>Join</strong> and enter the <strong>IP</strong> and the <strong>port</strong> in separate fields.</li>
    <li>As the username, enter the <strong>admin login from the email</strong> and its password - that's how you join with admin rights.</li>
    <li>Friends join the same way, just with their own nickname. The in-game account is created on first join.</li>
</ol>

<h2>4. First admin commands</h2>
<p>Type commands in the <strong>panel console</strong> (without a slash) or in the in-game chat (with a slash, e.g. <code>/save</code>):</p>
<pre><code>setaccesslevel "PlayerNick" admin      grants admin rights
servermsg "Restart in 5 minutes"       message to all players
save                                   saves the world
players                                list of players online
quit                                   saves and shuts the server down</code></pre>
<p>Always type <code>save</code> before a restart or a settings change - Zomboid saves the world periodically, not on every player move.</p>

<h2>5. Most common problems</h2>
<ul>
    <li><strong>The server keeps restarting, "Out of memory" in the console</strong> - not enough RAM for that many mods or players. Remove some mods or switch to a bigger plan.</li>
    <li><strong>"Server version mismatch" when connecting</strong> - the player's game and the server run different versions. Update the game in Steam; the server checks for updates on every start by itself.</li>
    <li><strong>The server isn't on the list</strong> - private servers don't show up in the public browser. Connect by IP and port (step 3).</li>
</ul>

<p>Next: <a href="/poradniki/ustawienia-serwera-project-zomboid">set a password, PvP and the player limit</a> or <a href="/poradniki/mody-na-serwer-project-zomboid">add mods from the Steam Workshop</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'So richtest du einen Project-Zomboid-Server ein (Build 42)',
    'title' => 'Project-Zomboid-Server einrichten (Build 42) | DNCT',
    'opis'  => 'Schritt für Schritt: wie viel RAM ein Project-Zomboid-Server braucht, erster Start, Verbindung per IP und Port, Admin-Befehle und die häufigsten Fehler.',
    'tresc' => <<<'HTML'
<p>Ein eigener Project-Zomboid-Server ist der einfachste Weg, mit Freunden in einer Welt zu spielen, die nicht verschwindet, wenn der Host das Spiel verlässt. Hier der ganze Weg: von der Servergröße über den ersten Start bis zum ersten Login im Spiel als Admin.</p>

<h2>1. Wie viel RAM braucht ein Zomboid-Server?</h2>
<p>Zomboid hält jedes geladene Kartenstück im Speicher, und jeder Spieler an einem anderen Ort lädt sein eigenes. Deshalb wächst der Speicherbedarf mit der Spielerzahl - und mit Mods noch stärker.</p>
<ul>
    <li><strong>bis 5 Spieler, ohne oder mit wenigen Mods:</strong> 6 GB RAM,</li>
    <li><strong>10-20 Spieler:</strong> 7-9 GB RAM,</li>
    <li><strong>großer Server mit Dutzenden Mods (Fahrzeuge, Karten):</strong> fang mit 10 GB an und beobachte die Auslastung im Panel.</li>
</ul>
<p>Ein Server ohne genug Speicher wird nicht langsamer, sondern schaltet sich ab. In der Konsole steht dann <code>Out of memory: true</code> und Exit-Code 137. Das heißt: größeres Paket oder weniger Mods.</p>

<h2>2. Bestellung und erster Start</h2>
<ol>
    <li>Wähl auf der Seite <a href="/hosting-project-zomboid">Project-Zomboid-Hosting</a> die Spielerzahl, den Zeitraum und die Zahlungsart.</li>
    <li>Nach der Zahlung richtet sich der Server selbst ein. Die Bestätigungsmail enthält <strong>Admin-Login und Passwort für das Spiel</strong> - speicher sie, wir zeigen sie kein zweites Mal.</li>
    <li>Öffne das Panel (Button „Im Panel öffnen“ auf der Seite <a href="/twoje-serwery">Deine Server</a>) und klick auf <strong>Start</strong>.</li>
    <li>Der erste Start lädt das Spiel von Steam - etwa <strong>7 GB</strong>, das dauert ein paar Minuten. Den Fortschritt siehst du in der Konsole.</li>
    <li>Der Server ist bereit, wenn in der Konsole <code>*** SERVER STARTED ****</code> erscheint. Beim ersten Start wird die Welt erzeugt, deshalb dauert es etwas.</li>
</ol>

<h2>3. So verbindest du dich mit dem Server</h2>
<ol>
    <li>Kopiere im Panel (oder auf der Seite „Deine Server“) die <strong>Serveradresse</strong>: IP und Port, z. B. <code>123.45.67.89:60001</code>.</li>
    <li>Wähl im Spiel <strong>Beitreten</strong> (Join) und gib <strong>IP</strong> und <strong>Port</strong> in getrennte Felder ein.</li>
    <li>Als Benutzernamen gibst du den <strong>Admin-Login aus der Mail</strong> und sein Passwort ein - so kommst du mit Admin-Rechten rein.</li>
    <li>Freunde verbinden sich genauso, nur mit eigenem Nick. Das Spielkonto entsteht beim ersten Login.</li>
</ol>

<h2>4. Erste Admin-Befehle</h2>
<p>Befehle gibst du in der <strong>Panel-Konsole</strong> (ohne Schrägstrich) oder im Spielchat ein (mit Schrägstrich, z. B. <code>/save</code>):</p>
<pre><code>setaccesslevel "SpielerNick" admin     vergibt Admin-Rechte
servermsg "Neustart in 5 Minuten"      Nachricht an alle Spieler
save                                   speichert die Welt
players                                Liste der Spieler online
quit                                   speichert und fährt den Server herunter</code></pre>
<p>Gib vor einem Neustart oder einer Einstellungsänderung immer <code>save</code> ein - Zomboid speichert die Welt regelmäßig, nicht bei jeder Spielerbewegung.</p>

<h2>5. Häufigste Probleme</h2>
<ul>
    <li><strong>Der Server startet ständig neu, „Out of memory“ in der Konsole</strong> - zu wenig RAM für so viele Mods oder Spieler. Entferne ein paar Mods oder wechsle auf ein größeres Paket.</li>
    <li><strong>„Server version mismatch“ beim Verbinden</strong> - Spiel und Server haben unterschiedliche Versionen. Aktualisiere das Spiel in Steam; der Server prüft bei jedem Start selbst auf Updates.</li>
    <li><strong>Der Server ist nicht in der Liste</strong> - private Server erscheinen nicht im öffentlichen Browser. Verbinde dich per IP und Port (Punkt 3).</li>
</ul>

<p>Weiter: <a href="/poradniki/ustawienia-serwera-project-zomboid">Passwort, PvP und Spielerlimit einstellen</a> oder <a href="/poradniki/mody-na-serwer-project-zomboid">Mods aus dem Steam Workshop hinzufügen</a>.</p>
HTML,
],
];
