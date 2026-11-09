<?php
/**
 * Kalkulator zasobów Minecrafta - przycisk w cenniku i okienko (<dialog>).
 *
 * Prośba klienta (2026-11-09): "ile RAM-u potrzebuje mój serwer?" to pierwsze
 * pytanie przed zakupem, a sama lista planów na nie nie odpowiada. Kalkulator
 * bierze wersję, silnik, liczbę graczy, pluginów/modów i view-distance, liczy
 * przybliżone RAM i CPU i wskazuje NAJMNIEJSZY pakiet, który to udźwignie.
 *
 * Model liczący żyje WYŁĄCZNIE w assets/js/cennik-kalkulator.js (jedno miejsce
 * na współczynniki). Tu jest tylko markup i lista planów z bazy - ta sama,
 * z której powstają karty, więc kalkulator nie poleci planu, którego nie ma.
 *
 * Okienko renderujemy POZA .cnk-catalogue. Katalog łapie kółko myszy w fazie
 * capture (cennik-showcase.js) i przewijałby karty planów zamiast treści okna.
 */

declare(strict_types=1);

if (!function_exists('render_mc_calculator')) {

/**
 * Przycisk otwierający kalkulator. Stoi w .cnk-plan-copy pod "Pomiń plany".
 *
 * Dwa wyglądy z jednego markupu: domyślnie skromny link w linii z "Pomiń
 * plany" (zero dodatkowej wysokości), a karta dopiero, gdy pod spodem jest
 * miejsce - decyduje cennik-kalkulator.js (klasa .is-card). Przy oknie
 * 1536x730 wolnego miejsca jest 4 px, a każdy piksel więcej wyłącza
 * przypięty, przewijany katalog (warunek w syncLayout, cennik-showcase.js).
 */
function render_mc_calculator_trigger(): void
{
    ?>
    <?php /* Materiał boxa = nowe, płaskie szkło jak kafel "4,9 opinii w Google"
             (.op-pasek-wynik): jedna barwa .04, ramka .09, rozmycie. Bez klasy
             .glass - jej wypukły gradient z krawędziami to stary styl. */ ?>
    <button type="button" class="mc-calc-open" data-mc-calc-open aria-haspopup="dialog" aria-controls="mc-calc">
        <span class="mc-calc-open-in">
            <span class="mc-calc-open-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M8.5 6.5h7M8.5 11h.01M12 11h.01M15.5 11h.01M8.5 14.5h.01M12 14.5h.01M15.5 14.5h.01M8.5 18h.01M12 18h3.5"/></svg>
            </span>
            <span class="mc-calc-open-txt">
                <b>Kalkulator</b>
                <span>Ile RAM-u i CPU potrzebujesz?</span>
            </span>
            <span class="mc-calc-open-arrow" aria-hidden="true">→</span>
        </span>
    </button>
    <?php
}

/**
 * Okienko kalkulatora.
 *
 * @param array $plans   plany Minecrafta w kolejności kart (cena rosnąco)
 * @param bool  $salesOn czy pokazywać "Zamawiam" (ten sam warunek co karty)
 */
function render_mc_calculator(array $plans, bool $salesOn): void
{
    if ($plans === []) {
        return;
    }

    // Tylko to, czego potrzebuje model i karta wyniku. Indeks = pozycja karty
    // w karuzeli, żeby "Pokaż w cenniku" mogło kliknąć [data-catalogue-go].
    $data = [];
    foreach (array_values($plans) as $k => $p) {
        $data[] = [
            'id'      => (int)$p['id'],
            'idx'     => $k,
            'name'    => (string)$p['name'],
            'ram'     => round((int)$p['ram_mb'] / 1024, 2),
            'cpu'     => (float)$p['cpu_cores'],
            'price'   => (float)$p['price'],
            'label'   => cennik_price_label($p['price']),
            'soldOut' => !empty($p['sold_out']),
        ];
    }

    $versions = [
        '26'   => '26.x - najnowsze',
        '1.21' => '1.21.x',
        '1.20' => '1.20.x',
        '1.19' => '1.19.x',
        '1.18' => '1.18.x',
        '1.17' => '1.17.x',
        '1.16' => '1.16.x',
        '1.13' => '1.13 - 1.15',
        '1.12' => '1.12.2',
        '1.8'  => '1.8 - 1.11',
        '1.7'  => '1.7.10 i starsze',
    ];
    $engines = [
        'paper'    => ['Paper', 'pluginy'],
        'purpur'   => ['Purpur', 'pluginy'],
        'spigot'   => ['Spigot', 'pluginy'],
        'vanilla'  => ['Vanilla', 'czysty'],
        'fabric'   => ['Fabric', 'mody'],
        'forge'    => ['Forge', 'mody'],
        'neoforge' => ['NeoForge', 'mody'],
    ];
    ?>
<dialog id="mc-calc" class="mc-calc glass" aria-labelledby="mc-calc-title" aria-describedby="mc-calc-lead" data-lenis-prevent<?php echo $salesOn ? ' data-sales="1"' : ''; ?>>
    <div class="mc-calc-shell glass-content">
        <header class="mc-calc-head">
            <div>
                <span class="mc-calc-label">Kalkulator zasobów<span> · Minecraft</span></span>
                <h2 id="mc-calc-title">Ile RAM-u i CPU potrzebuje Twój serwer?</h2>
                <p id="mc-calc-lead">Opisz serwer, a policzymy przybliżone zapotrzebowanie i podpowiemy pakiet.</p>
            </div>
            <button type="button" class="mc-calc-close" data-mc-calc-close aria-label="Zamknij kalkulator">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>

        <div class="mc-calc-body" data-mc-calc-body>
            <form class="mc-calc-form" data-mc-calc-form>
                <div class="mc-calc-field">
                    <label class="mc-calc-label" for="mc-calc-version">Wersja Minecrafta</label>
                    <div class="mc-calc-select">
                        <select id="mc-calc-version" name="version">
                            <?php foreach ($versions as $value => $label): ?>
                                <?php /* (string): klucz '26' PHP trzyma jako int, a strict_types nie przepuści go do htmlspecialchars(). */ ?>
                                <option value="<?php echo htmlspecialchars((string)$value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <fieldset class="mc-calc-field">
                    <legend class="mc-calc-label">Silnik serwera</legend>
                    <div class="mc-calc-engines">
                        <?php foreach ($engines as $value => [$label, $kind]): ?>
                            <label class="mc-calc-engine">
                                <input type="radio" name="engine" value="<?php echo $value; ?>"<?php echo $value === 'paper' ? ' checked' : ''; ?>>
                                <span><b><?php echo htmlspecialchars($label); ?></b><small><?php echo htmlspecialchars($kind); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="mc-calc-hint" data-mc-calc-engine-hint></p>
                </fieldset>

                <div class="mc-calc-field mc-calc-num" data-mc-calc-num="players">
                    <div class="mc-calc-num-head">
                        <label class="mc-calc-label" for="mc-calc-players">Gracze online naraz</label>
                        <input type="number" id="mc-calc-players" name="players" min="1" max="300" step="1" value="10" inputmode="numeric">
                    </div>
                    <input type="range" class="mc-calc-range" min="0" max="1" step="1" value="0" aria-label="Gracze online naraz - suwak" aria-describedby="mc-calc-players-hint">
                    <p class="mc-calc-hint" id="mc-calc-players-hint">Ilu gra jednocześnie, a nie ilu jest na whiteliście.</p>
                </div>

                <div class="mc-calc-field mc-calc-num" data-mc-calc-num="addons">
                    <div class="mc-calc-num-head">
                        <label class="mc-calc-label" for="mc-calc-addons" data-mc-calc-addons-label>Liczba pluginów</label>
                        <input type="number" id="mc-calc-addons" name="addons" min="0" max="500" step="1" value="10" inputmode="numeric">
                    </div>
                    <input type="range" class="mc-calc-range" min="0" max="1" step="1" value="0" aria-label="Liczba pluginów lub modów - suwak" aria-describedby="mc-calc-addons-hint">
                    <p class="mc-calc-hint" id="mc-calc-addons-hint" data-mc-calc-addons-hint>Policz wszystkie pliki .jar w folderze plugins.</p>
                </div>

                <div class="mc-calc-field mc-calc-num" data-mc-calc-num="view">
                    <div class="mc-calc-num-head">
                        <label class="mc-calc-label" for="mc-calc-view">Zasięg widzenia <span class="mc-calc-nowrap">(view-distance)</span></label>
                        <input type="number" id="mc-calc-view" name="view" min="2" max="32" step="1" value="10" inputmode="numeric">
                    </div>
                    <input type="range" class="mc-calc-range" min="0" max="1" step="1" value="0" aria-label="Zasięg widzenia - suwak" aria-describedby="mc-calc-view-hint">
                    <p class="mc-calc-hint" id="mc-calc-view-hint">W chunkach. Domyślnie 10, duże serwery zwykle ustawiają 6-8.</p>
                </div>
            </form>

            <section class="mc-calc-result" aria-labelledby="mc-calc-result-title" data-mc-calc-result>
                <h3 class="mc-calc-label" id="mc-calc-result-title">Szacunek</h3>
                <div class="mc-calc-stats">
                    <div class="mc-calc-stat">
                        <span>RAM</span>
                        <strong><small class="mc-calc-approx">ok.</small><span data-mc-calc-ram>-</span> <small>GB</small></strong>
                        <em data-mc-calc-ram-plan></em>
                        <div class="mc-calc-bar" data-mc-calc-bar aria-hidden="true">
                            <i class="is-server"></i><i class="is-players"></i><i class="is-addons"></i>
                        </div>
                    </div>
                    <div class="mc-calc-stat">
                        <span>Procesor</span>
                        <strong><small class="mc-calc-approx">ok.</small><span data-mc-calc-cpu>-</span> <small>vCore</small></strong>
                        <em data-mc-calc-cpu-plan></em>
                        <div class="mc-calc-cores" data-mc-calc-cores aria-hidden="true"></div>
                    </div>
                </div>
                <ul class="mc-calc-legend">
                    <li class="is-server"><span>Serwer i świat</span> <b data-mc-calc-part="server">-</b></li>
                    <li class="is-players"><span>Gracze i chunki</span> <b data-mc-calc-part="players">-</b></li>
                    <li class="is-addons"><span data-mc-calc-addons-part>Pluginy</span> <b data-mc-calc-part="addons">-</b></li>
                    <li class="is-free"><span>Zapas w pakiecie</span> <b data-mc-calc-part="free">-</b></li>
                </ul>

                <div class="mc-calc-plan" data-mc-calc-plan>
                    <span class="mc-calc-label">Pasujący pakiet</span>
                    <div class="mc-calc-plan-head">
                        <strong data-mc-calc-plan-name>-</strong>
                        <span class="mc-calc-plan-price"><b data-mc-calc-plan-price>-</b> zł<small>/ mies.</small></span>
                    </div>
                    <p class="mc-calc-plan-specs"><span data-mc-calc-plan-ram>-</span> GB RAM · <span data-mc-calc-plan-cpu>-</span> vCore</p>
                    <p class="mc-calc-plan-msg" data-mc-calc-plan-msg hidden></p>
                    <div class="mc-calc-actions">
                        <button type="button" class="mc-calc-btn" data-mc-calc-show>Pokaż w cenniku</button>
                        <?php if ($salesOn): ?>
                            <a class="mc-calc-btn is-primary" data-mc-calc-order href="/zamowienie">Zamawiam</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mc-calc-plan is-none" data-mc-calc-none hidden>
                    <span class="mc-calc-label">Pasujący pakiet</span>
                    <p>Żaden gotowy pakiet tego nie udźwignie. Napisz do nas - dobierzemy serwer pod Twój projekt.</p>
                    <div class="mc-calc-actions">
                        <a class="mc-calc-btn is-primary" href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Napisz na Discordzie</a>
                    </div>
                </div>

                <div class="mc-calc-note" role="note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                    <div>
                        <b>To szacunek, nie pomiar</b>
                        <ul>
                            <li data-mc-calc-addon-note><strong>Plugin pluginowi nierówny</strong> - jeden Dynmap zje więcej niż kilkanaście drobnych.</li>
                            <li><strong>Gracz graczowi nierówny</strong> - eksplorujący obciąża serwer bardziej niż stojący na spawnie.</li>
                            <li data-mc-calc-extra hidden></li>
                        </ul>
                        <p>Pakiet zmienisz później w Twoich serwerach - przy większym dopłacasz tylko różnicę za pozostałe dni.</p>
                    </div>
                </div>
            </section>
        </div>

        <button type="button" class="mc-calc-peek" data-mc-calc-peek aria-label="Przewiń do wyniku">
            <span><b data-mc-calc-peek-ram>-</b> GB · <b data-mc-calc-peek-cpu>-</b> vCore</span>
            <span class="mc-calc-peek-plan" data-mc-calc-peek-plan></span>
            <span aria-hidden="true">↓</span>
        </button>
        <p class="mc-calc-sr" aria-live="polite" data-mc-calc-live></p>
    </div>
    <script type="application/json" id="mc-calc-plans"><?php echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
</dialog>
    <?php
}

}
