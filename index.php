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
        /* Wszystkie aktywne plany Minecrafta (od 12.10.2026) - sekcja przewija je
           w 3D (assets/js/home.js), więc pełna lista ma sens; wcześniej stały
           tu trzy wybrane: id 1, 3 i 9. */
        ->query("SELECT * FROM hosting_plans WHERE type = 'minecraft' AND is_active = 1 ORDER BY price ASC")
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

<!-- ============ PLANY ============ -->
<?php /* PLANY W 3D (12.10.2026). Sekcja przypina się do ekranu (sticky), a
         przewijanie obraca karuzelą: aktywny plan z przodu, sąsiednie
         odsunięte w głąb i obrócone. Długość przewijania rośnie z liczbą
         planów (--n). Pozycje liczy assets/js/home.js; bez JS i przy
         reduced-motion sekcja jest zwykłym poziomym rzędem do przesuwania.

         Karta ta sama co w cenniku (components/plan-card-cennik.php) w tym
         samym wrapperze .plan-pick - wygląd zostaje 1:1 z cennikiem. */ ?>
<?php if ($showcasePlans): ?>
<section id="pricing" class="cennik-page home-p3d" data-plans3d style="--n:<?php echo count($showcasePlans); ?>">
    <div class="home-p3d-sticky">
        <div class="home-wrap home-p3d-grid">
            <div class="home-p3d-copy">
                <h2>Wybierz plan hostingu gier</h2>
                <p class="home-p3d-sub">Hosting <a href="/hosting-minecraft">Minecraft</a>, <a href="/hosting-project-zomboid">Project Zomboid</a> i <a href="/hosting-vintage-story">Vintage Story</a></p>

                <div class="home-p3d-count" aria-live="polite">
                    <b data-p3d-now>01</b><span>/ <?php echo str_pad((string)count($showcasePlans), 2, '0', STR_PAD_LEFT); ?></span>
                </div>
                <ol class="home-p3d-index">
                    <?php foreach ($showcasePlans as $k => $row): ?>
                        <li><button type="button" data-p3d-go="<?php echo $k; ?>"<?php echo !empty($row['sold_out']) ? ' class="is-out"' : ''; ?>>
                            <span<?php echo str_ends_with(trim((string)($row['name'] ?? '')), '+') ? ' class="is-legend"' : ''; ?>><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></span>
                            <em><?php echo number_format((float)($row['price'] ?? 0), 2, ',', ''); ?> zł</em>
                        </button></li>
                    <?php endforeach; ?>
                </ol>
                <p class="home-p3d-hint" aria-hidden="true">Przewiń, żeby obejrzeć kolejne plany</p>
                <a href="/cennik" class="home-p3d-more">Zobacz pełną ofertę (więcej gier) &rarr;</a>
                <?php /* Pominięcie sekcji: przypięta karuzela zajmuje kilka ekranów
                         przewijania, a nie każdy chce oglądać wszystkie plany. */ ?>
                <button type="button" class="home-p3d-skip" data-p3d-skip>
                    <span class="home-p3d-skip-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6"/></svg></span>
                    Pomiń plany
                </button>
            </div>

            <div class="home-p3d-stage">
                <?php foreach ($showcasePlans as $k => $row):
                    $ramMb = (int)($row['ram_mb'] ?? 0);
                    $ssdMb = (int)($row['ssd_mb'] ?? 0);
                ?>
                    <div class="home-p3d-card" data-p3d="<?php echo $k; ?>">
                        <div class="plan-pick">
                            <?php render_cennik_plan_card([
                                'name'       => $row['name'] ?? '',
                                'price'      => $row['price'] ?? '',
                                'old_price'  => $row['old_price'] ?? 0,
                                'is_on_sale' => !empty($row['is_on_sale']),
                                'popular'    => !empty($row['popular']),
                                // Stan liczony z pojemności węzła (backend/lib/capacity.php).
                                'sold_out'   => !empty($row['sold_out']),
                                'fits'       => $row['fits'] ?? null,
                                'ram'        => $ramMb >= 1024 ? ($ramMb / 1024) . 'GB' : $ramMb . 'MB',
                                'cpu'        => ($row['cpu_cores'] ?? 0) . ' vCore',
                                'ssd'        => $ssdMb >= 1024 ? ($ssdMb / 1024) . 'GB' : $ssdMb . 'MB',
                                'backups'    => $row['backups_limit'] ?? 0,
                                'bg'         => $row['background_img'] ?? '',
                                'icon'       => $row['icon_img'] ?? '',
                                // Karta na stronie głównej prowadzi do pełnego cennika.
                                'href'       => '/cennik',
                                'cta'        => 'Zobacz szczegóły',
                            ]); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php /* Opinie widoczne RAZEM z kartami przez całe przewijanie planów -
                 kompaktowy wariant komponentu (znika sam, gdy nie ma opinii). */ ?>
        <div class="home-wrap home-p3d-opinie">
            <?php $opinieWariant = 'pasek'; require __DIR__ . '/components/opinie-google.php'; ?>
        </div>
    </div>
