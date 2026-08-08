<?php
/**
 * Konfigurator serwera rozliczanego za liczbę slotów (Project Zomboid,
 * Vintage Story).
 *
 * Model inny niż przy Minecrafcie: zamiast gotowych planów klient wybiera
 * suwakiem liczbę graczy, a próg slotów decyduje o RAM-ie, CPU i cenie.
 *
 * Progi i okresy przychodzą z bazy (hosting_slot_tiers, hosting_periods).
 * W starej wersji ta sama tabela była zaszyta w dwóch plikach PHP i jeszcze
 * raz w JavaScripcie karty — trzy kopie, które prędzej czy później musiały się
 * rozjechać. Tutaj JavaScript dostaje dane z PHP, więc źródło jest jedno.
 *
 * @param string $type    Typ gry (hosting_games.type)
 * @param array  $tiers   Progi slotów, posortowane rosnąco
 * @param array  $periods Okresy rozliczeniowe
 */
function render_slot_configurator(string $type, array $tiers, array $periods): void
{
    if (!$tiers || !$periods) {
        return;
    }

    // Domyślnie środkowy próg — start od najtańszego zaniża postrzeganą wartość,
    // a od najdroższego odstrasza ceną, zanim klient zdąży cokolwiek policzyć.
    $defaultIndex = (int)floor(count($tiers) / 3);
    $defaultTier  = $tiers[$defaultIndex];

    // Miesiąc jako domyślny okres, a gdyby go nie było - pierwszy z listy.
    $defaultPeriod = $periods[0];
    foreach ($periods as $p) {
        if ((int)$p['days'] === 30) {
            $defaultPeriod = $p;
            break;
        }
    }

    $cfgId = 'cfg-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="cfg glass" id="<?php echo $cfgId; ?>"
         data-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
         data-tiers='<?php echo htmlspecialchars(json_encode(array_map(static fn(array $t): array => [
             'slots' => (int)$t['slots'],
             'ram'   => (float)$t['ram_gb'],
             'cpu'   => (float)$t['cpu_cores'],
             'price' => (float)$t['price'],
         ], $tiers), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
         data-periods='<?php echo htmlspecialchars(json_encode(array_map(static fn(array $p): array => [
             'days'       => (int)$p['days'],
             'label'      => $p['label'],
             'multiplier' => (float)$p['multiplier'],
             'adjust'     => (float)$p['adjust'],
             'badge'      => $p['badge'],
         ], $periods), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>

        <div class="cfg-grid">
            <!-- ============ LEWA STRONA: wybór ============ -->
            <div class="cfg-controls">
                <div class="cfg-block">
                    <div class="cfg-label">Liczba graczy</div>
                    <div class="cfg-slots">
                        <span class="cfg-slots-num" data-role="slots"><?php echo (int)$defaultTier['slots']; ?></span>
                        <span class="cfg-slots-unit">slotów</span>
                    </div>

                    <input type="range" class="cfg-range" data-role="range"
                           min="0" max="<?php echo count($tiers) - 1; ?>" step="1"
                           value="<?php echo $defaultIndex; ?>"
                           aria-label="Liczba slotów">

                    <div class="cfg-range-ends">
                        <span><?php echo (int)$tiers[0]['slots']; ?></span>
                        <span><?php echo (int)$tiers[count($tiers) - 1]['slots']; ?></span>
                    </div>

                    <?php /* Podpowiedź kontekstowa. Klient nietechniczny nie wie,
                             ile slotów mu potrzeba, a niepewność kończy się
                             zamknięciem karty częściej niż wyborem za dużego planu. */ ?>
                    <div class="cfg-hint" data-role="hint"></div>
                </div>

                <div class="cfg-block">
                    <div class="cfg-label">Okres rozliczeniowy</div>
                    <div class="cfg-periods" data-role="periods">
                        <?php foreach ($periods as $p): ?>
                            <button type="button"
                                    class="cfg-period<?php echo (int)$p['days'] === (int)$defaultPeriod['days'] ? ' is-active' : ''; ?>"
                                    data-days="<?php echo (int)$p['days']; ?>">
                                <span class="cfg-period-label"><?php echo htmlspecialchars($p['label']); ?></span>
                                <?php if (!empty($p['badge'])): ?>
                                    <span class="cfg-period-badge"><?php echo htmlspecialchars($p['badge']); ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ============ PRAWA STRONA: wynik ============ -->
            <div class="cfg-summary">
                <div class="cfg-specs">
                    <div class="cfg-spec">
                        <span class="cfg-spec-key">RAM</span>
                        <span class="cfg-spec-val" data-role="ram"></span>
                    </div>
                    <div class="cfg-spec">
                        <span class="cfg-spec-key">CPU</span>
                        <span class="cfg-spec-val" data-role="cpu"></span>
                    </div>
                </div>

                <div class="cfg-price-wrap">
                    <?php /* Cena przekreślona pokazuje się tylko wtedy, gdy okres
                             faktycznie jest tańszy niż ta sama liczba miesięcy
                             kupowana pojedynczo. Przy miesiącu nie ma czego
                             przekreślać, więc znika. */ ?>
                    <div class="cfg-price-old" data-role="oldPrice"></div>
                    <div class="cfg-price">
                        <span data-role="price"></span>
                        <span class="cfg-price-cur">zł</span>
                    </div>
                    <div class="cfg-price-per" data-role="pricePer"></div>
                    <div class="cfg-save" data-role="save"></div>
                </div>

                <button type="button" class="cfg-select" data-role="select">
                    Wybieram tę konfigurację
                </button>
            </div>
        </div>
    </div>
    <?php
}
