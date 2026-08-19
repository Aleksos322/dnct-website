<?php
/**
 * Cennik - pełna lista planów hostingowych.
 *
 * Docelowo pod adresem shop.dnct.pl; /cennik na stronie głównej będzie tu
 * przekierowywać. Do czasu podpięcia subdomeny strona żyje pod /cennik.
 *
 * Źródłem prawdy jest baza Pterodactyla (DB_*), czytana WYŁĄCZNIE do odczytu:
 *   - hosting_games - lista gier, kolejność i domyślne grafiki,
 *   - hosting_plans - plany przypisane do gry przez kolumnę `type`.
 *
 * Świadomie NIE MA tu żadnej edycji planów. W starej wersji (beta/cennik.php)
 * ta sama strona zawierała formularze INSERT/UPDATE i upload plików za
 * warunkiem `$_SESSION['root_admin']` - czyli publiczny cennik i panel
 * administracyjny w jednym pliku na 624 linie. Edycja planów należy do
 * admin.dnct.pl, po stronie dostępnej wyłącznie z LAN-u.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/components/plan-card-cennik.php';
require_once __DIR__ . '/components/slot-configurator.php';
require_once __DIR__ . '/components/game-decor.php';
require_once __DIR__ . '/components/panel-band.php';
require_once __DIR__ . '/backend/lib/stripe.php';

/* Czy karty mają prowadzić do zamówienia, czy tylko dawać się zaznaczać.
   Jedna zmienna dla całej strony - inaczej łatwo o wersję, w której przycisk
   na karcie prowadzi do kasy, a konfigurator obok nie. */
$salesOn = stripe_sales_enabled();

/**
 * Zwiastun w paśmie "Panel".
 *
 * Pusty napis = pasmo pokazuje zamiast filmu animowaną konsolę. Tak jest dziś
 * i tak ma zostać, dopóki nie ma czego wstawić - "wkrótce" w miejscu materiału
 * wideo wygląda gorzej niż brak sekcji.
 *
 * Wstawić sam identyfikator z adresu YouTube (fragment po `v=`), nie cały link.
 * Film ładuje się DOPIERO po kliknięciu w podgląd - osadzony od razu iframe
 * zakłada ciasteczka YouTube'a jeszcze zanim odwiedzający zdąży odpowiedzieć
 * na baner zgód, co przewraca całą zgodę na ciasteczka na tej stronie.
 */
const CENNIK_TRAILER_YT = '';

/** Dokąd prowadzi przycisk demo. Panel wpuszcza przez to samo SSO co strona. */
const CENNIK_PANEL_URL = 'https://panel.dnct.pl';

$pageTitle       = 'Cennik hostingu gier - DNCT Hosting';
$metaDescription = 'Cennik serwerów Minecraft, Project Zomboid i Vintage Story. Gwarantowane zasoby bez oversellingu, ochrona DDoS i dyski NVMe.';
$canonicalUrl    = 'https://dnct.pl/cennik';
$centerContent   = false;

