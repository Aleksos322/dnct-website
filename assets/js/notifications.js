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
 const csrf=wrap.dataset.csrf;let activeTab='normal',adminRecords=[],isAdmin=false,adminPreview=savedPreview();const counts={normal:0,admin:0};const tabs=document.createElement('div');tabs.className='dnct-notif-tabs';tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Rodzaj powiadomień');tabs.hidden=true;list.before(tabs);list.id='dnct-bell-list';list.setAttribute('role','tabpanel');const tabButtons={};for(const [id,label] of [['normal','Zwykłe'],['admin','Admin']]){const tab=document.createElement('button');tab.type='button';tab.id='dnct-notif-tab-'+id;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',list.id);tab.onclick=()=>switchTab(id);tabs.append(tab);tabButtons[id]=tab;tab.dataset.label=label;}function tabState(){trashState();preview.hidden=!isAdmin;preview.textContent='Podgląd: '+(adminPreview?'Admin':'Użytkownik');preview.setAttribute('aria-label',preview.textContent+'. Przełącz podgląd');preview.disabled=busy;tabs.hidden=!(isAdmin&&adminPreview);for(const [id,tab] of Object.entries(tabButtons)){tab.setAttribute('aria-selected',String(id===activeTab));tab.tabIndex=id===activeTab?0:-1;tab.textContent=tab.dataset.label+(counts[id]?' ('+counts[id]+')':'');}if(isAdmin&&adminPreview){list.setAttribute('role','tabpanel');list.setAttribute('aria-labelledby',tabButtons[activeTab].id);}else{list.removeAttribute('role');list.removeAttribute('aria-labelledby');}all.textContent=isAdmin&&adminPreview?'Odczytaj tę zakładkę':'Przeczytaj wszystko';all.disabled=busy||counts[activeTab]===0;}function switchTab(id){if(busy||!isAdmin||!adminPreview||id===activeTab)return;activeTab=id;window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);tabState();render();if(!matchMedia('(prefers-reduced-motion: reduce)').matches)list.animate([{opacity:0,transform:'translateX('+(id==='admin'?'10px':'-10px')+')'},{opacity:1,transform:'translateX(0)'}],{duration:220,easing:'ease-out'});}tabs.onkeydown=e=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(e.key)){e.preventDefault();switchTab(e.key==='Home'?'normal':e.key==='End'?'admin':activeTab==='normal'?'admin':'normal');tabButtons[activeTab].focus();}};
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
  const width=Math.min(460,innerWidth-24);viewport.style.width=width+'px';viewport.style.top=top+'px';viewport.style.right='auto';viewport.style.left=Math.max(12,document.documentElement.clientWidth-width-16)+'px';viewport.style.height=height+'px';
  panel.style.position='relative';panel.style.top='auto';panel.style.bottom='auto';panel.style.right='auto';panel.style.maxHeight=height+'px';
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',dock,{once:true});else dock();
 window.addEventListener('resize',fit);window.addEventListener('scroll',fit,{passive:true});
 let noteTimer=null,noteClear=null;
 function note(text,error=false){clearTimeout(noteTimer);clearTimeout(noteClear);if(!text){status.classList.remove('is-visible');noteClear=setTimeout(()=>{status.textContent='';},260);return;}status.textContent=text;status.dataset.error=String(error);requestAnimationFrame(()=>status.classList.add('is-visible'));if(!error)noteTimer=setTimeout(()=>note(''),4500);}
 let pendingBellShake=false;document.addEventListener('visibilitychange',()=>{if(!document.hidden&&pendingBellShake){pendingBellShake=false;shakeBell();}});
 function shakeBell(){if(document.hidden){pendingBellShake=true;return;}const icon=button.querySelector('svg');if(!icon||document.hidden||matchMedia('(prefers-reduced-motion: reduce)').matches||icon.getAnimations().some(a=>a.playState==='running'))return;icon.animate([0,-11,10,-7,5,-2,0].map(angle=>({transform:'rotate('+angle+'deg)'})),{duration:1000,easing:'ease-in-out'});}
 setInterval(()=>{if(count>0)shakeBell();},8000);

 document.addEventListener('dnct:notification-sound',shakeBell);
 function update(n){const previous=count;count=Number(n)||0;if(count>previous)shakeBell();badge.textContent=String(count);badge.classList.toggle('hidden',count===0);button.classList.toggle('dnct-bell-btn--unread',count>0);button.setAttribute('aria-label','Powiadomienia: '+count+' nieprzeczytanych');tabState();}
 function close(){opened=false;panel.classList.remove('is-open');panel.inert=true;clearTimeout(closing);closing=setTimeout(()=>{if(!opened)panel.classList.add('hidden');},matchMedia('(prefers-reduced-motion: reduce)').matches?0:680);button.setAttribute('aria-expanded','false');button.classList.remove('dnct-bell-btn--active');}
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
  if(typeof n.link==='string'&&!/[\\\x00-\x20]/.test(n.link)&&(/^\/(?!\/)/.test(n.link)||/^https:\/\/(auth\.dnct\.pl|panel\.dnct\.pl|invoice\.stripe\.com|pay\.stripe\.com|admin\.lan\.dnct\.pl)\//.test(n.link)||/^http:\/\/admin\.lan\.dnct\.pl\//.test(n.link))){link.href=n.link;link.textContent=n.action_label||'Przejdź do szczegółów';link.hidden=false;}
  link.onclick=e=>{if(n.audience!=='admin'&&n.category==='support'&&document.getElementById('dnct-support-config')){e.preventDefault();closePreview();close();document.dispatchEvent(new Event('dnct:notification-support'));}};clearTimeout(previewClosing);previewClosing=null;modal.classList.remove('is-closing');lockPreviewScroll();modal.showModal();modal.querySelector('button').focus({preventScroll:true});
  if(!n.is_read)mark(Number(n.id));
 }
 let savedView=false;
 const bookmarkSvg='<svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4V3Z"/></svg>';
 function saveButton(n){const b=document.createElement('button');b.type='button';b.className='dnct-save-notification';b.innerHTML=bookmarkSvg;const sync=()=>{b.classList.toggle('is-saved',Boolean(n.is_saved));b.setAttribute('aria-pressed',String(Boolean(n.is_saved)));b.title=n.is_saved?'Usuń z zapisanych':'Zapisz powiadomienie';b.setAttribute('aria-label',b.title);};sync();b.onclick=async e=>{e.stopPropagation();if(busy)return;busy=true;revision++;b.disabled=true;try{await request('mark-read',{id:n.id,action:'bookmark',saved:n.is_saved?'0':'1'});n.is_saved=!n.is_saved;n.is_read=true;sync();render();}catch(error){note(error.message,true);}finally{busy=false;b.disabled=false;revision++;refresh();}};return b;}
 const bookmarkStyle=document.createElement('style');bookmarkStyle.textContent=`.dnct-notif-item{position:relative;padding-bottom:38px!important;cursor:pointer}.dnct-save-notification{display:inline-grid;place-items:center;border:1px solid transparent;background:transparent;color:#92909f;width:30px;height:30px;padding:5px;border-radius:8px;cursor:pointer;transition:color .25s,background .25s,transform .25s}.dnct-save-notification:hover{background:#ffffff0b;transform:scale(1.1)}.dnct-save-notification.is-saved{color:#facc15;background:#facc1510}.dnct-notif-item>.dnct-save-notification{position:absolute;left:12px;bottom:6px}.dnct-preview-save{margin-left:auto}.dnct-notification-top>.dnct-preview-save+button{margin-left:8px}.dnct-notif-saved-toggle{color:#facc15!important}.dnct-notif-saved-toggle[aria-pressed=true]{background:#facc1520!important}`;document.head.append(bookmarkStyle);
 function render(){
  trashState();
  list.replaceChildren();
  const visible=[...(savedView?[...records,...(isAdmin&&adminPreview?adminRecords:[])]:(activeTab==='admin'?adminRecords:records))].filter(n=>Boolean(n.is_saved)===savedView).sort((a,b)=>Number(Boolean(a.is_read))-Number(Boolean(b.is_read)));
  if(!visible.length){const empty=document.createElement('p');empty.className='dnct-notif-empty';const symbol=document.createElement('span');symbol.className='dnct-notif-empty-icon';symbol.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M10 21h4"/><path d="m9 10 2 2 4-4"/></svg>';const title=document.createElement('strong');title.textContent=savedView?'Brak zapisanych powiadomień':activeTab==='admin'?'Brak alertów administracyjnych':'Jesteś na bieżąco';const hint=document.createElement('span');hint.textContent='Nowe powiadomienia pojawią się tutaj.';empty.append(symbol,title,hint);list.append(empty);return;}
  let previousUnread=false;
  for(const n of visible){
   if(previousUnread&&n.is_read){const divider=document.createElement('div');divider.className='dnct-notif-read-divider';divider.setAttribute('role','separator');divider.setAttribute('aria-label','Przeczytane powiadomienia');list.append(divider);}
   previousUnread=!n.is_read;
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
  const res=await fetch('/api/notifications/'+path+'.php',{credentials:'same-origin',cache:'no-store',...(body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(body)}:{})});
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
   records=data.notifications;isAdmin=data.is_admin===true;adminRecords=isAdmin&&Array.isArray(data.admin_notifications)?data.admin_notifications:[];if(!isAdmin)activeTab='normal';counts.normal=Number(data.counts?.normal??data.unread)||0;counts.admin=isAdmin?Number(data.counts?.admin)||0:0;update(data.unread);render();note('');window.DNCTAlerts?.notifications([...records,...adminRecords]);if(opened)window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);
   if(modal.open&&previewId){const updated=[...records,...adminRecords].find(n=>String(n.id)===previewId);if(!updated)closePreview();else if(JSON.stringify([updated.title,updated.message,updated.created_at,updated.attachments])!==previewVersion)show(updated,button);}
  }catch(e){if(started===revision)note(e.message,true);}
  finally{loading=false;}
 }
 async function mark(id){
  if(busy)return;
  const scope=activeTab;busy=true;revision++;all.disabled=true;
  try{await request(id?'mark-read':'mark-all-read',id?{id}:{scope});
   for(const n of (scope==='admin'?adminRecords:records))if(!id||Number(n.id)===id)n.is_read=true;counts[scope]=id?Math.max(0,counts[scope]-1):0;
   if(!id&&scope==='normal')document.dispatchEvent(new Event('dnct:read-all-support'));update(counts.normal+counts.admin);render();window.DNCTAlerts?.notifications([...records,...adminRecords]);note(id?'':'Powiadomienia w tej zakładce oznaczono jako przeczytane.');
  }catch(e){note(e.message,true);if(modal.open){const msg=document.createElement('p');msg.className='dnct-notif-read-error';msg.textContent='Odczyt nie został zapisany. Zamknij wiadomość i spróbuj ponownie.';modal.querySelector('.dnct-notification-content').append(msg);}}
  finally{busy=false;revision++;update(count);}
 }
 all.onclick=()=>mark(null);
 const clearButton=document.createElement('button');clearButton.type='button';clearButton.className='dnct-notif-clear';clearButton.setAttribute('aria-label','Usuń przeczytane powiadomienia');clearButton.title='Usuń przeczytane powiadomienia';clearButton.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg>';const headActions=document.createElement('div');headActions.className='dnct-notif-head-actions';all.before(headActions);headActions.append(clearButton,all);const savedToggle=document.createElement('button');savedToggle.type='button';savedToggle.className='dnct-notif-clear dnct-notif-saved-toggle';savedToggle.innerHTML=bookmarkSvg;savedToggle.title='Zapisane powiadomienia';savedToggle.setAttribute('aria-label','Zapisane powiadomienia');savedToggle.setAttribute('aria-pressed','false');headActions.prepend(savedToggle);savedToggle.onclick=()=>{savedView=!savedView;savedToggle.setAttribute('aria-pressed',String(savedView));heading.textContent=savedView?'Zapisane powiadomienia':'Powiadomienia';render();};
 function trashState(){const hasRead=(activeTab==='admin'?adminRecords:records).some(n=>n.is_read&&!n.is_saved);clearButton.disabled=busy||!hasRead||savedView;clearButton.title=hasRead?'Usuń przeczytane powiadomienia':'Brak przeczytanych powiadomień do usunięcia';}
 trashState();
 clearButton.onclick=()=>{if(busy||clearButton.disabled)return;const scope=activeTab,dialog=document.createElement('dialog');dialog.className='dnct-notification-dialog dnct-notif-confirm';dialog.setAttribute('aria-labelledby','dnct-clear-title');dialog.innerHTML='<div class="dnct-notification-content"><h2 id="dnct-clear-title">Usunąć powiadomienia?</h2><p>Usuniemy tylko przeczytane powiadomienia z tej zakładki. Nieprzeczytane i zapisane powiadomienia pozostaną zachowane.</p><p class="dnct-clear-error" role="alert"></p><div class="dnct-clear-actions"><button type="button" data-cancel>Anuluj</button><button type="button" data-confirm>Tak, usuń przeczytane</button></div></div>';document.body.append(dialog);const cancel=dialog.querySelector('[data-cancel]'),confirm=dialog.querySelector('[data-confirm]');cancel.onclick=()=>dialog.close();dialog.addEventListener('close',()=>{dialog.remove();clearButton.focus({preventScroll:true});});dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('click',e=>{e.stopPropagation();if(e.target===dialog&&!busy){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});confirm.onclick=async()=>{if(busy)return;busy=true;revision++;confirm.disabled=cancel.disabled=true;try{const data=await request('clear-read',{scope});if(scope==='admin')adminRecords=adminRecords.filter(n=>!n.is_read||n.is_saved);else records=records.filter(n=>!n.is_read||n.is_saved);render();note(data.hidden?'Usunięto przeczytane powiadomienia.':'Brak przeczytanych powiadomień do usunięcia.');dialog.close();}catch(e){dialog.querySelector('.dnct-clear-error').textContent=e.message;}finally{busy=false;revision++;confirm.disabled=cancel.disabled=false;update(count);}};dialog.showModal();cancel.focus();};
 button.onclick=()=>{window.DNCTAlerts?.opened(activeTab==='admin'?adminRecords:records);shakeBell();if(opened){close();return;}clearTimeout(closing);opened=true;panel.inert=false;panel.classList.remove('hidden');fit();void panel.offsetHeight;requestAnimationFrame(()=>{if(opened)panel.classList.add('is-open');});button.setAttribute('aria-expanded','true');button.classList.add('dnct-bell-btn--active');fit();document.dispatchEvent(new Event('dnct:close-chat'));const c=document.getElementById('dnct-support-chat-panel');if(c){c.classList.remove('is-open');c.setAttribute('aria-hidden','true');}refresh();};
 document.addEventListener('click',e=>{if(!wrap.contains(e.target)&&!panel.contains(e.target)&&!modal.contains(e.target)&&!modal.open&&!document.querySelector('.dnct-notif-confirm[open]'))close();});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.open&&!document.querySelector('.dnct-notif-confirm[open]')&&!panel.classList.contains('hidden')){close();button.focus();}});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
 document.addEventListener('dnct:close-notifications',close);
 refresh();setInterval(()=>{if(opened||modal.open)refresh();},3000);setInterval(refresh,5000);
 // Jednorazowe komunikaty formularzy nie uczestniczą w pobieraniu listy.
 document.querySelectorAll('.dnct-toast').forEach(toast=>{requestAnimationFrame(()=>toast.classList.remove('opacity-0','translate-x-4'));const remove=()=>toast.remove();toast.querySelector('.dnct-toast-close')?.addEventListener('click',remove);setTimeout(remove,5000);});
})();
