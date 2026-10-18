<?php
// The authenticated page renders launchers before paint; controllers hydrate them in place.
$nativeMessaging = isset($_SESSION['authentik_username'],$_SESSION['authentik_email']) && in_array(strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]),['dnct.pl','www.dnct.pl','game.dnct.pl'],true);
if($nativeMessaging):
require_once __DIR__.'/messaging-flash.php'; ?>
<style>
#dnct-bell-wrap[data-ssr]{display:block;position:relative;flex:0 0 42px;margin-left:8px}#dnct-bell-wrap[data-ssr] button{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border:1px solid #a78bfa40;background:#211b2c;color:#ddd6fe;border-radius:14px;cursor:pointer}#dnct-support-chat-wrap[data-ssr]{position:fixed;right:calc(1rem + env(safe-area-inset-right));bottom:calc(1rem + env(safe-area-inset-bottom));z-index:75}#dnct-support-chat-wrap[data-ssr] button{display:flex;align-items:center;justify-content:center;gap:8px;width:94px;height:48px;min-width:48px;padding:0 12px;border:1px solid #a78bfa55;border-radius:28px;background:#1d1729;color:#ddd1ff;box-shadow:0 7px 25px #0006;font:650 13px/1 Inter,system-ui,sans-serif;cursor:pointer}#dnct-support-chat-wrap[data-ssr] svg{width:23px;height:23px;flex:0 0 23px}
</style>
<div id="dnct-bell-wrap" class="is-navbar" data-ssr aria-busy="true"><button id="dnct-bell-btn" type="button" aria-label="Powiadomienia" aria-expanded="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg></button></div>
<div id="dnct-support-chat-wrap" data-ssr aria-busy="true"><button id="dnct-support-chat-btn" type="button" aria-label="Otwórz czat" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.5-5A9 9 0 1 1 21 11Z"/><path d="M8 10h8M8 14h5"/></svg><span>Czat</span></button></div>
<script>
(()=>{const bell=document.getElementById('dnct-bell-wrap');document.querySelector('#main-header .profile-magic-wrapper')?.after(bell);const pending=e=>{const b=e.target.closest('[data-ssr] button');if(b)window.DNCTPendingLauncher=b.id;};document.addEventListener('click',pending);document.addEventListener('dnct:messaging-ready',()=>{document.removeEventListener('click',pending);document.getElementById(window.DNCTPendingLauncher)?.click();delete window.DNCTPendingLauncher;},{once:true});})();
</script>
<script src="/messaging/assets/js/messaging-native.js" defer></script>
<?php endif;
return $nativeMessaging;