/**
 * Gry wraz z przypisanymi planami.
 *
 * Błąd bazy nie może położyć całej strony - w najgorszym razie pokażemy
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

    // Jedno zapytanie na wszystkie plany zamiast jednego na grę - przy czterech
    // grach różnica jest bez znaczenia, ale wzorzec "zapytanie w pętli" ma
    // paskudny zwyczaj przeżywać do czasu, gdy gier jest czterdzieści.
    $planRows = $pdo->query(
        'SELECT * FROM hosting_plans WHERE is_active = 1 ORDER BY price ASC'
    )->fetchAll();

    /* Dostępność liczona z realnej pojemności węzła klienckiego. Plan znika
       z oferty sam, gdy przestaje się na nim mieścić - bez ręcznego
       przestawiania flagi w bazie. Ręczne `sold_out = 1` nadal działa jako
       twarde wyłączenie. */
    $capacity  = node_capacity($pdo);
    $planRows  = annotate_plan_availability($planRows, $capacity);

    $plansByType = [];
    foreach ($planRows as $plan) {
        $plansByType[$plan['type']][] = $plan;
    }

    // Progi slotow dla gier rozliczanych za liczbe graczy (Zomboid, Vintage
    // Story). Jedno zapytanie na wszystkie typy, grupowane w PHP.
    $tierRows = $pdo->query(
        'SELECT * FROM hosting_slot_tiers WHERE is_active = 1 ORDER BY slots ASC'
    )->fetchAll();
    $tiersByType = [];
    foreach ($tierRows as $tier) {
        $tiersByType[$tier['type']][] = $tier;
    }

    $periods = $pdo->query(
        'SELECT * FROM hosting_periods WHERE is_active = 1 ORDER BY sort_order ASC'
    )->fetchAll();

    foreach ($gameRows as $game) {
        $game['plans'] = $plansByType[$game['type']] ?? [];
        $game['tiers'] = $tiersByType[$game['type']] ?? [];
        $games[] = $game;
    }

    // Dowod spoleczny na ZYWYCH danych - liczba dziala sie sama i nikt jej nie
    // musi pamietac aktualizowac. Zmyslona liczba to obietnica, ktorej nie da
    // sie dotrzymac, a klienci sprawdzaja.
    $liveServers = (int)$pdo->query('SELECT COUNT(*) FROM servers')->fetchColumn();
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

/* Gra jest do kupienia, gdy ma gotowe plany ALBO progi slotow do konfiguratora.
   Gry bez jednego i drugiego trafiaja do sekcji "Wkrotce" - pusta zakladka na
   gorze wyglada jak awaria, a nie jak zapowiedz. */
/* Zakres RAM w obrebie gry. Sluzy wskaznikowi mocy na karcie: klient widzi,
   gdzie dany plan lezy miedzy najmniejszym a najwiekszym. To odpowiedz na
   pierwsze pytanie osoby, ktora nie wie, czy 8 GB to duzo - sama liczba jej
   tego nie powie, pozycja na skali owszem. */
foreach ($games as $gi => $g) {
    $rams = array_map(static fn(array $p): int => (int)$p['ram_mb'], $g['plans']);
    $games[$gi]['ram_min'] = $rams ? min($rams) : 0;
    $games[$gi]['ram_max'] = $rams ? max($rams) : 0;
}

$isSellable     = static fn(array $g): bool => $g['plans'] !== [] || $g['tiers'] !== [];
$gamesWithPlans = array_values(array_filter($games, $isSellable));
$gamesSoon      = array_values(array_filter($games, static fn(array $g): bool => !$isSellable($g)));
$periods        = $periods ?? [];
$liveServers    = $liveServers ?? 0;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">

<div class="cennik-page">

<?php /* Warstwa dekoracji w marginesach bocznych. Wszystkie zestawy są w HTML-u
         od razu, a zakładka tylko przełącza widoczny - dzięki temu zmiana gry
         nie czeka na żadne pobieranie. Widoczna wyłącznie tam, gdzie naprawdę
         jest na nią miejsce (patrz media query w cennik.css); na węższych
         ekranach dekoracje wchodziłyby pod treść. */ ?>
<div class="cnk-decor-layer" aria-hidden="true">
    <?php foreach ($gamesWithPlans as $i => $game): ?>
        <div class="cnk-decor-set<?php echo $i === 0 ? ' is-active' : ''; ?>"
             data-decor-for="game-<?php echo htmlspecialchars($game['type']); ?>">
            <?php render_game_decor((string)$game['type']); ?>
        </div>
    <?php endforeach; ?>
</div>

