<?php
/* Tłumaczenie poradnika /poradniki/ustawienia-serwera-project-zomboid (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'Zomboid server settings: password, PvP, players, backups',
    'title' => 'Project Zomboid server settings: password, PvP | DNCT',
    'opis'  => 'How to set the password, PvP, player limit, autosave and world difficulty on a Project Zomboid server: ProjectZomboid.ini and SandboxVars step by step.',
    'tresc' => <<<'HTML'
<p>A Zomboid server has two settings files. The first decides <strong>who can play and how</strong> (password, PvP, player limit). The second decides <strong>how hard</strong> the world is (zombies, loot, power and water). You'll find both in the panel's file manager, in the folder:</p>
<pre><code>.cache/Server/</code></pre>
<p>You can also change the key options in the <strong>Configi</strong> tab (configs) in the panel, without looking for files. Edit them with the server <strong>stopped</strong>: type <code>save</code> in the console, click <strong>Stop</strong>, change the file, save it and start the server again.</p>

<h2>ProjectZomboid.ini - server rules</h2>
<p>The most important lines (each looks like <code>Name=value</code>):</p>
<pre><code>Password=secretpass        password to join the server (empty = no password)
Open=true                  false = only players added by an admin can join
PVP=true                   whether players can hurt each other
SafetySystem=true          players toggle PvP themselves (skull icon)
MaxPlayers=10              players online limit
PauseEmpty=true            time stops when nobody is on the server
Public=false               whether the server shows in the public in-game list
PublicName=My server       name in the server list
SleepAllowed=false         whether players can sleep
PlayerSafehouse=false      whether players can claim safehouses
Faction=true               player factions
SaveWorldEveryMinutes=0    autosave every X minutes (0 = off)
BackupsCount=5             how many world backups Zomboid keeps itself
BackupsOnStart=true        backup on every start</code></pre>
<ul>
    <li>Set <strong>MaxPlayers</strong> no higher than the number of slots in your plan - the plan is sized for that many people, and more players means more memory used.</li>
    <li><strong>Open=false</strong> means an allow list: you add new accounts with the <code>adduser "Nick" "password"</code> command in the console.</li>
    <li><strong>SaveWorldEveryMinutes=10</strong> is worth turning on for servers that run all day - after a sudden shutdown you'll lose at most 10 minutes of play.</li>
    <li>The welcome message is <code>ServerWelcomeMessage=</code>. You make a new line with the <code>&lt;LINE&gt;</code> tag.</li>
</ul>

<h2>ProjectZomboid_SandboxVars.lua - world difficulty</h2>
<p>This file holds the "Sandbox" menu settings: the number and speed of zombies, the amount of loot, when power and water shut off, how fast you gain experience. Every option has a comment in the file listing the allowed values, e.g.:</p>
<pre><code>-- 1 = Insanely High, 2 = Very High, 3 = High, 4 = Normal, 5 = Low, 6 = None
Zombies = 4,</code></pre>
<p>Only change the number after the <code>=</code> sign and don't remove the comma at the end of the line - a missing comma is a syntax error and the server won't start.</p>
<p>Some settings take effect right after a restart (e.g. experience gain speed). Others only apply to <strong>new</strong> map areas or a new world - buildings you've already visited won't get more loot after changing the setting.</p>

<h2>Backups before changes</h2>
<p>Before changing anything serious, make a backup in the panel (the backups tab). Restoring takes a moment; rebuilding a world after a broken setting takes weeks of play.</p>

<p>No server yet? Start with the <a href="/poradniki/jak-postawic-serwer-project-zomboid">first start guide</a>. Want mods? <a href="/poradniki/mody-na-serwer-project-zomboid">Here's how to add them</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'Zomboid-Servereinstellungen: Passwort, PvP, Spieler, Backups',
    'title' => 'Project-Zomboid-Servereinstellungen: Passwort, PvP | DNCT',
    'opis'  => 'So stellst du Passwort, PvP, Spielerlimit, automatisches Speichern und Schwierigkeit der Welt auf einem Project-Zomboid-Server ein: ProjectZomboid.ini und SandboxVars Schritt für Schritt.',
    'tresc' => <<<'HTML'
<p>Ein Zomboid-Server hat zwei Einstellungsdateien. Die erste bestimmt, <strong>wer wie spielen darf</strong> (Passwort, PvP, Spielerlimit). Die zweite, <strong>wie schwer</strong> die Welt ist (Zombies, Loot, Strom und Wasser). Beide findest du im Dateimanager des Panels im Ordner:</p>
<pre><code>.cache/Server/</code></pre>
<p>Die wichtigsten Optionen änderst du auch im Tab <strong>Configi</strong> (Configs) im Panel, ohne Dateien zu suchen. Bearbeite sie bei <strong>gestopptem</strong> Server: in der Konsole <code>save</code> eingeben, auf <strong>Stop</strong> klicken, Datei ändern, speichern und den Server wieder starten.</p>

<h2>ProjectZomboid.ini - Serverregeln</h2>
<p>Die wichtigsten Zeilen (jede sieht aus wie <code>Name=Wert</code>):</p>
<pre><code>Password=geheimpw          Passwort für den Server (leer = ohne Passwort)
Open=true                  false = nur vom Admin hinzugefügte Spieler
PVP=true                   ob Spieler sich gegenseitig verletzen können
SafetySystem=true          Spieler schalten PvP selbst um (Totenkopf-Symbol)
MaxPlayers=10              Limit der Spieler online
PauseEmpty=true            die Zeit steht, wenn niemand online ist
Public=false               ob der Server in der öffentlichen Liste im Spiel steht
PublicName=Mein Server     Name in der Serverliste
SleepAllowed=false         ob Spieler schlafen dürfen
PlayerSafehouse=false      ob Spieler Safehouses anlegen dürfen
Faction=true               Spielerfraktionen
SaveWorldEveryMinutes=0    automatisch speichern alle X Minuten (0 = aus)
BackupsCount=5             wie viele Welt-Backups Zomboid selbst behält
BackupsOnStart=true        Backup bei jedem Start</code></pre>
<ul>
    <li>Stell <strong>MaxPlayers</strong> nicht höher als die Slotzahl deines Pakets - das Paket ist für so viele Leute ausgelegt, und mehr Spieler brauchen mehr Speicher.</li>
    <li><strong>Open=false</strong> bedeutet eine Liste erlaubter Spieler: neue Konten legst du mit dem Befehl <code>adduser "Nick" "passwort"</code> in der Konsole an.</li>
    <li><strong>SaveWorldEveryMinutes=10</strong> lohnt sich auf Servern, die den ganzen Tag laufen - nach einem plötzlichen Absturz verlierst du höchstens 10 Minuten.</li>
    <li>Die Begrüßungsnachricht ist <code>ServerWelcomeMessage=</code>. Eine neue Zeile machst du mit dem Tag <code>&lt;LINE&gt;</code>.</li>
</ul>

<h2>ProjectZomboid_SandboxVars.lua - Schwierigkeit der Welt</h2>
<p>In dieser Datei stehen die Einstellungen aus dem Menü „Sandbox“: Anzahl und Tempo der Zombies, Menge an Loot, wann Strom und Wasser ausfallen, wie schnell man Erfahrung sammelt. Jede Option hat in der Datei einen Kommentar mit den erlaubten Werten, z. B.:</p>
<pre><code>-- 1 = Insanely High, 2 = Very High, 3 = High, 4 = Normal, 5 = Low, 6 = None
Zombies = 4,</code></pre>
<p>Ändere nur die Zahl nach dem <code>=</code> und lösch nicht das Komma am Zeilenende - ein fehlendes Komma ist ein Syntaxfehler, und der Server startet nicht.</p>
<p>Manche Einstellungen wirken sofort nach dem Neustart (z. B. das Erfahrungstempo). Andere gelten nur für <strong>neue</strong> Kartenbereiche oder eine neue Welt - in schon besuchten Gebäuden taucht nach der Änderung kein zusätzlicher Loot auf.</p>

<h2>Backups vor Änderungen</h2>
<p>Bevor du etwas Größeres änderst, mach ein Backup im Panel (Tab mit den Backups). Das Wiederherstellen dauert einen Moment, der Wiederaufbau einer Welt nach einer kaputten Einstellung Wochen an Spielzeit.</p>

<p>Noch kein Server? Fang mit dem <a href="/poradniki/jak-postawic-serwer-project-zomboid">ersten Start</a> an. Du willst Mods? <a href="/poradniki/mody-na-serwer-project-zomboid">So fügst du sie hinzu</a>.</p>
HTML,
],
];
