<?php if (isset($_SESSION['authentik_username'])): ?>
<!-- DZWONECZEK POWIADOMIEŃ -->
<div id="dnct-bell-wrap" class="is-navbar" data-preference-key="<?php echo hash('sha256', (string)$_SESSION['authentik_username']); ?>" data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
    <button id="dnct-bell-btn" type="button" class="dnct-bell-btn relative w-11 h-11 flex items-center justify-center rounded-full transition" aria-label="Powiadomienia" aria-controls="dnct-bell-dropdown" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span id="dnct-bell-badge" class="dnct-bell-badge hidden">0</span>
    </button>

    <div id="dnct-bell-dropdown" class="hidden dnct-notif-panel" role="region" aria-label="Powiadomienia">
        <div class="dnct-notif-head flex items-center justify-between px-4 py-3">
            <span class="font-bold text-sm tracking-wide">Powiadomienia</span>
            <button id="dnct-mark-all-read" type="button" class="dnct-notif-markall">Przeczytaj wszystko</button>
        </div>
        <p id="dnct-notif-status" role="status" aria-live="polite"></p>
        <div id="dnct-bell-list" data-lenis-prevent-wheel>
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

<?php require_once __DIR__ . '/chat-assets.php'; ?>
<script src="<?php echo asset('/assets/js/notifications.js'); ?>" defer></script>
<?php endif; ?>
