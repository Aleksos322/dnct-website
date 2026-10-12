<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/capacity.php';

$pageTitle       = 'Jaki serwer wybrać? – DNCT Hosting';
$metaDescription = 'Dobierz pakiet DNCT do gry, liczby graczy i sposobu rozgrywki. Rekomendacja bazuje na aktualnych planach i progach serwerów.';
$canonicalUrl    = 'https://beta.dnct.pl/wybierz-serwer';
$centerContent   = false;
$catalog = [];

try {
    $panel = panel_db();
    $games = $panel->query('SELECT type, name FROM hosting_games WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $plans = annotate_plan_availability($panel->query('SELECT * FROM hosting_plans WHERE is_active = 1 ORDER BY ram_mb, price')->fetchAll(PDO::FETCH_ASSOC), node_capacity($panel));
    $tiers = annotate_tier_availability($panel->query('SELECT * FROM hosting_slot_tiers WHERE is_active = 1 ORDER BY slots')->fetchAll(PDO::FETCH_ASSOC), node_capacity($panel));

    foreach ($games as $game) {
        $type = (string)$game['type'];
        $catalog[$type] = ['name' => (string)$game['name'], 'plans' => [], 'tiers' => []];
    }
    foreach ($plans as $plan) {
        if (isset($catalog[$plan['type']])) {
            $catalog[$plan['type']]['plans'][] = [
                'name' => (string)$plan['name'], 'ram' => (int)$plan['ram_mb'], 'cpu' => (float)$plan['cpu_cores'],
                'disk' => (int)$plan['ssd_mb'], 'price' => (float)$plan['price'], 'available' => empty($plan['sold_out']),
            ];
        }
    }
    foreach ($tiers as $tier) {
        if (isset($catalog[$tier['type']])) {
            $catalog[$tier['type']]['tiers'][] = [
                'slots' => (int)$tier['slots'], 'ram' => (float)$tier['ram_gb'], 'cpu' => (float)$tier['cpu_cores'],
                'disk' => (int)$tier['ssd_mb'], 'price' => (float)$tier['price'], 'available' => empty($tier['sold_out']),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('wybierz-serwer: ' . $e->getMessage());
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/wybierz-serwer.css'); ?>">
<style>
    /* Ten sam pionowy start co cennik: globalny layout ma pt-32 dla zwykłych
       stron, a cennik świadomie podciąga treść pod stały navbar. */
    .chooser-page { margin-top:-90px; }
    .chooser-hero { padding-top:calc(clamp(1.75rem, 4vw, 2.75rem) + clamp(.625rem, 1.25vw, .875rem) + clamp(.875rem, 1.75vw, 1.375rem) + 1rem); }
    .chooser-range { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:1rem; align-items:center; }
    .chooser-range input { --range-progress:4%; appearance:none; width:100%; height:.55rem; border-radius:99px; background:linear-gradient(90deg, #8b5cf6 0%, #3b82f6 var(--range-progress), rgba(148,163,184,.22) var(--range-progress), rgba(148,163,184,.22) 100%); cursor:pointer; }
    .chooser-range input::-webkit-slider-thumb { appearance:none; width:1.35rem; height:1.35rem; border:3px solid #ede9fe; border-radius:50%; background:#8b5cf6; box-shadow:0 0 0 .35rem rgba(139,92,246,.18); transition:transform .15s ease; }
    .chooser-range input::-moz-range-thumb { width:1.05rem; height:1.05rem; border:3px solid #ede9fe; border-radius:50%; background:#8b5cf6; box-shadow:0 0 0 .35rem rgba(139,92,246,.18); }
    .chooser-range input:hover::-webkit-slider-thumb { transform:scale(1.12); }
    .chooser-range input:focus-visible { outline:2px solid #c4b5fd; outline-offset:5px; }
    .chooser-range output { min-width:5.4rem; padding:.45rem .65rem; border:1px solid rgba(139,92,246,.35); border-radius:.55rem; background:rgba(124,58,237,.14); color:#f8fafc; font-weight:800; text-align:center; }
    .chooser-upgrade { margin-top:1.4rem; padding:1rem; border:1px solid rgba(167,139,250,.28); border-radius:.8rem; background:rgba(15,11,28,.46); }
    .chooser-upgrade strong { color:#e9d5ff; font-size:.9rem; }
    .chooser-upgrade p { margin:.45rem 0 0; color:#cbd5e1; font-size:.86rem; line-height:1.55; }
</style>
<div class="chooser-page">
    <header class="chooser-hero">
        <p class="chooser-kicker">Bez zgadywania</p>
        <h1>Jaki serwer<br><span>wybrać?</span></h1>
        <p>Wybierz grę, liczbę osób i styl rozgrywki. Pokażemy punkt startowy oparty na aktualnie dostępnych pakietach DNCT.</p>
    </header>

    <?php if (!$catalog): ?>
        <section class="chooser-error glass"><h2>Kalkulator chwilowo niedostępny</h2><p>Nie udało się pobrać aktualnych pakietów. Zobacz <a href="/cennik">cennik</a> albo napisz na Discordzie.</p></section>
    <?php else: ?>
        <section class="chooser-shell glass" aria-label="Kalkulator pakietu">
            <form id="server-chooser" class="chooser-form">
                <fieldset><legend>1. W jaką grę grasz?</legend><div class="chooser-options">
                    <?php foreach ($catalog as $type => $game): ?>
                    <label><input type="radio" name="game" value="<?php echo htmlspecialchars($type); ?>"<?php echo $type === 'minecraft' ? ' checked' : ''; ?>><span><?php echo htmlspecialchars($game['name']); ?></span></label>
                    <?php endforeach; ?>
                </div></fieldset>
                <fieldset><legend>2. Ilu graczy ma wejść jednocześnie?</legend>
                    <div class="chooser-range"><input id="players" type="range" min="2" max="100" value="6" step="1"><output for="players" id="players-value">6 osób</output></div>
                </fieldset>
                <fieldset id="style-fieldset"><legend>3. Jak chcesz grać?</legend><div class="chooser-options">
                    <label><input type="radio" name="style" value="vanilla" checked><span>Vanilla</span></label>
                    <label><input type="radio" name="style" value="plugins"><span>Pluginy</span></label>
                    <label><input type="radio" name="style" value="mods"><span>Mody / modpack</span></label>
                </div><p class="chooser-help">Ten wybór zmienia rekomendację dla Minecrafta. W Zomboidzie i Vintage Story kluczowa jest liczba slotów.</p></fieldset>
            </form>
            <aside class="chooser-result" aria-live="polite">
                <p class="chooser-kicker">Twoja rekomendacja</p>
                <h2 id="result-title">Sprawdzam pakiet…</h2>
                <p id="result-specs"></p>
                <p id="result-reason" class="chooser-reason"></p>
                <a id="result-link" class="chooser-cta" href="/cennik">Zobacz pakiety</a>
                <div class="chooser-upgrade">
                    <strong>Potrzebujesz więcej mocy później?</strong>
                    <p>Pakiet zmienisz w panelu jednym kliknięciem. Przy przejściu wyżej dopłacasz tylko różnicę za pozostały czas: pierwszego dnia pełną, a w połowie okresu tylko połowę. Przy przejściu niżej niewykorzystana różnica zamienia się w dodatkowe dni działania serwera.</p>
                </div>
                <p class="chooser-disclaimer">To bezpieczny punkt startowy, nie obietnica stałej wydajności przy każdym modpacku. Większe mody, duży świat lub wielu graczy mogą wymagać większego pakietu.</p>
            </aside>
        </section>
    <?php endif; ?>
    <section class="chooser-after"><h2>Nie wiesz, ile zasobów potrzebujesz?</h2><p>Napisz na Discordzie, podaj grę, liczbę graczy i listę modów lub pluginów. Podpowiemy przed zakupem.</p><a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Zapytaj na Discordzie →</a></section>
</div>
<?php if ($catalog): ?>
<script id="chooser-data" type="application/json"><?php echo json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo asset('/assets/js/wybierz-serwer.js'); ?>" defer></script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
