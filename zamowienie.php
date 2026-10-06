<?php
/**
 * Formularz zamówienia.
 *
 * Wejście z cennika: /zamowienie?plan=3  albo  /zamowienie?game=vintagestory&slots=20
 *
 * Strona NIE liczy ceny sama - pyta o nią backend/lib/pricing.php, ten sam
 * kod, który potem wystawia kwotę Stripe'owi. Dzięki temu to, co klient widzi
 * w podsumowaniu, jest z definicji tym, co zapłaci: nie ma dwóch wzorów, które
 * mogłyby się rozjechać.
 *
 * Wybór okresu i modelu rozliczenia przelicza podsumowanie w przeglądarce,
 * ale kwoty dla wszystkich wariantów są policzone TU, po stronie serwera,
 * i wstawione w atrybuty. Front tylko je pokazuje, niczego nie mnoży.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/stripe.php';

$pageTitle       = 'Zamówienie - DNCT Hosting';
$metaDescription = 'Podsumowanie zamówienia serwera gry w DNCT Hosting.';
$centerContent   = false;

/* Zamówienie wymaga konta: serwer zakładamy na konkretnym koncie w panelu,
   dopasowanym po adresie e-mail. Bez logowania nie ma czego dopasować. */
$isLoggedIn = isset($_SESSION['authentik_email'], $_SESSION['authentik_username']);

/* Adres, pod który logowanie ma odesłać z powrotem - z parametrami, więc
   po zalogowaniu wraca ten sam plan i te same sloty. Wiodące "//" byłoby
   już innym hostem, więc normalizujemy je do jednego ukośnika (i tak
   odrzuciłoby je oidc_safe_return_to, ale wtedy klient wylądowałby na
   stronie głównej zamiast na swoim zamówieniu). */
$returnTo = '/' . ltrim((string)($_SERVER['REQUEST_URI'] ?? '/zamowienie'), '/');

$error   = null;
$quotes  = [];
$spec    = null;
$periods = [];

try {
    $panel = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
        (string)env('DB_USERNAME'),
        (string)env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $planId = (int)($_GET['plan'] ?? 0);
    $slots  = (int)($_GET['slots'] ?? 0);
    $game   = (string)($_GET['game'] ?? '');

    // Przy wejściu z karty planu gra wynika z samego planu - nie każemy jej
    // podawać w adresie, bo to tylko okazja do niespójnego żądania.
    if ($planId > 0 && $game === '') {
        $stmt = $panel->prepare('SELECT type FROM hosting_plans WHERE id = ? AND is_active = 1');
        $stmt->execute([$planId]);
        $game = (string)($stmt->fetchColumn() ?: '');
    }

    $periods = $panel->query(
        'SELECT days, label, badge FROM hosting_periods WHERE is_active = 1 ORDER BY sort_order'
    )->fetchAll();

    /* Wyceniamy KAŻDY okres i KAŻDY model rozliczenia od razu. Wariantów jest
       sześć, więc to sześć tanich zapytań przy wejściu na stronę - w zamian
       przełączanie opcji nie wymaga ani jednego żądania i nie da się nim
       podmienić kwoty. */
    foreach ($periods as $p) {
        foreach (['payment', 'subscription'] as $mode) {
            try {
                $q = pricing_quote($panel, [
                    'game_type'    => $game,
                    'plan_id'      => $planId,
                    'slots'        => $slots,
                    'period_days'  => (int)$p['days'],
                    'billing_mode' => $mode,
                ]);
            } catch (RuntimeException $e) {
                // Abonament tygodniowy albo inny wariant bez odpowiednika
                // w Stripe po prostu nie pojawi się na liście.
                continue;
            }
            $quotes[(int)$p['days']][$mode] = $q;
            $spec = $spec ?? $q;
        }
    }

    if ($spec === null) {
        $error = 'Nie znaleziono takiej oferty. Wróć do cennika i wybierz plan jeszcze raz.';
    }
} catch (RuntimeException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('zamowienie: ' . $e->getMessage());
    $error = 'Nie udało się wczytać oferty. Spróbuj ponownie za chwilę.';
}