<?php /* PŁYWAJĄCA PLAKIETKA Z LICZNIKIEM.
         Szyna obok nagłówka przykleja się do krawędzi dopiero od 1400 px, bo
         niżej nie ma marginesu, w którym mogłaby stanąć - przy typowych 1000 px
         CSS zostaje go szesnaście pikseli. Licznik zniknąłby więc po pierwszym
         geście przewijania u niemal wszystkich odwiedzających.

         Ta plakietka jest odpowiedzią: mała, w rogu, pokazuje się DOPIERO gdy
         licznik z hero wyjedzie z ekranu. Ma aria-hidden, bo powtarza liczbę,
         która jest już na stronie - czytnik ekranu przeczytałby ją dwa razy. */ ?>
<?php if ($liveServers > 0): ?>
    <div class="cnk-live-float" data-role="liveFloat" aria-hidden="true">
        <span class="cnk-live-dot"></span>
        <strong><?php echo $liveServers; ?></strong>
        <span>serwerów online</span>
    </div>
<?php endif; ?>

<div class="cnk-wrap">

<!-- ============ HERO ============ -->
<section class="cnk-hero">
    <div class="cnk-hero-glow" aria-hidden="true"></div>

    <div class="cnk-hero-inner">
        <div class="cnk-hero-text">
            <span class="cnk-eyebrow">Cennik</span>
            <h1 class="cnk-h1">
                Serwer, który<br>
                <span class="cnk-h1-accent">nie zwalnia</span>
            </h1>
        </div>

        <?php /* SZYNA OBOK NAGŁÓWKA, nie pod nim.
                 Wcześniej stał tu akapit o gwarantowanym RAM-ie, a licznik
                 serwerów wisiał osobno - razem spychały karty o pół ekranu
                 w dół. Przeniesienie tych informacji pod nagłówek nic by nie
                 dało: zajęłyby dokładnie ten sam pion.

                 Po prawej stronie nagłówka jest puste miejsce i to ono je
                 przyjmuje - karty idą w górę o całą wysokość tego bloku,
                 a nie o różnicę w wysokości akapitu.

                 Od 1400 px szyna ODKLEJA SIĘ od hero i przywiera do prawej
                 krawędzi okna (position: fixed), więc licznik towarzyszy
                 przewijaniu zamiast zniknąć po pierwszym geście. Niżej nie ma
                 jej gdzie przykleić - weszłaby na treść. */ ?>
        <aside class="cnk-rail glass" aria-label="Skrót informacji o hostingu">
            <?php if ($liveServers > 0): ?>
                <?php /* Licznik z bazy panelu, liczony przy każdym wejściu. Nie jest
                         ozdobą: to jedyna liczba na tej stronie, której nie da się
                         podważyć, bo pochodzi z działających maszyn.

                         `data-count-to` uruchamia naliczanie od zera po wejściu
                         w kadr (cennik.js). Liczba, która się DOLICZA, czyta się
                         jak odczyt z żywego systemu; ta sama liczba wpisana na
                         sztywno wygląda jak element grafiki. */ ?>
                <div class="cnk-live">
                    <span class="cnk-live-eyebrow">
                        <span class="cnk-live-dot" aria-hidden="true"></span>
                        Na żywo
                    </span>
                    <span class="cnk-live-num" data-count-to="<?php echo $liveServers; ?>"><?php echo $liveServers; ?></span>
                    <span class="cnk-live-label">serwerów działa w tej chwili</span>
                </div>
            <?php endif; ?>

            <ul class="cnk-rail-points">
                <li><span aria-hidden="true">✓</span> Gwarantowany RAM i CPU</li>
                <li><span aria-hidden="true">✓</span> Dyski NVMe, bez oversellingu</li>
                <li><span aria-hidden="true">✓</span> Ochrona DDoS w każdym planie</li>
            </ul>
        </aside>
    </div>
