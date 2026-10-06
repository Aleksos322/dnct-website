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
 * raz w JavaScripcie karty - trzy kopie, które prędzej czy później musiały się
 * rozjechać. Tutaj JavaScript dostaje dane z PHP, więc źródło jest jedno.
 *
 * Płatność startuje WPROST z tej karty (fetch do api/checkout/create.php,
 * patrz initConfigurator() w cennik.js) - nie ma już pośredniego ekranu
 * /zamowienie z tymi samymi pytaniami o okres i model rozliczenia zadanymi
 * drugi raz. /zamowienie zostaje wyłącznie dla gotowych planów (karty
 * Minecrafta), które tego wyboru tutaj nie mają.
 *
 * @param string $type     Typ gry (hosting_games.type)
 * @param array  $tiers    Progi slotów, posortowane rosnąco
 * @param array  $periods  Okresy rozliczeniowe
 * @param string $gameName Nazwa gry do wyświetlenia w podsumowaniu
 * @param bool   $isLoggedIn Czy jest komu założyć zamówienie (patrz cennik.php)
 * @param bool   $salesOn    Czy przycisk płatności ma być klikalny
 */
function render_slot_configurator(
    string $type,
    array $tiers,
    array $periods,
    string $gameName = '',
    bool $isLoggedIn = false,
    bool $salesOn = true
): void {
    if (!$tiers || !$periods) {
        return;
    }

    // Domyślnie środkowy próg - start od najtańszego zaniża postrzeganą wartość,
    // a od najdroższego odstrasza ceną, zanim klient zdąży cokolwiek policzyć.
    $defaultIndex = (int)floor(count($tiers) / 3);

    // Ten próg akurat wyprzedany? Bierzemy najbliższy WOLNY, żeby strona nie
    // witała odwiedzającego stanem "brak miejsca" na starcie. Gdy WSZYSTKIE
    // progi są wyprzedane, zostaje przy pierwotnym wyborze - i tak nie ma
    // czego pokazać jako dostępne.
    if (!empty($tiers[$defaultIndex]['sold_out'])) {
        foreach ($tiers as $i => $t) {
            if (empty($t['sold_out'])) {
                $defaultIndex = $i;
                break;
            }
        }
    }
    $defaultTier = $tiers[$defaultIndex];

    // Miesiąc jako domyślny okres, a gdyby go nie było - pierwszy z listy.
    $defaultPeriod = $periods[0];
    foreach ($periods as $p) {
        if ((int)$p['days'] === 30) {
            $defaultPeriod = $p;
            break;
        }
    }

    $cfgId = 'cfg-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');

    // Po zalogowaniu Authentik odsyła dokładnie tutaj, na tę samą kartę gry -
    // dobór slotów i okresu w formularzu i tak wraca do wartości domyślnych,
    // ale klient trafia z powrotem na właściwą zakładkę, a nie na górę strony.
    $loginUrl = '/login?return_to=' . rawurlencode('/cennik#' . $cfgId);
    ?>
    <div class="cfg" id="<?php echo $cfgId; ?>"
         data-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
         data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>"
         data-login-url="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>"
         data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"
         data-tiers='<?php echo htmlspecialchars(json_encode(array_map(static fn(array $t): array => [
             'slots'    => (int)$t['slots'],
             'ram'      => (float)$t['ram_gb'],
             'cpu'      => (float)$t['cpu_cores'],
             'disk'     => (int)$t['ssd_mb'],
             'backups'  => (int)$t['backups_limit'],
             'price'    => (float)$t['price'],
             // Dokladane przez annotate_tier_availability() w cennik.php - nie
             // liczymy tego tutaj, zeby nie miec DRUGIEJ kopii logiki pojemnosci
             // wezla obok backend/lib/capacity.php.
             'soldOut'  => !empty($t['sold_out']),
             'fits'     => $t['fits'] ?? null,
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
            <div class="cfg-controls glass">
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

                    <?php /* Niedobór/brak miejsca na WYBRANYM progu - ten sam
                             pomysł co pcard-scarce na kartach planów
                             (plan-card-cennik.php), tu w jednym miejscu zamiast
                             osobnej karty na próg. */ ?>
                    <p class="cfg-scarce" data-role="scarce" style="display:none;"></p>
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

                <div class="cfg-block">
                    <div class="cfg-label">Model rozliczenia</div>
                    <div class="cfg-modes" data-role="modes">
                        <button type="button" class="cfg-mode is-active" data-mode="payment">
                            <span class="cfg-mode-label">Jednorazowo</span>
                            <span class="cfg-mode-note">Płacisz raz. Przed końcem okresu przypomnimy o przedłużeniu.</span>
                        </button>
                        <button type="button" class="cfg-mode" data-mode="subscription">
                            <span class="cfg-mode-label">Abonament</span>
                            <span class="cfg-mode-note">Odnawia się sam. Rezygnujesz kiedy chcesz, bez pisania do nas.</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ PRAWA STRONA: wynik ============ -->
            <?php /* Osobne pudełko (własne .glass), nie wnętrze lewej karty -
                     podsumowanie ma wyglądać jak samodzielny panel płatności,
                     a nie kolejna sekcja tego samego boxa. Treść owinięta w
                     .glass-content, żeby stać NAD poświatą kursora (::before)
                     i przebłyskiem szkła (.glass::after) - patrz navbar.css. */ ?>
            <div class="cfg-summary glass">
                <div class="glass-content">
                    <div class="cfg-summary-head">
                        <span class="cfg-summary-label">Podsumowanie</span>
                        <span class="cfg-summary-sub">
                            <?php if ($gameName !== ''): ?><?php echo htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8'); ?> · <?php endif; ?><strong data-role="pakiet"></strong>
                        </span>
                    </div>

                    <?php /* Te same ikony i ten sam kafelek 2x2 co .pcard-specs na
                             kartach Minecrafta (components/plan-card-cennik.php) -
                             krócej niż cztery pełne wiersze tekstu, więc karta mieści
                             się bez scrollowania. Dysk i backupy skalują się z progiem
                             (migracja 007) - stąd data-role zamiast echo stałej, tak
                             samo jak RAM/CPU niżej. */ ?>
                    <ul class="pcard-specs">
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                            <span class="pcard-spec-val" data-role="ram"></span>
                            <span class="pcard-spec-lbl">RAM</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
                            <span class="pcard-spec-val" data-role="dysk"></span>
                            <span class="pcard-spec-lbl">Dysk SSD</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>
                            <span class="pcard-spec-val" data-role="cpu"></span>
                            <span class="pcard-spec-lbl">Procesor</span>
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                            <span class="pcard-spec-val" data-role="backupy"></span>
                            <span class="pcard-spec-lbl">Backupy</span>
                        </li>
                    </ul>

                    <div class="cfg-price-wrap">
                        <?php /* Cena przekreślona pokazuje się tylko wtedy, gdy okres
                                 faktycznie jest tańszy niż ta sama liczba miesięcy
                                 kupowana pojedynczo. Przy miesiącu nie ma czego
                                 przekreślać, więc znika. Okres sam w sobie widać
                                 w pricePer ("za miesiąc" / "za rok · X zł/mies.") -
                                 osobny wiersz "Okres" tylko powtarzałby to samo. */ ?>
                        <div class="cfg-price-old" data-role="oldPrice"></div>
                        <div class="cfg-price">
                            <span data-role="price"></span>
                            <span class="cfg-price-cur">zł</span>
                        </div>
                        <div class="cfg-price-per" data-role="pricePer"></div>
                        <div class="cfg-save" data-role="save"></div>
                    </div>

                    <?php /* Uczciwie o ograniczeniu, ZANIM klient wybierze i zdziwi się
                             w Checkoucie, że nie ma BLIK-a - ten sam komunikat co na
                             starym /zamowienie. */ ?>
                    <p class="cfg-methods-hint" data-role="methodsHint"></p>

                    <button type="button" class="cfg-select" data-role="select"
                            <?php echo $salesOn ? '' : 'disabled'; ?>>
                        <?php echo $isLoggedIn ? 'Dalej - podsumowanie' : 'Zaloguj się, żeby zamówić'; ?>
                    </button>

                    <?php if (!$salesOn): ?>
                        <p class="cfg-error" data-role="salesOff">Sprzedaż jest chwilowo wyłączona - trwa przerwa techniczna.</p>
                    <?php endif; ?>
                    <p class="cfg-error" data-role="error" hidden></p>

                    <p class="cfg-legal">
                        W następnym kroku zaakceptujesz <a href="/regulamin">Regulamin</a> i możesz wpisać kod rabatowy.
                        Płatność obsługuje Stripe - nie przechowujemy danych Twojej karty.
                        <?php if (stripe_is_test_mode()): ?>
                            <span class="cfg-testmode">Tryb testowy - żadne pieniądze nie są pobierane.</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
}
