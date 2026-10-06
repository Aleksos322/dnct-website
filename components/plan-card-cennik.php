<?php
/**
 * Karta planu - wariant cennikowy (wysoki).
 *
 * DLACZEGO OSOBNY KOMPONENT, A NIE TRYB `compact` W plan-card.php
 *
 * Karta ze strony głównej jest zbudowana z klas Tailwinda i zaprojektowana pod
 * ekspozycję JEDNEGO planu z trzech. Dociskanie jej do siatki 3x3 skończyło się
 * dwudziestoma regułami `!important` nadpisującymi `.h-44`, `.mb-8`, `.text-4xl`
 * - czyli CSS-em, który opisuje cudzy układ zamiast własnego. Każda zmiana
 * w komponencie po cichu psuła cennik.
 *
 * Tutaj markup jest własny i semantyczny (`.pcard-*`), więc CSS mówi wprost,
 * co stylizuje. Strona główna zostaje na starym komponencie bez zmian.
 *
 * UKŁAD - kolejność jest celowa:
 *
 *   grafika → nazwa → siatka parametrów → [rozpórka] → cena → przycisk
 *
 * Cena siedzi NA DOLE, tuż nad przyciskiem. Klient najpierw czyta, co dostaje,
 * a dopiero potem ile to kosztuje - przy dziewięciu kartach obok siebie cena
 * na górze zamienia porównywanie ofert w porównywanie samych liczb.
 *
 * Rozpórka (`.pcard-spacer`, `flex: 1`) sprawia, że stopki wszystkich kart
 * w rzędzie leżą na tej samej wysokości niezależnie od długości nazwy planu.
 * Bez niej ceny skakały o kilkanaście pikseli i rząd wyglądał na krzywy.
 *
 * Ikony rysowane są inline. `<i data-lucide>` bez wykonanego `createIcons()`
 * zostaje pustym elementem o zerowym rozmiarze - parametr planu zniknąłby
 * bez śladu (patrz CLAUDE.md).
 */

declare(strict_types=1);

