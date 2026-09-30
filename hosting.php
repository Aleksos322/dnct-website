<?php
/**
 * /hosting - hosting WWW: pakiety, zakup, stan konta, przedłużenie.
 *
 * Kwoty pochodzą z HOSTING_PLANS (backend/lib/hosting_orders.php) - tego
 * samego miejsca, z którego api/checkout/hosting.php wystawia cenę Stripe'owi.
 * Pakiet zmienia wyłącznie webhook; ta strona tylko pokazuje stan.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/hosting_orders.php';

$pageTitle       = 'Hosting WWW - DNCT Hosting';
$metaDescription = 'Hosting stron WWW z pocztą i bazami danych. Jedno konto DNCT do stron, poczty i serwerów gier.';
$centerContent   = false;
// Ta sama strona jest główną web.dnct.pl (.htaccess, reguła 3b) - to adres, który ma być w wynikach wyszukiwania.
$canonicalUrl    = 'https://web.dnct.pl/';

$isLoggedIn = isset($_SESSION['authentik_email'], $_SESSION['authentik_username']);
$enabled    = hosting_sales_enabled() && stripe_sales_enabled();
$email      = (string)($_SESSION['authentik_email'] ?? '');

$current = null;
$last    = null;
if ($isLoggedIn && hosting_enabled()) {
    try {
        $dnct    = dnct_db();
        $current = hosting_current_order($dnct, $email);
        $last    = hosting_last_order($dnct, $email);
    } catch (Throwable $e) {
        error_log('hosting.php: ' . $e->getMessage());
    }
}

// Powrót z płatności: webhook zwykle jest szybszy od przeglądarki, ale nie zawsze.
$justPaid = isset($_GET['oplacono']);
$waiting  = $justPaid && $last !== null && in_array($last['status'], ['pending', 'paid'], true);

$fmt = static fn(int $gr): string => number_format($gr / 100, 2, ',', ' ');

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/hosting.css'); ?>">
<?php if ($waiting): ?><meta http-equiv="refresh" content="4"><?php endif; ?>

<div class="cennik-page">
<div class="cnk-wrap">

<section class="ord-head">
    <span class="cnk-eyebrow">Hosting WWW</span>
    <h1 class="cnk-h1">Strony, poczta i bazy<br>na jednym koncie DNCT</h1>
    <p class="cnk-lead">Logujesz się tym samym kontem co do panelu gier. WordPress, sklep albo własny kod,
        darmowy adres w dnct.pl albo własna domena, certyfikat SSL i poczta w pakiecie.</p>
    <?php /* Dla obecnych klientów - także tych logujących się hasłem panelu (np. strony
             przeniesione ze starego hostingu), których web.panel-dnct.pl bez sesji DNCT
             odsyła właśnie tutaj. */ ?>
    <p class="hst-mam"><a href="https://web.panel-dnct.pl/login">Masz już hosting? Zaloguj się do panelu &rarr;</a></p>
</section>

<?php if ($waiting): ?>
    <div class="cennik-info glass hst-state">
        <h2>Płatność przyjęta - włączamy pakiet</h2>
        <p>To zwykle kilka sekund. Strona odświeży się sama.</p>
    </div>
<?php elseif ($justPaid && $last !== null && $last['status'] === 'failed'): ?>
    <div class="cennik-info glass hst-state">
        <h2>Płatność doszła, pakiet jeszcze nie</h2>
        <p>Automat nie zdołał włączyć pakietu - zajmiemy się tym ręcznie i damy znać w powiadomieniach.</p>
    </div>
<?php endif; ?>

<?php if ($current !== null):
    $plan      = HOSTING_PLANS[$current['package']] ?? ['name' => $current['package']];
    $suspended = !empty($current['suspended_at']);
    $isSub     = $current['billing_mode'] === 'subscription';
?>
    <div class="hst-mine glass">
        <div class="hst-mine-head">
            <div>
                <span class="cnk-eyebrow">Twój hosting</span>
                <h2 class="ord-h2">Pakiet <?php echo htmlspecialchars($plan['name']); ?></h2>
            </div>
            <span class="hst-badge <?php echo $suspended ? 'is-off' : 'is-on'; ?>"><?php echo $suspended ? 'zawieszony' : 'aktywny'; ?></span>
        </div>
        <div class="ord-line"><span><?php echo $isSub ? 'Następne odnowienie' : 'Opłacone do'; ?></span><strong><?php echo htmlspecialchars(hosting_fmt_date((string)$current['expires_at'])); ?></strong></div>
        <div class="ord-line"><span>Rozliczenie</span><strong><?php echo $isSub ? 'abonament miesięczny' : 'jednorazowo, 30 dni'; ?></strong></div>
        <?php if (!empty($current['grace_until'])): ?>
            <p class="ord-error">Ostatnia płatność nie przeszła. Zawieszenie: <?php echo htmlspecialchars(hosting_fmt_date((string)$current['grace_until'])); ?>.</p>
        <?php endif; ?>
        <div class="hst-actions">
            <a class="ord-cta" href="<?php echo htmlspecialchars(hosting_public_url()); ?>" target="_blank" rel="noopener">Otwórz panel hostingu</a>
            <?php if (!$isSub): ?>
                <button type="button" class="ord-cta hst-cta-ghost" data-hosting-buy data-extend="1" data-mode="payment" <?php echo $enabled ? '' : 'disabled'; ?>>
                    Przedłuż o 30 dni - <?php echo $fmt((int)$plan['price']); ?> zł
                </button>
            <?php endif; ?>
        </div>
        <p class="ord-error" data-hosting-error hidden></p>
    </div>