</section>
<?php endif; ?>

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
                Serwery stoją na naszym własnym sprzęcie, nie na odsprzedanych
                zasobach - stąd realna dostępność, nie tylko deklaracja. Do tego polska
                infrastruktura, ochrona DDoS, kopie zapasowe w panelu i wsparcie przez Discord.
            </p>
        </div>

        <div class="home-bento-hw glass home-reveal" style="--d:1">
            <p class="home-hw-big">Własny sprzęt.<small>Serwery w Polsce, nie odsprzedane zasoby - dlatego „Wyprzedane” w cenniku znaczy naprawdę wyprzedane.</small></p>
            <?php /* Miniaturowa szafa serwerowa (13.10.2026). Po wejściu w kadr
                     serwery wsuwają się po kolei i "bootują" (diody zasilania),
                     potem migają dyski i zmienia się obciążenie (home.js), a po
                     szynach płynie sygnał danych. Etykiety są poglądowe - nie
                     opisują konkretnej konfiguracji węzłów. */ ?>
            <div class="hw-rack" data-hw-rack aria-hidden="true">
                <span class="hw-rail hw-rail-l"><i></i></span>
                <span class="hw-rail hw-rail-r"><i></i></span>
                <?php foreach (['PL-01', 'PL-02', 'PL-03', 'PL-04', 'BACKUP'] as $k => $etykieta): ?>
                    <div class="hw-unit" style="--k:<?php echo $k; ?>">
                        <span class="hw-power"></span>
                        <span class="hw-name"><b>DNCT</b> <?php echo $etykieta; ?></span>
                        <span class="hw-bays"><?php for ($d = 0; $d < 6; $d++): ?><i></i><?php endfor; ?></span>
                        <span class="hw-load"><i></i></span>
                        <span class="hw-net"><i></i><i></i></span>
                    </div>
                <?php endforeach; ?>
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

<!-- ============ JAK TO DZIAŁA ============ -->
<?php /* Kolejność strony (12.10.2026, decyzja właściciela): hero -> panel ->
         plany -> o hostingu -> "Jak to działa" -> Discord. Tu odpowiedź na
         "a co się dzieje po zapłacie", tuż przed zaproszeniem na Discorda.
         Klasa .cennik-page daje wspólne .cnk-eyebrow i .cnk-h2. */ ?>
<section class="px-6 home-wide mx-auto w-full cennik-page jtd-wrap">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/jak-to-dziala.css'); ?>">
    <?php render_jak_to_dziala(); ?>
    <script src="<?php echo asset('/assets/js/jak-to-dziala.js'); ?>" defer></script>
</section>

<!-- ============ DISCORD ============ -->
<?php
/* DISCORD - SCENA SPOŁECZNOŚCI (12.10.2026, trzecia wersja).
   Jeden punkt skupienia zamiast trzech: na środku nagłówek, licznik i
   przycisk, a wokół - po całej szerokości pasma - prawdziwe avatary
   z widgetu serwera (pobrane po stronie serwera, patrz $discordWidget
   wyżej) w kilku warstwach głębi. Warstwy przesuwają się za kursorem
   (paralaksa), każdy avatar lekko się unosi, a co chwilę któryś "mówi"
   zieloną falą jak w kanale głosowym. Kolor obwódki = status
   (zielony/bursztyn/czerwień), ten sam co w powiadomieniach.
   Wygląd: home.css (blok "DISCORD: SCENA"), ruch: home.js. */
$discordStatusColor = ['online' => '#22c55e', 'idle' => '#fbbf24', 'dnd' => '#ef4444'];
$discordMembers = is_array($discordWidget['members'] ?? null) ? $discordWidget['members'] : [];
$discordOnline  = (int)($discordWidget['presence_count'] ?? count($discordMembers));
/* Miejsca na avatary: [x %, y %, rozmiar px, głębia 0-1]. Środek pasma
   zostaje pusty pod tekst. Kolejność = ważność: na telefonie pokazuje
   się tylko początek listy (CSS: .dcx-av:nth-child(n+9)). */
