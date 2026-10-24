<?php
// The authenticated page renders launchers before paint; controllers hydrate them in place.
$nativeSite = in_array(strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]),['dnct.pl','www.dnct.pl','game.dnct.pl'],true);
$nativeMessaging = $nativeSite && isset($_SESSION['authentik_username'],$_SESSION['authentik_email']);
if($nativeSite) require_once __DIR__.'/messaging-flash.php';
if($nativeMessaging): ?>
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
?>
<?php if ($nativeSite && !$nativeMessaging): ?>
<style>
.dnct-guest-chat{--guest-text:#f4f2fb;--guest-muted:#aaa6bc;--guest-line:#ffffff12;position:fixed;right:calc(1rem + env(safe-area-inset-right));bottom:calc(1rem + env(safe-area-inset-bottom));z-index:75;font:14px/1.5 Inter,ui-sans-serif,system-ui,sans-serif;color:var(--guest-text)}
.dnct-guest-chat *{box-sizing:border-box}.dnct-guest-chat button,.dnct-guest-chat a{font:inherit;touch-action:manipulation}.dnct-guest-launcher{position:relative;z-index:3;display:flex;align-items:center;justify-content:center;gap:8px;width:94px;height:48px;min-width:48px;padding:0 12px;border:1px solid #a78bfa55;border-radius:28px;background:#1d1729;color:#ddd1ff;box-shadow:0 7px 25px #0006;font-weight:650;cursor:pointer;transition:width .32s cubic-bezier(.22,1,.36,1),padding .32s,gap .32s,background .2s}
.dnct-guest-launcher:hover{background:#2c203d}.dnct-guest-launcher svg{width:23px;height:23px;flex:0 0 23px}.dnct-guest-launcher span{display:block;max-width:40px;overflow:hidden;opacity:1;white-space:nowrap;transition:max-width .32s cubic-bezier(.22,1,.36,1),opacity .22s}.dnct-guest-launcher.is-open{width:48px;padding:0;gap:0}.dnct-guest-launcher.is-open span{max-width:0;opacity:0}
.dnct-guest-backdrop{position:fixed;inset:0;z-index:0;background:#07060b70;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}.dnct-guest-backdrop[hidden],.dnct-guest-panel[hidden]{display:none}
.dnct-guest-panel{position:fixed;right:calc(1rem + env(safe-area-inset-right));bottom:calc(4.8rem + env(safe-area-inset-bottom));z-index:2;display:flex;flex-direction:column;width:min(860px,calc(100vw - 2rem));height:min(660px,calc(100dvh - 84px));max-height:calc(100dvh - 84px);overflow:hidden;border:1px solid #a78bfa44;border-radius:24px;background:#111018;color:var(--guest-text);box-shadow:0 24px 80px #0009;text-align:left;transform-origin:bottom right;outline:none}
.dnct-guest-head{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:102px;padding:17px 19px;background:#1a1622;border-bottom:1px solid var(--guest-line);flex-shrink:0}.dnct-guest-head strong{display:block;font-size:18px;font-weight:750}.dnct-guest-status{display:inline-flex;align-items:center;gap:7px;margin-top:8px;padding:6px 10px;border:1px solid #a78bfa66;border-radius:8px;background:#8b5cf61b;color:#ddd6eb;font-size:10px}.dnct-guest-status i{width:8px;height:8px;border-radius:50%;background:#32d583;box-shadow:0 0 10px #32d58388}.dnct-guest-close{display:grid;width:34px;height:34px;place-items:center;padding:0;border:0;border-radius:11px;background:#ffffff08;color:#bcb3ce;font:25px/1 Arial,sans-serif;cursor:pointer}.dnct-guest-close:hover{background:#ffffff10;color:#fff}
.dnct-guest-body{position:relative;display:flex;flex:1;min-height:0;overflow:hidden}.dnct-guest-blurred{display:flex;flex:1;min-width:0;filter:blur(5px);opacity:.42;pointer-events:none;user-select:none}.dnct-guest-sidebar{display:flex;flex-direction:column;flex:0 0 270px;width:270px;padding:14px 10px 10px;border-right:1px solid var(--guest-line);overflow:hidden}.dnct-guest-search{height:46px;margin:0 0 18px;border:1px solid #ffffff14;border-radius:22px;background:#ffffff05}.dnct-guest-label{width:112px;height:9px;margin:3px 9px 11px;border-radius:5px;background:#ffffff28}.dnct-guest-tools{display:flex;gap:8px;margin:0 9px 12px}.dnct-guest-tools i{width:30px;height:30px;border-radius:50%;background:#a78bfa22}.dnct-guest-people{display:grid;gap:8px;overflow:hidden}.dnct-guest-person{display:flex;align-items:center;gap:10px;height:63px;padding:8px;border-radius:14px}.dnct-guest-avatar{width:38px;height:38px;flex:0 0 38px;border:1px solid #a78bfa35;border-radius:50%;background:#6f45c755}.dnct-guest-lines{display:grid;gap:7px;flex:1}.dnct-guest-lines i{display:block;width:72%;height:9px;border-radius:5px;background:#ffffff28}.dnct-guest-lines i+ i{width:54%;height:7px;background:#ffffff18}.dnct-guest-report{height:42px;margin-top:auto;border:1px solid #a78bfa40;border-radius:13px;background:#8b5cf610}
.dnct-guest-thread{display:grid;flex:1;min-width:0;place-content:center;justify-items:center;color:var(--guest-muted)}.dnct-guest-spark{font-size:37px;color:#a78bfa}.dnct-guest-thread i{display:block;width:180px;height:16px;margin-top:18px;border-radius:8px;background:#ffffff25}.dnct-guest-thread i+i{width:300px;max-width:70%;height:10px;margin-top:12px;background:#ffffff18}
.dnct-guest-lock{position:absolute;inset:0;z-index:2;display:grid;place-content:center;justify-items:center;padding:30px;text-align:center;background:#0a09104d;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px)}.dnct-guest-lock-icon{display:grid;width:52px;height:52px;place-items:center;margin-bottom:16px;border:1px solid #a78bfa66;border-radius:17px;color:#d8c8fa;background:#8b5cf62b;font-size:23px}.dnct-guest-lock h2{max-width:430px;margin:0 0 8px;color:#f5f1fb;font-size:20px;line-height:1.3}.dnct-guest-lock p{max-width:390px;margin:0 0 20px;color:#b7aec2;font-size:13px;line-height:1.6}.dnct-guest-login{display:inline-flex;min-height:44px;align-items:center;justify-content:center;padding:0 20px;border:1px solid #9b75f4;border-radius:12px;color:#fff;background:linear-gradient(135deg,#7c3aed,#4f46e5);font-weight:800;text-decoration:none}.dnct-guest-register{margin-top:11px;color:#bda7ee;font-size:12px;text-decoration:none}
.dnct-guest-chat button:focus-visible,.dnct-guest-chat a:focus-visible{outline:2px solid #c4b5fd;outline-offset:3px}
@media(max-width:640px){.dnct-guest-chat{right:calc(10px + env(safe-area-inset-right));bottom:calc(10px + env(safe-area-inset-bottom))}.dnct-guest-panel{right:10px;bottom:calc(4.5rem + env(safe-area-inset-bottom));width:calc(100vw - 20px);height:min(680px,calc(100dvh - 110px));max-height:calc(100dvh - 110px);border-radius:20px}.dnct-guest-head{min-height:78px;padding:13px 15px}.dnct-guest-head strong{font-size:16px}.dnct-guest-sidebar{width:100%;flex-basis:100%;border-right:0}.dnct-guest-thread{display:none}.dnct-guest-lock{padding:24px}.dnct-guest-lock h2{font-size:18px}.dnct-guest-launcher{width:94px;padding:0 12px}.dnct-guest-launcher.is-open{width:48px;padding:0}}
@media(prefers-reduced-motion:reduce){.dnct-guest-launcher{transition:none}}
</style>
<aside class="dnct-guest-chat" aria-label="Czat i tickety">
 <div class="dnct-guest-backdrop" hidden aria-hidden="true"></div>
 <section class="dnct-guest-panel" id="dnct-guest-panel" hidden tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="dnct-guest-title">
  <header class="dnct-guest-head"><div><strong id="dnct-guest-title">Czaty</strong><span class="dnct-guest-status"><i aria-hidden="true"></i>Powiadomienia systemowe</span></div><button class="dnct-guest-close" type="button" aria-label="Zamknij">×</button></header>
  <div class="dnct-guest-body">
   <div class="dnct-guest-blurred" aria-hidden="true">
    <div class="dnct-guest-sidebar"><div class="dnct-guest-search"></div><div class="dnct-guest-label"></div><div class="dnct-guest-tools"><i></i><i></i><i></i><i></i></div><div class="dnct-guest-people"><div class="dnct-guest-person"><span class="dnct-guest-avatar"></span><span class="dnct-guest-lines"><i></i><i></i></span></div><div class="dnct-guest-person"><span class="dnct-guest-avatar"></span><span class="dnct-guest-lines"><i></i><i></i></span></div><div class="dnct-guest-person"><span class="dnct-guest-avatar"></span><span class="dnct-guest-lines"><i></i><i></i></span></div><div class="dnct-guest-person"><span class="dnct-guest-avatar"></span><span class="dnct-guest-lines"><i></i><i></i></span></div></div><div class="dnct-guest-report"></div></div>
    <div class="dnct-guest-thread"><span class="dnct-guest-spark">✦</span><i></i><i></i></div>
   </div>
   <div class="dnct-guest-lock"><span class="dnct-guest-lock-icon" aria-hidden="true">⌁</span><h2>Dostęp tylko dla zalogowanych użytkowników</h2><p>Czaty, historia rozmów i zgłoszenia są dostępne po zalogowaniu do konta DNCT.</p><a class="dnct-guest-login" href="/login">Zaloguj się</a><a class="dnct-guest-register" href="/register">Nie masz konta? Zarejestruj się</a></div>
  </div>
 </section>
 <button class="dnct-guest-launcher" type="button" aria-controls="dnct-guest-panel" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11a9 9 0 0 1-9 9 10 10 0 0 1-4-.8L3 21l1.5-5A9 9 0 1 1 21 11Z"/><path d="M8 10h8M8 14h5"/></svg><span>Czat</span></button>
</aside>
<script>
(()=>{
 const root=document.querySelector('.dnct-guest-chat');
 const panel=root?.querySelector('.dnct-guest-panel');
 const backdrop=root?.querySelector('.dnct-guest-backdrop');
 const launcher=root?.querySelector('.dnct-guest-launcher');
 const closeButton=root?.querySelector('.dnct-guest-close');
 if(!root||!panel||!backdrop||!launcher||!closeButton)return;
 const reduced=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
 let panelMotion=null,backdropMotion=null,closing=false;
 const stopMotion=()=>{
  panelMotion?.cancel();
  backdropMotion?.cancel();
  panelMotion=null;
  backdropMotion=null;
 };
 const open=()=>{
  stopMotion();
  closing=false;
  panel.hidden=false;
  backdrop.hidden=false;
  launcher.classList.add('is-open');
  launcher.setAttribute('aria-expanded','true');
  if(!reduced()){
   backdropMotion=backdrop.animate([{opacity:0},{opacity:1}],{duration:220,easing:'ease-out'});
   panelMotion=panel.animate([{opacity:0,transform:'translateY(20px) scale(.94)'},{opacity:1,transform:'none'}],{duration:300,easing:'cubic-bezier(.22,1,.36,1)'});
   panelMotion.onfinish=stopMotion;
  }
  panel.focus({preventScroll:true});
 };
 const close=()=>{
  if(closing)return;
  closing=true;
  stopMotion();
  launcher.classList.remove('is-open');
  launcher.setAttribute('aria-expanded','false');
  const finish=()=>{
   panel.hidden=true;
   backdrop.hidden=true;
   closing=false;
   stopMotion();
   launcher.focus();
  };
  if(reduced())return finish();
  const timing={duration:190,easing:'ease-in',fill:'forwards'};
  backdropMotion=backdrop.animate([{opacity:1},{opacity:0}],timing);
  panelMotion=panel.animate([{opacity:1,transform:'none'},{opacity:0,transform:'translateY(14px) scale(.96)'}],timing);
  panelMotion.onfinish=finish;
 };
 launcher.onclick=()=>panel.hidden?open():close();
 closeButton.onclick=close;
 root.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden)close();});
})();
</script>
<?php endif; ?>

<?php
// Own the game layout for guests too: never fall back to the legacy anonymous chat.
return $nativeSite;
