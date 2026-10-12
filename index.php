<?php
/**
 * Strona główna. Treść przeniesiona z beta/index.php i dostosowana:
 *
 *   - plany są wyświetlane, ale WYMUSZONO na nich status "wyprzedane" -
 *     nowa strona nie przyjmuje jeszcze zamówień, a modal zamówień i Stripe
 *     świadomie nie zostały tu przeniesione (żyją na razie w beta/),
 *   - karta Discorda dostała widget serwera zamiast samego odnośnika,
 *   - doszedł przycisk demo, prowadzący do panelu na koncie poglądowym.
 *
 * Układ, navbar, stopkę i tło dokłada assets/clear/clear.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$isLoggedIn = isset($_SESSION['authentik_username']);
$username   = $_SESSION['authentik_username'] ?? null;

$panelUrl = (string)env('PANEL_SSO_URL', 'https://panel.dnct.pl/extensions/vikingsso/auth/auto');
$demoUrl  = (string)env('DEMO_LOGIN_URL', '');

// ------------------------------------------------------------
// Plany pokazowe. hosting_plans mieszka w bazie Pterodactyla (DB_*),
// czytamy ją tylko do odczytu. Błąd bazy nie może położyć strony głównej -
// w najgorszym razie sekcja cennika po prostu się nie pokaże.
// ------------------------------------------------------------
$showcasePlans = [];
$heroGames     = [];
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
        (string)env('DB_USERNAME'),
        (string)env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $showcasePlans = $pdo
        ->query('SELECT * FROM hosting_plans WHERE id IN (1, 3, 9) AND is_active = 1 ORDER BY price ASC')
        ->fetchAll();

    /* Dostępność z realnej pojemności węzła klienckiego - ta sama funkcja, co
       na cenniku. Wcześniej karty miały tu wymuszone `sold_out => true`, przez
       co strona główna pokazywała wszystko jako wyprzedane, a cennik jako
       dostępne. Dwie strony tego samego serwisu przeczyły sobie nawzajem. */
    $capacity      = node_capacity($pdo);
    $showcasePlans = annotate_plan_availability($showcasePlans, $capacity);

    /* Chipy gier w hero: "od X zł" z najtańszego aktywnego planu albo progu
       slotów. Gra bez żadnej ceny (np. dodana do hosting_games, ale jeszcze
       bez oferty) w hero się nie pokazuje - chip bez ceny to obietnica. */
    $heroGames = $pdo->query(
        'SELECT g.type, g.name,
                COALESCE(
                    (SELECT MIN(p.price) FROM hosting_plans p WHERE p.type = g.type AND p.is_active = 1),
                    (SELECT MIN(t.price) FROM hosting_slot_tiers t WHERE t.type = g.type AND t.is_active = 1)
                ) AS price_from
           FROM hosting_games g
          WHERE g.is_active = 1
          ORDER BY g.id'
    )->fetchAll();
    $heroGames = array_values(array_filter($heroGames, static fn(array $g): bool => $g['price_from'] !== null));

    /* Gry slotowe (Zomboid, Vintage Story) celowo NIE trafiają na stronę
       główną - tu stoją trzy plany Minecrafta, a pozostałe gry są na /cennik
       (link "Zobacz pełną ofertę" pod kartami). Dawniej doklejaliśmy tu
       najtańszy próg każdej gry i po włączeniu sprzedaży Zomboida (07.10.2026)
       w siatce wisiała jedna samotna karta. */
} catch (Throwable $e) {
    error_log('index: nie udalo sie pobrac planow: ' . $e->getMessage());
}

