<?php
/* Tłumaczenie poradnika /poradniki/mody-na-serwer-minecraft (components/poradnik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'Mods and modpacks on a Minecraft server (Fabric, Forge)',
    'title' => 'Mods and modpacks on a Minecraft server - Fabric, Forge | DNCT',
    'opis'  => 'How to switch the server engine to Fabric or Forge, upload mods to the mods folder, install a CurseForge or Modrinth modpack and how much RAM you need.',
    'tresc' => <<<'HTML'
<p>Unlike plugins, mods change the game on the player's side too: new blocks, mobs, machines, dimensions. That's why <strong>the server and every player need the same set of mods</strong> and the same game version.</p>

<h2>1. Pick an engine for your mods</h2>
<p>Each mod is written for a specific engine - you'll find this on its page (Modrinth, CurseForge):</p>
<ul>
    <li><strong>Fabric</strong> - lightweight mods, fast updates, most mods also need <strong>Fabric API</strong>,</li>
    <li><strong>Forge</strong> / <strong>NeoForge</strong> - big mods and most classic modpacks.</li>
</ul>
<p>Fabric mods don't work on Forge and vice versa. Paper plugins don't work on either.</p>

<h2>2. Switching the server engine</h2>
<ol>
    <li>Make a backup in the <strong>Backupy</strong> tab (backups).</li>
    <li>Stop the server.</li>
    <li>Open the <strong>Wersje (MC)</strong> tab (versions), choose the engine (Fabric, Forge or NeoForge) and <strong>exactly the Minecraft version</strong> your mods are made for.</li>
    <li>Start the server once without mods - it should start and create the <code>mods</code> folder.</li>
</ol>

<h2>3. Uploading mods</h2>
<ol>
    <li>Download the <strong>server</strong> or universal version of each mod. Leave client-only mods (minimaps, shaders, graphics tweaks) to the players - on the server they can stop it from starting.</li>
    <li>In the panel open <strong>Pliki → mods</strong> (files) and upload the <code>.jar</code> files.</li>
    <li>On Fabric, also add <strong>Fabric API</strong> for the same game version.</li>
    <li>Restart the server and watch the console until the <code>Done (...)!</code> line.</li>
</ol>

<h2>4. Modpacks (CurseForge, Modrinth)</h2>
<ol>
    <li>On the modpack page download the <strong>Server Pack</strong> (Server Files) - the version prepared for servers.</li>
    <li>In <strong>Wersje (MC)</strong> set the engine and version the modpack asks for.</li>
    <li>Upload the <code>.zip</code> in the file manager and <strong>extract</strong> it in the server's main folder (the "Unarchive" option next to the file).</li>
    <li>Copy the <code>mods</code> and <code>config</code> folders from the pack (if they sit in a subfolder, move them one level up).</li>
    <li>Start the server. The first start of a modpack takes longer.</li>
</ol>
<p>Some packs on newer Forge ship only a <code>run.sh</code> script instead of a <code>.jar</code> file. If yours does, message us on Discord - we'll set up the server start for you.</p>

<h2>5. How much RAM for mods</h2>
<p>A few light Fabric mods fit in 4 GB. A typical Forge modpack needs <strong>8-10 GB</strong>, and big packs with hundreds of mods need even more. If the console shows "Out of memory", the server needs a bigger plan.</p>

<h2>6. When the server won't start</h2>
<ul>
    <li><strong>"Missing mods" / "requires ..."</strong> - a dependency is missing. The console names the missing mod.</li>
    <li><strong>"Mod ... is not compatible with this version"</strong> - the mod is for another Minecraft version or another engine.</li>
    <li><strong>A player gets kicked with a list of mods when joining</strong> - the player is missing a mod or has a different version. The easiest fix is giving players the same modpack in a launcher (CurseForge, Modrinth, Prism).</li>
</ul>
HTML,
],
'de' => [
    'tytul' => 'Mods und Modpacks auf dem Minecraft-Server (Fabric, Forge)',
    'title' => 'Mods und Modpacks auf dem Minecraft-Server - Fabric, Forge | DNCT',
    'opis'  => 'Wie du die Server-Engine auf Fabric oder Forge umstellst, Mods in den mods-Ordner lädst, ein Modpack von CurseForge oder Modrinth installierst und wie viel RAM du brauchst.',
    'tresc' => <<<'HTML'
<p>Anders als Plugins verändern Mods das Spiel auch beim Spieler: neue Blöcke, Mobs, Maschinen, Dimensionen. Deshalb <strong>brauchen der Server und jeder Spieler dieselben Mods</strong> und dieselbe Spielversion.</p>

<h2>1. Engine für die Mods wählen</h2>
<p>Jeder Mod ist für eine bestimmte Engine geschrieben - das steht auf seiner Seite (Modrinth, CurseForge):</p>
<ul>
    <li><strong>Fabric</strong> - leichte Mods, schnelle Updates, die meisten Mods brauchen zusätzlich <strong>Fabric API</strong>,</li>
    <li><strong>Forge</strong> / <strong>NeoForge</strong> - große Mods und die meisten klassischen Modpacks.</li>
</ul>
<p>Fabric-Mods laufen nicht auf Forge und umgekehrt. Paper-Plugins laufen auf keinem von beiden.</p>

<h2>2. Engine auf dem Server wechseln</h2>
<ol>
    <li>Mach ein Backup im Tab <strong>Backupy</strong> (Backups).</li>
    <li>Stopp den Server.</li>
    <li>Öffne den Tab <strong>Wersje (MC)</strong> (Versionen), wähl die Engine (Fabric, Forge oder NeoForge) und <strong>genau die Minecraft-Version</strong>, für die deine Mods gemacht sind.</li>
    <li>Starte den Server einmal ohne Mods - er sollte hochfahren und den Ordner <code>mods</code> anlegen.</li>
</ol>

<h2>3. Mods hochladen</h2>
<ol>
    <li>Lade die <strong>Server</strong>- oder Universal-Version der Mods herunter. Reine Client-Mods (Minimaps, Shader, Grafik-Tweaks) bleiben bei den Spielern - auf dem Server können sie den Start verhindern.</li>
    <li>Öffne im Panel <strong>Pliki → mods</strong> (Dateien) und lade die <code>.jar</code>-Dateien hoch.</li>
    <li>Auf Fabric zusätzlich <strong>Fabric API</strong> für dieselbe Spielversion hinzufügen.</li>
    <li>Starte den Server neu und beobachte die Konsole bis zur Zeile <code>Done (...)!</code>.</li>
</ol>

<h2>4. Modpacks (CurseForge, Modrinth)</h2>
<ol>
    <li>Lade auf der Modpack-Seite das <strong>Server Pack</strong> (Server Files) herunter - die für Server vorbereitete Version.</li>
    <li>Stell in <strong>Wersje (MC)</strong> die Engine und Version ein, die das Modpack angibt.</li>
    <li>Lade das <code>.zip</code> im Dateimanager hoch und <strong>entpacke</strong> es im Hauptordner des Servers (Option „Unarchive“ bei der Datei).</li>
    <li>Kopiere die Ordner <code>mods</code> und <code>config</code> aus dem Pack (liegen sie in einem Unterordner, verschieb sie eine Ebene höher).</li>
    <li>Starte den Server. Der erste Start eines Modpacks dauert länger.</li>
</ol>
<p>Manche Packs auf neuem Forge haben statt einer <code>.jar</code>-Datei nur ein Skript <code>run.sh</code>. Ist das bei deinem Pack so, schreib uns auf Discord - wir richten den Serverstart ein.</p>

<h2>5. Wie viel RAM für Mods</h2>
<p>Ein paar leichte Fabric-Mods passen in 4 GB. Ein typisches Forge-Modpack braucht <strong>8-10 GB</strong>, große Packs mit Hunderten Mods noch mehr. Siehst du in der Konsole „Out of memory“, braucht der Server ein größeres Paket.</p>

<h2>6. Wenn der Server nicht startet</h2>
<ul>
    <li><strong>„Missing mods“ / „requires ...“</strong> - eine Abhängigkeit fehlt. Die Konsole nennt den fehlenden Mod.</li>
    <li><strong>„Mod ... is not compatible with this version“</strong> - der Mod ist für eine andere Minecraft-Version oder Engine.</li>
    <li><strong>Ein Spieler fliegt beim Verbinden mit einer Mod-Liste raus</strong> - ihm fehlt ein Mod oder er hat eine andere Version. Am einfachsten gibst du allen dasselbe Modpack im Launcher (CurseForge, Modrinth, Prism).</li>
</ul>
HTML,
],
];
