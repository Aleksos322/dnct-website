<?php
/**
 * Sekcja "Opinie z Google" - nad pasmem Discorda na stronie głównej.
 *
 * Dane: backend/lib/google_reviews.php (Places API, cache 6 h, więc nowa
 * opinia pojawia się sama). Brak klucza, brak opinii albo błąd Google =
 * sekcja znika w całości, strona wygląda jak przed jej dodaniem.
 *
 * Kolejność losuje przeglądarka (assets/js/opinie-google.js) przy każdym
 * wejściu - serwer daje zawsze ten sam HTML, więc cache po drodze nie
 * zamraża jednego układu. Taśma przewija się w pętli; najechanie myszą
 * albo fokus ją zatrzymuje, prefers-reduced-motion zamienia ją w zwykłe
 * przewijanie palcem.
 *
 * Średnia i liczba opinii są ze WSZYSTKICH opinii, a link prowadzi do pełnej
 * listy w Google - na taśmie są tylko opinie z treścią i 4-5 gwiazdkami.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/lib/google_reviews.php';

$opinieGoogle = null;
try {
    $opinieGoogle = google_reviews();
} catch (Throwable $e) {
    error_log('opinie-google: ' . $e->getMessage());
}

if ($opinieGoogle === null) {
    return;
}

/** Bezpośredni formularz "Napisz opinię" w wizytówce DNCT. */
const OPINIE_LINK_WYSTAW = 'https://g.page/r/CRwe2fxVy-gHEBM/review';

/** "1 opinia", "3 opinie", "5 opinii". */
$opinieOdmiana = static function (int $n): string {
    if ($n === 1) {
        return 'opinia';
    }
    $d = $n % 10;
    $s = $n % 100;
    return ($d >= 2 && $d <= 4 && ($s < 12 || $s > 14)) ? 'opinie' : 'opinii';
};
$opinieGwiazdki = static fn(int $n): string => str_repeat('★', $n) . str_repeat('☆', 5 - $n);
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/opinie-google.css'); ?>">
<section class="opinie py-20 w-full relative overflow-hidden" aria-labelledby="opinie-tytul">
    <div class="opinie-naglowek px-6 max-w-5xl mx-auto" data-reveal>
        <div>
            <span class="opinie-eyebrow">Opinie z Google</span>
            <h2 id="opinie-tytul" class="opinie-tytul">Co mówią gracze</h2>
        </div>
        <div class="opinie-ocena-obok">
            <?php /* Odręczny dopisek jak "sprawdź nasz panel!" w paśmie panelu
                     (cennik.css .pnl-scribble) - strzałka celuje w ocenę,
                     sam napis prowadzi prosto do formularza opinii. */ ?>
            <a class="opinie-scribble" href="<?php echo htmlspecialchars(OPINIE_LINK_WYSTAW); ?>" target="_blank" rel="noopener">
                <span class="opinie-hand">zostaw nam opinię!</span>
                <svg class="opinie-arrow" viewBox="0 0 90 56" fill="none" aria-hidden="true">
                    <path class="opinie-arrow-line" d="M4 50C26 56 52 50 68 34 76 26 80 18 81 8"
                          stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path class="opinie-arrow-head" d="M81 8 69 21M81 8l5 17"
                          stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <a class="opinie-ocena glass" href="<?php echo htmlspecialchars($opinieGoogle['url']); ?>" target="_blank" rel="noopener">
                <strong><?php echo number_format($opinieGoogle['rating'], 1, ',', ''); ?></strong>
                <span>
                    <span class="opinie-gwiazdki" aria-hidden="true"><?php echo $opinieGwiazdki((int)round($opinieGoogle['rating'])); ?></span>
                    <span class="opinie-ocena-opis"><?php echo $opinieGoogle['count'] . ' ' . $opinieOdmiana($opinieGoogle['count']); ?> w Google <span aria-hidden="true">→</span></span>
                </span>
            </a>
        </div>
    </div>

    <div class="opinie-tasma" data-opinie>
        <ul class="opinie-tor" data-opinie-tor>
            <?php foreach ($opinieGoogle['reviews'] as $o): ?>
                <li class="opinie-karta glass">
                    <div class="opinie-autor">
                        <?php if ($o['zdjecie'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars($o['zdjecie']); ?>" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <span class="opinie-inicjal" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($o['autor'], 0, 1))); ?></span>
                        <?php endif; ?>
                        <div>
                            <?php if ($o['profil'] !== ''): ?>
                                <a href="<?php echo htmlspecialchars($o['profil']); ?>" target="_blank" rel="noopener nofollow"><?php echo htmlspecialchars($o['autor']); ?></a>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($o['autor']); ?></span>
                            <?php endif; ?>
                            <small><?php echo htmlspecialchars($o['kiedy']); ?></small>
                        </div>
                    </div>
                    <p class="opinie-gwiazdki" aria-label="<?php echo $o['gwiazdki']; ?> na 5 gwiazdek"><?php echo $opinieGwiazdki($o['gwiazdki']); ?></p>
                    <blockquote><?php echo nl2br(htmlspecialchars($o['tekst'])); ?></blockquote>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <p class="opinie-zrodlo px-6">Opinie pobierane automatycznie z Google</p>
</section>
<script src="<?php echo asset('/assets/js/opinie-google.js'); ?>" defer></script>
