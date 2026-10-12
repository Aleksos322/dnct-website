<?php
/**
 * Lista poradników (/poradniki/...) - jedno źródło dla spisu /poradniki/
 * i podpowiedzi pod zamówieniem (zamowienie.php pokazuje poradniki do gry,
 * którą klient właśnie kupuje). Nowy poradnik = nowy wpis tutaj ORAZ
 * w PORADNIKI w components/poradnik.php (tam linkowanie między poradnikami).
 *
 * [slug, gra (hosting_games.type), tytuł, opis]
 */

declare(strict_types=1);

return [
    ['jak-postawic-serwer-minecraft', 'minecraft', 'Jak postawić serwer Minecraft', 'Ile RAM-u, Paper czy Forge, pierwsze uruchomienie, server.properties i komendy.'],
    ['pluginy-na-serwer-minecraft', 'minecraft', 'Jak dodać pluginy na serwer Minecraft (Paper)', 'Skąd brać pluginy, wgrywanie do folderu plugins, config.yml i typowe błędy.'],
    ['mody-na-serwer-minecraft', 'minecraft', 'Mody i modpacki na serwer Minecraft (Fabric, Forge)', 'Zmiana silnika, folder mods, modpacki z CurseForge i Modrinth.'],
    ['jak-wgrac-swiat-na-serwer-minecraft', 'minecraft', 'Jak wgrać własny świat na serwer Minecraft', 'Świat z gry jednoosobowej na serwer: pakowanie, wgrywanie, level-name, Nether i End.'],
    ['serwer-minecraft-dla-znajomych', 'minecraft', 'Serwer Minecraft dla znajomych: jaki plan wybrać?', 'Konkretny dobór RAM-u i planu dla vanilla, pluginów oraz modpacków.'],
    ['jak-postawic-serwer-project-zomboid', 'zomboid', 'Jak postawić serwer Project Zomboid (Build 42)', 'Ile RAM-u potrzeba, pierwsze uruchomienie, łączenie przez IP i port, komendy admina.'],
    ['mody-na-serwer-project-zomboid', 'zomboid', 'Jak dodać mody ze Steam Workshop na serwer Zomboid', 'Workshop ID i Mod ID, kolejność bibliotek, co zrobić, gdy serwer nie startuje.'],
    ['ustawienia-serwera-project-zomboid', 'zomboid', 'Ustawienia serwera Zomboid: hasło, PvP, gracze, kopie', 'ProjectZomboid.ini i SandboxVars - najważniejsze opcje z wyjaśnieniem.'],
    ['zapis-zomboid-na-serwer', 'zomboid', 'Jak przenieść zapis Project Zomboid na serwer', 'Świat z gry jednoosobowej na serwer: co przechodzi, mody i podmiana zapisu.'],
    ['serwer-project-zomboid-dla-znajomych', 'zomboid', 'Serwer Project Zomboid dla znajomych: koszt i dobór', 'Ile kosztuje prywatny świat, ile RAM-u potrzeba i kiedy wybrać większy pakiet.'],
    ['jak-postawic-serwer-vintage-story', 'vintagestory', 'Jak postawić serwer Vintage Story', 'RAM, pierwszy start, łączenie, komenda /op i serverconfig.json.'],
    ['mody-na-serwer-vintage-story', 'vintagestory', 'Jak dodać mody na serwer Vintage Story', 'Mody z ModDB w folderze data/Mods, mody u graczy i typowe błędy.'],
    ['serwer-vintage-story-dla-znajomych', 'vintagestory', 'Serwer Vintage Story dla znajomych: jaki plan wybrać?', 'Dobór pakietu dla małej osady, modów i większej ekipy.'],
];
