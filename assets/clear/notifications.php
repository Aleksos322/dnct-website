<?php
/**
 * assets/clear/notifications.php
 *
 * Dwie warstwy powiadomień:
 *  1. Flash (sesja) - jednorazowe komunikaty po akcji. Bez zmian względem
 *     poprzedniej wersji, patrz $_SESSION['flash'][] = [...]
 *  2. Trwałe (baza danych) - dzwoneczek w prawym górnym rogu + dropdown +
 *     toast dla nowych. Wysyłane przez backend/lib/notifications.php
 *     (notify_user / notify_admins / notify_all).
 *
 * Cztery typy (success/error/warning/info) mają OSOBNY kolor, OSOBNĄ ikonę
 * i OSOBNĄ tekstową etykietę - nie tylko kolor, bo poleganie wyłącznie na
 * barwie zawodzi część odbiorców (patrz DESIGN.md, kolory semantyczne).
 * Paleta trzyma się tokenów DESIGN.md; "info" ponownie używa Głębokiego
 * Indygo (#6366f1) - koloru już zdefiniowanego w systemie jako partner CTA,
 * zamiast wprowadzać piąty kolor spoza palety.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flashMessages = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$isLoggedInForBell = isset($_SESSION['authentik_username']);

/** Ikona + etykieta PL dla typu powiadomienia - współdzielone przez flash i JS niżej. */
function dnct_notif_type_meta(string $type): array
{
    return match ($type) {
        'success' => [
            'label' => 'Sukces',
            'icon'  => '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>',
        ],
        'error' => [
            'label' => 'Błąd',
            'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>',
        ],
        'warning' => [
            'label' => 'Uwaga',
            'icon'  => '<path d="M12 3.5 21 19H3L12 3.5Z"/><path d="M12 9.5v4"/><path d="M12 16.5h.01"/>',
        ],
        default => [
            'label' => 'Info',
            'icon'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        ],
    };
}
?>

<?php if (!empty($flashMessages)): ?>
<div id="dnct-notifications" class="fixed right-4 z-[9999] flex flex-col gap-2 w-[90%] max-w-sm" style="bottom: calc(5rem + env(safe-area-inset-bottom));">
    <?php foreach ($flashMessages as $i => $flash):
        $type = $flash['type'] ?? 'info';
        $type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
        $message = $flash['message'] ?? '';
        $meta    = dnct_notif_type_meta($type);
    ?>
    <div
        class="dnct-toast dnct-toast--<?php echo $type; ?> opacity-0 translate-x-4 transition-all duration-300 ease-out"
        data-toast-id="flash-<?php echo $i; ?>"
        data-type="<?php echo $type; ?>"
    >
        <span class="dnct-toast-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $meta['icon']; ?></svg>
        </span>
        <span class="dnct-toast-body">
            <span class="dnct-toast-label"><?php echo htmlspecialchars($meta['label']); ?></span>
            <span class="dnct-toast-msg"><?php echo htmlspecialchars($message); ?></span>
        </span>
        <button type="button" class="dnct-toast-close" aria-label="Zamknij">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isLoggedInForBell): ?>
