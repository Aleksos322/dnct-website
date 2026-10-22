<?php
/**
 * Historia opłat - /oplaty
 *
 * Odpowiada na jedno pytanie: „za co i kiedy zapłaciłem?".
 *
 * W przeciwieństwie do /twoje-serwery pokazuje WSZYSTKIE opłacone zamówienia,
 * także przedłużenia i zmiany pakietu - tam były zdarzeniami w życiu usługi,
 * tutaj są osobnymi pozycjami rachunku. Pomijamy natomiast porzucone sesje
 * płatności (`pending`): klient ich nie opłacił, więc na liście wydatków
 * byłyby tylko niepokojącym szumem.
 *
 * Strona NICZEGO nie liczy od nowa. Kwoty pochodzą z zamówienia: `amount_paid`
 * (to, co pobrał Stripe, po kodzie rabatowym), a bez niego `amount_gross` z chwili
 * zakupu - historia ma pokazywać to, co klient FAKTYCZNIE zapłacił.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'Historia opłat - DNCT Hosting';
$metaDescription = 'Twoje płatności w DNCT Hosting - kwoty, daty i okresy rozliczeniowe.';
$centerContent   = false;

$isLoggedIn = isset($_SESSION['authentik_email'], $_SESSION['authentik_username']);

$rows  = [];
$suma  = 0;
$error = null;

if ($isLoggedIn) {
    try {
        $stmt = dnct_db()->prepare(
            'SELECT o.*, p.game_type AS parent_game
               FROM orders o
          LEFT JOIN orders p ON p.id = o.extends_order_id
              WHERE o.user_email = ?
                AND o.status IN ("paid", "provisioned", "cancelled")
              ORDER BY o.created_at DESC, o.id DESC'
        );
        $stmt->execute([(string)$_SESSION['authentik_email']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* Sumy z kwot FAKTYCZNIE zapłaconych - przy kodzie rabatowym
           amount_gross to cena z cennika, a nie to, co pobrał Stripe. */
        foreach ($rows as &$r) {
            order_fill_paid_amount(dnct_db(), $r);
            $suma += order_paid_amount($r);
        }
        unset($r);
    } catch (Throwable $e) {
        error_log('oplaty: ' . $e->getMessage());
        $error = 'Nie udało się wczytać historii. Spróbuj ponownie za chwilę.';
    }
}

/** Pełna nazwa gry ("Project Zomboid"), nie typ z bazy ("zomboid"). */
function oplaty_gra(string $type): string
{
    return ['minecraft' => 'Minecraft', 'zomboid' => 'Project Zomboid', 'vintagestory' => 'Vintage Story'][$type] ?? ucfirst($type);
}

/** Ikona gry - te same pliki co chipy na stronie głównej. */
function oplaty_ikona(string $type): ?string
{
    $plik = '/assets/media/icons/gry/' . basename($type) . '.webp';
    return is_file(__DIR__ . $plik) ? $plik : null;
}

/** Nazwa miesiąca w mianowniku, do nagłówków grup. */
function oplaty_miesiac(string $data): string
{
    $m = ['', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
    return $m[(int)czas_fmt($data, 'n')] . ' ' . czas_fmt($data, 'Y');
}

/** Nazwa rodzaju zamówienia - w języku klienta, nie bazy danych. */
function oplaty_rodzaj(string $kind): array
{
    return [
        'new'         => ['Nowy serwer',    'op-kind-new'],
        'extension'   => ['Przedłużenie',   'op-kind-ext'],
        'plan_change' => ['Zmiana pakietu', 'op-kind-plan'],
    ][$kind] ?? ['Zamówienie', 'op-kind-new'];
}

/** Okres w formie, w jakiej klient go wybierał. */
function oplaty_okres(int $days): string
{
    if ($days % 365 === 0) {
        $n = intdiv($days, 365);
        return $n === 1 ? 'rok' : $n . ' lata';
    }
    if ($days % 30 === 0) {
        $n = intdiv($days, 30);
        return $n === 1 ? 'miesiąc' : $n . ' miesiące';
    }
    if ($days % 7 === 0) {
        $n = intdiv($days, 7);
        return $n === 1 ? 'tydzień' : $n . ' tygodnie';
    }

    return $days . ' dni';
}

/* Eksport CSV (?csv=1): te same wiersze co tabela, do arkusza albo księgowości.
   Średnik i BOM, bo tak plik otwiera polski Excel bez kreatora importu. */
if ($isLoggedIn && $error === null && isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dnct-oplaty-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Zamówienie', 'Rodzaj', 'Gra', 'Okres', 'Rozliczenie', 'Cena z cennika (zł)', 'Kod rabatowy', 'Rabat (zł)', 'Zapłacono brutto (zł)'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            czas_fmt((string)$r['created_at'], 'Y-m-d H:i'),
            '#' . (int)$r['id'],
            oplaty_rodzaj((string)$r['kind'])[0],
            oplaty_gra((string)($r['parent_game'] ?? $r['game_type'])),
            oplaty_okres((int)$r['period_days']),
            $r['billing_mode'] === 'subscription' ? 'Abonament' : 'Jednorazowo',
            pricing_format((int)$r['amount_gross']),
            (string)($r['promo_code'] ?? ''),
            pricing_format(order_discount_amount($r)),
            pricing_format(order_paid_amount($r)),
        ], ';');
    }
    exit;
}

