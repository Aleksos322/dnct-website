<?php
/**
 * Cennik — pełna lista planów hostingowych.
 *
 * Docelowo pod adresem shop.dnct.pl; /cennik na stronie głównej będzie tu
 * przekierowywać. Do czasu podpięcia subdomeny strona żyje pod /cennik.
 *
 * Źródłem prawdy jest baza Pterodactyla (DB_*), czytana WYŁĄCZNIE do odczytu:
 *   - hosting_games — lista gier, kolejność i domyślne grafiki,
 *   - hosting_plans — plany przypisane do gry przez kolumnę `type`.
 *
 * Świadomie NIE MA tu żadnej edycji planów. W starej wersji (beta/cennik.php)
 * ta sama strona zawierała formularze INSERT/UPDATE i upload plików za
 * warunkiem `$_SESSION['root_admin']` — czyli publiczny cennik i panel
 * administracyjny w jednym pliku na 624 linie. Edycja planów należy do
 * admin.dnct.pl, po stronie dostępnej wyłącznie z LAN-u.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/components/plan-card.php';

$pageTitle       = 'Cennik hostingu gier — DNCT Hosting';
$metaDescription = 'Cennik serwerów Minecraft, Project Zomboid i Vintage Story. Gwarantowane zasoby bez oversellingu, ochrona DDoS i dyski NVMe.';
$canonicalUrl    = 'https://dnct.pl/cennik';
$centerContent   = false;

/**
 * Gry wraz z przypisanymi planami.
 *
 * Błąd bazy nie może położyć całej strony — w najgorszym razie pokażemy
 * komunikat zamiast cennika. Cennik to strona marketingowa, a nie krytyczna
 * ścieżka; biały ekran kosztowałby więcej niż brak tabeli.
 */
$games = [];
$dbError = false;

try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
        (string)env('DB_USERNAME'),
        (string)env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $gameRows = $pdo->query('SELECT type, name, icon_img FROM hosting_games WHERE is_active = 1 ORDER BY id')->fetchAll();

    // Jedno zapytanie na wszystkie plany zamiast jednego na grę — przy czterech
    // grach różnica jest bez znaczenia, ale wzorzec "zapytanie w pętli" ma
    // paskudny zwyczaj przeżywać do czasu, gdy gier jest czterdzieści.
    $planRows = $pdo->query(
        'SELECT * FROM hosting_plans WHERE is_active = 1 ORDER BY price ASC'
    )->fetchAll();

    $plansByType = [];
    foreach ($planRows as $plan) {
        $plansByType[$plan['type']][] = $plan;
    }

    foreach ($gameRows as $game) {
        $game['plans'] = $plansByType[$game['type']] ?? [];
        $games[] = $game;
    }
} catch (Throwable $e) {
    error_log('cennik: baza niedostepna - ' . $e->getMessage());
    $dbError = true;
}

/** Megabajty na czytelny zapis: 1536 -> "1.5GB", 512 -> "512MB". */
function cennik_size(int $mb): string
{
    if ($mb >= 1024) {
        $gb = $mb / 1024;
        return rtrim(rtrim(number_format($gb, 1, '.', ''), '0'), '.') . 'GB';
    }
    return $mb . 'MB';
}

/* Gry z planami idą pierwsze — pusta zakładka na górze wygląda jak awaria. */
$gamesWithPlans = array_values(array_filter($games, static fn(array $g): bool => $g['plans'] !== []));
$gamesSoon      = array_values(array_filter($games, static fn(array $g): bool => $g['plans'] === []));

ob_start();
?>
<link rel="stylesheet" href="/assets/css/cennik.css">

