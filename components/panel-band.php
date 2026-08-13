<?php
/**
 * Pasmo "Zobacz, zanim zapłacisz" - demo panelu, odręczny dopisek i konsola.
 *
 * Wspólne dla cennika i strony głównej. Wcześniej istniało tylko w cennik.php;
 * skopiowanie go na stronę główną dałoby dwie kopie tego samego bloku, a takie
 * pary rozjeżdżają się przy pierwszej poprawce - zwykle tak, że nikt tego nie
 * zauważa, dopóki klient nie zapyta, czemu na jednej podstronie pisze co innego.
 *
 * @param array{trailer?:string,panelUrl?:string} $opts
 */

declare(strict_types=1);

if (!function_exists('render_panel_band')) {

function render_panel_band(array $opts = []): void
{
    $trailerId = (string)($opts['trailer'] ?? '');
    $panelUrl  = (string)($opts['panelUrl'] ?? 'https://panel.dnct.pl');

    /* Zalogowanemu NIE proponujemy demo. Panel trzyma jedną sesję na
       przeglądarkę, więc wejście na demo wyrzuciłoby go z własnego konta -
       a i tak ma pod ręką coś lepszego niż konto poglądowe. */
    $panelLoggedIn = isset($_SESSION['authentik_username']);
    $demoReady     = function_exists('panel_demo_email') && panel_demo_email() !== '';

    if ($panelLoggedIn) {
        $ctaHref  = '/panel';
        $ctaLabel = 'Otwórz swój panel';
    } elseif ($demoReady) {
        // /demo wystawia jednorazowy bilet do panelu - patrz auth/demo.php.
        $ctaHref  = '/demo';
        $ctaLabel = 'Otwórz demo panelu';
    } else {
        // Demo nieskonfigurowane: zostaje sam panel, czyli ekran logowania.
        $ctaHref  = $panelUrl;
        $ctaLabel = 'Przejdź do panelu';
    }
?>
    <!-- ============ PANEL: DEMO + ZWIASTUN ============ -->
    <?php /* Największa dziura na tej stronie była właśnie tutaj: między
             planami a stopką klient nie dostawał żadnego powodu, żeby uwierzyć,
             że panel jest dobry. Cena i lista funkcji tego nie załatwiają -
             panel trzeba zobaczyć. */ ?>
    <section class="pnl-band">
        <div class="pnl-copy">
            <span class="cnk-eyebrow">Panel</span>
            <h2 class="cnk-h2">Zobacz, zanim zapłacisz</h2>
            <p class="pnl-lead">
                Konsola na żywo, pliki, backupy i harmonogramy - wszystko w jednym
                miejscu, po polsku. Wejdź i poklikaj, konto zakładasz dopiero wtedy,
                gdy Ci się spodoba.
            </p>

            <ul class="pnl-points">
                <li><span aria-hidden="true">▹</span> Konsola i FTP bez dodatkowych programów</li>
                <li><span aria-hidden="true">▹</span> Backupy i przywracanie jednym kliknięciem</li>
                <li><span aria-hidden="true">▹</span> Foldery na serwery i udostępnianie ich ekipie</li>
            </ul>

            <div class="pnl-actions">
                <a class="pnl-demo" href="<?php echo htmlspecialchars($ctaHref); ?>"
                   target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    <?php echo htmlspecialchars($ctaLabel); ?>
                </a>

                <?php /* Odręczna strzałka z podpisem. Rysowana ścieżką SVG,
                         nie obrazkiem - dzięki temu da się ją animować (linia
                         "dopisuje się" po wejściu w kadr) i nie trzeba trzymać
                         pliku. Podpis leci pod kątem, bo dopisek na marginesie
                         ma wyglądać na dopisany, a nie zaprojektowany. */ ?>
                <div class="pnl-scribble" aria-hidden="true">
                    <?php /* Grot jest na GÓRZE ścieżki, nie na końcu - strzałka
                             biegnie od podpisu w prawym dolnym rogu i celuje
                             w przycisk leżący bezpośrednio nad nią. */ ?>
                    <svg class="pnl-arrow" viewBox="0 0 160 90" fill="none">
                        <path class="pnl-arrow-line"
                              d="M156 78C124 86 86 83 58 70 34 59 21 41 22 16"
                              stroke="currentColor" stroke-width="2.8"
                              stroke-linecap="round" stroke-linejoin="round"/>
                        <path class="pnl-arrow-head"
                              d="M22 16 8 36M22 16l19 13"
                              stroke="currentColor" stroke-width="2.8"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="pnl-hand">sprawdź nasz panel!</span>
                </div>
            </div>
        </div>

        <div class="pnl-visual">
            <?php if ($trailerId !== ''): ?>
                <?php /* Podgląd zamiast osadzonego filmu - patrz komentarz przy
                         stałej CENNIK_TRAILER_YT. Iframe wstawia skrypt dopiero
                         po kliknięciu. */ ?>
                <button type="button" class="pnl-trailer" data-yt="<?php echo htmlspecialchars($trailerId); ?>">
                    <span class="pnl-trailer-play" aria-hidden="true"></span>
                    <span class="pnl-trailer-label">Obejrzyj zwiastun</span>
                </button>
            <?php else: ?>
                <?php /* Makieta konsoli. Wiersze wypisuje skrypt, żeby pasmo
                         żyło zamiast stać. Treść jest przykładowa i tak też jest
                         opisana w nagłówku okna - udawanie logu z prawdziwej
                         maszyny byłoby zwykłym kłamstwem. */ ?>
                <div class="pnl-console" data-role="console">
                    <div class="pnl-console-bar">
                        <span class="pnl-dot r"></span><span class="pnl-dot y"></span><span class="pnl-dot g"></span>
                        <span class="pnl-console-title">konsola - podgląd</span>
                    </div>
                    <pre class="pnl-console-body" data-role="consoleBody"></pre>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php
}

}