if (!function_exists('render_cennik_plan_card')) {

/** Skleja ścieżkę do grafiki, jeśli w bazie jest sama nazwa pliku. */
function cennik_card_media(?string $file, string $subdir): string
{
    $file = (string)$file;
    if ($file === '') {
        return '';
    }
    if (str_starts_with($file, 'http') || str_starts_with($file, '/')) {
        return $file;
    }
    return '/assets/media/Server_card/' . $subdir . '/' . $file;
}

/**
 * Cena w zapisie polskim: 6.99 -> "6,99", 65.00 -> "65".
 *
 * W bazie ceny są kolumną DECIMAL, więc PHP dostaje je jako "6.99" - z kropką
 * i, przy okrągłych kwotach, z zerowymi końcówkami ("65.00"). Wyświetlanie
 * tego wprost daje cennik w zapisie, którego nie używa żaden polski sklep.
 * Wartość nieliczbowa przechodzi bez zmian - lepiej pokazać dziwny napis
 * z bazy niż zamienić go w "0".
 */
function cennik_price_label(mixed $price): string
{
    if (!is_numeric($price)) {
        return (string)$price;
    }

    $value = (float)$price;
    $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

    return number_format($value, $decimals, ',', ' ');
}

/**
 * @param array $p Klucze: name, price, old_price, is_on_sale, popular, sold_out,
 *                 ram, ssd, cpu, backups (gotowe napisy), bg, icon, fits, badge
 */
function render_cennik_plan_card(array $p): void
{
    $isSoldOut = !empty($p['sold_out']);
    $isPopular = !empty($p['popular']);
    $isOnSale  = !empty($p['is_on_sale']);
    $oldPrice  = (float)($p['old_price'] ?? 0);

    $classes = 'pcard glass';
    if ($isSoldOut)      { $classes .= ' is-soldout'; }
    elseif ($isPopular)  { $classes .= ' is-popular'; }
    elseif ($isOnSale)   { $classes .= ' is-sale'; }

    $bgSrc   = cennik_card_media($p['bg'] ?? '', 'background');
    $iconSrc = cennik_card_media($p['icon'] ?? '', 'icons');

    /* Ikony parametrów. Kolejność siatki 2x2 od najważniejszego: RAM decyduje
       o tym, ile modów i graczy uciągnie serwer, i to jego szuka się pierwszego. */
    $specs = [
        ['label' => 'RAM',     'value' => (string)($p['ram'] ?? ''),
         'svg' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>'],
        ['label' => 'Dysk SSD', 'value' => (string)($p['ssd'] ?? ''),
         'svg' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>'],
        ['label' => 'Procesor', 'value' => (string)($p['cpu'] ?? ''),
         'svg' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/>'],
        ['label' => 'Backupy', 'value' => (string)($p['backups'] ?? '0'),
         'svg' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>'],
    ];
    ?>
    <article class="<?php echo $classes; ?>">

        <?php if ($isSoldOut): ?>
            <span class="pcard-badge pcard-badge-out">Wyprzedane</span>
        <?php elseif ($isPopular): ?>
            <span class="pcard-badge pcard-badge-hot">Najpopularniejszy</span>
        <?php elseif ($isOnSale): ?>
            <span class="pcard-badge pcard-badge-sale">Promocja</span>
        <?php endif; ?>

        <div class="pcard-art">
            <?php if ($bgSrc !== ''): ?>
                <img class="pcard-art-bg" src="<?php echo htmlspecialchars($bgSrc); ?>"
                     alt="" loading="lazy" decoding="async">
            <?php endif; ?>
            <?php if ($iconSrc !== ''): ?>
                <span class="pcard-art-icon">
                    <img src="<?php echo htmlspecialchars($iconSrc); ?>"
                         alt="<?php echo htmlspecialchars((string)($p['name'] ?? 'Plan')); ?>"
                         width="52" height="52" loading="lazy" decoding="async">
                </span>
            <?php endif; ?>

            <?php /* Wskaźnik mocy przy dolnej krawędzi kadru. Sama liczba "8 GB"
                     nic nie mówi komuś, kto kupuje serwer pierwszy raz - pozycja
                     między najmniejszym a największym planem mówi wszystko od razu.

                     Wcześniej ten pasek leżał na górnej krawędzi karty i wchodził
                     pod odznakę "Najpopularniejszy". Tutaj jest częścią kadru,
                     więc nie ma z czym kolidować. */ ?>
            <?php if (isset($p['power'])): ?>
                <span class="pcard-power" title="Moc na tle pozostałych planów">
                    <span style="width: <?php echo (int)round(max((float)$p['power'], 0.04) * 100); ?>%"></span>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="pcard-name"><?php echo htmlspecialchars((string)($p['name'] ?? '')); ?></h3>

        <ul class="pcard-specs">
            <?php foreach ($specs as $s): ?>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $s['svg']; ?></svg>
                    <span class="pcard-spec-val"><?php echo htmlspecialchars($s['value']); ?></span>
                    <span class="pcard-spec-lbl"><?php echo htmlspecialchars($s['label']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php /* Rozpórka: dosuwa cenę i przycisk do dołu karty, żeby stopki
                 w całym rzędzie leżały równo. */ ?>
        <div class="pcard-spacer"></div>

        <?php /* Niedobór na ŻYWYCH danych z węzła. Liczba pojawia się dopiero,
                 gdy naprawdę robi się ciasno - stale wyświetlany komunikat
                 "zostały 3 sztuki" przestaje znaczyć cokolwiek po drugiej
                 wizycie, a klienci to wychwytują. */ ?>
        <?php if (!$isSoldOut && isset($p['fits']) && $p['fits'] !== null && $p['fits'] <= 4): ?>
            <p class="pcard-scarce">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                Zostały <?php echo (int)$p['fits']; ?> wolne miejsca na węźle
            </p>
        <?php endif; ?>

        <div class="pcard-price">
            <?php if ($isOnSale && $oldPrice > 0 && !$isSoldOut): ?>
                <span class="pcard-price-old"><?php echo htmlspecialchars(number_format($oldPrice, 2, ',', ' ')); ?> zł</span>
            <?php endif; ?>
            <span class="pcard-price-now"><?php echo htmlspecialchars(cennik_price_label($p['price'] ?? '')); ?></span>
            <span class="pcard-price-cur">zł</span>
            <span class="pcard-price-per">/ mies.</span>
        </div>

        <?php /* Przycisk jest LINKIEM tylko wtedy, gdy sprzedaż działa i jest
                 dokąd prowadzić. Przy wyłączonej sprzedaży zostaje <span>:
                 element klikalny prowadzący donikąd to obietnica, której strona
                 nie spełnia, a do tego psuje nawigację klawiaturą. */ ?>
        <?php if ($isSoldOut): ?>
            <button type="button" class="pcard-cta is-off" aria-disabled="true" tabindex="-1">Wyprzedane</button>
        <?php elseif (!empty($p['href'])): ?>
            <a class="pcard-cta" href="<?php echo htmlspecialchars((string)$p['href']); ?>">
                <?php echo htmlspecialchars((string)($p['cta'] ?? 'Zamawiam')); ?>
            </a>
        <?php else: ?>
            <span class="pcard-cta">Wybierz ten plan</span>
        <?php endif; ?>
    </article>
    <?php
}

}
