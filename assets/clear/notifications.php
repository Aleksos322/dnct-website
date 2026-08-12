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
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flashMessages = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$isLoggedInForBell = isset($_SESSION['authentik_username']);
?>

<?php if (!empty($flashMessages)): ?>
<div id="dnct-notifications" class="fixed right-4 z-[9999] flex flex-col gap-2 w-[90%] max-w-sm" style="bottom: calc(5rem + env(safe-area-inset-bottom));">
    <?php foreach ($flashMessages as $i => $flash):
        $type    = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';

        $styles = match ($type) {
            'success' => ['bg' => 'bg-green-600',  'icon' => '✓'],
            'error'   => ['bg' => 'bg-red-600',    'icon' => '✕'],
            'warning' => ['bg' => 'bg-yellow-500', 'icon' => '!'],
            default   => ['bg' => 'bg-blue-600',   'icon' => 'i'],
        };
    ?>
    <div
        class="dnct-toast <?php echo $styles['bg']; ?> text-white rounded-lg shadow-2xl px-4 py-3 flex items-center gap-3 opacity-0 translate-x-4 transition-all duration-300 ease-out"
        data-toast-id="flash-<?php echo $i; ?>"
    >
        <span class="font-bold text-lg leading-none"><?php echo $styles['icon']; ?></span>
        <span class="flex-1 text-sm text-left"><?php echo htmlspecialchars($message); ?></span>
        <button type="button" class="dnct-toast-close text-white/70 hover:text-white text-lg leading-none" aria-label="Zamknij">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isLoggedInForBell): ?>
<!-- DZWONECZEK POWIADOMIEŃ -->
<div id="dnct-bell-wrap" class="fixed right-4 z-[9998]" style="bottom: calc(1rem + env(safe-area-inset-bottom));">
    <button id="dnct-bell-btn" type="button" class="relative w-10 h-10 flex items-center justify-center rounded-full bg-gray-900/80 border border-gray-800 backdrop-blur-md hover:bg-gray-800 transition" aria-label="Powiadomienia">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span id="dnct-bell-badge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[11px] font-bold rounded-full flex items-center justify-center">0</span>
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
            <div class="px-4 py-6 text-center text-gray-500 text-sm">Ładowanie...</div>
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

    const typeStyle = {
        success: { bg: 'bg-green-600', icon: '✓' },
        error:   { bg: 'bg-red-600',   icon: '✕' },
        warning: { bg: 'bg-yellow-500', icon: '!' },
        info:    { bg: 'bg-blue-600',  icon: 'i' },
    };

    function timeAgo(dateStr) {
        const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T')).getTime();
        const mins = Math.floor(diffMs / 60000);
        if (mins < 1) return 'przed chwilą';
        if (mins < 60) return mins + ' min temu';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + ' godz. temu';
        return Math.floor(hrs / 24) + ' dni temu';
    }

    function renderList(notifications) {
        if (!notifications.length) {
            listEl.innerHTML = '<div class="px-4 py-6 text-center text-gray-500 text-sm">Brak powiadomień</div>';
            return;
        }
        listEl.innerHTML = notifications.map(n => {
            const s = typeStyle[n.type] || typeStyle.info;
            const unreadDot = n.is_read ? '' : '<span class="w-2 h-2 rounded-full bg-blue-500 inline-block mr-2 flex-shrink-0"></span>';
            const inner = `
                <div class="flex items-start gap-2">
                    ${unreadDot}
                    <div class="flex-1">
                        <div class="text-sm font-semibold ${n.is_read ? 'text-gray-300' : 'text-white'}">${escapeHtml(n.title)}</div>
                        <div class="text-xs text-gray-400 mt-0.5">${escapeHtml(n.message)}</div>
                        <div class="text-[11px] text-gray-600 mt-1">${timeAgo(n.created_at)}</div>
                    </div>
                </div>`;
            const linkOpen = n.link ? `<a href="${escapeHtml(n.link)}">` : '';
            const linkClose = n.link ? `</a>` : '';
            return `<div class="dnct-notif-item px-4 py-3 hover:bg-gray-800/60 cursor-pointer" data-id="${n.id}">${linkOpen}${inner}${linkClose}</div>`;
        }).join('');

        listEl.querySelectorAll('.dnct-notif-item').forEach(el => {
            el.addEventListener('click', () => markRead(parseInt(el.dataset.id, 10)));
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

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    async function markRead(id) {
        try {
            await fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            fetchList();
            fetchUnreadCount();
        } catch (e) {}
    }

    markAllBtn.addEventListener('click', async () => {
        try {
            await fetch('/api/notifications/mark-all-read.php', { method: 'POST' });
            fetchList();
            fetchUnreadCount();
        } catch (e) {}
    });

    bellBtn.addEventListener('click', () => {
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) fetchList();
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('dnct-bell-wrap').contains(e.target)) {
            dropdown.classList.add('hidden');
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
        const s = typeStyle[n.type] || typeStyle.info;
        const container = document.getElementById('dnct-notifications') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `dnct-toast ${s.bg} text-white rounded-lg shadow-2xl px-4 py-3 flex items-center gap-3 opacity-0 translate-x-4 transition-all duration-300 ease-out`;
        toast.innerHTML = `
            <span class="font-bold text-lg leading-none">${s.icon}</span>
            <span class="flex-1 text-sm text-left">${escapeHtml(n.title)}</span>
            <button type="button" class="dnct-toast-close text-white/70 hover:text-white text-lg leading-none" aria-label="Zamknij">&times;</button>`;
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