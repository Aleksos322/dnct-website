<?php
/**
 * Pasmo "Jak to działa" - animowana scenka od wyboru planu do działającego
 * serwera, w czterech krokach. Czysty HTML/CSS + mały skrypt, bez pliku wideo:
 * ostre na każdym ekranie, nic nie waży i nie rusza wyniku PageSpeed.
 *
 * Ta sama scenka służy do nagrywania rolek (Playwright -> MP4), więc kolejne
 * kroki zmienia wyłącznie klasa .is-active na .jtd-step i .jtd-scene -
 * skrypt (assets/js/jak-to-dziala.js) niczego nie rysuje.
 *
 * Bez JS albo przy prefers-reduced-motion widać krok pierwszy i klikalną
 * listę kroków - żadna treść nie jest dostępna wyłącznie w animacji.
 */

declare(strict_types=1);

if (!function_exists('render_jak_to_dziala')) {

function render_jak_to_dziala(): void
{
    $steps = [
        ['Wybierasz plan', 'Gra, RAM i okres - cena od razu na ekranie.'],
        ['Płacisz', 'BLIK, Przelewy24 albo karta. Abonament lub jednorazowo.'],
        ['Serwer sam się zakłada', 'Bez czekania na admina - instalacja rusza zaraz po płatności.'],
        ['Grasz', 'Adres serwera dostajesz od razu, konsola i pliki są w panelu.'],
    ];
?>
    <section class="jtd" aria-labelledby="jtd-title" data-jtd>
        <div class="jtd-copy">
            <span class="cnk-eyebrow">Jak to działa</span>
            <h2 class="cnk-h2" id="jtd-title">Od kliknięcia do gry</h2>
            <p class="jtd-lead">
                Serwer zakłada się automatycznie, zaraz po płatności.
                Nikt nie musi go ręcznie uruchamiać ani wysyłać Ci danych.
            </p>
        </div>

        <ol class="jtd-steps">
                <?php foreach ($steps as $i => [$title, $desc]): ?>
                    <li>
                        <button type="button" class="jtd-step<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                data-step="<?php echo $i; ?>" aria-controls="jtd-stage">
                            <span class="jtd-num"><?php echo sprintf('%02d', $i + 1); ?></span>
                            <span class="jtd-step-text">
                                <strong><?php echo htmlspecialchars($title); ?></strong>
                                <span><?php echo htmlspecialchars($desc); ?></span>
                            </span>
                            <span class="jtd-bar" aria-hidden="true"><i></i></span>
                        </button>
                    </li>
                <?php endforeach; ?>
        </ol>

        <div class="jtd-stage" id="jtd-stage" aria-live="polite">
            <div class="jtd-window">
                <div class="jtd-titlebar" aria-hidden="true">
                    <i></i><i></i><i></i>
                    <span class="jtd-url">dnct.pl</span>
                </div>

                <!-- 1. Wybór planu -->
                <div class="jtd-scene is-active" data-scene="0">
                    <div class="jtd-plans">
                        <div class="jtd-plan">
                            <span class="jtd-label">Minecraft</span>
                            <strong>2 GB</strong>
                            <span class="jtd-muted">do 10 graczy</span>
                        </div>
                        <div class="jtd-plan jtd-plan-pick">
                            <span class="jtd-label">Minecraft</span>
                            <strong>4 GB</strong>
                            <span class="jtd-muted">do 20 graczy</span>
                            <span class="jtd-pick">Wybrany</span>
                        </div>
                        <div class="jtd-plan">
                            <span class="jtd-label">Minecraft</span>
                            <strong>8 GB</strong>
                            <span class="jtd-muted">modpacki</span>
                        </div>
                    </div>
                    <span class="jtd-cursor" aria-hidden="true"></span>
                </div>

                <!-- 2. Płatność -->
                <div class="jtd-scene" data-scene="1">
                    <div class="jtd-pay">
                        <span class="jtd-label">Kod BLIK</span>
                        <div class="jtd-blik" aria-hidden="true">
                            <i>4</i><i>8</i><i>2</i><i>9</i><i>1</i><i>6</i>
                        </div>
                        <div class="jtd-paid">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            Opłacono
                        </div>
                    </div>
                </div>

                <!-- 3. Instalacja -->
                <div class="jtd-scene" data-scene="2" data-url="panel.dnct.pl">
                    <div class="jtd-console" aria-hidden="true">
                        <p><b>dnct@hosting</b> Instalacja serwera Paper...</p>
                        <p><b>dnct@hosting</b> Pobieranie paper-26.2.jar</p>
                        <p>[INFO] Starting minecraft server version 26.2</p>
                        <p>[INFO] Preparing level "world"</p>
                        <p class="jtd-ok">[INFO] Done (6.2s)! For help, type "help"</p>
                    </div>
                </div>

                <!-- 4. Gra -->
                <div class="jtd-scene" data-scene="3" data-url="panel.dnct.pl">
                    <div class="jtd-live">
                        <div class="jtd-live-head">
                            <span class="jtd-dot" aria-hidden="true"></span>
                            <span class="jtd-label">Serwer działa</span>
                        </div>
                        <div class="jtd-addr">
                            <span class="jtd-muted">Adres serwera</span>
                            <strong>twojserwer.dnct.pl</strong>
                        </div>
                        <div class="jtd-meters" aria-hidden="true">
                            <div><span class="jtd-muted">Gracze</span><strong class="jtd-count">0 / 20</strong></div>
                            <div><span class="jtd-muted">RAM</span><span class="jtd-meter"><i></i></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php
}

}