<!-- DZWONECZEK POWIADOMIEŃ -->
<div id="dnct-bell-wrap" class="fixed right-4 z-[9998]" style="bottom: calc(1rem + env(safe-area-inset-bottom));">
    <button id="dnct-bell-btn" type="button" class="dnct-bell-btn relative w-11 h-11 flex items-center justify-center rounded-full transition" aria-label="Powiadomienia">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span id="dnct-bell-badge" class="dnct-bell-badge hidden">0</span>
    </button>

    <?php /* Lista otwiera się W GÓRĘ, nie w dół.
             Dzwonek jest przyklejony do DOLNEJ krawędzi ekranu, więc `mt-2`
             rozwijało panel poniżej widocznego obszaru - historia powiadomień
             istniała, ale nie dało się jej zobaczyć ani przewinąć do niej. */ ?>
    <div id="dnct-bell-dropdown" class="hidden absolute right-0 bottom-full mb-3 w-80 max-h-[60vh] overflow-y-auto dnct-notif-panel">
        <div class="dnct-notif-head flex items-center justify-between px-4 py-3">
            <span class="font-bold text-sm tracking-wide">Powiadomienia</span>
            <button id="dnct-mark-all-read" type="button" class="dnct-notif-markall">Oznacz wszystkie</button>
        </div>
        <div id="dnct-bell-list">
            <?php // Skeleton zamiast golego "Ladowanie..." - to samo, co JS wstawia przy kazdym otwarciu. ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="dnct-skel-row">
                <span class="dnct-skel-dot"></span>
                <span class="dnct-skel-lines"><span></span><span></span></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const POLL_MS = 30000;
    const LAST_CHECK_KEY = 'dnct_last_notif_check';

    const bellBtn   = document.getElementById('dnct-bell-btn');
    const badge     = document.getElementById('dnct-bell-badge');
    const dropdown  = document.getElementById('dnct-bell-dropdown');
    const listEl    = document.getElementById('dnct-bell-list');
    const markAllBtn = document.getElementById('dnct-mark-all-read');

    /* Ta sama para (etykieta + ikona) co w PHP wyżej (dnct_notif_type_meta) -
       toast serwerowy i lista/toast renderowane z JS mają wyglądać identycznie. */
    const typeMeta = {
        success: { label: 'Sukces', icon: '<path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/>' },
        error:   { label: 'Błąd',   icon: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5M14.5 9.5l-5 5"/>' },
        warning: { label: 'Uwaga',  icon: '<path d="M12 3.5 21 19H3L12 3.5Z"/><path d="M12 9.5v4"/><path d="M12 16.5h.01"/>' },
        info:    { label: 'Info',   icon: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>' },
    };

    function iconSvg(type) {
        const m = typeMeta[type] || typeMeta.info;
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${m.icon}</svg>`;
    }

    function timeAgo(dateStr) {
        const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T')).getTime();
        const mins = Math.floor(diffMs / 60000);
        if (mins < 1) return 'przed chwilą';
        if (mins < 60) return mins + ' min temu';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + ' godz. temu';
        return Math.floor(hrs / 24) + ' dni temu';
    }

    function skeletonHtml(rows) {
        let html = '';
        for (let i = 0; i < rows; i++) {
            html += '<div class="dnct-skel-row"><span class="dnct-skel-dot"></span><span class="dnct-skel-lines"><span></span><span></span></span></div>';
        }
        return html;
    }

    function emptyHtml() {
        return `<div class="dnct-notif-empty">
            <span class="dnct-notif-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-9.33-5"/><path d="M6.26 6.26A6 6 0 0 0 6 8c0 7-3 9-3 9h14"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M3 3l18 18"/>
                </svg>
            </span>
            <p>Na razie tu pusto</p>
            <span>Damy znać, gdy coś się wydarzy.</span>
        </div>`;
    }

    /* Wpisy dostają rosnące (ograniczone do 8) opóźnienie wejścia, żeby lista
       "spływała" zamiast wyskoczyć naraz jednym blokiem. Oznaczenie jako
       przeczytane NIE przeładowuje listy - przełącza tylko `data-read`, a
       przejście koloru/paska robi CSS (patrz layout.css). Dzięki temu klik
       daje natychmiastowy, płynny feedback zamiast czekać na sieć. */
    function renderList(notifications) {
        if (!notifications.length) {
            listEl.innerHTML = emptyHtml();
            return;
        }
        listEl.innerHTML = notifications.map((n, i) => {
            const type = typeMeta[n.type] ? n.type : 'info';
            const delay = Math.min(i, 8) * 40;
            const inner = `
                <span class="dnct-notif-icon dnct-notif-icon--${type}" aria-hidden="true">${iconSvg(type)}</span>
                <div class="flex-1 min-w-0">
                    <div class="dnct-notif-title">${escapeHtml(n.title)}<span class="dnct-notif-unread-dot" aria-label="Nieprzeczytane"></span></div>
                    <div class="text-xs text-gray-400 mt-0.5">${escapeHtml(n.message)}</div>
                    <div class="text-[11px] text-gray-600 mt-1">${timeAgo(n.created_at)}</div>
                </div>`;
            const linkOpen = n.link ? `<a href="${escapeHtml(n.link)}" class="flex items-start gap-3">` : '<div class="flex items-start gap-3">';
            const linkClose = n.link ? `</a>` : '</div>';
            return `<div class="dnct-notif-item px-4 py-3 cursor-pointer" data-id="${n.id}" data-type="${type}" data-read="${n.is_read ? 1 : 0}" style="animation-delay:${delay}ms">${linkOpen}${inner}${linkClose}</div>`;
        }).join('');

        listEl.querySelectorAll('.dnct-notif-item').forEach(el => {
            el.addEventListener('click', () => markRead(parseInt(el.dataset.id, 10), el));
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    async function fetchList() {
        try {
            const res = await fetch('/api/notifications/list.php');
            if (!res.ok) return;
            const data = await res.json();
            renderList(data.notifications || []);
            maybeToastNew(data.notifications || []);
        } catch (e) { /* cicho ignorujemy błędy sieci */ }
    }

    async function fetchUnreadCount() {
        try {
            const res = await fetch('/api/notifications/unread-count.php');
            if (!res.ok) return;
            const data = await res.json();
            updateBadge(data.unread || 0);
        } catch (e) {}
    }

    // Poprzedni stan licznika - żeby odróżnić "przybyło nowe" (dzwonek ma
    // zadzwonić) od zwykłego odświeżenia po otwarciu/oznaczeniu jako przeczytane.
    let prevUnread = null;

    function ringBell() {
        bellBtn.classList.remove('dnct-bell-btn--ring');
        void bellBtn.offsetWidth; // restart animacji CSS przy kolejnym wywołaniu
        bellBtn.classList.add('dnct-bell-btn--ring');
        setTimeout(() => bellBtn.classList.remove('dnct-bell-btn--ring'), 650);
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
        if (prevUnread !== null && count > prevUnread) ringBell();
        prevUnread = count;
    }

    /* Optymistyczne oznaczenie: kolor/pasek/kropka gasną NATYCHMIAST (CSS
       reaguje na `data-read`), zapytanie do API leci w tle. Celowo NIE
       przeładowujemy tu całej listy (fetchList()) - zastąpienie DOM-u w
       trakcie przejścia ucięłoby animację w połowie. Serwer i tak się
       zsynchronizuje przy najbliższym odświeżeniu (poll albo kolejne otwarcie). */
    async function markRead(id, el) {
        if (el) el.dataset.read = '1';
        try {
            await fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            fetchUnreadCount();
        } catch (e) {}
    }

    markAllBtn.addEventListener('click', async () => {
        markAllBtn.disabled = true;
        listEl.querySelectorAll('.dnct-notif-item').forEach(el => { el.dataset.read = '1'; });
        try {
            await fetch('/api/notifications/mark-all-read.php', { method: 'POST' });
            fetchUnreadCount();
        } catch (e) {
        } finally {
            markAllBtn.disabled = false;
        }
    });

    /* Zamknięcie odtwarza `dnctNotifOut` i dopiero PO jej zakończeniu dokleja
       `.hidden` - inaczej panel znikałby skokiem, mimo animacji zdefiniowanej
       w CSS (patrz pułapka z `.hidden` w root CLAUDE.md). Timeout to
       zabezpieczenie na wypadek `prefers-reduced-motion`, gdzie animacja jest
       wyłączona i `animationend` nigdy by nie odpalił. */
    function openDropdown() {
        dropdown.classList.remove('hidden', 'dnct-notif-panel--closing');
        bellBtn.classList.add('dnct-bell-btn--active');
        fetchList();
    }

    function closeDropdown() {
        if (dropdown.classList.contains('hidden')) return;
        dropdown.classList.add('dnct-notif-panel--closing');
        bellBtn.classList.remove('dnct-bell-btn--active');
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            dropdown.classList.add('hidden');
            dropdown.classList.remove('dnct-notif-panel--closing');
        };
        dropdown.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 220);
    }

    bellBtn.addEventListener('click', () => {
        if (dropdown.classList.contains('hidden')) openDropdown();
        else closeDropdown();
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('dnct-bell-wrap').contains(e.target)) {
            closeDropdown();
        }
    });

    // Toast dla powiadomień utworzonych po ostatnim sprawdzeniu
    function maybeToastNew(notifications) {
        const lastCheck = parseInt(localStorage.getItem(LAST_CHECK_KEY) || '0', 10);
        const now = Date.now();
        const fresh = notifications.filter(n => {
            const created = new Date(n.created_at.replace(' ', 'T')).getTime();
            return !n.is_read && created > lastCheck;
        });
        fresh.forEach(n => showToast(n));
        localStorage.setItem(LAST_CHECK_KEY, String(now));
    }

    function showToast(n) {
        const type = typeMeta[n.type] ? n.type : 'info';
        const m = typeMeta[type];
        const container = document.getElementById('dnct-notifications') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `dnct-toast dnct-toast--${type} opacity-0 translate-x-4 transition-all duration-300 ease-out`;
        toast.dataset.type = type;
        toast.innerHTML = `
            <span class="dnct-toast-icon" aria-hidden="true">${iconSvg(type)}</span>
            <span class="dnct-toast-body">
                <span class="dnct-toast-label">${escapeHtml(m.label)}</span>
                <span class="dnct-toast-msg">${escapeHtml(n.title)}</span>
            </span>
            <button type="button" class="dnct-toast-close" aria-label="Zamknij">&times;</button>`;
        container.appendChild(toast);
        initToast(toast);
    }

    function createToastContainer() {
        const div = document.createElement('div');
        div.id = 'dnct-notifications';
        div.className = 'fixed right-4 z-[9999] flex flex-col gap-2 w-[90%] max-w-sm';
        div.style.bottom = 'calc(5rem + env(safe-area-inset-bottom))';
        document.body.appendChild(div);
        return div;
    }

    // Inicjalizacja istniejących (flash) toastów renderowanych przez PHP
    document.querySelectorAll('.dnct-toast').forEach(initToast);

    function initToast(toast) {
        const AUTO_HIDE_MS = 5000;
        function remove() {
            toast.classList.add('opacity-0', 'translate-x-4');
            setTimeout(() => toast.remove(), 300);
        }
        requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-x-4'));
        if (window.DnctSound) window.DnctSound.play(toast.dataset.type || 'info');
        const timer = setTimeout(remove, AUTO_HIDE_MS);
        const closeBtn = toast.querySelector('.dnct-toast-close');
        closeBtn.addEventListener('click', () => { clearTimeout(timer); remove(); });
    }

    // Start
    fetchUnreadCount();
    setInterval(() => {
        fetchUnreadCount();
        if (!dropdown.classList.contains('hidden')) fetchList();
        else fetchListSilentForToast();
    }, POLL_MS);

    // Sprawdzanie w tle (bez otwierania dropdownu) tylko po to, by pokazać toast dla nowych
    async function fetchListSilentForToast() {
        try {
            const res = await fetch('/api/notifications/list.php');
            if (!res.ok) return;
            const data = await res.json();
            maybeToastNew(data.notifications || []);
        } catch (e) {}
    }
})();
</script>
<?php endif; ?>