</section>

    <?php if ($dbError): ?>
        <div class="cennik-info glass">
            <h2>Cennik chwilowo niedostępny</h2>
            <p>Nie udało się pobrać listy planów. Spróbuj za chwilę albo napisz do nas na Discordzie - podamy ceny ręcznie.</p>
        </div>

    <?php elseif (!$gamesWithPlans): ?>
        <div class="cennik-info glass">
            <h2>Trwa przerwa techniczna</h2>
            <p>Wszystkie plany są chwilowo niedostępne. Wróć za jakiś czas albo dopytaj o termin na Discordzie.</p>
        </div>

    <?php else: ?>

        <?php /* Zakładki gier. Pokazujemy je zawsze, gdy gier jest więcej niż
                 jedna - to jedyny sposób, żeby klient w ogóle zauważył, że
                 hostujemy coś poza Minecraftem. */ ?>
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
                        <span class="cennik-tab-count">
                            <?php echo $game['plans'] ? count($game['plans']) : 'suwak'; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($gamesWithPlans as $i => $game): ?>
            <div class="cennik-panel<?php echo $i === 0 ? ' is-active' : ''; ?>"
                 id="game-<?php echo htmlspecialchars($game['type']); ?>"
                 role="tabpanel">

                <?php if ($game['plans']): ?>
                    <div class="cnk-panel">
                    <div class="cnk-panel-head">
                        <h2>Gotowe plany</h2>
                        <p>Wybierz rozmiar - resztę mamy taką samą w każdym.</p>
                    </div>
                    <?php /* Gotowe plany. Każda karta ma własne id planu i daje się
                             zaznaczyć - zaznaczenie ląduje w pasku podsumowania
                             na dole ekranu. */ ?>
                    <div class="plans-grid gap-8 items-stretch">
                        <?php foreach ($game['plans'] as $plan): ?>
                            <?php
                            // Pozycja planu na skali mocy w obrebie gry.
                            // Skala PIERWIASTKOWA, nie liniowa. Rozpiętość planów to
                            // 1.5 GB do 32 GB, czyli ponad dwudziestokrotność - przy
                            // podziale liniowym cztery najmniejsze plany dostawały
                            // praktycznie identyczny pasek i wskaźnik przestawał
                            // odróżniać to, co miał odróżniać.
                            $ramSpan = max(1, $game['ram_max'] - $game['ram_min']);
                            $ramPos  = sqrt(((int)$plan['ram_mb'] - $game['ram_min']) / $ramSpan);
                            ?>
                            <div class="plan-pick"
                                 data-plan-id="<?php echo (int)$plan['id']; ?>"
                                 data-plan-name="<?php echo htmlspecialchars((string)$plan['name']); ?>"
                                 data-plan-game="<?php echo htmlspecialchars((string)$game['name']); ?>"
                                 data-plan-price="<?php echo htmlspecialchars(cennik_price_label($plan['price'])); ?>"
                                 data-plan-soldout="<?php echo !empty($plan['sold_out']) ? '1' : '0'; ?>"
                                 data-plan-fits="<?php echo $plan['fits'] ?? ''; ?>"
                                 tabindex="0" role="button"
                                 aria-label="Wybierz plan <?php echo htmlspecialchars((string)$plan['name']); ?>">

                                <?php
                                /* Wariant cennikowy: wysoka karta z ceną przy
                                   dole. Karta nie jest linkiem - sprzedaż jest
                                   wyłączona, a kliknięcie obsługuje skrypt
                                   zaznaczania na kontenerze .plan-pick. */
                                render_cennik_plan_card([
                                    'name'       => $plan['name'] ?? '',
                                    'price'      => $plan['price'] ?? '',
                                    'popular'    => !empty($plan['popular']),
                                    'sold_out'   => !empty($plan['sold_out']),
                                    // Wygasła przecena (sale_ends_at w przeszłości) ma nie
                                    // pokazywać przekreślonej ceny, nawet gdy admin zapomniał
                                    // odznaczyć is_on_sale - patrz pricing_effective_on_sale().
                                    'is_on_sale' => pricing_effective_on_sale($plan),
                                    'old_price'  => $plan['old_price'] ?? 0,
                                    'ram'        => cennik_size((int)($plan['ram_mb'] ?? 0)),
                                    'cpu'        => ($plan['cpu_cores'] ?? 0) . ' vCore',
                                    'ssd'        => cennik_size((int)($plan['ssd_mb'] ?? 0)),
                                    'backups'    => (string)($plan['backups_limit'] ?? 0),
                                    'bg'         => $plan['background_img'] ?? '',
                                    'icon'       => $plan['icon_img'] ?? '',
                                    'fits'       => $plan['fits'] ?? null,
                                    'power'      => $ramPos,
                                    // Przy wyłączonej sprzedaży brak adresu -
                                    // karta zostaje wtedy tylko do zaznaczania.
                                    'href'       => $salesOn ? '/zamowienie?plan=' . (int)$plan['id'] : null,
                                ]);
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>

                <?php else: ?>
                    <?php /* Gry rozliczane za sloty: zamiast gotowych planów klient
                             sam ustawia liczbę graczy suwakiem. */ ?>
                    <div class="cnk-panel cnk-panel-cfg">
                    <div class="cfg-intro">
                        <h2>Zbuduj serwer pod swoją ekipę</h2>
                        <p>Przesuń suwak i ustaw liczbę graczy - resztę dobierzemy automatycznie.</p>
                    </div>
                    <?php render_slot_configurator($game['type'], $game['tiers'], $periods); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <?php /* Pasmo wspólne ze stroną główną - patrz components/panel-band.php. */ ?>
    <?php render_panel_band(['trailer' => CENNIK_TRAILER_YT, 'panelUrl' => CENNIK_PANEL_URL]); ?>

    <?php /* ZAPROSZENIE ZAMIAST OBIETNICY.
             Wcześniej stało tu "Wkrótce" z listą gier, których nie hostujemy.
             Zapowiedź bez terminu nic nie daje klientowi, a nam wisi nad głową:
             im dłużej "wkrótce" nie nadchodzi, tym gorzej wygląda cała strona.
             Zamiana na rozmowę kosztuje nas jedno kliknięcie, a daje realny
             sygnał, o co ludzie pytają. */ ?>
    <div class="cennik-ask glass">
        <div class="cennik-ask-text">
            <h2>Szukasz innej gry?</h2>
            <p>
                Napisz do nas na Discordzie - coś poradzimy. Postawimy serwer pod grę,
                której nie ma w cenniku, albo powiemy wprost, że się nie da.
                <?php if ($gamesSoon): ?>
                    Najczęściej pytacie o:
                    <?php echo htmlspecialchars(implode(', ', array_column($gamesSoon, 'name'))); ?>.
                <?php endif; ?>
            </p>
        </div>

        <a class="cennik-ask-btn" href="https://discord.gg/r2SsJp9e" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
            Napisz na Discordzie
        </a>
    </div>

    <!-- ============ CO ZAWSZE W CENIE / DLACZEGO MY ============ -->
    <?php /* Dwie kolumny zamiast rzędu kafelków na dole. Po lewej to, czego
             karty planów nie muszą już powtarzać dziewięć razy; po prawej
             powody, dla których warto zaryzykować pierwszy zakup. Rozdzielenie
             tych dwóch rzeczy jest celowe: pierwsze to specyfikacja, drugie to
             zaufanie, i mieszanie ich osłabia oba. */ ?>
    <section class="cnk-split">
        <div class="cnk-split-col cnk-included">
            <span class="cnk-eyebrow">W każdym planie</span>
            <h2 class="cnk-h2">Bez gwiazdek i dopłat</h2>
            <ul class="cnk-list">
                <li><span class="cnk-check" aria-hidden="true">✓</span> Pełny dostęp do panelu i konsoli</li>
                <li><span class="cnk-check" aria-hidden="true">✓</span> Dyski NVMe, bez współdzielenia RAM-u i CPU</li>
                <li><span class="cnk-check" aria-hidden="true">✓</span> Codzienne backupy z retencją 7 dni</li>
                <li><span class="cnk-check" aria-hidden="true">✓</span> Ochrona DDoS: Cloudflare Magic Transit</li>
                <li><span class="cnk-check" aria-hidden="true">✓</span> Własna subdomena i zmiana wersji gry</li>
                <li><span class="cnk-check" aria-hidden="true">✓</span> Wsparcie przez Discord, bez systemu biletów</li>
            </ul>
        </div>

        <div class="cnk-split-col cnk-trust">
            <span class="cnk-eyebrow">Zanim klikniesz</span>
            <h2 class="cnk-h2">Ryzykujesz mniej, niż myślisz</h2>

            <div class="cnk-trust-item cnk-trust-hero">
                <span class="cnk-trust-big"><span class="cnk-trust-num" data-role="count">24</span><span class="cnk-trust-unit">h</span></span>
                <div>
                    <strong>na rezygnację</strong>
                    <span>Nie spodobało się? Piszesz na Discordzie i odzyskujesz pieniądze.</span>
                </div>
            </div>

            <?php /* PING NA PASKACH Z GRY. Zamiast pisać "niski ping" - liczba,
                     której i tak nikt nie sprawdzi - pokazujemy pasek, który
                     każdy grający zna z listy serwerów. Napełnia się od jednej
                     kreski do pięciu i wraca; ruch mówi to samo, co zdanie
                     obok, tylko szybciej. */ ?>
            <div class="cnk-trust-item cnk-ping-item">
                <span class="cnk-ping" aria-hidden="true">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <img src="/assets/media/minecraft/Server_Ping_<?php echo $i; ?>.webp"
                             alt="" class="pg pg<?php echo $i; ?>" loading="lazy" decoding="async">
                    <?php endfor; ?>
                </span>
                <div>
                    <strong>Serwery w Polsce</strong>
                    <span>Stoją u nas, nie za granicą. Krótsza droga do serwera to niższy
                          ping i mniej lagów w grze.</span>
                </div>
            </div>

            <div class="cnk-trust-item">
                <strong>Zasoby są Twoje</strong>
                <span>Zero oversellingu - RAM i CPU nie są dzielone z nikim innym.</span>
            </div>

            <div class="cnk-trust-item">
                <strong>Rok taniej o miesiąc</strong>
                <span>Płatność roczna liczy się jak jedenaście miesięcy, nie dwanaście.</span>
            </div>
        </div>
    </section>

    <div class="cennik-note">
        <p>
            Ceny brutto w złotych. Szczegółowe warunki świadczenia usług opisuje
            <a href="/regulamin">Regulamin</a>.
        </p>
    </div>