$discordSloty = [
    [7, 22, 66, 1], [93, 24, 64, 1], [16, 64, 54, 0.75], [86, 62, 56, 0.8],
    [5, 82, 46, 0.6], [95, 80, 48, 0.65], [25, 30, 42, 0.5], [76, 34, 44, 0.5],
    [26, 82, 38, 0.45], [74, 84, 40, 0.45], [13, 42, 30, 0.3], [88, 44, 30, 0.3],
    [33, 12, 32, 0.35], [66, 10, 34, 0.35], [35, 92, 28, 0.25], [64, 93, 28, 0.25],
    [44, 6, 24, 0.2], [56, 95, 24, 0.2], [3, 50, 26, 0.25], [97, 52, 26, 0.25],
];
$discordShown = array_slice($discordMembers, 0, count($discordSloty));
$discordExtra = max(0, $discordOnline - count($discordShown));
?>
<?php /* Opinie z Google są teraz paskiem w przypiętej sekcji planów (#pricing) -
         widać je razem z kartami. Osobna sekcja zniknęła, żeby się nie dublowały. */ ?>
<section class="dcx" data-dcx>
    <div class="home-wrap">
        <div class="dcx-stage glass">
            <div class="dcx-glow" aria-hidden="true"></div>
            <svg class="dcx-logo" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>

            <div class="dcx-copy">
                <span class="dcx-live"><i aria-hidden="true"></i><b data-dcx-count="<?php echo $discordOnline; ?>"><?php echo $discordOnline; ?></b> na żywo na naszym Discordzie</span>
                <h2 class="dcx-h2">Dołącz do rozmowy</h2>
                <p class="dcx-lead">
                    Masz pytania? Potrzebujesz pomocy technicznej? Jesteśmy na Discordzie -
                    zwykle odpisujemy w kilka minut.
                </p>

                <?php if ($discordShown): ?>
                <div class="dcx-faces" aria-hidden="true">
                    <?php foreach (array_slice($discordShown, 0, 8) as $k => $m):
                        $status = in_array($m['status'] ?? '', ['online', 'idle', 'dnd'], true) ? $m['status'] : 'online';
                    ?>
                        <span class="dcx-av" style="--k:<?php echo $k; ?>; --ring:<?php echo $discordStatusColor[$status]; ?>">
                            <img src="<?php echo htmlspecialchars((string)($m['avatar_url'] ?? '')); ?>" alt="" width="44" height="44" loading="lazy" decoding="async">
                        </span>
                    <?php endforeach; ?>
                    <?php if ($discordExtra > 0): ?><span class="dcx-av dcx-av-more">+<?php echo $discordExtra; ?></span><?php endif; ?>
                </div>
                <?php endif; ?>

                <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener" class="dcx-btn">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                    <span>Dołącz do DNCT<span class="dcx-btn-long"> na Discordzie</span></span>
                    <span class="dcx-btn-ar" aria-hidden="true">→</span>
                </a>
            </div>

            <?php /* Kanał #pomoc: rozmowy w pętli (pytanie -> "DNCT Support pisze..."
                     -> odpowiedź -> reakcja). Pokazuje to, co mówi tekst obok,
                     zamiast tylko to twierdzić. Rozmowy są przykładowe, nagłówek
                     mówi "podgląd". Wiadomości dokłada home.js. */ ?>
            <div class="dnct-discord-demo" data-dc-chat>
                <div class="dc-chat-head" aria-hidden="true">
                    <span class="dc-chat-hash">#</span><b>pomoc</b>
                    <span class="dc-chat-sub">podgląd</span>
                    <span class="dc-chat-on"><i></i>Support online</span>
                </div>
                <div class="dc-chat-body" data-dc-body aria-hidden="true"></div>
                <a class="dc-chat-join" href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                    <span><span class="dc-chat-join-long">Masz pytanie? </span><b>Dołącz na Discord DNCT</b></span>
                    <span class="dc-chat-join-ar" aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php /* Ten sam skrypt co na cenniku - stąd guard w initPlanPicks() dla kart
         bez data-plan-id (karty-wizytówki tutaj nie zaznaczają się, tylko
         linkują do /cennik) i ten sam initChromaCards() dla kart i konsoli. */ ?>
<script src="<?php echo asset('/assets/js/cennik.js'); ?>" defer></script>
<script src="<?php echo asset('/assets/js/home.js'); ?>" defer></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