// ------------------------------------------------------------
// Widget Discorda - avatary i status "na żywo" jako dowód aktywnej
// społeczności (PRODUCT.md: "najmocniejszy dowód żywej społeczności").
// discord.com NIE wysyła Access-Control-Allow-Origin na tym endpoincie,
// więc fetch po stronie przeglądarki zablokowałby CORS - stąd pobranie
// po stronie serwera, z krótkim timeoutem i cache na dysku (ten sam
// wzorzec co OIDC discovery w backend/lib/oidc.php), żeby wolny/martwy
// Discord nigdy nie spowalniał całej strony głównej.
// ------------------------------------------------------------
$discordWidget = null;
try {
    $discordGuildId = '1351197915942621334';
    $cacheFile = sys_get_temp_dir() . '/dnct_discord_widget_' . $discordGuildId . '.json';
    $maxAge = 120; // 2 min - Discord sam sugeruje max-age=300 w swojej odpowiedzi

    if (is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $maxAge) {
        $discordWidget = json_decode((string)@file_get_contents($cacheFile), true);
    }

    if (!is_array($discordWidget) || !isset($discordWidget['members'])) {
        $ch = curl_init("https://discord.com/api/guilds/{$discordGuildId}/widget.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $fresh = $code === 200 ? json_decode((string)$raw, true) : null;
        if (is_array($fresh) && isset($fresh['members'])) {
            $discordWidget = $fresh;
            @file_put_contents($cacheFile, json_encode($fresh));
        } elseif (is_readable($cacheFile)) {
            // Discord chwilowo niedostępny - lepszy przeterminowany cache niż nic.
            $discordWidget = json_decode((string)@file_get_contents($cacheFile), true);
        }
    }
} catch (Throwable $e) {
    error_log('index: nie udalo sie pobrac widgetu Discord: ' . $e->getMessage());
    $discordWidget = null;
}

require_once __DIR__ . '/components/plan-card-cennik.php';
require_once __DIR__ . '/components/panel-band.php';
require_once __DIR__ . '/components/jak-to-dziala.php';

$pageTitle       = 'DNCT Hosting - serwery Minecraft i Project Zomboid';
$metaDescription = 'Polski hosting serwerów Minecraft, Project Zomboid i Vintage Story z ochroną DDoS. Serwer startuje zaraz po płatności, od 3,99 zł za tydzień.';
$canonicalUrl    = 'https://game.dnct.pl/';
$centerContent   = false; // strona wielosekcyjna, nie centrujemy pionowo

ob_start();
?>
<?php /* Style pasma "Zobacz, zanim zapłacisz" mieszkają w cennik.css. */ ?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<?php /* Układ hero, "O hostingu" i chipów gier: assets/css/home.css (zwykły CSS,
         bez nowych klas Tailwinda - nie wymaga przebudowy tailwind.css).
         Konsola w karcie i przechył: assets/js/home.js. */ ?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/home.css'); ?>">

<!-- ============ HERO ============ -->
<?php
$heroGameUrl  = ['minecraft' => '/hosting-minecraft', 'zomboid' => '/hosting-project-zomboid', 'vintagestory' => '/hosting-vintage-story'];
// Awatary: przycięte i zmniejszone (96 px WebP, ~5 KB) ikony gier
// z media/Server_card/icons - oryginały mają 100-300 KB i duży margines.
$heroGameIcon = ['minecraft' => '/assets/media/icons/gry/minecraft.webp', 'zomboid' => '/assets/media/icons/gry/zomboid.webp', 'vintagestory' => '/assets/media/icons/gry/vintagestory.webp'];
?>
<section class="home-hero">
    <div class="home-hero-glow" aria-hidden="true"></div>
    <div class="home-wrap home-hero-grid">
        <div class="home-hero-copy">
            <h1 class="home-h1">
                <?php if ($isLoggedIn): ?>
                    <span class="home-line"><span style="--d:1">Cześć, <?php echo htmlspecialchars((string)$username); ?>!</span></span>
                    <span class="home-line"><span class="gradient-text" style="--d:2">Gotowy na grę?</span></span>
                <?php else: ?>
                    <span class="home-line"><span style="--d:1">Twoja przygoda</span></span>
                    <span class="home-line"><span style="--d:2">zaczyna się</span></span>
                    <span class="home-line"><span class="gradient-text" style="--d:3">bez opóźnień.</span></span>
                <?php endif; ?>
            </h1>

            <p class="home-lead home-in" style="--d:4">
                Serwery Minecraft, Project Zomboid i Vintage Story.
                Ochrona DDoS, szybkie dyski SSD i wsparcie na Discordzie.
            </p>

            <div class="home-cta home-in" style="--d:5">
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="home-btn home-btn-main bg-gradient-main">
                        Przejdź do Panelu!
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                <?php else: ?>
                    <a href="/cennik" class="home-btn home-btn-main bg-gradient-main">
                        Sprawdź ofertę
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <?php /* Zalogowanemu nie dajemy demo: panel trzyma jedną sesję na
                             przeglądarkę i bilet demo wyrzuciłby go z własnego konta
                             (to samo robi components/panel-band.php). */ ?>
                    <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener" class="home-btn glass">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                        Discord
                    </a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($demoUrl !== '' ? $demoUrl : '/demo'); ?>" class="home-btn glass">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="m7 9 3 2-3 2M12 13h4M8 21h8"/></svg>
                        Demo panelu
                    </a>
                    <a href="https://panel.dnct.pl" class="home-btn glass">Panel</a>
                <?php endif; ?>
            </div>

            <?php if ($heroGames): ?>
            <div class="home-chips home-in" style="--d:6">
                <?php foreach ($heroGames as $g):
                    $type = (string)$g['type'];
                    $icon = $heroGameIcon[$type] ?? null;
                ?>
                    <a class="home-chip" data-g="<?php echo htmlspecialchars($type); ?>" href="<?php echo htmlspecialchars($heroGameUrl[$type] ?? '/cennik'); ?>">
                        <?php if ($icon): ?>
                            <img src="<?php echo asset($icon); ?>" alt="" width="40" height="40" decoding="async">
                        <?php else: ?>
                            <b><?php echo htmlspecialchars(mb_strtoupper(mb_substr((string)$g['name'], 0, 2))); ?></b>
                        <?php endif; ?>
                        <?php echo htmlspecialchars((string)$g['name']); ?> · od <?php echo number_format((float)$g['price_from'], 2, ',', ''); ?> zł
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php /* Karta serwera: poglądowa (te same dane co scenka "Grasz"
                 w "Jak to działa"). Liczby nie udają statystyk prawdziwego
                 serwera - to ilustracja tego, co klient zobaczy w panelu. */ ?>
        <div class="home-card glass home-in" style="--d:3" data-home-tilt aria-hidden="true">
            <div class="home-card-top">
                <strong>Twój serwer</strong>
                <span class="home-card-addr">twojserwer.dnct.pl</span>
                <span class="home-card-on"><i></i>Online</span>
            </div>
            <div class="home-card-stats">
                <div><span>CPU</span><b>38%</b><span class="home-bar"><i style="--v:38%"></i></span></div>
                <div><span>RAM</span><b>3,1 / 8 GB</b><span class="home-bar"><i style="--v:39%"></i></span></div>
                <div><span>Gracze</span><b>7 / 20</b><span class="home-bar"><i style="--v:35%"></i></span></div>
            </div>
            <div class="home-card-console" data-home-console></div>
            <div class="home-card-input"><b>›</b> wpisz komendę...</div>
        </div>
    </div>
</section>

<!-- ============ ZOBACZ, ZANIM ZAPŁACISZ ============ -->
<?php /* Zaraz pod hero, PRZED opisem hostingu i przed planami. Odwiedzający
         trafia tu z reklamy albo z wyszukiwarki i nie zna nas - najpierw
         potrzebuje powodu, żeby uwierzyć, że panel jest dobry, a dopiero potem
         cennika. Odwrotna kolejność każe mu ocenić cenę czegoś, czego jeszcze
         nie widział.

         Pasmo jest tym samym komponentem co w cenniku (components/panel-band.php),
         a wygląd bierze z cennik.css - stąd klasa .cennik-page na opakowaniu.
         Kopia markupu rozjechałaby się z oryginałem przy pierwszej poprawce. */ ?>
<section class="px-6 home-wide mx-auto w-full cennik-page">
    <?php render_panel_band(); ?>
<script src="<?php echo asset('/assets/js/panel-band.js'); ?>" defer></script>
</section>

<!-- ============ JAK TO DZIAŁA ============ -->
<?php /* Zaraz po "Zobacz, zanim zapłacisz": panel już widział, teraz dostaje
         odpowiedź na "a co się dzieje po zapłacie". Klasa .cennik-page daje
         wspólne .cnk-eyebrow i .cnk-h2. */ ?>
<section class="px-6 home-wide mx-auto w-full cennik-page jtd-wrap">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/jak-to-dziala.css'); ?>">
    <?php render_jak_to_dziala(); ?>
    <script src="<?php echo asset('/assets/js/jak-to-dziala.js'); ?>" defer></script>
</section>

<!-- ============ O HOSTINGU (treść pod wyszukiwarki) ============ -->
<?php /* Bento: opis po lewej, "Własny sprzęt" po prawej, cztery cechy pod
         spodem. Tekst opisu bez zmian - to treść pod wyszukiwarki. */ ?>
<section id="hosting" class="home-about">
    <div class="home-wrap home-bento">
        <div class="home-bento-intro glass home-reveal">
            <span class="home-eyebrow">Plany dla Minecraft, Project Zomboid i Vintage Story</span>
            <h2>Hosting serwerów Minecraft, Project Zomboid i Vintage Story</h2>
            <p>
                DNCT Hosting to serwery dla Minecraft, Project Zomboid i Vintage Story,
                od 3,99 zł za tydzień - stabilne CPU/RAM, szybkie dyski SSD i pełna kontrola.
            </p>
            <p>
                Serwery stoją na naszym własnym sprzęcie (Proxmox), nie na odsprzedanych
                zasobach - stąd realna dostępność, nie tylko deklaracja. Do tego polska
                infrastruktura, ochrona DDoS, kopie zapasowe w panelu i wsparcie przez Discord.
            </p>
        </div>

        <div class="home-bento-hw glass home-reveal" style="--d:1">
            <p class="home-hw-big">Własny sprzęt.<small>Proxmox w Polsce, nie odsprzedane zasoby - dlatego „Wyprzedane” w cenniku znaczy naprawdę wyprzedane.</small></p>
            <div class="home-rack" aria-hidden="true">
                <i style="--k:0"></i><i style="--k:1"></i><i style="--k:2"></i><i style="--k:3"></i><i style="--k:4"></i>
            </div>
        </div>

        <?php foreach ([
            ['Ochrona DDoS i stały monitoring serwerów', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
            ['Dyski SSD, nowoczesne CPU, stabilne zasoby', '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3"/>'],
            ['Backupy i szybkie przywrócenie danych', '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>'],
            ['Wsparcie dla modów i konfiguracji serwerów', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'],
        ] as $k => [$feature, $icon]): ?>
            <div class="home-bento-feat glass home-reveal" style="--d:<?php echo $k; ?>">
                <span class="home-feat-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon; ?></svg></span>
                <p><?php echo htmlspecialchars($feature); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ PLANY ============ -->
<?php if ($showcasePlans): ?>
<section id="pricing" class="cennik-page py-20 px-6 home-wide mx-auto w-full">
    <div class="text-center mb-16">
        <h2 class="text-3xl font-bold mb-4">Wybierz plan hostingu gier</h2>
        <p class="text-gray-400 text-sm md:text-base mb-4">Hosting <a href="/hosting-minecraft" class="text-purple-400 hover:text-purple-300">Minecraft</a>, <a href="/hosting-project-zomboid" class="text-purple-400 hover:text-purple-300">Project Zomboid</a> i <a href="/hosting-vintage-story" class="text-purple-400 hover:text-purple-300">Vintage Story</a></p>
        <div class="w-24 h-1 bg-gradient-main mx-auto rounded-full"></div>
    </div>

    <?php /* Ta sama karta (components/plan-card-cennik.php) i ten sam wrapper
             .plan-pick co w cenniku - hover/lift/obwodka liczone sa wylacznie
             wzgledem .plan-pick w cennik.css. Bez tej klasy sekcja i tak
             dostaje style (.cennik-page dodane do <section> wyzej), ale karty
             musza byc w tym samym wrapperze co tam, zeby wygladaly identycznie,
             nie tylko "podobnie". Bez interaktywnego wyboru planu (data-plan-id,
             role="button") - tu karta linkuje wprost do /cennik, nie zaznacza. */ ?>
    <div class="plans-grid gap-8 items-stretch">
        <?php foreach ($showcasePlans as $row):
            $ramMb = (int)($row['ram_mb'] ?? 0);
            $ssdMb = (int)($row['ssd_mb'] ?? 0);
        ?>
            <div class="plan-pick">
                <?php render_cennik_plan_card([
                    'name'       => $row['name'] ?? '',
                    'price'      => $row['price'] ?? '',
                    'old_price'  => $row['old_price'] ?? 0,
                    'is_on_sale' => !empty($row['is_on_sale']),
                    'popular'    => !empty($row['popular']),
                    // Stan liczony z pojemności węzła (backend/lib/capacity.php),
                    // a nie wymuszony na sztywno.
                    'sold_out'   => !empty($row['sold_out']),
                    'fits'       => $row['fits'] ?? null,
                    'ram'        => $ramMb >= 1024 ? ($ramMb / 1024) . 'GB' : $ramMb . 'MB',
                    'cpu'        => ($row['cpu_cores'] ?? 0) . ' vCore',
                    'ssd'        => $ssdMb >= 1024 ? ($ssdMb / 1024) . 'GB' : $ssdMb . 'MB',
                    'backups'    => $row['backups_limit'] ?? 0,
                    'bg'         => $row['background_img'] ?? '',
                    'icon'       => $row['icon_img'] ?? '',
                    // Karta na stronie glownej prowadzi do pelnego cennika,
                    // nie zamawia bezposrednio - stad wlasny href/cta zamiast
                    // domyslnego "Zamawiam" z komponentu.
                    'href'       => '/cennik',
                    'cta'        => 'Zobacz szczegóły',
                ]); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-12 text-center">
        <a href="/cennik" class="text-gray-400 hover:text-white underline decoration-[#8b5cf6] decoration-2 underline-offset-4 transition-all">
            Zobacz pełną ofertę (więcej gier) &rarr;
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ============ DISCORD ============ -->
<?php
/* Zamiast osadzonego iframe'a Discorda (obcy, blurple wygląd, generyczny
   na każdej stronie, która go wstawi) - własny, ostatni akcent strony:
   prawdziwe avatary i status graczy pobrane po stronie serwera (patrz
   $discordWidget wyżej), w tej samej kolorystyce statusów co panel
   powiadomień (zielony/bursztyn/czerwień). To ostatnia rzecz, którą
   widzi odwiedzający przed stopką - ma zostawić wrażenie żywej, a nie
   wypożyczonej społeczności. */
$discordStatusColor = ['online' => '#22c55e', 'idle' => '#fbbf24', 'dnd' => '#ef4444'];
$discordMembers = is_array($discordWidget['members'] ?? null) ? $discordWidget['members'] : [];
$discordShown   = array_slice($discordMembers, 0, 12);
$discordExtra   = max(0, count($discordMembers) - count($discordShown));
$discordOnline  = (int)($discordWidget['presence_count'] ?? count($discordMembers));
?>
<?php /* Opinie z Google - znika sama, gdy nie ma klucza albo opinii. */ require __DIR__ . '/components/opinie-google.php'; ?>
<section class="discord-band py-24 px-6 w-full relative overflow-hidden">
    <div class="discord-band-glow" aria-hidden="true"></div>
    <div class="home-wide mx-auto relative z-10">
        <div class="discord-panel glass p-8 md:p-14 rounded-[2rem]" data-reveal>
            <div class="grid grid-cols-1 md:grid-cols-[1.1fr_0.9fr] gap-10 md:gap-14 items-center">
                <div class="text-center md:text-left">
                    <span class="discord-eyebrow">Społeczność</span>
                    <h2 class="discord-heading">Dołącz do rozmowy</h2>
                    <p class="text-gray-400 mt-4 mb-8 text-sm md:text-base max-w-md mx-auto md:mx-0">
                        Masz pytania? Potrzebujesz pomocy technicznej? Jesteśmy na Discordzie -
                        zwykle odpisujemy w kilka minut.
                    </p>
                    <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-3 bg-[#5865F2] px-6 md:px-8 py-4 rounded-xl font-bold hover:scale-105 hover:brightness-110 transition-all shadow-xl shadow-indigo-500/20 w-full md:w-auto justify-center">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                        </svg>
                        Otwórz zaproszenie
                    </a>
                </div>

                <?php if ($discordShown): ?>
                <?php
                /* Orbita: licznik w środku, avatary na dwóch pierścieniach,
                   które obracają się bardzo wolno w przeciwne strony. Kąty
                   liczymy tu, CSS tylko je obraca (--a, pierścień w klasie). */
                $orbitInner = array_slice($discordShown, 0, 5);
                $orbitOuter = array_slice($discordShown, 5);
                ?>
                <div class="discord-live">
                    <div class="discord-orbit">
                        <?php foreach (['inner' => $orbitInner, 'outer' => $orbitOuter] as $ringName => $ringMembers):
                            if (!$ringMembers) continue;
                            $n = count($ringMembers);
                            $offset = $ringName === 'outer' ? 360 / $n / 2 : -90;
                        ?>
                        <div class="discord-orbit-ring is-<?php echo $ringName; ?>" aria-hidden="true">
                            <?php foreach ($ringMembers as $k => $m):
                                $status = in_array($m['status'] ?? '', ['online', 'idle', 'dnd'], true) ? $m['status'] : 'online';
                                $name   = (string)($m['username'] ?? 'gracz');
                                $activity = !empty($m['game']['name']) ? ' - gra w ' . $m['game']['name'] : '';
                                $i = $ringName === 'outer' ? $k + 5 : $k;
                            ?>
                            <span class="discord-orbit-item" style="--a:<?php echo round($offset + 360 / $n * $k, 2); ?>deg">
                                <span class="discord-orbit-face">
                                    <img class="discord-avatar" style="--i:<?php echo $i; ?>; --ring:<?php echo $discordStatusColor[$status]; ?>"
                                         src="<?php echo htmlspecialchars((string)($m['avatar_url'] ?? '')); ?>"
                                         alt="" title="<?php echo htmlspecialchars($name . $activity); ?>"
                                         width="44" height="44" loading="lazy" decoding="async">
                                </span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>

                        <div class="discord-orbit-core">
                            <span class="discord-live-number" data-count-target="<?php echo $discordOnline; ?>">0</span>
                            <span class="discord-live-label"><span class="discord-live-dot" aria-hidden="true"></span>na żywo teraz</span>
                            <?php if ($discordExtra > 0): ?>
                            <span class="discord-orbit-more">+<?php echo $discordExtra; ?> więcej</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="discord-live discord-live-fallback">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                    <p>Porozmawiaj z nami na Discordzie</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const panel = document.querySelector('.discord-band [data-reveal]');
    const counter = document.querySelector('.discord-live-number');
    if (!panel) return;

    function countUp() {
        if (!counter) return;
        const target = parseInt(counter.dataset.countTarget || '0', 10);
        if (reduceMotion || !target) {
            counter.textContent = String(target);
            return;
        }
        const start = performance.now();
        const dur = 900;
        function tick(now) {
            const p = Math.min(1, (now - start) / dur);
            const eased = 1 - Math.pow(1 - p, 3);
            counter.textContent = String(Math.round(target * eased));
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    if (reduceMotion) {
        panel.classList.add('is-revealed');
        countUp();
        return;
    }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            panel.classList.add('is-revealed');
            countUp();
            io.disconnect();
        });
    }, { threshold: 0.35 });
    io.observe(panel);

    /* "Mówi": co 1,5-2,5 s od jednego losowego avatara rozchodzi się zielona
       fala na ~1,1 s (CSS .discord-orbit-item.is-speaking). Tylko gdy pasmo jest na ekranie i karta
       widoczna - poza ekranem pętla stoi. Nigdy dwa razy z rzędu ten sam. */
    const avatars = Array.from(panel.querySelectorAll('.discord-orbit-item'));
    if (avatars.length < 2) return;
    let speakTimer = null, last = -1, onScreen = false;

    function speak() {
        clearTimeout(speakTimer);
        if (!onScreen || document.hidden) return;
        let i = Math.floor(Math.random() * avatars.length);
        if (i === last) i = (i + 1) % avatars.length;
        last = i;
        avatars[i].classList.add('is-speaking');
        setTimeout(() => avatars[i].classList.remove('is-speaking'), 1100);
        speakTimer = setTimeout(speak, 1500 + Math.random() * 1000);
    }

    new IntersectionObserver((entries) => {
        onScreen = entries[0].isIntersecting;
        if (onScreen) speakTimer = setTimeout(speak, 1400); else clearTimeout(speakTimer);
    }, { threshold: 0.2 }).observe(panel);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && onScreen) speak(); });
})();
</script>

<?php /* Ten sam skrypt co na cenniku - stąd guard w initPlanPicks() dla kart
         bez data-plan-id (karty-wizytówki tutaj nie zaznaczają się, tylko
         linkują do /cennik) i ten sam initChromaCards() dla kart i konsoli. */ ?>
<script src="<?php echo asset('/assets/js/cennik.js'); ?>" defer></script>
<script src="<?php echo asset('/assets/js/home.js'); ?>" defer></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
