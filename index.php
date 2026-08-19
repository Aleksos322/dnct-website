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
    $showcasePlans = annotate_plan_availability($showcasePlans, node_capacity($pdo));
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

$pageTitle       = 'DNCT Hosting | Serwery Minecraft, Project Zomboid, Vintage Story - Od 3.99 PLN';
$metaDescription = 'DNCT Hosting to serwery Minecraft, Project Zomboid i Vintage Story z ochroną DDoS. Start od 3.99 PLN, szybkie NVMe i wsparcie 24/7.';
$canonicalUrl    = 'https://dnct.pl/';
$centerContent   = false; // strona wielosekcyjna, nie centrujemy pionowo

ob_start();
?>
<?php /* Style pasma "Zobacz, zanim zapłacisz" mieszkają w cennik.css. */ ?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">

<!-- ============ HERO ============ -->
<section class="relative pt-10 md:pt-16 pb-20 px-6 text-center overflow-hidden">
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[300px] md:w-[600px] h-[300px] md:h-[600px] bg-purple-600/10 rounded-full blur-[80px] md:blur-[120px] -z-10"></div>

    <div class="max-w-7xl mx-auto">
        <h1 class="text-4xl md:text-7xl font-extrabold mb-6 leading-tight">
            <?php if ($isLoggedIn): ?>
                Cześć, <span class="gradient-text"><?php echo htmlspecialchars((string)$username); ?>!</span><br>Gotowy na grę?
            <?php else: ?>
                Twoja przygoda zaczyna się <br><span class="gradient-text">bez opóźnień.</span>
            <?php endif; ?>
        </h1>

        <p class="text-gray-400 text-base md:text-xl max-w-2xl mx-auto mb-10 px-4">
            Serwery Minecraft, Project Zomboid i Vintage Story.
            Ochrona DDoS, szybkie NVMe i wsparcie 24/7.
        </p>

        <div class="flex flex-col md:flex-row justify-center gap-4 px-4">
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo htmlspecialchars($panelUrl); ?>"
                   class="bg-gradient-main px-10 py-4 rounded-xl font-bold text-lg hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all w-full md:w-auto">
                    Przejdź do Panelu!
                </a>
            <?php else: ?>
                <a href="/cennik"
                   class="bg-gradient-main px-10 py-4 rounded-xl font-bold text-lg hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all w-full md:w-auto">
                    Sprawdź ofertę
                </a>

                <?php if ($demoUrl !== ''): ?>
                    <!-- Konto poglądowe: wpuszcza do panelu bez zakładania konta -->
                    <a href="<?php echo htmlspecialchars($demoUrl); ?>"
                       class="glass px-10 py-4 rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-white/10 transition-all w-full md:w-auto">
                        <svg class="w-5 h-5 text-purple-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>
                        </svg>
                        Wypróbuj demo
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener"
               class="glass px-10 py-4 rounded-xl font-bold text-lg flex items-center justify-center gap-2 hover:bg-white/10 transition-all w-full md:w-auto">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                </svg>
                Discord
            </a>
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
<section class="px-6 max-w-7xl mx-auto w-full cennik-page">
    <?php render_panel_band(); ?>
<script src="<?php echo asset('/assets/js/panel-band.js'); ?>" defer></script>
</section>

<!-- ============ O HOSTINGU (treść pod wyszukiwarki) ============ -->
<section id="hosting" class="py-16 px-6 max-w-7xl mx-auto w-full">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
        <div>
            <h2 class="text-3xl md:text-4xl font-bold mb-4">
                Hosting gier Minecraft, Project Zomboid i Vintage Story
            </h2>
            <p class="text-gray-400 mb-4">
                DNCT Hosting to serwery gier dla Minecraft, Project Zomboid i Vintage Story.
                Hosting gier od 3.99 PLN - stabilne CPU/RAM, szybkie dyski NVMe i pełna kontrola.
            </p>
            <p class="text-gray-400">
                Polska infrastruktura, ochrona DDoS, codzienne backupy i wsparcie 24/7 przez Discord.
                Hosting Minecraft z modami, hosting Project Zomboid, hosting Vintage Story - wszystko w jednym miejscu.
            </p>
        </div>

        <div class="glass p-6 rounded-2xl border border-white/5">
            <ul class="space-y-3 text-sm text-gray-300">
                <?php foreach ([
                    'Ochrona DDoS i monitoring 24/7',
                    'NVMe SSD, nowoczesne CPU, stabilne zasoby',
                    'Backupy i szybkie przywrócenie danych',
                    'Wsparcie dla modów i konfiguracji serwerów',
                    'Plany dla Minecraft, Zomboid i Vintage Story',
                ] as $feature): ?>
                    <li class="flex items-start gap-3">
                        <svg class="w-4 h-4 text-purple-500 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span><?php echo htmlspecialchars($feature); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<!-- ============ PLANY ============ -->
<?php if ($showcasePlans): ?>
<section id="pricing" class="cennik-page py-20 px-6 max-w-7xl mx-auto w-full">
    <div class="text-center mb-16">
        <h2 class="text-3xl font-bold mb-4">Wybierz plan hostingu gier</h2>
        <p class="text-gray-500 text-sm md:text-base mb-4">Hosting Minecraft, Project Zomboid i Vintage Story</p>
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
        <p class="text-gray-500 text-sm mb-4">
            Sprzedaż nowych serwerów startuje wkrótce. Zapytaj o dostępność na Discordzie.
        </p>
        <a href="/cennik" class="text-gray-400 hover:text-white underline decoration-purple-500 decoration-2 underline-offset-4 transition-all">
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
<section class="discord-band py-24 px-6 w-full relative overflow-hidden">
    <div class="discord-band-glow" aria-hidden="true"></div>
    <div class="max-w-5xl mx-auto relative z-10">
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
                <div class="discord-live">
                    <div class="discord-live-count">
                        <span class="discord-live-dot" aria-hidden="true"></span>
                        <span class="discord-live-number" data-count-target="<?php echo $discordOnline; ?>">0</span>
                        <span class="discord-live-label">na żywo teraz</span>
                    </div>
                    <div class="discord-avatars">
                        <?php foreach ($discordShown as $i => $m):
                            $status = in_array($m['status'] ?? '', ['online', 'idle', 'dnd'], true) ? $m['status'] : 'online';
                            $ring   = $discordStatusColor[$status];
                            $name   = (string)($m['username'] ?? 'gracz');
                            $activity = !empty($m['game']['name']) ? ' - gra w ' . $m['game']['name'] : '';
                        ?>
                        <img class="discord-avatar" style="--i:<?php echo (int)$i; ?>; --ring:<?php echo $ring; ?>"
                             src="<?php echo htmlspecialchars((string)($m['avatar_url'] ?? '')); ?>"
                             alt="" title="<?php echo htmlspecialchars($name . $activity); ?>"
                             width="44" height="44" loading="lazy" decoding="async">
                        <?php endforeach; ?>
                        <?php if ($discordExtra > 0): ?>
                        <span class="discord-avatar discord-avatar-more" style="--i:<?php echo count($discordShown); ?>">+<?php echo $discordExtra; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="discord-live discord-live-fallback">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                    </svg>
                    <p>Setki graczy rozmawiają codziennie</p>
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
})();
</script>

<?php /* Ten sam skrypt co na cenniku - stąd guard w initPlanPicks() dla kart
         bez data-plan-id (karty-wizytówki tutaj nie zaznaczają się, tylko
         linkują do /cennik) i ten sam initChromaCards() dla kart i konsoli. */ ?>
<script src="<?php echo asset('/assets/js/cennik.js'); ?>" defer></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
