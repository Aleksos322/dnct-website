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
            <h2 class="cnk-h2">Zobacz, zanim zapłacisz</h2>
            <p class="pnl-lead">
                Konsola na żywo, pliki, backupy i harmonogramy - wszystko w jednym
                miejscu, po polsku. Wejdź i poklikaj, konto zakładasz dopiero wtedy,
                gdy Ci się spodoba.
            </p>

            <?php /* Punkty jako karty z ikonami (12.10.2026, układ z makiety 01).
                     Kliknięcie punktu przełącza makietę obok na pasującą
                     zakładkę - data-pnl-goto, obsługa w assets/js/panel-band.js. */ ?>
            <ul class="pnl-points">
                <li><button type="button" data-pnl-goto="konsola">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 17 6-6-6-6M12 19h8"/></svg>
                    <span>Konsola i FTP bez dodatkowych programów</span>
                </button></li>
                <li><button type="button" data-pnl-goto="backupy">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                    <span>Backupy i przywracanie jednym kliknięciem</span>
                </button></li>
                <li class="is-highlight"><button type="button" data-pnl-goto="udostepnij">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                    <span>Foldery na serwery i udostępnianie ich znajomym bez oddawania hasła <span class="pnl-badge">Tylko u nas</span></span>
                </button></li>
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
                <?php /* MAKIETA PANELU Z KLIKALNYMI ZAKŁADKAMI (12.10.2026).
                         Każda zakładka ma swoją scenkę pokazującą, JAK działa dana
                         funkcja (wgranie pliku, nowa baza, harmonogram, kopia...).
                         Zakładki przełączają się same co kilka sekund, dopóki
                         ktoś nie kliknie - wtedy zostaje to, co wybrał. Klik w
                         punkt po lewej (data-pnl-goto) też przełącza.

                         Wszystkie dane są przykładowe i tak opisane w pasku okna
                         ("podgląd") - prawdziwy panel jest w demo.

                         Animacje: CSS w cennik.css (blok "PANEL V2"), stan
                         KOŃCOWY jest stylem bazowym, a keyframes zaczynają się od
                         stanu początkowego. Bez JS i przy reduced-motion widać
                         więc od razu gotową scenkę. Logika: assets/js/panel-band.js. */ ?>
                <?php
                $pnlTabs = [
                    'konsola'      => 'Konsola',
                    'pliki'        => 'Pliki',
                    'bazy'         => 'Bazy danych',
                    'harmonogramy' => 'Harmonogramy',
                    'backupy'      => 'Backupy',
                    'subdomena'    => 'Subdomena',
                    'wersje'       => 'Wersje (MC)',
                    'udostepnij'   => 'Udostępnij',
                ];
                ?>
                <div class="pnl-mock" data-pnl-mock>
                    <nav class="pnl-mock-nav" role="tablist" aria-label="Zakładki panelu (podgląd)">
                        <?php $first = true; foreach ($pnlTabs as $key => $label): ?>
                            <button type="button" role="tab" class="pnl-mock-tab<?php echo $first ? ' is-active' : ''; ?>"
                                    data-pnl-tab="<?php echo $key; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($label); ?>
                                <i class="pnl-mock-prog" aria-hidden="true"></i>
                            </button>
                        <?php $first = false; endforeach; ?>
                    </nav>

                    <div class="pnl-mock-main">
                        <div class="pnl-mock-head">
                            <span><b data-pnl-title>Konsola</b> - podgląd</span>
                            <span class="pnl-mock-power">
                                <button type="button" class="is-start" data-pnl-power="start">Start</button>
                                <button type="button" data-pnl-power="restart">Restart</button>
                                <button type="button" data-pnl-power="stop">Stop</button>
                            </span>
                        </div>

                        <div class="pnl-mock-stage" aria-live="polite">
                            <!-- Konsola: wiersze dopisuje initConsole() -->
                            <div class="pnl-scene is-active" data-pnl-scene="konsola">
                                <pre class="pnl-console-body" data-role="consoleBody"></pre>
                            </div>

                            <!-- Pliki: wgranie pluginu -->
                            <div class="pnl-scene" data-pnl-scene="pliki">
                                <div class="pm-path">/home/container/<b>plugins</b></div>
                                <div class="pm-drop">
                                    <span class="pm-file-chip">EssentialsX.jar</span>
                                    <span class="pm-drop-txt">Wysyłanie...<b class="pm-ok">Wgrano</b></span>
                                    <span class="pm-bar"><i></i></span>
                                </div>
                                <ul class="pm-list">
                                    <li class="pm-new"><span class="pm-ico f"></span>EssentialsX.jar<em>1,2 MB</em></li>
                                    <li><span class="pm-ico f"></span>LuckPerms.jar<em>2,4 MB</em></li>
                                    <li><span class="pm-ico f"></span>WorldEdit.jar<em>3,1 MB</em></li>
                                    <li><span class="pm-ico d"></span>config/<em>14 plików</em></li>
                                </ul>
                            </div>

                            <!-- Bazy danych: nowa baza jednym kliknięciem -->
                            <div class="pnl-scene" data-pnl-scene="bazy">
                                <div class="pm-row-top"><span class="pm-muted">Bazy danych · 1 / 2</span><span class="pm-btn pm-press">+ Nowa baza</span></div>
                                <div class="pm-table">
                                    <div class="pm-th"><span>Nazwa</span><span>Użytkownik</span><span>Hasło</span></div>
                                    <div class="pm-tr pm-new"><span>s12_luckperms</span><span>u12_ekipa</span><span class="pm-pass">••••••••<b class="pm-copied">Skopiowano</b></span></div>
                                </div>
                                <p class="pm-hint">Host i dane logowania wklejasz prosto do configu pluginu.</p>
                            </div>

                            <!-- Harmonogramy: restart i kopia same o czasie -->
                            <div class="pnl-scene" data-pnl-scene="harmonogramy">
                                <div class="pm-task">
                                    <div><b>Restart serwera</b><span class="pm-muted">codziennie o 04:00 · <code>0 4 * * *</code></span></div>
                                    <span class="pm-toggle t1"><i></i></span>
                                </div>
                                <div class="pm-task">
                                    <div><b>Kopia zapasowa</b><span class="pm-muted">co 6 godzin · <code>0 */6 * * *</code></span></div>
                                    <span class="pm-toggle t2"><i></i></span>
                                </div>
                                <div class="pm-task">
                                    <div><b>Komunikat na czacie</b><span class="pm-muted">5 min przed restartem · <code>say ...</code></span></div>
                                    <span class="pm-toggle t3"><i></i></span>
                                </div>
                                <p class="pm-next">Następne uruchomienie za 3 h 12 min</p>
                            </div>

                            <!-- Backupy: kopia jednym kliknięciem -->
                            <div class="pnl-scene" data-pnl-scene="backupy">
                                <div class="pm-row-top"><span class="pm-muted">Kopie · 3 / 4</span><span class="pm-btn pm-press">Utwórz kopię</span></div>
                                <div class="pm-bk pm-new">
                                    <div><b>kopia-12-10</b><span class="pm-bk-st"><span class="pm-wait">Tworzenie...</span><span class="pm-done">412 MB · przed chwilą</span></span></div>
                                    <span class="pm-bar"><i></i></span>
                                </div>
                                <div class="pm-bk"><div><b>przed-aktualizacja</b><span class="pm-muted">398 MB · wczoraj</span></div><span class="pm-mini">Przywróć</span></div>
                                <div class="pm-bk"><div><b>auto-04-00</b><span class="pm-muted">405 MB · 2 dni temu</span></div><span class="pm-mini">Przywróć</span></div>
                            </div>

                            <!-- Subdomena: łatwy adres zamiast IP -->
                            <div class="pnl-scene" data-pnl-scene="subdomena">
                                <span class="pm-muted">Adres dla graczy</span>
                                <div class="pm-input"><span class="pm-type">ekipa</span><span class="pm-caret"></span><span class="pm-suffix">.dnct.pl</span></div>
                                <div class="pm-status"><span class="pm-wait">Sprawdzam...</span><span class="pm-done">✓ Adres wolny</span></div>
                                <div class="pm-result">
                                    <span class="pm-muted">Gracze łączą się przez</span>
                                    <b>ekipa.dnct.pl</b>
                                </div>
                            </div>

                            <!-- Wersje: zmiana silnika jednym kliknięciem -->
                            <div class="pnl-scene" data-pnl-scene="wersje">
                                <span class="pm-muted">Silnik</span>
                                <div class="pm-chips">
                                    <span class="pm-chip c-paper">Paper</span>
                                    <span class="pm-chip">Purpur</span>
                                    <span class="pm-chip c-fabric">Fabric</span>
                                    <span class="pm-chip">Forge</span>
                                    <span class="pm-chip">NeoForge</span>
                                    <span class="pm-chip">Vanilla</span>
                                </div>
                                <div class="pm-row-top"><span class="pm-select">Wersja <b>26.2</b> ▾</span><span class="pm-btn pm-press">Zainstaluj</span></div>
                                <span class="pm-bar"><i></i></span>
                                <div class="pm-status"><span class="pm-wait">Instaluję Fabric 26.2...</span><span class="pm-done">✓ Zainstalowano Fabric 26.2</span></div>
                                <p class="pm-hint">Przed zmianą silnika zrób kopię zapasową.</p>
                            </div>

                            <!-- Udostępnij: dostęp dla znajomego bez hasła -->
                            <div class="pnl-scene" data-pnl-scene="udostepnij">
                                <div class="pm-folder"><b>Folder „Ekipa”</b><span class="pm-muted">Survival · Kreatywny</span><span class="pnl-badge">Tylko u nas</span></div>
                                <div class="pm-input pm-input-sm"><span class="pm-muted">Zaproś:</span><span class="pm-type pm-type-k">kasia</span><span class="pm-caret"></span></div>
                                <div class="pm-friend pm-new">
                                    <span class="pm-av">K</span>
                                    <b>kasia</b>
                                    <span class="pm-perm p1">Konsola</span>
                                    <span class="pm-perm p2">Pliki</span>
                                    <span class="pm-perm p3">Start / Stop</span>
                                </div>
                                <p class="pm-hint">Kasia dostaje tylko te uprawnienia - Twoje hasło zostaje u Ciebie.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php
}

}