/* Niezalogowanego odsyłamy do logowania OD RAZU, zamiast pokazywać mu
   podsumowanie z przyciskiem "zaloguj się". Wybór planu jest w adresie,
   a return_to sprowadza go tu z powrotem dokładnie na to samo zamówienie.

   Dwa wyjątki, w których redirect byłby wysyłaniem po nic: gdy oferty nie
   udało się wczytać i gdy sprzedaż jest wyłączona. Wtedy lepiej od razu
   pokazać powód - po zalogowaniu i tak nie byłoby czego kupić. */
if (!$isLoggedIn && $error === null && stripe_sales_enabled()) {
    header('Location: /login?return_to=' . rawurlencode($returnTo));
    exit();
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

<section class="ord-head">
    <span class="cnk-eyebrow">Zamówienie</span>
    <h1 class="cnk-h1">Jeszcze tylko dwa wybory</h1>
</section>

<?php if ($error !== null): ?>
    <div class="cennik-info glass">
        <h2>Nie tędy droga</h2>
        <p><?php echo htmlspecialchars($error); ?></p>
        <p><a class="ord-back" href="/cennik">Wróć do cennika</a></p>
    </div>

<?php elseif (!stripe_sales_enabled()): ?>
    <?php /* Sprzedaż wyłączona to normalny stan tej strony (przerwa
             techniczna), więc mówimy o tym wprost zamiast pokazywać formularz,
             który i tak odbije się od API. */ ?>
    <div class="cennik-info glass">
        <h2>Sprzedaż jest chwilowo wyłączona</h2>
        <p>Trwa przerwa techniczna - nie przyjmujemy w tej chwili nowych zamówień.
           Konfigurację poniżej możesz sobie obejrzeć, ale przycisk płatności jest nieaktywny.</p>
        <p><a class="ord-back" href="/cennik">Wróć do cennika</a></p>
    </div>
<?php endif; ?>

<?php if ($error === null): ?>
<div class="ord-grid">

    <div class="ord-choices glass">
        <h2 class="ord-h2">Okres rozliczeniowy</h2>
        <div class="ord-options" data-role="periods">
            <?php foreach ($periods as $i => $p): ?>
                <?php if (!isset($quotes[(int)$p['days']])) { continue; } ?>
                <button type="button"
                        class="ord-option<?php echo $i === 1 ? ' is-active' : ''; ?>"
                        data-days="<?php echo (int)$p['days']; ?>">
                    <span class="ord-option-label"><?php echo htmlspecialchars((string)$p['label']); ?></span>
                    <?php if (!empty($p['badge'])): ?>
                        <span class="ord-option-badge"><?php echo htmlspecialchars((string)$p['badge']); ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <h2 class="ord-h2">Model rozliczenia</h2>
        <div class="ord-options ord-options-modes" data-role="modes">
            <button type="button" class="ord-option is-active" data-mode="payment">
                <span class="ord-option-label">Jednorazowo</span>
                <span class="ord-option-note">Płacisz raz. Przed końcem okresu przypomnimy o przedłużeniu.</span>
            </button>
            <button type="button" class="ord-option" data-mode="subscription">
                <span class="ord-option-label">Abonament</span>
                <span class="ord-option-note">Odnawia się sam. Rezygnujesz kiedy chcesz, bez pisania do nas.</span>
            </button>
        </div>

        <?php /* Uczciwie o ograniczeniu, ZANIM klient wybierze i zdziwi się
                 w Checkoucie, że nie ma BLIK-a. */ ?>
        <p class="ord-hint" data-role="methodsHint"></p>
    </div>

    <aside class="ord-summary glass">
        <h2 class="ord-h2">Podsumowanie</h2>

        <div class="ord-line"><span>Gra</span><strong><?php echo htmlspecialchars((string)$spec['game_name']); ?></strong></div>
        <div class="ord-line"><span>Pakiet</span><strong><?php echo htmlspecialchars((string)$spec['label']); ?></strong></div>
        <div class="ord-line"><span>RAM</span><strong><?php echo (int)$spec['ram_mb']; ?> MB</strong></div>
        <div class="ord-line"><span>Procesor</span><strong><?php echo rtrim(rtrim(number_format((float)$spec['cpu_cores'], 1, '.', ''), '0'), '.'); ?> vCore</strong></div>
        <div class="ord-line"><span>Dysk</span><strong><?php echo (int)$spec['ssd_mb']; ?> MB SSD</strong></div>
        <div class="ord-line"><span>Backupy</span><strong><?php echo (int)$spec['backups_limit']; ?></strong></div>
        <div class="ord-line"><span>Okres</span><strong data-role="sumPeriod"></strong></div>

        <div class="ord-total">
            <span data-role="sumPrice">-</span>
            <span class="ord-total-cur">zł</span>
            <span class="ord-total-per" data-role="sumPer"></span>
        </div>

        <?php if (!$isLoggedIn): ?>
            <?php /* Tu trafia tylko ktoś, kogo redirect wyżej przepuścił, czyli
                     przy wyłączonej sprzedaży. Mimo to niesiemy return_to -
                     żeby po włączeniu sprzedaży ta ścieżka też wracała na
                     właściwe zamówienie, a nie na stronę główną. */ ?>
            <a class="ord-cta" href="/login?return_to=<?php echo htmlspecialchars(rawurlencode($returnTo)); ?>">Zaloguj się, żeby zamówić</a>
            <p class="ord-hint">Serwer zakładamy na Twoim koncie w panelu - dlatego potrzebujemy logowania.</p>
        <?php else: ?>
            <?php $kodStartowy = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['kod'] ?? $_COOKIE['dnct_kod'] ?? '')) ?? ''); ?>
            <label class="ord-promo">
                <span>Kod rabatowy <small>(opcjonalnie)</small></span>
                <input type="text" data-role="promo" maxlength="64" autocomplete="off" spellcheck="false"
                       value="<?php echo htmlspecialchars(substr($kodStartowy, 0, 64)); ?>" placeholder="np. kod twórcy">
            </label>
            <fieldset class="ord-consents" data-role="consents">
                <legend>Warunki zamówienia</legend>
                <label class="ord-check">
                    <input type="checkbox" data-role="terms" required>
                    <span>Akceptuję <a href="/regulamin" target="_blank" rel="noopener">Regulamin DNCT</a>.</span>
                </label>
                <label class="ord-check ord-check-start">
                    <input type="checkbox" data-role="immediateStart">
                    <span>Żądam rozpoczęcia usługi niezwłocznie po potwierdzeniu płatności. Przyjmuję do wiadomości, że po pełnym wykonaniu usługi mogę utracić prawo odstąpienia, jeżeli są spełnione warunki ustawowe.</span>
                </label>
                <p class="ord-consents-hint">Bez tego żądania zapłacisz teraz, a usługę uruchomimy po 14 dniach od zawarcia umowy.</p>
            </fieldset>
            <button type="button" class="ord-cta" data-role="pay"
                    <?php echo stripe_sales_enabled() ? '' : 'disabled'; ?>>
                Przejdź do płatności
            </button>
            <p class="ord-error" data-role="error" hidden></p>
        <?php endif; ?>

        <p class="ord-legal">
            Płatność obsługuje Stripe - nie przechowujemy danych Twojej karty.
            <?php if (stripe_is_test_mode()): ?>
                <span class="ord-testmode">Tryb testowy - żadne pieniądze nie są pobierane.</span>
            <?php endif; ?>
        </p>
    </aside>
</div>

<?php /* Wszystkie warianty cenowe policzone po stronie serwera. Front wybiera
         z tej listy, a nie liczy. */ ?>
<script id="ord-data" type="application/json"><?php
    echo json_encode([
        'csrf'    => csrf_token(),
        'game'    => $spec['game_type'],
        'plan_id' => $spec['plan_id'],
        'slots'   => $spec['slots'],
        'sales_enabled' => stripe_sales_enabled(),
        'quotes'  => array_map(
            static fn(array $byMode): array => array_map(
                static fn(array $q): array => [
                    'amount' => pricing_format($q['amount_gross']),
                    'label'  => $q['period_label'],
                ],
                $byMode
            ),
            $quotes
        ),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script src="<?php echo asset('/assets/js/order.js'); ?>" defer></script>
<?php endif; ?>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
