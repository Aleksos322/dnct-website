<?php
if (!isset($_SESSION['authentik_username'])) return;
$messengerAdmin = defined('ADMIN_BASE_PATH');
$messengerRoot = dirname(__DIR__, 2);
require_once $messengerRoot.'/backend/lib/support_message_features.php';
$messengerLimits=['image'=>SUPPORT_MESSAGE_IMAGE_MAX_BYTES,'video'=>SUPPORT_MESSAGE_VIDEO_MAX_BYTES,'files'=>SUPPORT_MESSAGE_MEDIA_MAX_FILES];
$messengerApi = $messengerAdmin ? '/czat-api' : '/api/chat.php';
require_once __DIR__ . '/chat-assets.php';
?>
<?php if ($messengerAdmin): ?>
<div id="dnct-admin-chat-launcher">
<button id="dnct-admin-chat-button" type="button" aria-label="Otwórz czat" aria-controls="dnct-messenger" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.5-5A9 9 0 1 1 21 11Z"/><path d="M8 10h8M8 14h5"/></svg><span class="dm-unread-dot" hidden></span></button>
</div>
<?php endif; ?>
<section id="dnct-messenger" class="dm-panel" hidden aria-label="Czat" data-media-limits="<?= htmlspecialchars(json_encode($messengerLimits), ENT_QUOTES) ?>" data-api="<?= $messengerApi ?>" data-admin="<?= $messengerAdmin?'1':'0' ?>" data-trigger="<?= $messengerAdmin?'dnct-admin-chat-button':'dnct-support-chat-btn' ?>" data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
 <header class="dm-top"><div><strong>Czaty</strong><span class="dm-connection" role="status">Łączenie…</span></div><div class="dm-top-actions"><button type="button" class="dm-close" data-dm-close aria-label="Zamknij czat">×</button></div></header>
 <div class="dm-body">
  <aside class="dm-sidebar" data-lenis-prevent>
   <label class="dm-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg><input type="search" data-dm-search placeholder="Szukaj osoby lub e-maila" aria-label="Szukaj osoby lub rozmowy" autocomplete="off" maxlength="150"></label>
   <div class="dm-directory" data-dm-directory data-lenis-prevent hidden aria-label="Wyniki wyszukiwania"></div>
   <p class="dm-section-label">Twoje rozmowy</p>
   <div class="dm-conversations" data-dm-conversations data-lenis-prevent></div>
   <?php if (!$messengerAdmin): ?><div class="dm-sidebar-footer"><button type="button" data-dm-support><span aria-hidden="true">↗</span> Zgłoś problem</button></div><?php endif; ?>
  </aside>
  <section class="dm-thread" aria-label="Rozmowa">
   <div class="dm-welcome" data-dm-welcome><span aria-hidden="true">✦</span><h3>Porozmawiajmy</h3><p>Wybierz rozmowę lub znajdź osobę po nazwie albo adresie e-mail.</p></div>
   <div class="dm-active" data-dm-active hidden>
    <header class="dm-peer"><button type="button" data-dm-back aria-label="Wróć do rozmów">←</button><span class="dm-avatar" data-dm-avatar></span><div><strong data-dm-peer></strong><small data-dm-peer-detail></small></div><?php if (!$messengerAdmin): ?><button type="button" class="dm-support-mobile" data-dm-support-mobile>Zgłoś problem</button><?php endif; ?></header>
    <div class="dm-chat-mount" data-dm-chat></div>
   </div>
  </section>
 </div>
 <p class="dm-status" data-dm-status role="status" aria-live="polite"></p>
</section>
<script src="/assets/js/messenger-controller.js?v=<?= (int)filemtime($messengerRoot . '/assets/js/messenger-controller.js') ?>" defer></script>