</div><!-- /.cnk-wrap -->

<?php /* Pasek podsumowania. Startuje ukryty; pokazuje go skrypt po pierwszym
         zaznaczeniu. Zgodnie z ustaleniem NIE MA tu przycisku "kup" - sprzedaż
         jest wyłączona, a przycisk prowadzący donikąd jest gorszy niż jego brak. */ ?>
<div class="pick-bar" id="pick-bar" role="status" aria-live="polite">
    <div class="pick-bar-inner glass glass-top-edge">
        <div class="pick-bar-info">
            <span class="pick-bar-label">Twój wybór</span>
            <span class="pick-bar-name" data-role="pickName"></span>
        </div>
        <div class="pick-bar-price">
            <span data-role="pickPrice"></span>
            <span class="pick-bar-cur">zł</span>
            <span class="pick-bar-per" data-role="pickPer"></span>
        </div>
        <?php if ($salesOn): ?>
            <a class="pick-bar-go" data-role="pickGo" href="#">Zamawiam</a>
        <?php endif; ?>
        <button type="button" class="pick-bar-clear" data-role="pickClear" aria-label="Wyczyść wybór">✕</button>
    </div>
</div>

<script src="<?php echo asset('/assets/js/cennik.js'); ?>" defer></script>
<script src="<?php echo asset('/assets/js/panel-band.js'); ?>" defer></script>

</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
