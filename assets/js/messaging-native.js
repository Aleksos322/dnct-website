(() => {
 'use strict';
 if(window.DNCTNativeMessaging)return;
 window.DNCTNativeMessaging=true;
 const owned=[];
 async function boot(){
  const response=await fetch('/messaging/frame',{credentials:'same-origin',cache:'no-store'});
  if(!response.ok)throw Error('Nie udało się wczytać komunikacji.');
  const doc=new DOMParser().parseFromString(await response.text(),'text/html');
  const styles=[...doc.querySelectorAll('link[rel="stylesheet"]')].filter(n=>!n.href.includes('/tailwind.css'));
  await Promise.all(styles.map(n=>new Promise((resolve,reject)=>{const link=document.createElement('link');link.rel='stylesheet';link.href=n.getAttribute('href');link.onload=resolve;link.onerror=reject;document.head.append(link);owned.push(link);})));
  const scripts=[...doc.querySelectorAll('script[src]')].map(n=>n.getAttribute('src')).filter(src=>!src.includes('panel-messaging-frame.js'));
  doc.querySelectorAll('script:not([type="application/json"]),link,style').forEach(n=>n.remove());
  // Keep SSR wrappers and button nodes: no replacement flash or lost early clicks.
  let bell=doc.getElementById('dnct-bell-wrap');if(bell)doc.body.append(bell);
  for(const id of ['dnct-bell-wrap','dnct-support-chat-wrap']){
   const shell=document.querySelector('#'+id+'[data-ssr]'),loaded=doc.getElementById(id);
   if(!shell||!loaded)continue;
   const oldButton=shell.querySelector('button'),newButton=loaded.querySelector('button');
   if(oldButton&&newButton){for(const attr of newButton.attributes)oldButton.setAttribute(attr.name,attr.value);oldButton.replaceChildren(...newButton.childNodes);newButton.replaceWith(oldButton);}
   for(const attr of loaded.attributes)shell.setAttribute(attr.name,attr.value);
   shell.replaceChildren(...loaded.childNodes);loaded.remove();
   if(id==='dnct-bell-wrap')bell=shell;
  }

  doc.querySelectorAll('header').forEach(n=>{if(!n.closest('#dnct-messenger'))n.remove();});
  const root=document.createElement('div');root.id='dnct-native-messaging';root.style.display='contents';
  root.append(...Array.from(doc.body.childNodes));document.body.append(root);owned.push(root);
  const place=()=>{
   const profile=document.querySelector('#main-header .profile-magic-wrapper');
   if(profile&&bell&&bell.previousElementSibling!==profile){profile.after(bell);bell.style.cssText='';}
   else if(!profile&&bell){
    const header=document.querySelector('header:has(#user-panel)');
    const user=header?.querySelector('#user-panel');
    if(user)user.style.marginRight='56px';
    const rect=header?.getBoundingClientRect();
    bell.style.cssText='position:fixed;right:20px;bottom:auto;top:'+Math.max(12,rect?rect.top+(rect.height-42)/2:16)+'px;z-index:9998';
   }
  };
  place();window.addEventListener('resize',place);
  let scheduled=false;new MutationObserver(()=>{if(!scheduled){scheduled=true;requestAnimationFrame(()=>{scheduled=false;place();});}}).observe(document.getElementById('app')||document.body,{childList:true,subtree:true});
  for(const src of [...new Set(scripts)]){if(src.startsWith('/messaging/assets/js/')){const preload=document.createElement('link');preload.rel='preload';preload.as='script';preload.href=src;document.head.append(preload);}}
  for(const src of [...new Set(scripts)]){
   if(!src.startsWith('/messaging/assets/js/'))continue;
   await new Promise((resolve,reject)=>{const script=document.createElement('script');script.src=src;script.async=false;script.onload=resolve;script.onerror=reject;document.body.append(script);owned.push(script);});
  }
  document.querySelectorAll('[data-ssr]').forEach(el=>{el.removeAttribute('data-ssr');el.removeAttribute('aria-busy');});
  document.dispatchEvent(new Event('dnct:messaging-ready'));
 }
 boot().catch(()=>{const notice=document.createElement('button');notice.type='button';notice.textContent='Nie udało się połączyć z czatem. Odśwież stronę.';notice.style.cssText='position:fixed;right:16px;bottom:16px;z-index:9998;background:#211b2c;color:#ddd6fe;padding:12px;border-radius:14px;border:1px solid #a78bfa55';notice.onclick=()=>location.reload();document.body.append(notice);});
})();
