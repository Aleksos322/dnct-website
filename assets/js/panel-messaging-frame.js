(() => {
 'use strict';
 if (window.parent === window) return;
 const send = data => parent.postMessage({source:'dnct-messaging',...data},location.origin);
 const bell=document.getElementById('dnct-bell-btn'),chat=document.getElementById('dnct-support-chat-btn');
 let last='',timer;
 const opened=()=>Boolean(document.querySelector('dialog[open]')||chat?.getAttribute('aria-expanded')==='true'||bell?.getAttribute('aria-expanded')==='true'||window.DNCTSupport?.isOpen());
 const report=()=>{
  const state={type:'state',open:opened(),notifications:Number(document.getElementById('dnct-bell-badge')?.textContent)||0,chatUnread:Boolean(chat?.querySelector('.dm-unread-dot:not([hidden])'))};
  const next=JSON.stringify(state);if(next!==last){last=next;send(state);}
 };
 const close=()=>{document.dispatchEvent(new Event('dnct:close-chat'));document.dispatchEvent(new Event('dnct:close-notifications'));window.DNCTSupport?.close();document.querySelectorAll('dialog[open]').forEach(d=>d.close());};
 window.addEventListener('message',event=>{
  if(event.origin!==location.origin||event.source!==parent||event.data?.source!=='dnct-panel')return;
  if(event.data.type==='notifications')bell?.click();
  if(event.data.type==='chat')chat?.click();
  if(event.data.type==='support'){document.dispatchEvent(new Event('dnct:close-chat'));window.DNCTSupport?.open();}
  if(event.data.type==='close')close();
  if(event.data.type==='activity')document.dispatchEvent(new Event('pointermove'));
  report();
 });
 document.addEventListener('click',event=>{if(event.target===document.body||event.target===document.documentElement)close();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!document.querySelector('dialog[open]'))close();});
 new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(report,40);}).observe(document.body,{subtree:true,attributes:true,attributeFilter:['class','hidden','aria-expanded','open'],childList:true,characterData:true});
 document.addEventListener('click',event=>{const a=event.target.closest('a');if(a&&a.href&&!a.href.startsWith('blob:')){const u=new URL(a.href);if(u.origin===location.origin&&!u.pathname.startsWith('/messaging/'))u.href='https://beta.dnct.pl'+u.pathname+u.search+u.hash;a.href=u.href;a.target='_blank';a.rel='noopener noreferrer';}},true);
 send({type:'ready'});report();
})();