$rokTeraz = (int)czas_fmt_ts(time(), 'Y');
$sumaRok  = 0;
$grupy    = [];
foreach ($rows as $r) {
    if ((int)czas_fmt((string)$r['created_at'], 'Y') === $rokTeraz) {
        $sumaRok += order_paid_amount($r);
    }
    $klucz = czas_fmt((string)$r['created_at'], 'Y-m');
    $grupy[$klucz]['nazwa']    ??= oplaty_miesiac((string)$r['created_at']);
    $grupy[$klucz]['suma']     = ($grupy[$klucz]['suma'] ?? 0) + order_paid_amount($r);
    $grupy[$klucz]['wiersze'][] = $r;
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/order.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/servers.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/oplaty.css'); ?>">

<div class="cennik-page srv-strona op-strona">
<div class="cnk-wrap">

<section class="ord-head srv-top">
    <div>
        <span class="cnk-eyebrow">Rozliczenia</span>
        <h1 class="cnk-h1">Historia opłat</h1>
    </div>
    <?php if ($isLoggedIn): ?>
        <div class="srv-top-akcje">
            <a class="srv-btn is-link" href="/twoje-serwery">Twoje serwery</a>
            <?php if ($rows): ?><a class="srv-btn is-primary" href="/oplaty?csv=1">Pobierz CSV</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!$isLoggedIn): ?>
    <div class="cennik-info glass">
        <h2>Zaloguj się, żeby zobaczyć swoje płatności</h2>
        <p>Historia jest przypisana do konta - bez logowania nie mamy czego pokazać.</p>
        <p><a class="ord-back" href="/login?return_to=<?php echo rawurlencode('/oplaty'); ?>">Przejdź do logowania</a></p>
    </div>

<?php elseif ($error !== null): ?>
    <div class="cennik-info glass">
        <h2>Nie tędy droga</h2>
        <p><?php echo htmlspecialchars($error); ?></p>
    </div>

<?php elseif (!$rows): ?>
    <div class="cennik-info glass">
        <h2>Jeszcze nic tu nie ma</h2>
        <p>Nie znaleźliśmy żadnej opłaconej usługi na tym koncie. Gdy zamówisz serwer,
           każda płatność pojawi się na tej liście.</p>
        <p><a class="ord-back" href="/cennik">Zobacz cennik</a></p>
    </div>

<?php else: ?>

    <?php /* Podsumowanie na górze, bo to pierwsza rzecz, o którą pyta człowiek
             wchodzący na taką stronę: ile mnie to w sumie kosztowało.
             Kwoty zostają w złotych także w EN/DE (data-i18n-pln,
             assets/js/i18n.js) - tyle naprawdę pobrał Stripe. */ ?>
    <ul class="srv-podsumowanie glass" aria-label="Podsumowanie">
        <li><strong data-i18n-pln><?php echo pricing_format($suma); ?> zł</strong><span>łącznie</span></li>
        <li><strong data-i18n-pln><?php echo pricing_format($sumaRok); ?> zł</strong><span>w <?php echo $rokTeraz; ?> roku</span></li>
        <li><strong><?php echo count($rows); ?></strong><span>płatności</span></li>
        <li><strong><?php echo czas_fmt((string)$rows[0]['created_at'], 'd.m.Y'); ?></strong><span>ostatnia płatność</span></li>
    </ul>

    <?php foreach ($grupy as $g): ?>
    <section class="op-grupa">
        <h2 class="op-miesiac"><?php echo htmlspecialchars($g['nazwa']); ?> <span data-i18n-pln><?php echo pricing_format((int)$g['suma']); ?> zł</span></h2>
        <div class="op-list glass">
            <?php foreach ($g['wiersze'] as $r):
                [$rodzajNazwa, $rodzajKlasa] = oplaty_rodzaj((string)$r['kind']);
                $typ   = (string)($r['parent_game'] ?? $r['game_type']);
                $ikona = oplaty_ikona($typ);
            ?>
            <div class="op-row">
                <span class="op-what" data-label="Czego dotyczy">
                    <?php if ($ikona): ?><img class="op-ikona" src="<?php echo asset($ikona); ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?>
                    <span class="op-what-tekst">
                        <strong class="op-game"><?php echo htmlspecialchars(oplaty_gra($typ)); ?></strong>
                        <span class="op-meta">
                            <span class="op-kind <?php echo $rodzajKlasa; ?>"><?php echo $rodzajNazwa; ?></span>
                            Zamówienie #<?php echo (int)$r['id']; ?><?php if (!empty($r['extends_order_id'])): ?> · do #<?php echo (int)$r['extends_order_id']; ?><?php endif; ?>
                        </span>
                    </span>
                </span>

                <span class="op-date" data-label="Data">
                    <?php echo czas_fmt((string)$r['created_at'], 'd.m.Y'); ?>
                    <small><?php echo czas_fmt((string)$r['created_at'], 'H:i'); ?></small>
                </span>

                <span class="op-okres" data-label="Okres">
                    <?php echo oplaty_okres((int)$r['period_days']); ?>
                    <small><?php echo $r['billing_mode'] === 'subscription' ? 'abonament' : 'jednorazowo'; ?></small>
                </span>

                <span class="op-amount op-right" data-label="Kwota" data-i18n-pln>
                    <?php if (order_discount_amount($r) > 0): ?><s class="op-cena-stara"><?php echo pricing_format((int)$r['amount_gross']); ?> zł</s><?php endif; ?>
                    <span><?php echo pricing_format(order_paid_amount($r)); ?> zł</span>
                    <?php if (order_discount_amount($r) > 0): ?><small class="op-rabat">kod <?php echo htmlspecialchars((string)$r['promo_code']); ?></small><?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <p class="op-note">
        Faktury i potwierdzenia płatności znajdziesz w wiadomościach od
        <strong>info@dnct.pl</strong>. Rozliczenia obsługuje Stripe - nie przechowujemy
        danych Twojej karty.
    </p>

<?php endif; ?>

</div><!-- /.cnk-wrap -->
</div><!-- /.cennik-page -->
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
