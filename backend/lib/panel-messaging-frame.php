<?php
declare(strict_types=1);
if(!isset($p) || !isset($_SESSION['authentik_username'])) {http_response_code(403);exit;}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(),ENT_QUOTES) ?>"><title>Komunikacja DNCT</title>
<link rel="stylesheet" href="/assets/css/tailwind.css"><link rel="stylesheet" href="/assets/css/notification-bell.css">
<style>html,body{background:transparent!important;margin:0;width:100%;height:100%;overflow:hidden;color:#e5e7eb;font-family:Montserrat,system-ui,sans-serif}body>header{height:76px;position:fixed;top:0;left:0;right:0;pointer-events:none}#dnct-bell-wrap{position:fixed;right:24px;top:16px;pointer-events:auto}#dnct-bell-btn,#dnct-support-chat-btn{visibility:hidden}#dnct-support-chat-wrap{pointer-events:none}#dnct-support-chat-panel{pointer-events:auto}a{color:inherit}button,input,textarea{font:inherit}</style>
<script>
(()=>{const token=document.querySelector('meta[name="csrf-token"]').content;const fetchOriginal=window.fetch;
window.fetch=(input,init={})=>{const url=new URL(typeof input==='string'||input instanceof URL?input:input.url,location.href);if(url.origin===location.origin){const headers=new Headers(init.headers||(input instanceof Request?input.headers:undefined));headers.set('X-CSRF-TOKEN',token);init={...init,headers};}return fetchOriginal(input,init);};
const open=XMLHttpRequest.prototype.open,send=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.open=function(method,url,...args){this.dnctSameOrigin=new URL(url,location.href).origin===location.origin;return open.call(this,method,url,...args);};XMLHttpRequest.prototype.send=function(body){if(this.dnctSameOrigin)this.setRequestHeader('X-CSRF-TOKEN',token);return send.call(this,body);};})();
</script><script src="/assets/js/sound.js"></script></head><body>
<header><?php require __DIR__.'/../../assets/clear/notification-bell.php'; ?></header>
<?php require __DIR__.'/../../assets/clear/support-chat.php';require __DIR__.'/../../assets/clear/support-ticket-panel.php';require __DIR__.'/../../assets/clear/direct-chat.php'; ?>
<script src="/assets/js/panel-messaging-frame.js" defer></script>
</body></html>
