<?php
/* Tłumaczenie poradnika /poradniki/pluginy-na-serwer-minecraft (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'How to add plugins to a Minecraft server (Paper)',
    'title' => 'Minecraft server plugins (Paper) - how to add them | DNCT',
    'opis'  => 'Where to download plugins, how to check the version, upload the .jar file to the plugins folder, configure config.yml and what to do when a plugin fails.',
    'tresc' => <<<'HTML'
<p>Plugins add features to the server without players installing anything: land claims, economy, ranks, teleports, minigames. They run on the <strong>Paper</strong> and <strong>Purpur</strong> engines - our default engine is Paper, so usually there's nothing to change.</p>

<h2>1. Where to get plugins</h2>
<ul>
    <li><strong>Modrinth</strong> (modrinth.com, "Plugins" filter) and <strong>Hangar</strong> (hangar.papermc.io) - modern, safe catalogs,</li>
    <li><strong>SpigotMC</strong> (spigotmc.org/resources) - the biggest selection, older plugins too.</li>
</ul>
<p>Only download plugins from these sites. A <code>.jar</code> file from an unknown source can take over the whole server.</p>

<h2>2. Check the version</h2>
<p>Every plugin lists the Minecraft versions it supports. You'll find the server version in the <strong>Wersje (MC)</strong> tab (versions) or in the console at startup. A plugin made for an older version often works; one for a newer version almost never does.</p>

<h2>3. Uploading a plugin</h2>
<ol>
    <li>In the panel open <strong>Pliki</strong> (files) and go to the <code>plugins</code> folder.</li>
    <li>Click <strong>Upload</strong> and choose the downloaded <code>.jar</code> file.</li>
    <li>Restart the server with the <strong>Restart</strong> button.</li>
    <li>Type <code>plugins</code> in the console - the new plugin should be listed in green.</li>
</ol>
<p>Don't use the <code>reload</code> command to load plugins - many of them break after that kind of reload. Always do a full restart.</p>

<h2>4. Configuring a plugin</h2>
<p>After the first start the plugin creates its own folder in <code>plugins/PluginName/</code>, usually with a <code>config.yml</code> file. Edit it in the file manager and restart the server.</p>
<p>In <code>.yml</code> files <strong>indentation with spaces</strong> matters (not tabs). One misplaced space can disable the whole plugin - the console will then show an error with the line number.</p>

<h2>5. Plugins worth starting with</h2>
<ul>
    <li><strong>LuckPerms</strong> - ranks and permissions,</li>
    <li><strong>EssentialsX</strong> - homes, teleports, kits, basic commands,</li>
    <li><strong>WorldGuard</strong> + <strong>WorldEdit</strong> - land protection and building,</li>
    <li><strong>CoreProtect</strong> - block change history, rolling back grief damage.</li>
</ul>
<p>Some plugins need others (e.g. WorldGuard needs WorldEdit). The console names the missing dependency at startup, in the error line.</p>

<h2>6. When something doesn't work</h2>
<ul>
    <li><strong>Plugin shown in red in <code>plugins</code></strong> - it didn't start. Scroll the console to the first error with its name: usually a wrong version or a missing dependency.</li>
    <li><strong>The server started lagging</strong> - disable plugins one by one (move the <code>.jar</code> to another folder and restart) to find the culprit.</li>
    <li><strong>Plugins don't work at all</strong> - check in <strong>Wersje (MC)</strong> that the server runs Paper, not Vanilla, Fabric or Forge.</li>
</ul>
<p>Before installing a bigger set of plugins, make a backup in the <strong>Backupy</strong> tab.</p>
HTML,
],
'de' => [
    'tytul' => 'So fügst du Plugins zu einem Minecraft-Server hinzu (Paper)',
    'title' => 'Plugins für Minecraft-Server (Paper) - so geht’s | DNCT',
    'opis'  => 'Woher du Plugins bekommst, wie du die Version prüfst, die .jar-Datei in den plugins-Ordner lädst, config.yml einstellst und was tun, wenn ein Plugin nicht läuft.',
    'tresc' => <<<'HTML'
<p>Plugins geben dem Server neue Funktionen, ohne dass Spieler etwas installieren müssen: Grundstücksschutz, Wirtschaft, Ränge, Teleports, Minispiele. Sie laufen auf den Engines <strong>Paper</strong> und <strong>Purpur</strong> - unsere Standard-Engine ist Paper, also musst du meist nichts ändern.</p>

<h2>1. Woher du Plugins bekommst</h2>
<ul>
    <li><strong>Modrinth</strong> (modrinth.com, Filter „Plugins“) und <strong>Hangar</strong> (hangar.papermc.io) - moderne, sichere Kataloge,</li>
    <li><strong>SpigotMC</strong> (spigotmc.org/resources) - die größte Auswahl, auch ältere Plugins.</li>
</ul>
<p>Lade Plugins nur von diesen Seiten. Eine <code>.jar</code>-Datei aus unbekannter Quelle kann den ganzen Server übernehmen.</p>

<h2>2. Version prüfen</h2>
<p>Bei jedem Plugin steht, welche Minecraft-Versionen es unterstützt. Die Serverversion siehst du im Tab <strong>Wersje (MC)</strong> (Versionen) oder beim Start in der Konsole. Ein Plugin für eine ältere Version läuft oft, eins für eine neuere fast nie.</p>

<h2>3. Plugin hochladen</h2>
<ol>
    <li>Öffne im Panel <strong>Pliki</strong> (Dateien) und geh in den Ordner <code>plugins</code>.</li>
    <li>Klick auf <strong>Upload</strong> und wähl die heruntergeladene <code>.jar</code>-Datei.</li>
    <li>Starte den Server mit dem Button <strong>Restart</strong> neu.</li>
    <li>Gib in der Konsole <code>plugins</code> ein - das neue Plugin sollte grün in der Liste stehen.</li>
</ol>
<p>Lade Plugins nicht mit dem Befehl <code>reload</code> - viele gehen danach kaputt. Immer ein vollständiger Neustart.</p>

<h2>4. Plugin konfigurieren</h2>
<p>Nach dem ersten Start legt das Plugin einen eigenen Ordner <code>plugins/PluginName/</code> an, meist mit einer Datei <code>config.yml</code>. Bearbeite sie im Dateimanager und starte den Server neu.</p>
<p>In <code>.yml</code>-Dateien zählt die <strong>Einrückung mit Leerzeichen</strong> (keine Tabs). Ein verrutschtes Leerzeichen kann das ganze Plugin abschalten - die Konsole zeigt dann einen Fehler mit Zeilennummer.</p>

<h2>5. Plugins für den Anfang</h2>
<ul>
    <li><strong>LuckPerms</strong> - Ränge und Rechte,</li>
    <li><strong>EssentialsX</strong> - Homes, Teleports, Kits, Grundbefehle,</li>
    <li><strong>WorldGuard</strong> + <strong>WorldEdit</strong> - Geländeschutz und Bauen,</li>
    <li><strong>CoreProtect</strong> - Verlauf von Blockänderungen, Griefing rückgängig machen.</li>
</ul>
<p>Manche Plugins brauchen andere (z. B. braucht WorldGuard WorldEdit). Die fehlende Abhängigkeit nennt die Konsole beim Start in der Fehlerzeile.</p>

<h2>6. Wenn etwas nicht klappt</h2>
<ul>
    <li><strong>Plugin rot in <code>plugins</code></strong> - es ist nicht gestartet. Scroll in der Konsole zum ersten Fehler mit seinem Namen: meist falsche Version oder fehlende Abhängigkeit.</li>
    <li><strong>Der Server laggt plötzlich</strong> - schalte Plugins nacheinander ab (die <code>.jar</code> in einen anderen Ordner verschieben und neu starten), um den Schuldigen zu finden.</li>
    <li><strong>Plugins funktionieren gar nicht</strong> - prüf in <strong>Wersje (MC)</strong>, ob der Server Paper nutzt und nicht Vanilla, Fabric oder Forge.</li>
</ul>
<p>Mach vor der Installation größerer Plugin-Pakete ein Backup im Tab <strong>Backupy</strong>.</p>
HTML,
],
];
