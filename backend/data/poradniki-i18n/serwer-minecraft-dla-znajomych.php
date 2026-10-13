<?php
/* Tłumaczenie poradnika /poradniki/serwer-minecraft-dla-znajomych (components/poradnik.php).
   Nazwy pakietów jak w słowniku (backend/data/i18n-slownik.php). */
declare(strict_types=1);

return [
'en' => [
    'tytul' => 'Minecraft server for friends: which plan to choose?',
    'title' => 'Minecraft server for friends: which plan to choose? | DNCT',
    'opis'  => 'Which Minecraft server to pick for your friends? Plans compared for vanilla, plugins and modpacks: how much RAM you need, what it costs and when to upgrade.',
    'tresc' => <<<'HTML'
<p><strong>Short answer:</strong> for 2-5 friends playing plain survival or with light plugins, a sensible start is <strong>Budget</strong>: 3 GB RAM, 2 vCore and 5 GB SSD for 9,99 zł per month. If the world gets bigger or you add more plugins, choose <strong>Basic</strong>: 4 GB RAM and 15 GB SSD for 15,99 zł per month.</p>

<h2>First decide how you'll play</h2>
<p>In Minecraft the number of players isn't the only factor. The same server for five people can run light on vanilla, yet put real load on the CPU and memory when everyone explores new chunks or uses mods.</p>
<ul>
    <li><strong>2-5 players, vanilla or Paper with a few plugins:</strong> Budget - 3 GB RAM, 2 vCore, 5 GB SSD, 9,99 zł/mies.</li>
    <li><strong>5-10 players, a bigger world or more plugins:</strong> Basic - 4 GB RAM, 2 vCore, 15 GB SSD, 15,99 zł/mies.</li>
    <li><strong>Forge, Fabric or NeoForge modpack:</strong> start by checking the pack's requirements. Full modpacks usually need at least 8 GB RAM, i.e. the Standard plan for 36,99 zł/mies.</li>
</ul>

<h2>What not to choose just because it's the cheapest</h2>
<p>The DEVeloper plan with 1.5 GB RAM makes sense for a short test of a small, simple world. It isn't a good default for a group that wants to build for a long time. Too little memory usually shows up as stutters while terrain generates, slow chest opening and TPS drops.</p>

<h2>Vanilla, plugins or mods?</h2>
<p><strong>Vanilla</strong> is the lightest. <strong>Plugins</strong> usually run on Paper or Purpur and load the server depending on how many there are and what they do. <strong>Mods</strong> need Fabric, Forge or NeoForge, and how heavy the pack is often matters more than how many people are online.</p>
<p>If you haven't picked a pack yet, start with vanilla or light plugins. Once you choose a modpack, compare its recommendations with the RAM in your plan. Back up the world before switching the engine or doing a bigger update.</p>

<h2>What if you need more power later?</h2>
<p>You don't have to buy the biggest plan from day one. Start with a plan that fits how you actually play, watch the console and resource usage, and when the world and the player count grow - change the plan in the panel. Make a backup before changing the game version, engine or plan.</p>

<p>Once you've picked a plan, see <a href="/hosting-minecraft">Minecraft hosting</a>. If you need help with the setup, read the guide <a href="/poradniki/mody-na-serwer-minecraft">on mods and modpacks</a> or <a href="/poradniki/pluginy-na-serwer-minecraft">on Paper plugins</a>.</p>
HTML,
],
'de' => [
    'tytul' => 'Minecraft-Server für Freunde: welches Paket?',
    'title' => 'Minecraft-Server für Freunde: welches Paket? | DNCT',
    'opis'  => 'Welchen Minecraft-Server für deine Freunde? Pakete im Vergleich für Vanilla, Plugins und Modpacks: wie viel RAM du brauchst, was es kostet und wann du upgraden solltest.',
    'tresc' => <<<'HTML'
<p><strong>Kurz gesagt:</strong> für 2-5 Freunde mit normalem Survival oder leichten Plugins ist <strong>Budget</strong> ein guter Start: 3 GB RAM, 2 vCore und 5 GB SSD für 9,99 zł im Monat. Wird die Welt größer oder kommen mehr Plugins dazu, nimm <strong>Basis</strong>: 4 GB RAM und 15 GB SSD für 15,99 zł im Monat.</p>

<h2>Erst klären, wie ihr spielt</h2>
<p>In Minecraft ist die Spielerzahl nicht der einzige Faktor. Derselbe Server für fünf Leute läuft mit Vanilla locker, kann CPU und Speicher aber stark belasten, wenn alle neue Chunks erkunden oder Mods nutzen.</p>
<ul>
    <li><strong>2-5 Spieler, Vanilla oder Paper mit ein paar Plugins:</strong> Budget - 3 GB RAM, 2 vCore, 5 GB SSD, 9,99 zł/mies.</li>
    <li><strong>5-10 Spieler, größere Welt oder mehr Plugins:</strong> Basis - 4 GB RAM, 2 vCore, 15 GB SSD, 15,99 zł/mies.</li>
    <li><strong>Modpack mit Forge, Fabric oder NeoForge:</strong> prüf zuerst die Anforderungen des Packs. Volle Modpacks brauchen meist mindestens 8 GB RAM, also das Paket Standard für 36,99 zł/mies.</li>
</ul>

<h2>Was du nicht nur nehmen solltest, weil es am billigsten ist</h2>
<p>Das Paket DEVeloper mit 1,5 GB RAM taugt für einen kurzen Test einer kleinen, einfachen Welt. Für eine Gruppe, die lange bauen will, ist es keine gute Standardwahl. Zu wenig Speicher merkt man meist an Rucklern beim Generieren des Geländes, langsam öffnenden Kisten und TPS-Einbrüchen.</p>

<h2>Vanilla, Plugins oder Mods?</h2>
<p><strong>Vanilla</strong> ist am leichtesten. <strong>Plugins</strong> laufen meist auf Paper oder Purpur und belasten den Server je nach Anzahl und Funktion. <strong>Mods</strong> brauchen Fabric, Forge oder NeoForge, und wie schwer das Pack ist, zählt oft mehr als die Zahl der Spieler online.</p>
<p>Wenn ihr noch kein Pack ausgesucht habt, fangt mit Vanilla oder leichten Plugins an. Habt ihr ein Modpack gewählt, vergleich seine Empfehlungen mit dem RAM im Paket. Mach vor einem Engine-Wechsel oder größeren Update ein Backup der Welt.</p>

<h2>Und wenn später mehr Leistung nötig ist?</h2>
<p>Du musst nicht gleich das größte Paket kaufen. Fang mit einem Paket an, das zu eurer tatsächlichen Spielweise passt, beobachte Konsole und Auslastung, und wenn Welt und Spielerzahl wachsen - wechsle das Paket im Panel. Mach vor dem Wechsel von Spielversion, Engine oder Paket ein Backup.</p>

<p>Hast du ein Paket gewählt, schau dir das <a href="/hosting-minecraft">Minecraft-Hosting</a> an. Brauchst du Hilfe bei der Einrichtung, lies die Anleitung <a href="/poradniki/mody-na-serwer-minecraft">zu Mods und Modpacks</a> oder <a href="/poradniki/pluginy-na-serwer-minecraft">zu Paper-Plugins</a>.</p>
HTML,
],
];
