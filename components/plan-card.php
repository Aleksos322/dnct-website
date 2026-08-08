<?php
/**
 * Karta planu hostingowego.
 *
 * Przeniesiona z beta/components/Server_card/Server_card.php i odchudzona:
 *   - bez przycisku edycji dla admina (panel administracyjny bedzie osobno),
 *   - bez wywolania openOrderModal() - modal zamowien nie zyje na stronie
 *     glownej, karta prowadzi do cennika,
 *   - sciezki do grafik wskazuja na /assets/media/Server_card/.
 *
 * Wyglad (klasy, cienie, odznaki) zostawiony 1:1, zeby nowa strona wygladala
 * tak samo jak obecna produkcja.
 *
 * @param int   $id Identyfikator planu (z tabeli hosting_plans)
 * @param array $p  Dane planu; klucze uzywane nizej sa opisane w render_plan_card()
 */

declare(strict_types=1);

if (!function_exists('render_plan_card')) {

/** Skleja sciezke do grafiki karty, jesli w bazie jest sama nazwa pliku. */
function plan_card_media(?string $file, string $subdir): string
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

function render_plan_card(int $id, array $p): void
{
    $isPopular = !empty($p['popular']);
    $isSoldOut = !empty($p['sold_out']);
    $isOnSale  = !empty($p['is_on_sale']);
    $oldPrice  = (float)($p['old_price'] ?? 0);

    // Dokad prowadzi karta, gdy plan jest dostepny. Na stronie glownej to cennik.
    $href    = (string)($p['href'] ?? '/cennik');
    $btnText = (string)($p['btn_text'] ?? 'Zobacz szczegóły');

    $classes = 'relative group glass p-8 rounded-[2.5rem] transition-all duration-500 overflow-hidden border ';

    if ($isSoldOut) {
        $classes .= 'border-red-500/40 shadow-[0_0_20px_rgba(239,68,68,0.15)] opacity-90 ';
    } elseif ($isPopular) {
        $classes .= 'border-purple-500 shadow-[0_0_40px_rgba(139,92,246,0.15)] scale-[1.02] ';
    } elseif ($isOnSale) {
        $classes .= 'border-blue-500/40 shadow-[0_0_30px_rgba(59,130,246,0.2)] hover:scale-[1.05] ';
    } else {
        $classes .= 'border-white/5 hover:scale-[1.05] hover:border-blue-500/30 '
                  . 'hover:shadow-[0_0_50px_rgba(59,130,246,0.2)] hover:-translate-y-2 ';
    }

    $bgSrc   = plan_card_media($p['bg'] ?? '', 'background');
    $iconSrc = plan_card_media($p['icon'] ?? '', 'icons');
    ?>
    <div class="<?php echo trim($classes); ?>">

        <!-- Poswiata pod kursorem -->
        <div class="absolute -top-[50%] -left-[50%] w-[200%] h-[200%] bg-[radial-gradient(circle,rgba(139,92,246,0.1)_0%,transparent_70%)] opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>

        <?php if ($isSoldOut): ?>
            <div class="absolute top-0 right-0 bg-gradient-to-r from-red-600 to-orange-600 px-6 py-1.5 rounded-bl-2xl text-[10px] font-black uppercase tracking-[0.2em] text-white z-10 shadow-lg">
                Wyprzedane
            </div>
        <?php elseif ($isPopular): ?>
            <div class="absolute top-0 right-0 bg-purple-600 px-6 py-1.5 rounded-bl-2xl text-[10px] font-black uppercase tracking-[0.2em] text-white z-10 shadow-lg">
                Najpopularniejszy
            </div>
        <?php endif; ?>

        <?php if ($isOnSale && !$isSoldOut): ?>
            <div class="absolute top-0 left-0 bg-gradient-to-r from-blue-600 to-cyan-600 px-6 py-1.5 rounded-br-2xl text-[10px] font-black uppercase tracking-[0.2em] text-white z-10 shadow-lg">
                Promocja
            </div>
        <?php endif; ?>

        <!-- Grafika gry -->
        <div class="relative w-full h-44 mb-8 rounded-3xl overflow-hidden bg-black/40 border border-white/5 flex items-center justify-center">
            <?php if ($bgSrc !== ''): ?>
                <img src="<?php echo htmlspecialchars($bgSrc); ?>"
                     alt=""
                     loading="lazy" decoding="async"
                     class="absolute inset-0 w-full h-full object-cover blur-sm transition-all duration-700 group-hover:scale-110 group-hover:opacity-60 <?php echo $isSoldOut ? 'opacity-20 grayscale' : 'opacity-20'; ?>">
            <?php endif; ?>

            <div class="absolute inset-0 bg-gradient-to-b from-purple-500/10 to-transparent"></div>

            <?php if ($iconSrc !== ''): ?>
                <div class="relative z-10 p-4 rounded-2xl bg-white/5 backdrop-blur-md border border-white/10 transition-all duration-500 <?php echo $isSoldOut ? 'grayscale opacity-75' : 'group-hover:border-purple-500/50 group-hover:rotate-6 group-hover:scale-110'; ?>">
                    <img src="<?php echo htmlspecialchars($iconSrc); ?>"
                         alt="<?php echo htmlspecialchars((string)($p['name'] ?? 'Plan')); ?>"
                         width="64" height="64" loading="lazy" decoding="async"
                         class="w-16 h-16 object-contain filter drop-shadow-[0_0_8px_rgba(168,85,247,0.4)]">
                </div>
            <?php endif; ?>
        </div>

        <!-- Nazwa i cena -->
        <div class="text-center mb-6">
            <h3 class="text-3xl font-black tracking-tight mb-2 group-hover:gradient-text transition-all duration-300">
                <?php echo htmlspecialchars((string)($p['name'] ?? '')); ?>
                <span class="text-sm font-light text-gray-400 uppercase tracking-widest block mt-1">Plan</span>
            </h3>

            <div class="flex items-center justify-center gap-1 relative">
                <?php if ($isSoldOut): ?>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-full h-[2px] bg-red-500 rotate-[-10deg] opacity-80"></div>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col items-end">
                    <?php if ($isOnSale && $oldPrice > 0): ?>
                        <span class="text-xs text-red-500/70 line-through decoration-red-500 font-bold mb-1">
                            <?php echo htmlspecialchars(number_format($oldPrice, 2, ',', ' ')); ?> PLN
                        </span>
                    <?php endif; ?>

                    <div class="flex items-center gap-1">
                        <span class="text-4xl font-black bg-clip-text text-transparent <?php echo $isSoldOut ? 'bg-gray-600 text-gray-600' : 'bg-gradient-to-b from-white to-gray-400'; ?>">
                            <?php echo htmlspecialchars((string)($p['price'] ?? '')); ?>
                        </span>
                        <div class="text-left">
                            <span class="<?php echo $isSoldOut ? 'text-gray-600' : 'text-purple-500'; ?> font-bold block leading-none">PLN</span>
                            <span class="text-[10px] text-gray-500 uppercase tracking-tighter">/ miesiąc</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Specyfikacja. Ikony rysowane inline (nie <i data-lucide>), zeby karta
             nie znikala, gdyby biblioteka ikon sie nie wykonala. -->
        <ul class="space-y-4 mb-10 relative z-10 <?php echo $isSoldOut ? 'opacity-50' : ''; ?>">
            <?php
            $iconColor = $isSoldOut ? 'text-gray-500' : 'text-purple-500';
            $specs = [
                ['svg' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/>', 'val' => (string)($p['cpu'] ?? '')],
                ['svg' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>', 'val' => ($p['ram'] ?? '') . ' RAM'],
                ['svg' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>', 'val' => ($p['ssd'] ?? '') . ' NVMe'],
                ['svg' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>', 'val' => ($p['backups'] ?? '0') . ' Backupy'],
            ];
            foreach ($specs as $s): ?>
                <li class="flex items-center gap-4 group/item">
                    <div class="p-2 rounded-lg bg-white/5 group-hover/item:bg-purple-500/20 transition-colors">
                        <svg class="w-4 h-4 <?php echo $iconColor; ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $s['svg']; ?></svg>
                    </div>
                    <span class="text-sm text-gray-400 group-hover/item:text-white transition-colors"><?php echo htmlspecialchars((string)$s['val']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Wezwanie do dzialania -->
        <?php if ($isSoldOut): ?>
            <button type="button" aria-disabled="true"
                    class="relative flex items-center justify-center w-full py-4 mt-8 rounded-2xl font-black uppercase tracking-[0.15em] text-xs bg-white/5 text-gray-500 cursor-not-allowed border border-white/5">
                <span class="relative z-10">Wyprzedane</span>
            </button>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($href); ?>"
               class="relative flex items-center justify-center w-full py-4 mt-8 rounded-2xl font-black uppercase tracking-[0.15em] text-xs transition-all duration-300 active:scale-95 overflow-hidden group bg-gradient-main shadow-lg shadow-purple-500/20">
                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-shimmer pointer-events-none"></div>
                <span class="relative z-10"><?php echo htmlspecialchars($btnText); ?></span>
            </a>
        <?php endif; ?>
    </div>
    <?php
}

}