<?php endif; ?>

<?php if (!$enabled): ?>
    <div class="cennik-info glass hst-state">
        <h2>Sprzedaż hostingu jeszcze nie ruszyła</h2>
        <p>Pakiety możesz obejrzeć już teraz - zakup włączymy wkrótce.</p>
    </div>
<?php endif; ?>

<?php if ($current === null): ?>
<div class="hst-modes" role="radiogroup" aria-label="Model rozliczenia">
    <button type="button" class="ord-option is-active" data-hosting-mode="payment">
        <span class="ord-option-label">Jednorazowo</span>
        <span class="ord-option-note">30 dni. Przypomnimy o przedłużeniu.</span>
    </button>
    <button type="button" class="ord-option" data-hosting-mode="subscription">
        <span class="ord-option-label">Abonament</span>
        <span class="ord-option-note">Odnawia się sam co miesiąc.</span>
    </button>
</div>
<?php endif; ?>

<div class="hst-plans">
    <?php foreach (HOSTING_PLANS as $key => $p): ?>
        <article class="hst-plan glass<?php echo $key === 'dnct-pro' ? ' is-featured' : ''; ?>">
            <?php if ($key === 'dnct-pro'): ?><span class="ord-option-badge">Najczęściej wybierany</span><?php endif; ?>
            <h2 class="hst-plan-name"><?php echo htmlspecialchars($p['name']); ?></h2>
            <div class="ord-total">
                <span><?php echo $fmt((int)$p['price']); ?></span>
                <span class="ord-total-cur">zł</span>
                <span class="ord-total-per">/ 30 dni</span>
            </div>
            <ul class="hst-list">
                <li><strong><?php echo (int)$p['sites']; ?></strong> <?php echo $p['sites'] === 1 ? 'strona' : 'stron'; ?> WWW</li>
                <li><strong><?php echo (int)$p['disk_gb']; ?> GB</strong> dysku SSD</li>
                <li><strong><?php echo (int)$p['mailboxes']; ?></strong> skrzynek pocztowych</li>
                <li><strong><?php echo (int)$p['databases']; ?></strong> baz danych</li>
                <li>SSL, kopie zapasowe, WordPress jednym kliknięciem</li>
            </ul>
            <?php if ($current !== null): ?>
                <p class="ord-hint"><?php echo $current['package'] === $key ? 'Twój obecny pakiet.' : 'Zmiana pakietu - napisz do nas, rozliczymy różnicę.'; ?></p>
            <?php elseif (!$isLoggedIn): ?>
                <a class="ord-cta" href="https://dnct.pl/login?return_to=%2Fhosting">Zaloguj się, żeby zamówić</a>
            <?php else: ?>
                <button type="button" class="ord-cta" data-hosting-buy data-package="<?php echo htmlspecialchars($key); ?>" <?php echo $enabled ? '' : 'disabled'; ?>>Wybieram <?php echo htmlspecialchars($p['name']); ?></button>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php if ($current === null): ?><p class="ord-error hst-error" data-hosting-error hidden></p><?php endif; ?>

<p class="ord-legal hst-legal">
    Ceny brutto. Klikając „Wybieram”, akceptujesz <a href="/regulamin">Regulamin</a> (pkt 15-17) i zawierasz
    <a href="/umowa-powierzenia">umowę powierzenia</a> danych umieszczanych w hostingu, a także żądasz rozpoczęcia usługi
    przed upływem 14 dni na odstąpienie od umowy (przy odstąpieniu zwracamy kwotę za niewykorzystany okres). Płatność obsługuje Stripe.
    Po terminie hosting jest zawieszany, a nie kasowany - po opłaceniu wraca od razu.
    <?php if (stripe_is_test_mode()): ?><span class="ord-testmode">Tryb testowy - żadne pieniądze nie są pobierane.</span><?php endif; ?>
</p>

</div>
</div>

<script>
(function () {
    var csrf = <?php echo json_encode(csrf_token()); ?>;
    var mode = 'payment';
    document.querySelectorAll('[data-hosting-mode]').forEach(function (b) {
        b.addEventListener('click', function () {
            mode = b.getAttribute('data-hosting-mode');
            document.querySelectorAll('[data-hosting-mode]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        });
    });
    document.querySelectorAll('[data-hosting-buy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            var label = btn.textContent;
            var err = document.querySelector('[data-hosting-error]');
            // Blokada na czas żądania: podwójne kliknięcie = dwie sesje Stripe.
            document.querySelectorAll('[data-hosting-buy]').forEach(function (x) { x.disabled = true; });
            btn.textContent = 'Przekierowuję do płatności…';
            if (err) err.hidden = true;
            fetch('/api/checkout/hosting.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrf,
                    package: btn.getAttribute('data-package') || '',
                    extend: btn.getAttribute('data-extend') || '0',
                    billing_mode: btn.getAttribute('data-mode') || mode
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (b) {
                    if (b.ok && b.url) { window.location.href = b.url; return; }
                    throw new Error(b.message || 'Nie udało się rozpocząć płatności.');
                })
                .catch(function (e) {
                    if (err) { err.textContent = e.message || 'Brak połączenia z serwerem.'; err.hidden = false; }
                    document.querySelectorAll('[data-hosting-buy]').forEach(function (x) { x.disabled = false; });
                    btn.textContent = label;
                });
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
