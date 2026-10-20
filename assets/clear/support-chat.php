<?php
if (!isset($_SESSION['authentik_username'])) return;
/* The launcher stays visible while its label collapses. */
?>
<div id="dnct-support-chat-wrap">
    <section id="dnct-support-chat-panel" class="dnct-support-chat-panel" aria-hidden="true" aria-label="Wsparcie DNCT" tabindex="-1"></section>
    <button id="dnct-support-chat-btn" type="button" aria-label="Otwórz czat" aria-controls="dnct-support-chat-panel" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.5-5A9 9 0 1 1 21 11Z"/><path d="M8 10h8M8 14h5"/></svg><span>Czat</span>
    </button>
</div>