<section class="w-full max-w-7xl mx-auto px-6 py-16">

    <div class="text-center mb-14">
        <h1 class="text-3xl md:text-4xl font-black mb-4">Cennik hostingu gier</h1>
        <p class="text-gray-400 text-sm md:text-base max-w-2xl mx-auto">
            Gwarantowane zasoby bez oversellingu, ochrona DDoS i dyski NVMe.
            Wszystkie plany zawierają pełny dostęp do panelu i wsparcie przez Discord.
        </p>
        <div class="w-24 h-1 bg-gradient-main mx-auto rounded-full mt-6"></div>
    </div>

    <?php if ($dbError): ?>
        <div class="cennik-info glass">
            <h2>Cennik chwilowo niedostępny</h2>
            <p>Nie udało się pobrać listy planów. Spróbuj za chwilę albo napisz do nas na Discordzie — podamy ceny ręcznie.</p>
        </div>

    <?php elseif (!$gamesWithPlans): ?>
        <div class="cennik-info glass">
            <h2>Trwa przerwa techniczna</h2>
            <p>Wszystkie plany są chwilowo niedostępne. Wróć za jakiś czas albo dopytaj o termin na Discordzie.</p>
        </div>

    <?php else: ?>

        <?php /* Zakładki pokazujemy dopiero od dwóch gier — przy jednej byłby to
                 przełącznik, który nie ma czego przełączać. */ ?>
        <?php if (count($gamesWithPlans) > 1): ?>
            <div class="cennik-tabs" role="tablist">
                <?php foreach ($gamesWithPlans as $i => $game): ?>
                    <button type="button"
                            class="cennik-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                            data-target="game-<?php echo htmlspecialchars($game['type']); ?>">
                        <?php if (!empty($game['icon_img'])): ?>
                            <img src="/assets/media/Server_card/icons/<?php echo htmlspecialchars(basename($game['icon_img'])); ?>"
                                 alt="" width="20" height="20" loading="lazy">
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($game['name']); ?></span>
                        <span class="cennik-tab-count"><?php echo count($game['plans']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($gamesWithPlans as $i => $game): ?>
            <div class="cennik-panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
                 id="game-<?php echo htmlspecialchars($game['type']); ?>"
                 role="tabpanel">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                    <?php foreach ($game['plans'] as $plan): ?>
                        <?php
                        // btn_text i href dotyczą wyłącznie planów DOSTĘPNYCH.
                        // Dla wyprzedanych komponent rysuje własny, nieaktywny
                        // przycisk "Wyprzedane" i te wartości ignoruje.
                        render_plan_card((int)$plan['id'], [
                            'name'       => $plan['name'] ?? '',
                            'price'      => $plan['price'] ?? '',
                            'popular'    => !empty($plan['popular']),
                            'sold_out'   => !empty($plan['sold_out']),
                            'is_on_sale' => !empty($plan['is_on_sale']),
                            'old_price'  => $plan['old_price'] ?? 0,
                            'ram'        => cennik_size((int)($plan['ram_mb'] ?? 0)),
                            'cpu'        => ($plan['cpu_cores'] ?? 0) . ' vCore',
                            'ssd'        => cennik_size((int)($plan['ssd_mb'] ?? 0)),
                            'backups'    => $plan['backups_limit'] ?? 0,
                            'bg'         => $plan['background_img'] ?? '',
                            'icon'       => $plan['icon_img'] ?? '',
                            // Sprzedaż jest wyłączona na czas przerwy technicznej.
                            // Gdy wróci, podmień href na ścieżkę zamówienia
                            // (np. /zamowienie?plan=ID) i zdejmij ten komentarz.
                            'href'       => 'https://discord.gg/r2SsJp9e',
                            'btn_text'   => 'Zapytaj na Discordzie',
                        ]);
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <?php if ($gamesSoon): ?>
        <div class="cennik-soon glass">
            <h2>Wkrótce</h2>
            <p>Pracujemy nad planami dla kolejnych gier. Napisz na Discordzie, jeśli któraś Cię interesuje — kolejność ustalamy po zainteresowaniu.</p>
            <div class="cennik-soon-list">
                <?php foreach ($gamesSoon as $game): ?>
                    <span class="cennik-soon-item">
                        <?php if (!empty($game['icon_img'])): ?>
                            <img src="/assets/media/Server_card/icons/<?php echo htmlspecialchars(basename($game['icon_img'])); ?>"
                                 alt="" width="18" height="18" loading="lazy">
                        <?php endif; ?>
                        <?php echo htmlspecialchars($game['name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="cennik-note">
        <p>
            Ceny brutto w złotych. Szczegółowe warunki świadczenia usług opisuje
            <a href="/regulamin">Regulamin</a>.
        </p>
    </div>
</section>

<script src="/assets/js/cennik.js" defer></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
