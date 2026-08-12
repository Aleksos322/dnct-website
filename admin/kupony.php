<?php
/**
 * Panel kuponów promocyjnych — /admin/kupony
 *
 * Generowanie kodów i podgląd istniejących. Kody są zakładane W STRIPE, więc
 * działają naprawdę: klient wpisuje je w Checkoucie, a zniżkę, limit użyć
 * i termin ważności pilnuje Stripe (patrz backend/lib/coupons.php).
 *
 * DOSTĘP: wyłącznie superuser z Authentika, ta sama zasada co w
 * admin/notifications/send.php. Docelowo ta strona ma zamieszkać na
 * admin.dnct.pl, wystawionym tylko po LAN-ie — do tego czasu jedyną barierą
 * jest logowanie, więc trzymamy ją poza indeksowaniem i bez odnośników
 * z żadnej publicznej podstrony.
 */

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/lib/coupons.php';
require_once __DIR__ . '/../backend/lib/notifications.php';

if (!isset($_SESSION['authentik_username']) || !dnct_is_admin()) {
    http_response_code(403);
    exit('Brak dostępu.');
}

$dnct    = dnct_db();
$notice  = null;
$problem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check_request()) {
        $problem = 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['action'] ?? '') === 'deactivate') {
        $res = coupon_deactivate($dnct, (int)($_POST['id'] ?? 0));
        $res['ok'] ? $notice = 'Kupon wyłączony.' : $problem = $res['error'];
    } else {
        $res = coupon_create($dnct, $_POST, (string)$_SESSION['authentik_username']);
        $res['ok']
            ? $notice = 'Kupon ' . $res['code'] . ' gotowy — można go już wpisywać przy płatności.'
            : $problem = $res['error'];
    }
}

// Licznik użyć rośnie po stronie Stripe'a i nie ma zdarzenia, które by o nim
// mówiło — odświeżamy go przy wejściu na listę.
coupons_sync($dnct);
$coupons = coupons_list($dnct);

$pageTitle     = 'Kupony — DNCT';
$centerContent = false;

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/admin.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

    <section class="ord-head">
        <span class="cnk-eyebrow">Administracja</span>
        <h1 class="cnk-h1">Kupony promocyjne</h1>
        <?php if (stripe_is_test_mode()): ?>
            <p class="adm-testbadge">Klucze testowe — kupony powstają na testowym koncie Stripe.</p>
        <?php endif; ?>
    </section>

    <?php if ($notice !== null): ?>
        <div class="adm-flash is-ok"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>
    <?php if ($problem !== null): ?>
        <div class="adm-flash is-bad"><?php echo htmlspecialchars($problem); ?></div>
    <?php endif; ?>

    <div class="ord-grid">
        <form class="ord-choices glass" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">

            <h2 class="ord-h2">Nowy kupon</h2>

            <label class="adm-field">
                <span>Kod</span>
                <input type="text" name="code" required maxlength="60" placeholder="LATO2026"
                       pattern="[A-Za-z0-9_-]{3,60}" autocomplete="off">
                <small>To wpisuje klient przy płatności. Litery, cyfry, myślnik i podkreślnik.</small>
            </label>

            <?php /* Dwa pola, z których wypełnia się DOKŁADNIE jedno. Walidacja
                     po stronie serwera i tak to sprawdza — tutaj chodzi tylko
                     o to, żeby było widać, że to alternatywa. */ ?>
            <div class="adm-pair">
                <label class="adm-field">
                    <span>Zniżka procentowa</span>
                    <input type="number" name="percent_off" min="1" max="100" step="1" placeholder="20">
                    <small>np. 20 = −20%</small>
                </label>
                <label class="adm-field">
                    <span>albo kwotowa (zł)</span>
                    <input type="number" name="amount_off" min="0.01" step="0.01" placeholder="10.00">
                    <small>np. 10 = −10 zł</small>
                </label>
            </div>

            <label class="adm-field">
                <span>Jak długo obowiązuje</span>
                <select name="duration">
                    <option value="once">Jednorazowo — tylko pierwsza płatność</option>
                    <option value="repeating">Przez kilka miesięcy abonamentu</option>
                    <option value="forever">Zawsze — każde odnowienie</option>
                </select>
                <small>Przy płatności jednorazowej wszystkie trzy działają tak samo.</small>
            </label>

            <label class="adm-field">
                <span>Liczba miesięcy (dla „przez kilka miesięcy")</span>
                <input type="number" name="duration_months" min="1" max="36" step="1" placeholder="3">
            </label>

            <div class="adm-pair">
                <label class="adm-field">
                    <span>Limit użyć</span>
                    <input type="number" name="max_redemptions" min="1" step="1" placeholder="bez limitu">
                </label>
                <label class="adm-field">
                    <span>Ważny do</span>
                    <input type="datetime-local" name="redeem_by">
                </label>
            </div>

            <button type="submit" class="ord-cta">Utwórz kupon</button>
        </form>

        <aside class="ord-summary glass">
            <h2 class="ord-h2">Jak to działa</h2>
            <p class="ord-hint">
                Kupon powstaje w Stripe, nie u nas. Klient wpisuje kod w polu „kod rabatowy"
                na stronie płatności, a Stripe sam sprawdza jego ważność, limit użyć i przelicza kwotę.
            </p>
            <p class="ord-hint">
                Wyłączenie kuponu blokuje kod dla nowych zakupów. Osoby, które mają już
                abonament z tą zniżką, zachowują ją do końca — dlatego wyłączamy kod,
                a nie kasujemy zniżki.
            </p>
        </aside>
    </div>

    <section class="adm-list glass">
        <h2 class="ord-h2">Istniejące kupony (<?php echo count($coupons); ?>)</h2>

        <?php if (!$coupons): ?>
            <p class="ord-hint">Jeszcze żadnego nie ma.</p>
        <?php else: ?>
            <div class="adm-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Kod</th><th>Zniżka</th><th>Użycia</th>
                            <th>Ważny do</th><th>Autor</th><th>Stan</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($coupons as $c): ?>
                        <tr class="<?php echo $c['active'] ? '' : 'is-off'; ?>">
                            <td><code><?php echo htmlspecialchars((string)$c['code']); ?></code></td>
                            <td><?php echo htmlspecialchars(coupon_describe($c)); ?></td>
                            <td>
                                <?php echo (int)$c['times_redeemed']; ?>
                                <?php echo $c['max_redemptions'] !== null ? ' / ' . (int)$c['max_redemptions'] : ''; ?>
                            </td>
                            <td><?php echo $c['redeem_by'] !== null ? htmlspecialchars((string)$c['redeem_by']) : 'bezterminowo'; ?></td>
                            <td><?php echo htmlspecialchars((string)($c['created_by'] ?? '—')); ?></td>
                            <td>
                                <span class="adm-pill <?php echo $c['active'] ? 'is-ok' : 'is-off'; ?>">
                                    <?php echo $c['active'] ? 'aktywny' : 'wyłączony'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($c['active']): ?>
                                    <form method="post" onsubmit="return confirm('Wyłączyć kupon <?php echo htmlspecialchars((string)$c['code'], ENT_QUOTES); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                        <button type="submit" class="adm-off-btn">Wyłącz</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/../assets/clear/clear.php';
