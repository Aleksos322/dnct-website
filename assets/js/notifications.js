(() => {
 'use strict';
 const notificationTypes={
 info:{label:'Informacja',path:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>'},
 success:{label:'Sukces',path:'<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'},
 warning:{label:'Ostrzeżenie',path:'<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5M12 17h.01"/>'},
 error:{label:'Ważne',path:'<path d="m8 3-5 5v8l5 5h8l5-5V8l-5-5H8Z"/><path d="M12 7v6M12 17h.01"/>'}
 };
 const typeMeta=type=>notificationTypes[type]||notificationTypes.info;
 const typeIcon=type=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+typeMeta(type).path+'</svg>';

 const wrap=document.getElementById('dnct-bell-wrap'); if(!wrap)return;
 const button=wrap.querySelector('#dnct-bell-btn'),badge=wrap.querySelector('#dnct-bell-badge');
 const panel=wrap.querySelector('#dnct-bell-dropdown'),list=wrap.querySelector('#dnct-bell-list'),status=wrap.querySelector('#dnct-notif-status'),all=wrap.querySelector('#dnct-mark-all-read');
 const viewport=document.createElement('div');viewport.className='dnct-notif-viewport';document.body.append(viewport);viewport.append(panel);panel.inert=true;
 const heading=panel.querySelector('.dnct-notif-head > span');const headingCopy=document.createElement('div');headingCopy.className='dnct-notif-heading';heading.before(headingCopy);headingCopy.append(heading);const preview=document.createElement('button');preview.type='button';preview.className='dnct-notif-preview-mode';preview.hidden=true;preview.onclick=()=>{if(busy||!isAdmin)return;adminPreview=!adminPreview;savePreview();activeTab='normal';tabState();render();if(!matchMedia('(prefers-reduced-motion: reduce)').matches)list.animate([{opacity:0,transform:'translateY(-6px)'},{opacity:1,transform:'translateY(0)'}],{duration:220,easing:'ease-out'});};headingCopy.append(preview);
 const previewPreferenceKey=wrap.dataset.preferenceKey?'dnct.notifications.preview.'+wrap.dataset.preferenceKey:null;
 function savedPreview(){try{return !previewPreferenceKey||localStorage.getItem(previewPreferenceKey)!=='user';}catch{return true;}}
 function savePreview(){if(!isAdmin||!previewPreferenceKey)return;try{localStorage.setItem(previewPreferenceKey,adminPreview?'admin':'user');}catch{}}
 const csrf=wrap.dataset.csrf;let activeTab='normal',adminRecords=[],isAdmin=false,adminPreview=savedPreview();const counts={normal:0,admin:0};const tabs=document.createElement('div');tabs.className='dnct-notif-tabs';tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Rodzaj powiadomień');tabs.hidden=true;list.before(tabs);list.id='dnct-bell-list';list.setAttribute('role','tabpanel');const tabButtons={};for(const [id,label] of [['normal','Zwykłe'],['admin','Admin']]){const tab=document.createElement('button');tab.type='button';tab.id='dnct-notif-tab-'+id;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',list.id);tab.onclick=()=>switchTab(id);tabs.append(tab);tabButtons[id]=tab;tab.dataset.label=label;}function tabState(){trashState();preview.hidden=!isAdmin;preview.textContent='Podgląd: '+(adminPreview?'Admin':'Użytkownik')+(!adminPreview&&counts.admin?' · alerty: '+counts.admin:'');preview.setAttribute('aria-label',preview.textContent+'. Przełącz podgląd');preview.disabled=busy;tabs.hidden=!(isAdmin&&adminPreview);for(const [id,tab] of Object.entries(tabButtons)){tab.setAttribute('aria-selected',String(id===activeTab));tab.tabIndex=id===activeTab?0:-1;tab.textContent=tab.dataset.label+(counts[id]?' ('+counts[id]+')':'');}if(isAdmin&&adminPreview){list.setAttribute('role','tabpanel');list.setAttribute('aria-labelledby',tabButtons[activeTab].id);}else{list.removeAttribute('role');list.removeAttribute('aria-labelledby');}all.textContent=isAdmin&&adminPreview?'Odczytaj tę zakładkę':'Przeczytaj wszystko';all.disabled=busy||(isAdmin&&!adminPreview?counts.normal+counts.admin:counts[activeTab])===0;}function switchTab(id){if(busy||!isAdmin||!adminPreview||id===activeTab)return;activeTab=id;window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);tabState();render();if(!matchMedia('(prefers-reduced-motion: reduce)').matches)list.animate([{opacity:0,transform:'translateX('+(id==='admin'?'10px':'-10px')+')'},{opacity:1,transform:'translateX(0)'}],{duration:220,easing:'ease-out'});}tabs.onkeydown=e=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(e.key)){e.preventDefault();switchTab(e.key==='Home'?'normal':e.key==='End'?'admin':activeTab==='normal'?'admin':'normal');tabButtons[activeTab].focus();}};
 let records=[],count=0,revision=0,busy=false,loading=false,nextRequest=0,returnFocus=null,closing=null,opened=false;
 const modal=document.createElement('dialog');modal.className='dnct-notification-dialog';modal.setAttribute('aria-labelledby','dnct-notification-title');
 modal.innerHTML='<div class="dnct-notification-top"><span id="dnct-notification-kind">Powiadomienie</span><button type="button" aria-label="Zamknij powiadomienie">×</button></div><div class="dnct-notification-content"><h2 id="dnct-notification-title"></h2><p></p><a hidden>Przejdź do szczegółów</a><time class="dnct-notification-footer"></time></div>';
 let previewClosing=null,previewScroll=null;
 function lockPreviewScroll(){if(previewScroll)return;const root=document.documentElement,body=document.body,lenis=window.dnctLenis;previewScroll={x:scrollX,y:scrollY,lenis,resume:!!lenis&&!lenis.isStopped,styles:[root,body].map(el=>({el,overflow:el.style.getPropertyValue('overflow'),priority:el.style.getPropertyPriority('overflow')}))};lenis?.stop();for(const {el} of previewScroll.styles)el.style.setProperty('overflow','hidden','important');}
 function unlockPreviewScroll(){if(!previewScroll)return;const saved=previewScroll;previewScroll=null;for(const {el,overflow,priority} of saved.styles){if(overflow)el.style.setProperty('overflow',overflow,priority);else el.style.removeProperty('overflow');}window.scrollTo({left:saved.x,top:saved.y,behavior:'instant'});if(saved.resume){saved.lenis.start();saved.lenis.scrollTo(saved.y,{immediate:true,force:true});}}
 modal.setAttribute('data-lenis-prevent','');

 function closePreview(){if(!modal.open||previewClosing!==null)return;modal.querySelectorAll('video').forEach(video=>video.pause());if(matchMedia('(prefers-reduced-motion: reduce)').matches){modal.close();return;}modal.classList.add('is-closing');previewClosing=setTimeout(()=>{modal.close();modal.classList.remove('is-closing');previewClosing=null;},200);}
 modal.addEventListener('cancel',e=>{e.preventDefault();closePreview();});
 document.body.append(modal);modal.querySelector('button').onclick=()=>closePreview();
 modal.addEventListener('click',e=>{if(e.target===modal){const r=modal.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closePreview();}});
 modal.addEventListener('close',()=>{unlockPreviewScroll();modal.querySelectorAll('video').forEach(video=>video.pause());returnFocus?.focus({preventScroll:true});});
 function dock(){const profile=document.querySelector('#main-header .profile-magic-wrapper');if(profile&&!wrap.classList.contains('is-navbar')){profile.after(wrap);wrap.classList.add('is-navbar');}fit();}
 function fit(){
  const r=button.getBoundingClientRect(),header=document.getElementById('main-header'),edge=Math.max(r.bottom,(header?.querySelector('nav')||header)?.getBoundingClientRect().bottom||0);
  const top=Math.max(0,edge),height=Math.max(80,Math.min(680,innerHeight-top-12));
  const width=Math.min(460,innerWidth-24);viewport.style.width=width+'px';viewport.style.top=top+'px';viewport.style.right='auto';const cw=document.documentElement.clientWidth;
  // Prawa krawędź listy pod prawą krawędzią dzwonka; dawniej przy krawędzi okna, na 1920 ok. 170 px obok dzwonka.
  viewport.style.left=(r.width?Math.max(12,Math.min(cw-width-12,Math.round(r.right)-width)):Math.max(12,cw-width-16))+'px';viewport.style.height=height+'px';
  panel.style.position='relative';panel.style.top='auto';panel.style.bottom='auto';panel.style.right='auto';panel.style.maxHeight=height+'px';
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',dock,{once:true});else dock();
 window.addEventListener('resize',fit);window.addEventListener('scroll',fit,{passive:true});
 let noteTimer=null,noteClear=null;
 function note(text,error=false){clearTimeout(noteTimer);clearTimeout(noteClear);if(!text){status.classList.remove('is-visible');noteClear=setTimeout(()=>{status.textContent='';},260);return;}status.textContent=text;status.dataset.error=String(error);requestAnimationFrame(()=>status.classList.add('is-visible'));if(!error)noteTimer=setTimeout(()=>note(''),4500);}
 let pendingBellShake=false;document.addEventListener('visibilitychange',()=>{if(!document.hidden&&pendingBellShake){pendingBellShake=false;shakeBell();}});
 function shakeBell(){if(document.hidden){pendingBellShake=true;return;}const icon=button.querySelector('svg');if(!icon||document.hidden||matchMedia('(prefers-reduced-motion: reduce)').matches||icon.getAnimations().some(a=>a.playState==='running'))return;icon.animate([0,-11,10,-7,5,-2,0].map(angle=>({transform:'rotate('+angle+'deg)'})),{duration:1000,easing:'ease-in-out'});}
 // Dzwonek trzęsie się tylko przy nowym powiadomieniu (update/dźwięk), nie w kółko co kilka sekund.

 document.addEventListener('dnct:notification-sound',shakeBell);
 function update(n){const previous=count;count=Number(n)||0;if(count>previous)shakeBell();badge.textContent=String(count);badge.classList.toggle('hidden',count===0);button.classList.toggle('dnct-bell-btn--unread',count>0);button.setAttribute('aria-label','Powiadomienia: '+count+' nieprzeczytanych');tabState();}
 function close(){opened=false;readHere.clear();renderSignature='';panel.classList.remove('is-open');panel.inert=true;clearTimeout(closing);closing=setTimeout(()=>{if(!opened)panel.classList.add('hidden');},matchMedia('(prefers-reduced-motion: reduce)').matches?0:680);button.setAttribute('aria-expanded','false');button.classList.remove('dnct-bell-btn--active');}
 function date(value){const d=new Date(String(value||'').replace(' ','T')+(/[zZ]|[+-]\d{2}:?\d{2}$/.test(String(value||''))?'':'Z'));return Number.isNaN(d.getTime())?'':d.toLocaleString('pl-PL',{dateStyle:'medium',timeStyle:'short'});}
 let disposeMedia=()=>{},previewId=null,previewVersion=null;
 function show(n,trigger){
  modal.querySelector('.dnct-preview-save')?.remove();const previewSave=saveButton(n);previewSave.classList.add('dnct-preview-save');modal.querySelector('.dnct-notification-top button').before(previewSave);
  previewId=String(n.id);previewVersion=JSON.stringify([n.title,n.message,n.created_at,n.attachments]);
  disposeMedia();
  returnFocus=button;modal.querySelectorAll('.dnct-notif-read-error').forEach(el=>el.remove());
  modal.querySelector('h2').textContent=n.title||'Powiadomienie';modal.querySelector('p').textContent=n.message||'';
  modal.querySelector('time').textContent=date(n.created_at)+(n.author?' · Autor: '+n.author:'');
  modal.querySelector('.dnct-notif-files')?.remove();
  const files=document.createElement('div');files.className='dnct-notif-files';
  for(const file of n.attachments||[]){if(!/^[a-f0-9]{32}$/.test(file.id))continue;const url='/api/notifications/media.php?id='+file.id;const box=document.createElement('div');if(file.mime_type.startsWith('image/')){const img=document.createElement('img');img.src=url;img.alt='Zdjęcie dołączone do powiadomienia';img.loading='lazy';img.draggable=false;const expand=document.createElement('button');expand.type='button';expand.className='dnct-notif-image-open';expand.setAttribute('aria-label','Otwórz większy podgląd zdjęcia');expand.append(img);expand.onclick=()=>{const proto=window.DNCTChat?.Conversation.prototype;if(!proto)return;proto.lightbox.call({root:modal,dialog:proto.dialog,mediaUrl:proto.mediaUrl},{...file,url},false,0,{display_name:n.author||'Powiadomienie',username:n.author||'',created_at:n.created_at});};box.append(expand);}else if(file.mime_type.startsWith('video/')){const video=document.createElement('video');video.src=url;video.controls=true;video.preload='metadata';video.playsInline=true;box.append(video);}files.append(box);}
  if(files.childElementCount>1){
   const slides=[...files.children],track=document.createElement('div');track.className='dnct-notif-media-track';track.setAttribute('role','region');track.setAttribute('aria-label','Załączniki powiadomienia');
   slides.forEach((slide,i)=>{slide.className='dnct-notif-media-slide';slide.setAttribute('role','group');slide.setAttribute('aria-label','Załącznik '+(i+1)+' z '+slides.length);track.append(slide);});
   const controls=document.createElement('div');controls.className='dnct-notif-media-controls';const counter=document.createElement('span');counter.className='dnct-notif-media-count';counter.setAttribute('aria-live','polite');
   const arrow=(label,path)=>{const b=document.createElement('button');b.type='button';b.setAttribute('aria-label',label);b.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="'+path+'"/></svg>';return b;};
   const prev=arrow('Poprzedni załącznik','m14 6-6 6 6 6'),next=arrow('Następny załącznik','m10 6 6 6-6 6');controls.append(counter,prev,next);files.append(controls,track);
   let index=0,target=0,frame=0;
   const update=()=>{const width=track.clientWidth+12;if(!track.clientWidth)return;const current=Math.max(0,Math.min(slides.length-1,Math.round(track.scrollLeft/width)));if(current!==index){index=current;slides.forEach((slide,i)=>{if(i!==index)slide.querySelectorAll('video').forEach(v=>v.pause());});if(!matchMedia('(prefers-reduced-motion: reduce)').matches)counter.animate([{opacity:.4,transform:'translateY(3px)'},{opacity:1,transform:'translateY(0)'}],{duration:180});}counter.textContent=(index+1)+' / '+slides.length;prev.disabled=index===0;next.disabled=index===slides.length-1;};
   const go=delta=>{target=Math.max(0,Math.min(slides.length-1,index+delta));track.scrollTo({left:target*(track.clientWidth+12),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});};
   prev.onclick=()=>go(-1);next.onclick=()=>go(1);
   track.addEventListener('scroll',()=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(update);},{passive:true});
   const resize=new ResizeObserver(()=>{track.scrollTo({left:index*(track.clientWidth+12),behavior:'instant'});update();});resize.observe(track);
   disposeMedia=()=>{resize.disconnect();cancelAnimationFrame(frame);};
   counter.textContent='1 / '+slides.length;prev.disabled=true;
  }
  if(files.childElementCount)modal.querySelector('.dnct-notification-footer').before(files);
  const type={info:'Informacja',warning:'Ważna informacja',error:'Ważna informacja',success:'Dobra wiadomość'};
  modal.dataset.type=n.type;const kind=modal.querySelector('#dnct-notification-kind');kind.innerHTML=typeIcon(n.type);kind.append(document.createTextNode(typeMeta(n.type).label+' · '+(n.category_label||'System')));
  const link=modal.querySelector('a');link.hidden=true;link.removeAttribute('href');
  if(typeof n.link==='string'&&!/[\\\x00-\x20]/.test(n.link)&&(/^\/(?!\/)/.test(n.link)||/^https:\/\/(auth\.dnct\.pl|panel\.dnct\.pl|invoice\.stripe\.com|pay\.stripe\.com|admin\.lan\.dnct\.pl)\//.test(n.link)||/^http:\/\/admin\.lan\.dnct\.pl\//.test(n.link))){link.href=n.link;const adminLink=new URL(n.link,location.origin).hostname==='admin.lan.dnct.pl';if(adminLink){link.target='_blank';link.rel='noopener noreferrer';}else{link.removeAttribute('target');link.removeAttribute('rel');}link.textContent=n.action_label||'Przejdź do szczegółów';link.hidden=false;}
  link.onclick=e=>{if(n.audience!=='admin'&&n.category==='support'&&document.getElementById('dnct-support-config')){e.preventDefault();closePreview();close();document.dispatchEvent(new Event('dnct:notification-support'));}};clearTimeout(previewClosing);previewClosing=null;modal.classList.remove('is-closing');lockPreviewScroll();modal.showModal();modal.querySelector('button').focus({preventScroll:true});
  if(!n.is_read)mark(Number(n.id));
 }
 let savedView=false;
 // Przeczytane w tym otwarciu listy zostają na swoim miejscu (dawniej kliknięte zjeżdżało na dół); kolejność porządkuje się po zamknięciu.
 const readHere=new Set(),settled=n=>Boolean(n.is_read)&&!readHere.has(String(n.id));
 const bookmarkSvg='<svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4V3Z"/></svg>';
 function saveButton(n){const b=document.createElement('button');b.type='button';b.className='dnct-save-notification';b.dataset.notificationId=String(n.id);b.innerHTML=bookmarkSvg;const sync=()=>{b.classList.toggle('is-saved',Boolean(n.is_saved));b.setAttribute('aria-pressed',String(Boolean(n.is_saved)));b.title=n.is_saved?'Usuń z zapisanych':'Zapisz powiadomienie';b.setAttribute('aria-label',b.title);};sync();b.onclick=async e=>{e.stopPropagation();if(busy)return;busy=true;revision++;b.disabled=true;try{await request('mark-read',{id:n.id,action:'bookmark',saved:n.is_saved?'0':'1'});const peers=[...document.querySelectorAll('.dnct-save-notification')].filter(el=>el.dataset.notificationId===String(n.id));const row=b.closest('.dnct-notif-item')||peers.map(el=>el.closest('.dnct-notif-item')).find(Boolean);n.is_saved=!n.is_saved;n.is_read=true;for(const item of [...records,...adminRecords]){if(String(item.id)===String(n.id)){item.is_saved=n.is_saved;item.is_read=true;}}sync();for(const peer of peers){peer.classList.toggle('is-saved',Boolean(n.is_saved));peer.setAttribute('aria-pressed',String(Boolean(n.is_saved)));peer.title=b.title;peer.setAttribute('aria-label',b.title);}
if(!matchMedia('(prefers-reduced-motion: reduce)').matches)b.animate([{transform:'scale(1)'},{transform:'scale(1.3)'},{transform:'scale(.92)'},{transform:'scale(1)'}],{duration:380,easing:'cubic-bezier(.2,.8,.2,1)'});
if(savedView&&!n.is_saved){
 if(row&&!matchMedia('(prefers-reduced-motion: reduce)').matches){
  await row.animate([{opacity:1,transform:'scale(1)'},{opacity:0,transform:'scale(.95)'}],{duration:220,fill:'forwards',easing:'ease-out'}).finished.catch(()=>{});
  const rect=row.getBoundingClientRect(),style=getComputedStyle(row);
  await row.animate([{height:rect.height+'px',minHeight:'0',paddingTop:style.paddingTop,paddingBottom:style.paddingBottom,marginTop:style.marginTop,marginBottom:style.marginBottom,borderWidth:style.borderWidth},{height:'0px',minHeight:'0',paddingTop:'0px',paddingBottom:'0px',marginTop:'0px',marginBottom:'0px',borderWidth:'0px'}],{duration:300,fill:'forwards',easing:'cubic-bezier(.22,1,.36,1)'}).finished.catch(()=>{});
 }
 render();
}else{renderSignature=currentSignature();}
note(n.is_saved?'Dodano do zapisanych':'Usunięto z zapisanych');}catch(error){note(error.message,true);}finally{busy=false;b.disabled=false;revision++;tabState();}};return b;}
 const bookmarkStyle=document.createElement('style');bookmarkStyle.textContent=`.dnct-notif-item{box-sizing:border-box;transform-origin:center;position:relative;padding-bottom:38px;cursor:pointer}.dnct-save-notification{display:inline-grid;place-items:center;border:1px solid transparent;background:transparent;color:#92909f;width:30px;height:30px;padding:5px;border-radius:8px;cursor:pointer;transition:color .25s,background .25s,transform .25s}.dnct-save-notification:hover{background:#ffffff0b}.dnct-save-notification svg{pointer-events:none}.dnct-notif-item:has(.dnct-save-notification:hover){transform:none!important}.dnct-save-notification.is-saved{color:#facc15;background:#facc1510}.dnct-notif-item>.dnct-save-notification{position:absolute;left:12px;bottom:6px}.dnct-preview-save{margin-left:auto}.dnct-notification-top>.dnct-preview-save+button{margin-left:8px}.dnct-notif-saved-toggle{color:#facc15!important}.dnct-notif-saved-toggle[aria-pressed=true]{background:#facc1520!important}.dnct-notification-dialog[data-type][open] .dnct-notification-top{background:linear-gradient(115deg,#ffffff0b,transparent 60%),#12131e}.dnct-notif-viewport:has(#dnct-bell-dropdown:not(.hidden)){z-index:95}`;document.head.append(bookmarkStyle);
 let renderSignature='';const currentSignature=()=>JSON.stringify([[...records].sort((a,b)=>a.id-b.id),[...adminRecords].sort((a,b)=>a.id-b.id),savedView,activeTab,adminPreview]);
 function render(){
  const signature=currentSignature();trashState();if(signature===renderSignature)return;renderSignature=signature;
  list.replaceChildren();
  const visible=[...(savedView?[...records,...(isAdmin&&adminPreview?adminRecords:[])]:(activeTab==='admin'?adminRecords:records))].filter(n=>!savedView||Boolean(n.is_saved)).sort((a,b)=>Number(settled(a))-Number(settled(b)));
  if(!visible.length){const empty=document.createElement('p');empty.className='dnct-notif-empty';const symbol=document.createElement('span');symbol.className='dnct-notif-empty-icon';symbol.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M10 21h4"/><path d="m9 10 2 2 4-4"/></svg>';const title=document.createElement('strong');title.textContent=savedView?'Brak zapisanych powiadomień':activeTab==='admin'?'Brak alertów administracyjnych':'Jesteś na bieżąco';const hint=document.createElement('span');hint.textContent='Nowe powiadomienia pojawią się tutaj.';empty.append(symbol,title,hint);list.append(empty);return;}
  let previousUnread=false;
  for(const n of visible){
   if(previousUnread&&settled(n)){const divider=document.createElement('div');divider.className='dnct-notif-read-divider';divider.setAttribute('role','separator');divider.setAttribute('aria-label','Przeczytane powiadomienia');list.append(divider);}
   previousUnread=!settled(n);
   const row=document.createElement('div');row.tabIndex=0;row.setAttribute('role','button');row.onkeydown=e=>{if(e.target===row&&['Enter',' '].includes(e.key)){e.preventDefault();show(n,row);}};row.className='dnct-notif-item dnct-notif-open';row.dataset.read=n.is_read?'1':'0';row.dataset.id=n.id;row.dataset.type=n.type;
   const icon=document.createElement('span');icon.className='dnct-notif-icon';icon.setAttribute('aria-hidden','true');icon.innerHTML=typeIcon(n.type);row.dataset.category=n.category||'system';
   const text=document.createElement('span');text.className='dnct-notif-copy';
   const title=document.createElement('strong');title.className='dnct-notif-title';title.textContent=n.title;
   const preview=document.createElement('span');preview.className='dnct-notif-preview';preview.textContent=n.message;
   const time=document.createElement('time');time.textContent=date(n.created_at)+(n.author?' · Autor: '+n.author:'')+(n.attachments?.length?' · Załączniki: '+n.attachments.length:'');
   const meta=document.createElement('span');meta.className='dnct-notif-category';meta.textContent=typeMeta(n.type).label+' · '+(n.category_label||'System');const metaRow=document.createElement('span');metaRow.className='dnct-notif-meta-row';metaRow.append(meta);if(n.is_read){const read=document.createElement('span');read.className='dnct-notif-read-label';read.innerHTML='Przeczytano <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';metaRow.append(read);}text.append(metaRow,title,preview,time);if(n.link){const action=document.createElement('span');action.className='dnct-notif-action';action.textContent=(n.action_label||'Szczegóły')+' →';text.append(action);}row.append(icon,text,saveButton(n));row.onclick=()=>show(n,row);list.append(row);
  }
 }
 async function request(path,body){
  const res=await fetch('/api/notifications/'+path+'.php'+(path==='list'?'?device='+deviceId():''),{credentials:'same-origin',cache:'no-store',...(body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(body)}:{})});
  const data=await res.json().catch(()=>null);
  if(res.status===429){nextRequest=Date.now()+Math.max(1,Number(res.headers.get('Retry-After'))||30)*1000;throw new Error('Za dużo odświeżeń. Lista wczyta się ponownie za chwilę.');}
  if(res.status===401)throw new Error(data?.reason==='unauthorized'?'Sesja wygasła. Zaloguj się ponownie.':'Dostęp do bety wygasł. Odśwież stronę i zaloguj się.');
  if(!res.ok||!data?.ok)throw new Error(data?.message||'Nie można pobrać powiadomień (HTTP '+res.status+'). Spróbuj ponownie.');
  return data;
 }
 async function refresh(){
  if(loading||busy||Date.now()<nextRequest)return;
  loading=true;const started=revision;
  try{
   const data=await request('list');
   if(started!==revision)return;
   if(!Array.isArray(data.notifications))throw new Error('Serwer zwrócił nieprawidłową listę powiadomień.');
   records=data.notifications;deskState(data.desktop);isAdmin=data.is_admin===true;adminRecords=isAdmin&&Array.isArray(data.admin_notifications)?data.admin_notifications:[];if(!isAdmin)activeTab='normal';counts.normal=Number(data.counts?.normal??data.unread)||0;counts.admin=isAdmin?Number(data.counts?.admin)||0:0;update(data.unread);render();note('');window.DNCTAlerts?.notifications([...records,...adminRecords]);if(opened)window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);
   if(modal.open&&previewId){const updated=[...records,...adminRecords].find(n=>String(n.id)===previewId);if(!updated)closePreview();else if(JSON.stringify([updated.title,updated.message,updated.created_at,updated.attachments])!==previewVersion)show(updated,button);}
  }catch(e){if(started===revision)note(e.message,true);}
  finally{loading=false;}
 }
 async function mark(id){
  if(busy)return;
  if(id)readHere.add(String(id));
  // Zakres wynika z tego, gdzie leży powiadomienie, a nie z otwartej zakładki. W podglądzie
  // "Użytkownik" admin nie widzi zakładek, więc "Przeczytaj wszystko" czyści oba zakresy -
  // inaczej alerty admina zostawały nieprzeczytane i licznik w panelu się nie zgadzał.
  const scope=id?(adminRecords.some(n=>Number(n.id)===id)?'admin':'normal'):(isAdmin&&!adminPreview?'all':activeTab);busy=true;revision++;all.disabled=true;
  try{await request(id?'mark-read':'mark-all-read',id?{id}:{scope});
   for(const n of (scope==='all'?[...records,...adminRecords]:scope==='admin'?adminRecords:records))if(!id||Number(n.id)===id)n.is_read=true;
   if(scope==='all'){counts.normal=0;counts.admin=0;}else counts[scope]=id?Math.max(0,counts[scope]-1):0;
   if(!id&&scope!=='admin')document.dispatchEvent(new Event('dnct:read-all-support'));update(counts.normal+counts.admin);render();window.DNCTAlerts?.notifications([...records,...adminRecords]);note(id?'':scope==='all'?'Wszystkie powiadomienia oznaczono jako przeczytane.':'Powiadomienia w tej zakładce oznaczono jako przeczytane.');
  }catch(e){note(e.message,true);if(modal.open){const msg=document.createElement('p');msg.className='dnct-notif-read-error';msg.textContent='Odczyt nie został zapisany. Zamknij wiadomość i spróbuj ponownie.';modal.querySelector('.dnct-notification-content').append(msg);}}
  finally{busy=false;revision++;update(count);}
 }
 all.onclick=()=>mark(null);
 const clearButton=document.createElement('button');clearButton.type='button';clearButton.className='dnct-notif-clear';clearButton.setAttribute('aria-label','Usuń przeczytane powiadomienia');clearButton.title='Usuń przeczytane powiadomienia';clearButton.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg>';const headActions=document.createElement('div');headActions.className='dnct-notif-head-actions';all.before(headActions);headActions.append(clearButton,all);const savedToggle=document.createElement('button');savedToggle.type='button';savedToggle.className='dnct-notif-clear dnct-notif-saved-toggle';savedToggle.innerHTML=bookmarkSvg;savedToggle.title='Zapisane';savedToggle.setAttribute('aria-label','Zapisane');savedToggle.setAttribute('aria-pressed','false');headActions.prepend(savedToggle);savedToggle.onclick=async()=>{if(busy)return;busy=true;revision++;try{if(!matchMedia('(prefers-reduced-motion: reduce)').matches)await list.animate([{opacity:1,transform:'translateX(0)'},{opacity:0,transform:'translateX(-14px)'}],{duration:180}).finished.catch(()=>{});savedView=!savedView;savedToggle.setAttribute('aria-pressed',String(savedView));heading.textContent=savedView?'Zapisane':'Powiadomienia';render();if(!matchMedia('(prefers-reduced-motion: reduce)').matches)await list.animate([{opacity:0,transform:'translateX(14px)'},{opacity:1,transform:'translateX(0)'}],{duration:260}).finished.catch(()=>{});}finally{busy=false;revision++;}};
 function trashState(){const hasRead=(activeTab==='admin'?adminRecords:records).some(n=>n.is_read&&!n.is_saved);clearButton.disabled=busy||!hasRead||savedView;clearButton.title=hasRead?'Usuń przeczytane powiadomienia':'Brak przeczytanych powiadomień do usunięcia';}
 trashState();
 clearButton.onclick=()=>{if(busy||clearButton.disabled)return;const scope=activeTab,dialog=document.createElement('dialog');dialog.className='dnct-notification-dialog dnct-notif-confirm';dialog.setAttribute('aria-labelledby','dnct-clear-title');dialog.innerHTML='<div class="dnct-notification-content"><h2 id="dnct-clear-title">Usunąć powiadomienia?</h2><p>Usuniemy tylko przeczytane powiadomienia z tej zakładki. Nieprzeczytane i zapisane powiadomienia pozostaną zachowane.</p><p class="dnct-clear-error" role="alert"></p><div class="dnct-clear-actions"><button type="button" data-cancel>Anuluj</button><button type="button" data-confirm>Tak, usuń przeczytane</button></div></div>';document.body.append(dialog);const cancel=dialog.querySelector('[data-cancel]'),confirm=dialog.querySelector('[data-confirm]');cancel.onclick=()=>dialog.close();dialog.addEventListener('close',()=>{dialog.remove();clearButton.focus({preventScroll:true});});dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('click',e=>{e.stopPropagation();if(e.target===dialog&&!busy){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});confirm.onclick=async()=>{if(busy)return;busy=true;revision++;confirm.disabled=cancel.disabled=true;try{const data=await request('clear-read',{scope});if(scope==='admin')adminRecords=adminRecords.filter(n=>!n.is_read||n.is_saved);else records=records.filter(n=>!n.is_read||n.is_saved);render();note(data.hidden?'Usunięto przeczytane powiadomienia.':'Brak przeczytanych powiadomień do usunięcia.');dialog.close();}catch(e){dialog.querySelector('.dnct-clear-error').textContent=e.message;}finally{busy=false;revision++;confirm.disabled=cancel.disabled=false;update(count);}};dialog.showModal();cancel.focus();};
 button.onclick=()=>{window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);shakeBell();if(opened){close();return;}clearTimeout(closing);opened=true;render();panel.inert=false;panel.classList.remove('hidden');fit();void panel.offsetHeight;requestAnimationFrame(()=>{if(opened)panel.classList.add('is-open');});button.setAttribute('aria-expanded','true');button.classList.add('dnct-bell-btn--active');fit();document.dispatchEvent(new Event('dnct:close-chat'));const c=document.getElementById('dnct-support-chat-panel');if(c){c.classList.remove('is-open');c.setAttribute('aria-hidden','true');}refresh();};
 document.addEventListener('click',e=>{if(!wrap.contains(e.target)&&!panel.contains(e.target)&&!modal.contains(e.target)&&!modal.open&&!document.querySelector('.dnct-notif-confirm[open]'))close();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.open&&!document.querySelector('.dnct-notif-confirm[open]')&&!panel.classList.contains('hidden')){close();button.focus();}});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
 document.addEventListener('dnct:close-notifications',close);
 // --- Powiadomienia systemowe (na komputerze), wariant "karta otwarta" ----------------
 // Stan włącz/wyłącz jest na KONCIE (list.php -> data.desktop), bo zgoda przeglądarki
 // i localStorage są osobne dla game.dnct.pl, panel.dnct.pl i bety. Ten sam przełącznik
 // jest tu (stopka listy) i w czacie (chat-ui.js, DNCTAlerts). Przeglądarka pyta o zgodę
 // tylko po kliknięciu, więc przy nowej wiadomości pokazujemy własną prośbę z przyciskiem.
 // Telefony: Android pokazuje je wyłącznie przez service worker, iOS tylko jako
 // aplikację z ekranu początkowego - to wariant B (Web Push).
 const reducedMotion=()=>matchMedia('(prefers-reduced-motion: reduce)').matches;
 const desk={enabled:null,snoozeEnds:0,offerShown:false,offerPending:false,last:'',pushKey:null,pushDevice:false,pushBusy:false,pushTried:false};
 // Wariant B: Web Push (service worker) działa też przy zamkniętej karcie i na Androidzie.
 const pushSupported='serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window&&window.isSecureContext!==false;
 const deskSupported=pushSupported||('Notification' in window&&window.isSecureContext!==false&&!/Android|iPhone|iPad|iPod/i.test(navigator.userAgent));
 const perm=()=>deskSupported?Notification.permission:'unsupported';
 const deskOn=()=>perm()==='granted'&&desk.enabled!==false;
 const deskInfo=()=>({supported:deskSupported,permission:perm(),on:deskOn(),enabled:desk.enabled});
 const deskStyle=document.createElement('style');deskStyle.textContent=
  '.dnct-notif-desktop{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px 14px;border-top:1px solid #ffffff12;flex-shrink:0}.dnct-notif-desktop[hidden]{display:none}'
 +'.dnct-notif-desktop-copy{display:grid;gap:2px;min-width:0}.dnct-notif-desktop-copy strong{font-size:13px;font-weight:700;color:#ede9fe}.dnct-notif-desktop-copy small{font-size:11px;line-height:1.4;color:#a8a3b8}'
 +'.dnct-notif-switch{position:relative;flex:0 0 42px;width:42px;height:24px;padding:0;border:1px solid #ffffff1f;border-radius:99px;background:#ffffff12;cursor:pointer;transition:background .2s,border-color .2s}.dnct-notif-switch span{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#c4bfd3;transition:transform .2s,background .2s}'
 +'.dnct-notif-switch.is-on{background:#7c3aed;border-color:#a78bfa}.dnct-notif-switch.is-on span{transform:translateX(18px);background:#fff}.dnct-notif-switch:disabled{opacity:.5;cursor:default}.dnct-notif-switch:focus-visible{outline:2px solid #c4b5fd;outline-offset:2px}'
 +'.dnct-desktop-offer{position:fixed;z-index:96;box-sizing:border-box;padding:16px;border:1px solid #ffffff17;border-radius:16px;background:linear-gradient(150deg,#211b2cf0,#15121cf0);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);box-shadow:0 18px 45px #0006;color:#e9e6f2;font-family:Inter,ui-sans-serif,system-ui,sans-serif;font-size:13px;line-height:1.5;text-align:left}.dnct-desktop-offer[hidden]{display:none}'
 +'.dnct-desktop-offer-eyebrow{display:block;margin-bottom:6px;color:#c4b5fd;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.dnct-desktop-offer p{margin:0 0 12px;color:#cfcbdb}.dnct-desktop-offer-actions{display:flex;gap:8px;justify-content:flex-end}'
 +'.dnct-desktop-offer button{min-height:36px;padding:0 14px;border:1px solid #ffffff1f;border-radius:10px;background:#ffffff0a;color:#ddd6fe;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:background .2s}.dnct-desktop-offer button:hover{background:#ffffff14}.dnct-desktop-offer button[data-allow]{border-color:#9b75f4;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:#fff}.dnct-desktop-offer button:focus-visible{outline:2px solid #c4b5fd;outline-offset:2px}';
 document.head.append(deskStyle);
 const deskRow=document.createElement('div');deskRow.className='dnct-notif-desktop';deskRow.hidden=!deskSupported;
 deskRow.innerHTML='<span class="dnct-notif-desktop-copy"><strong id="dnct-desktop-label">Powiadomienia na komputerze</strong><small></small></span><button type="button" role="switch" class="dnct-notif-switch" aria-labelledby="dnct-desktop-label"><span></span></button>';
 const deskSwitch=deskRow.querySelector('button'),deskHint=deskRow.querySelector('small');panel.append(deskRow);
 function deskRender(){const i=deskInfo();deskSwitch.classList.toggle('is-on',i.on);deskSwitch.setAttribute('aria-checked',String(i.on));deskSwitch.disabled=i.permission==='denied';deskHint.textContent=i.permission==='denied'?'Zablokowane w przeglądarce. Odblokuj je przy kłódce obok adresu strony.':i.on?(desk.pushDevice?'Przyjdą także, gdy karta jest zamknięta.':'Pokażemy je także, gdy ta karta jest w tle.'):'Wyłączone.';}
 function deskEmit(){const info=deskInfo(),sig=JSON.stringify(info);deskRender();if(sig===desk.last)return;desk.last=sig;document.dispatchEvent(new CustomEvent('dnct:desktop-state',{detail:info}));}
 function deskState(s){if(!s||typeof s!=='object')return;desk.enabled=s.enabled===null||s.enabled===undefined?null:Boolean(s.enabled);desk.snoozeEnds=Date.now()+(Number(s.snoozed_for)||0)*1000;if(typeof s.push_key==='string')desk.pushKey=s.push_key;desk.pushDevice=s.push_device===true;deskEmit();
  // Zgoda jest, a ta przeglądarka nie ma jeszcze subskrypcji (np. włączone w innej przeglądarce albo przed wariantem B): dopisz po cichu, raz na wejście.
  if(deskOn()&&pushSupported&&desk.pushKey&&!desk.pushDevice&&!desk.pushTried){desk.pushTried=true;pushEnsure();}}
 async function deskSave(on){const prev=desk.enabled;desk.enabled=on;deskEmit();try{const d=await request('mark-read',{action:'desktop',enabled:on?'1':'0',device:deviceId()});deskState(d.desktop);if(on)await pushEnsure();else await pushRemove();note(on?'Powiadomienia na komputerze włączone.':'Powiadomienia na komputerze wyłączone.');}catch(e){desk.enabled=prev;deskEmit();note(e.message,true);}}
 async function deskToggle(){
  if(!deskSupported)return;
  let p=perm();
  if(p==='denied'){deskEmit();note('Powiadomienia są zablokowane w przeglądarce. Kliknij kłódkę obok adresu strony i zezwól na powiadomienia.',true);return;}
  if(p==='default'){
   try{p=await Notification.requestPermission();}catch{p=perm();}
   if(p!=='granted'){deskEmit();if(p==='denied')note('Przeglądarka zablokowała powiadomienia. Zmienisz to przy kłódce obok adresu strony.',true);return;}
   return deskSave(true);
  }
  return deskSave(!deskOn());
 }
 deskSwitch.onclick=()=>deskToggle();
 // Prośba o zgodę: tylko gdy przeglądarka jeszcze nie pytała, konto nie wyłączyło
 // powiadomień i nie kliknięto "Nie teraz" w ciągu doby (stan wspólny dla domen).
 const offer=document.createElement('div');offer.className='dnct-desktop-offer';offer.hidden=true;offer.setAttribute('role','dialog');offer.setAttribute('aria-labelledby','dnct-desktop-offer-title');
 offer.innerHTML='<span class="dnct-desktop-offer-eyebrow" id="dnct-desktop-offer-title">Bądź na bieżąco</span><p>Dostajesz wiadomości na czacie i powiadomienia pod dzwonkiem. Zezwól na powiadomienia systemowe, a pokażemy je także wtedy, gdy ta karta jest w tle.</p><div class="dnct-desktop-offer-actions"><button type="button" data-later>Nie teraz</button><button type="button" data-allow>Zezwól</button></div>';
 document.body.append(offer);
 function placeOffer(){const r=button.getBoundingClientRect(),w=Math.min(340,document.documentElement.clientWidth-24);offer.style.width=w+'px';offer.style.top=Math.round(r.bottom+10)+'px';offer.style.left=Math.max(12,Math.min(document.documentElement.clientWidth-w-12,Math.round(r.right)-w))+'px';}
 function hideOffer(){if(offer.hidden)return;if(reducedMotion()){offer.hidden=true;return;}offer.animate([{opacity:1},{opacity:0}],{duration:200,easing:'ease-in'}).finished.catch(()=>{}).then(()=>{offer.hidden=true;});}
 function deskOffer(){
  if(!deskSupported||perm()!=='default'||desk.enabled===false||Date.now()<desk.snoozeEnds||desk.offerShown)return;
  if(document.hidden||opened){desk.offerPending=true;return;}
  desk.offerShown=true;desk.offerPending=false;placeOffer();offer.hidden=false;
  if(!reducedMotion())offer.animate([{opacity:0,transform:'translateY(-6px)'},{opacity:1,transform:'none'}],{duration:200,easing:'ease-out'});
 }
 offer.querySelector('[data-allow]').onclick=()=>{hideOffer();deskToggle();};
 offer.querySelector('[data-later]').onclick=async()=>{hideOffer();desk.snoozeEnds=Date.now()+86400000;try{deskState((await request('mark-read',{action:'desktop-snooze',device:deviceId()})).desktop);}catch{}};
 button.addEventListener('click',hideOffer);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&desk.offerPending)deskOffer();});
 window.addEventListener('resize',()=>{if(!offer.hidden)placeOffer();});window.addEventListener('scroll',()=>{if(!offer.hidden)placeOffer();},{passive:true});
 // Jedna karta na powiadomienie: karta, na którą patrzysz, "zabiera" je od razu i nic nie
 // pokazuje (widzisz dzwonek). Karty w tle czekają chwilę; pierwsza zgłoszona wygrywa.
 async function deskNotify(key,{title,body,onclick}={}){
  if(!deskOn()||desk.pushDevice||!/^[a-z]{1,12}:[0-9:]{1,60}$/.test(key))return false;   // push pokaże je sam (service worker)
  const looking=()=>!document.hidden&&document.hasFocus();
  const focused=looking();
  if(!focused)await new Promise(r=>setTimeout(r,1200));
  let claimed=true;try{claimed=(await request('mark-read',{action:'desktop-claim',key})).claimed===true;}catch{}
  if(!claimed||focused||looking())return false;
  try{const n=new Notification(String(title||'DNCT'),{body:String(body||'').slice(0,240),icon:document.querySelector('link[rel~="icon"]')?.href||new URL('/'+'assets/media/icons/favicon.png',location.origin).href,tag:'dnct:'+key,silent:true});n.onclick=()=>{window.focus();try{onclick?.();}catch{}n.close();};return true;}catch{return false;}
 }
 function deskOpen(n){if(!opened)button.click();const fresh=[...records,...adminRecords].find(x=>String(x.id)===String(n.id))||n;show(fresh,button);}
 // Identyfikator przeglądarki we wspólnym ciasteczku *.dnct.pl: strona i panel w tej samej
 // przeglądarce mają jedną subskrypcję, więc jedno powiadomienie zamiast dwóch.
 function deviceId(){
  let id=(document.cookie.match(/(?:^|;\s*)dnct_dev=([a-f0-9]{32})/)||[])[1];
  if(!id){id=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
   document.cookie='dnct_dev='+id+'; Path=/; Max-Age=63072000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'')+(/(^|\.)dnct\.pl$/.test(location.hostname)?'; Domain=.dnct.pl':'');}
  return id;
 }
 const b64bytes=s=>{const pad='='.repeat((4-s.length%4)%4),raw=atob((s+pad).replace(/-/g,'+').replace(/_/g,'/'));return Uint8Array.from(raw,c=>c.charCodeAt(0));};
 async function pushRegistration(){
  // Most przepisuje ten adres na /messaging/assets/js/push-sw.js (game.dnct.pl, panel).
  const reg=await navigator.serviceWorker.register('/assets/js/push-sw.js');
  if(!reg.active){const sw=reg.installing||reg.waiting;if(sw)await new Promise(r=>{if(sw.state==='activated')return r();sw.addEventListener('statechange',()=>{if(sw.state==='activated')r();});setTimeout(r,8000);});}
  return reg;
 }
 async function pushEnsure(){
  if(!pushSupported||!desk.pushKey||perm()!=='granted'||desk.enabled===false||desk.pushBusy)return false;
  desk.pushBusy=true;
  try{
   const reg=await pushRegistration(),key=b64bytes(desk.pushKey);let sub=await reg.pushManager.getSubscription();
   const same=sub&&sub.options?.applicationServerKey&&new Uint8Array(sub.options.applicationServerKey).join()===key.join();
   if(sub&&!same){await sub.unsubscribe().catch(()=>{});sub=null;}
   if(!sub)sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
   const d=await request('mark-read',{action:'desktop-push',subscription:JSON.stringify(sub),device:deviceId(),origin:location.origin});
   deskState(d.desktop);return true;
  }catch(e){console.warn('DNCT push:',e?.message||e);return false;}
  finally{desk.pushBusy=false;}
 }
 async function pushRemove(){
  if(!pushSupported)return;
  try{const reg=await navigator.serviceWorker.getRegistration('/assets/js/push-sw.js');const sub=await reg?.pushManager.getSubscription();await sub?.unsubscribe();}catch{}
  try{deskState((await request('mark-read',{action:'desktop-push-off',device:deviceId()})).desktop);}catch{}
 }
 window.DNCTDesktop={info:deskInfo,toggle:deskToggle,offer:deskOffer,notify:deskNotify,open:deskOpen};
 deskEmit();
 refresh();setInterval(()=>{if(opened||modal.open)refresh();},3000);setInterval(refresh,5000);
 // Jednorazowe komunikaty formularzy nie uczestniczą w pobieraniu listy.
 document.querySelectorAll('.dnct-toast').forEach(toast=>{requestAnimationFrame(()=>toast.classList.remove('opacity-0','translate-x-4'));const remove=()=>toast.remove();toast.querySelector('.dnct-toast-close')?.addEventListener('click',remove);setTimeout(remove,5000);});
})();
