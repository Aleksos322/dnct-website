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
 * Strona NICZEGO nie liczy od nowa. Kwoty pochodzą z `amount_gross` zapisanego
 * w chwili zakupu - gdyby cennik się zmienił, historia ma pokazywać to, co
 * klient FAKTYCZNIE zapłacił, a nie ile kosztowałoby to dzisiaj.
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

        foreach ($rows as $r) {
            $suma += (int)$r['amount_gross'];
        }
    } catch (Throwable $e) {
        error_log('oplaty: ' . $e->getMessage());
        $error = 'Nie udało się wczytać historii. Spróbuj ponownie za chwilę.';
    }
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

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/cennik.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/servers.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('/assets/css/oplaty.css'); ?>">

<div class="cennik-page">
<div class="cnk-wrap">

<section class="ord-head">
    <span class="cnk-eyebrow">Rozliczenia</span>
    <h1 class="cnk-h1">Historia opłat</h1>
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
             wchodzący na taką stronę: ile mnie to w sumie kosztowało. */ ?>
    <div class="op-sum glass">
        <div class="op-sum-item">
            <span class="op-sum-label">Płatności</span>
            <strong class="op-sum-value"><?php echo count($rows); ?></strong>
        </div>
        <div class="op-sum-item">
            <span class="op-sum-label">Łącznie</span>
            <?php /* Kwoty zostają w złotych także w EN/DE (data-i18n-pln,
                     assets/js/i18n.js) - tyle naprawdę pobrał Stripe. */ ?>
            <strong class="op-sum-value" data-i18n-pln><span><?php echo pricing_format($suma); ?></span> <span>zł</span></strong>
        </div>
    </div>

    <div class="op-list glass">
        <?php /* Nagłówek tabeli tylko na szerokim ekranie - na telefonie każdy
                 wiersz jest osobną kartą i etykiety niosą same wartości. */ ?>
        <div class="op-row op-head" aria-hidden="true">
            <span>Data</span>
            <span>Czego dotyczy</span>
            <span>Okres</span>
            <span>Rozliczenie</span>
            <span class="op-right">Kwota</span>
        </div>

        <?php foreach ($rows as $r): ?>
            <?php
            [$rodzajNazwa, $rodzajKlasa] = oplaty_rodzaj((string)$r['kind']);
            $gra = ucfirst((string)($r['parent_game'] ?? $r['game_type']));
            ?>
            <div class="op-row">
                <span class="op-date" data-label="Data">
                    <?php echo czas_fmt((string)$r['created_at'], 'd.m.Y'); ?>
                    <small><?php echo czas_fmt((string)$r['created_at'], 'H:i'); ?></small>
                </span>

                <span class="op-what" data-label="Czego dotyczy">
                    <span class="op-kind <?php echo $rodzajKlasa; ?>"><?php echo $rodzajNazwa; ?></span>
                    <span class="op-game"><?php echo htmlspecialchars($gra); ?></span>
                </span>

                <span data-label="Okres"><?php echo oplaty_okres((int)$r['period_days']); ?></span>

                <span data-label="Rozliczenie">
                    <?php echo $r['billing_mode'] === 'subscription' ? 'Abonament' : 'Jednorazowo'; ?>
                </span>

                <span class="op-amount op-right" data-label="Kwota" data-i18n-pln>
                    <span><?php echo pricing_format((int)$r['amount_gross']); ?></span> zł
                </span>
            </div>
        <?php endforeach; ?>
    </div>

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
