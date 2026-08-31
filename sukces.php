<?php
/**
 * Strona powrotu ze Stripe Checkout - /sukces?session_id=cs_...
 *
 * TA STRONA NICZEGO NIE TWORZY. Serwer zakłada webhook; tutaj tylko odpytujemy
 * o stan zamówienia i pokazujemy, na czym stoi.
 *
 * Stara wersja robiła odwrotnie: cała logika zakładania serwera siedziała
 * w success.php i wykonywała się przy WYŚWIETLENIU strony. Konsekwencje były
 * dwie i obie bolały klienta - kto zamknął kartę zaraz po zapłaceniu, nie
 * dostawał nic, a odświeżenie strony potrafiło ruszyć zakładanie po raz drugi.
 *
 * Webhook często przychodzi po powrocie klienta, więc strona odpytuje stan co
 * sekundę zamiast od razu ogłaszać porażkę.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'Dziękujemy za zamówienie - DNCT Hosting';
$metaDescription = 'Potwierdzenie zamówienia serwera gry w DNCT Hosting.';
$centerContent   = false;

$sessionId = trim((string)($_GET['session_id'] ?? ''));

/* Rodzaj zamówienia ustalamy JUŻ TERAZ, po stronie serwera.
   Wiersz zamówienia powstaje w api/checkout/create.php, czyli zanim klient
   w ogóle trafi do Stripe'a - jest więc na pewno, gdy tu wraca. Bez tego
   strona witałaby każdego "zakładamy Twój serwer", także tego, kto właśnie
   dokupił miesiąc do działającej maszyny, a poprawny tekst pojawiałby się
   dopiero po pierwszej odpowiedzi z API. */
$kind = 'new';

if ($sessionId !== '') {
    $ownEmail = (string)($_SESSION['authentik_email'] ?? '');
    $o        = order_by_session(dnct_db(), $sessionId);

    if ($o !== null && $ownEmail !== '' && hash_equals((string)$o['user_email'], $ownEmail)) {
        $kind = (string)$o['kind'];
    }
}

$startCopy = [
    'new'         => ['Płatność przyjęta', 'Zakładamy Twój serwer - to zwykle kilkanaście sekund. Nie zamykaj tej strony.'],
    'extension'   => ['Płatność przyjęta', 'Dopisujemy kolejny okres do Twojego serwera - to potrwa chwilę.'],
    'plan_change' => ['Płatność przyjęta', 'Przestawiamy zasoby Twojego serwera - to potrwa chwilę.'],
][$kind] ?? ['Płatność przyjęta', 'Kończymy zamówienie - to potrwa chwilę.'];

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

<?php if ($sessionId === ''): ?>
    <div class="suc glass">
        <div class="suc-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
        </div>
        <h1>Brakuje identyfikatora płatności</h1>
        <p>Ten adres otwiera się normalnie po powrocie ze Stripe'a. Jeśli trafiłeś tu z zakładki,
           sprawdź swoje zamówienia albo napisz do nas na Discordzie.</p>
        <div class="suc-actions"><a class="suc-ghost" href="/cennik">Wróć do cennika</a></div>
    </div>

<?php else: ?>
    <div class="suc glass" data-role="card" data-session="<?php echo htmlspecialchars($sessionId, ENT_QUOTES); ?>">
        <div class="suc-icon" data-role="icon">
            <span class="suc-spin"></span>
        </div>

        <h1 data-role="title"><?php echo htmlspecialchars($startCopy[0]); ?></h1>
        <p data-role="text"><?php echo htmlspecialchars($startCopy[1]); ?></p>

        <div class="suc-actions" data-role="actions" hidden>
            <a class="suc-primary" href="https://panel.dnct.pl" target="_blank" rel="noopener">Otwórz panel</a>
            <?php /* Po przedłużeniu i zmianie pakietu klient wraca do swoich
                     serwerów, nie do cennika - kupił już to, po co przyszedł. */ ?>
            <?php if ($kind === 'new'): ?>
                <a class="suc-ghost" href="/cennik">Wróć do cennika</a>
            <?php else: ?>
                <a class="suc-ghost" href="/twoje-serwery">Twoje serwery</a>
            <?php endif; ?>
        </div>

        <div class="suc-meta" data-role="meta" hidden></div>

        <?php /* Wypełnia JS z pola "debug" w odpowiedzi /api/checkout/status.php -
                 tam obecne WYŁĄCZNIE gdy dnct_is_admin(), więc zwykły klient
                 nigdy nie dostaje tych danych, nawet w źródle strony. Aktualizuje
                 się co sekundę razem z resztą pollingu - widać na żywo, na jakim
                 etapie utknęło, bez zaglądania do bazy przez SSH. */ ?>
        <pre class="suc-debug" data-role="debug" hidden></pre>
    </div>

    <script src="<?php echo asset('/assets/js/success.js'); ?>" defer></script>
<?php endif; ?>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
