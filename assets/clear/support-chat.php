<?php
/**
 * Globalny, na razie wyłącznie prezentacyjny interfejs czatu supportu.
 * Komponent jest ładowany przez assets/clear/clear.php na każdej podstronie.
 */
$isLoggedInForSupportChat = isset($_SESSION["authentik_username"]);
?>
<div id="dnct-support-chat-wrap" class="dnct-support-chat-wrap">
    <section id="dnct-support-chat-panel" class="dnct-support-chat-panel" aria-hidden="true" aria-labelledby="dnct-support-chat-title" tabindex="-1">
        <div class="dnct-support-chat-topbar">
            <span class="dnct-support-chat-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <p id="dnct-support-chat-title"><span>support</span> — czat</p>
            <button id="dnct-support-chat-close" type="button" class="dnct-support-chat-close" aria-label="Zamknij czat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="dnct-support-chat-body">
            <?php if ($isLoggedInForSupportChat): ?>
                <span class="dnct-support-chat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg></span>
                <h2>Jak możemy pomóc?</h2>
                <p>Tu rozpoczniesz rozmowę z zespołem DNCT. Czat jest już przygotowany — jego obsługa pojawi się w kolejnym etapie.</p>
            <?php else: ?>
                <span class="dnct-support-chat-icon dnct-support-chat-icon--locked" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="11" x="4" y="11" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2"/></svg></span>
                <h2>Zaloguj się, aby skorzystać z czatu</h2>
                <p>Czat supportu jest dostępny dla zalogowanych klientów DNCT.</p>
                <a href="/login" class="dnct-support-chat-login">Zaloguj się</a>
            <?php endif; ?>
        </div>
        <div class="dnct-support-chat-foot" aria-hidden="true"><span></span> Czat supportu — w przygotowaniu</div>
    </section>
    <button id="dnct-support-chat-btn" type="button" class="dnct-support-chat-btn" aria-controls="dnct-support-chat-panel" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg><span>Otwórz czat</span></button>
</div>
<script>
(function () {
    const wrap = document.getElementById("dnct-support-chat-wrap");
    const panel = document.getElementById("dnct-support-chat-panel");
    const button = document.getElementById("dnct-support-chat-btn");
    const closeButton = document.getElementById("dnct-support-chat-close");
    if (!wrap || !panel || !button || !closeButton) return;
    function setOpen(open) {
        panel.classList.toggle("is-open", open);
        panel.setAttribute("aria-hidden", String(!open));
        button.setAttribute("aria-expanded", String(open));
        if (open) panel.focus({ preventScroll: true });
    }
    button.addEventListener("click", function () { setOpen(!panel.classList.contains("is-open")); });
    closeButton.addEventListener("click", function () { setOpen(false); });
    document.addEventListener("keydown", function (event) { if (event.key === "Escape") setOpen(false); });
    document.addEventListener("pointerdown", function (event) {
        if (panel.classList.contains("is-open") && !wrap.contains(event.target)) setOpen(false);
    });
})();
</script>
