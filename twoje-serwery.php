<?php
/**
 * Twoje serwery — /twoje-serwery
 *
 * Lista zamówień zalogowanej osoby wraz z terminem ważności i przyciskiem
 * przedłużenia. Zastępuje beta/przedluzenie.php (84 KB w jednym pliku,
 * z formularzami, zapytaniami i widokiem wymieszanymi w jednym miejscu).
 *
 * CO TU JEST, A CZEGO NIE MA. Strona pokazuje stan HANDLOWY: za co zapłacono,
 * do kiedy jest opłacone, czy abonament się odnawia. Konsola, pliki i backupy
 * są w panelu i mają tam zostać — dublowanie ich tutaj oznaczałoby dwa miejsca
 * do utrzymania i dwa źródła prawdy o tym samym serwerze.
 *
 * Dane o wygaśnięciu pochodzą z NASZEJ tabeli zamówień, nie z Pterodactyla.
 * Panel nie wie nic o okresach rozliczeniowych — zna tylko zawieszenie.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/stripe.php';

$pageTitle       = 'Twoje serwery — DNCT Hosting';
$metaDescription = 'Twoje serwery, terminy ważności i przedłużanie usług w DNCT Hosting.';
$centerContent   = false;

$isLoggedIn = isset($_SESSION['authentik_email'], $_SESSION['authentik_username']);

$orders  = [];
$periods = [];

if ($isLoggedIn) {
    $dnct = dnct_db();

    /* Pokazujemy tylko zamówienia, które coś uruchomiły. Porzucone sesje
       płatności zostają w bazie jako ślad do diagnostyki, ale na liście
       klienta byłyby wyłącznie zamieszaniem — nikt nie chce oglądać
       „zamówienia", którego nie dokończył. */
    $stmt = $dnct->prepare(
        'SELECT * FROM orders
          WHERE user_email = ?
            AND extends_order_id IS NULL
            AND status IN ("provisioned", "failed", "cancelled", "paid")
          ORDER BY id DESC'
    );
    $stmt->execute([(string)$_SESSION['authentik_email']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $panel = new PDO(
            'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
            (string)env('DB_USERNAME'),
            (string)env('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $periods = $panel->query(
            'SELECT days, label FROM hosting_periods WHERE is_active = 1 ORDER BY sort_order'
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('twoje-serwery: cennik niedostępny — ' . $e->getMessage());
    }
}

/** Ile dni zostało. Ujemna wartość = tyle dni po terminie. */
function srv_days_left(?string $expiresAt): ?int
{
    if ($expiresAt === null) {
        return null;
    }
    $ts = strtotime($expiresAt);
    return $ts === false ? null : (int)floor(($ts - time()) / 86400);
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/servers.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

<section class="ord-head">
    <span class="cnk-eyebrow">Twoje konto</span>
    <h1 class="cnk-h1">Twoje serwery</h1>
</section>

<?php if (!$isLoggedIn): ?>
    <div class="cennik-info glass">
        <h2>Zaloguj się, żeby zobaczyć swoje serwery</h2>
        <p>Usługi przypisujemy do konta — bez logowania nie mamy czego pokazać.</p>
        <p><a class="ord-back" href="/login">Przejdź do logowania</a></p>
    </div>

<?php elseif (!$orders): ?>
    <div class="cennik-info glass">
        <h2>Nie masz jeszcze żadnego serwera</h2>
        <p>Gdy zamówisz pierwszy, pojawi się tutaj razem z terminem ważności i przyciskiem przedłużenia.</p>
        <p><a class="ord-back" href="/cennik">Zobacz cennik</a></p>
    </div>

<?php else: ?>
    <div class="srv-list">
        <?php foreach ($orders as $o): ?>
            <?php
            $left     = srv_days_left($o['expires_at']);
            $isSub    = $o['billing_mode'] === 'subscription';
            $inGrace  = !empty($o['grace_until']);
            $failed   = $o['status'] === 'failed';
            $expired  = $left !== null && $left < 0;

            /* Jeden stan na kartę, w kolejności ważności. Klient ma zobaczyć
               NAJPILNIEJSZĄ rzecz, a nie wszystkie naraz. */
            if ($failed) {
                [$tone, $stateLabel] = ['bad',  'Wymaga naszej reakcji'];
            } elseif ($expired) {
                [$tone, $stateLabel] = ['bad',  'Wygasł'];
            } elseif ($inGrace) {
                [$tone, $stateLabel] = ['warn', 'Nieopłacona płatność'];
            } elseif ($o['status'] === 'cancelled') {
                [$tone, $stateLabel] = ['warn', 'Bez odnawiania'];
            } elseif ($left !== null && $left <= 3) {
                [$tone, $stateLabel] = ['warn', 'Kończy się'];
            } else {
                [$tone, $stateLabel] = ['ok',   $isSub ? 'Abonament aktywny' : 'Aktywny'];
            }
            ?>
            <article class="srv glass is-<?php echo $tone; ?>">
                <header class="srv-head">
                    <div>
                        <h2 class="srv-name">
                            <?php echo htmlspecialchars(ucfirst((string)$o['game_type'])); ?>
                            <span class="srv-spec">
                                <?php echo $o['slots'] !== null
                                    ? (int)$o['slots'] . ' slotów'
                                    : 'plan #' . (int)$o['plan_id']; ?>
                            </span>
                        </h2>
                        <p class="srv-sub">
                            <?php echo (int)$o['ram_mb']; ?> MB RAM ·
                            <?php echo rtrim(rtrim(number_format((float)$o['cpu_cores'], 1, '.', ''), '0'), '.'); ?> vCore ·
                            <?php echo (int)$o['ssd_mb']; ?> MB NVMe
                        </p>
                    </div>
                    <span class="srv-state"><?php echo htmlspecialchars($stateLabel); ?></span>
                </header>

                <div class="srv-facts">
                    <div>
                        <span>Opłacone do</span>
                        <strong><?php echo $o['expires_at'] !== null
                            ? htmlspecialchars(substr((string)$o['expires_at'], 0, 16))
                            : '—'; ?></strong>
                    </div>
                    <div>
                        <span>Pozostało</span>
                        <strong><?php
                            if ($left === null)      { echo '—'; }
                            elseif ($left < 0)       { echo abs($left), ' dni po terminie'; }
                            else                     { echo $left, ' dni'; }
                        ?></strong>
                    </div>
                    <div>
                        <span>Rozliczenie</span>
                        <strong><?php echo $isSub ? 'abonament' : 'jednorazowo'; ?></strong>
                    </div>
                    <div>
                        <span>Ostatnia płatność</span>
                        <strong><?php echo pricing_format((int)$o['amount_gross']); ?> zł</strong>
                    </div>
                </div>

                <?php if ($failed): ?>
                    <p class="srv-alert">
                        Płatność doszła, ale serwer nie został utworzony automatycznie.
                        Wiemy o tym — napisz na Discordzie, jeśli chcesz przyspieszyć.
                    </p>
                <?php elseif ($inGrace): ?>
                    <p class="srv-alert">
                        Nie udało się pobrać opłaty. Serwer działa do
                        <?php echo htmlspecialchars(substr((string)$o['grace_until'], 0, 16)); ?> —
                        popraw dane karty albo przedłuż jednorazowo.
                    </p>
                <?php elseif ($isSub && $o['status'] !== 'cancelled'): ?>
                    <p class="srv-note">
                        Ten serwer odnawia się sam. Dokupowanie dni nie jest potrzebne.
                    </p>
                <?php endif; ?>

                <?php /* Przedłużenie ma sens tylko dla serwera, który istnieje.
                         Przy zamówieniu w stanie „failed" nie ma czego przedłużać,
                         a przy abonamencie odnowienie przyjdzie samo. */ ?>
                <?php if (!$failed && $periods && (!$isSub || $o['status'] === 'cancelled' || $inGrace)): ?>
                    <div class="srv-extend" data-order="<?php echo (int)$o['id']; ?>">
                        <span class="srv-extend-label">Przedłuż o:</span>
                        <?php foreach ($periods as $p): ?>
                            <button type="button" class="srv-extend-btn"
                                    data-days="<?php echo (int)$p['days']; ?>"
                                    <?php echo stripe_sales_enabled() ? '' : 'disabled'; ?>>
                                <?php echo htmlspecialchars((string)$p['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="srv-error" data-role="error" hidden></p>
                <?php endif; ?>

                <footer class="srv-foot">
                    <a class="srv-link" href="https://panel.dnct.pl" target="_blank" rel="noopener">Otwórz w panelu</a>
                    <span class="srv-id">zamówienie #<?php echo (int)$o['id']; ?></span>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!stripe_sales_enabled()): ?>
        <p class="ord-hint">Przedłużanie jest chwilowo wyłączone — trwa przerwa techniczna.</p>
    <?php endif; ?>

    <script id="srv-data" type="application/json"><?php
        echo json_encode(['csrf' => csrf_token()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?></script>
    <script src="<?php echo asset('/assets/js/servers.js'); ?>" defer></script>
<?php endif; ?>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
