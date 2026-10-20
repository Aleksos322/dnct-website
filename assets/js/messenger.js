(() => {
 'use strict';
 const panel=document.getElementById('dnct-messenger');if(!panel||panel.dataset.ready)return;panel.dataset.ready='1';
 const trigger=document.getElementById(panel.dataset.trigger);if(!trigger)return;
 const get=s=>panel.querySelector(s),api=panel.dataset.api,csrf=panel.dataset.csrf;
 const search=get('[data-dm-search]'),directory=get('[data-dm-directory]'),conversations=get('[data-dm-conversations]'),messages=get('[data-dm-messages]'),form=get('form'),text=form.querySelector('textarea'),status=get('[data-dm-status]'),connection=get('.dm-connection');
 let dot=trigger.querySelector('.dm-unread-dot');if(!dot){dot=document.createElement('span');dot.className='dm-unread-dot';dot.hidden=true;trigger.append(dot);}
 let inbox=[],current=null,rows=[],inboxBusy=false,threadBusy=false,sending=false,searchVersion=0,searchTimer=null,older=null,me=null,ws=null,wsReady=false,connecting=false,reconnectAt=0,lastRead=0,readBusy=false,unread=0;
 let inboxTask=null,threadQueued=false;
 const drafts=new Map(),attempts=new Map();let threadSignature='';const animatedMessages=new Set();let transitionId=0,replyTo=null,editingId=null,emojiFor=null,composerEmoji=false;
 const standardEmoji=['👍','❤️','😂','😮','😢','🙏'];
 const allEmoji=['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😍','🥰','😘','😋','😎','🤔','🥹','😮','😢','😭','😡','👍','👎','❤️','🔥','🎉','🙏','✅','👀','💯','🚀','🤝','✨','💜','👏'];
 const escape=s=>String(s??'');
 const key=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),x=>x.toString(16).padStart(2,'0')).join('');
 function fit(){const nav=document.querySelector('#main-header nav')?.getBoundingClientRect();const top=Math.max(16,nav&&nav.bottom>0?nav.bottom+12:16);const buttonTop=trigger.getBoundingClientRect().top;panel.style.maxHeight=Math.max(150,buttonTop-top-12)+'px';}
 window.addEventListener('resize',fit);window.addEventListener('scroll',fit,{passive:true});
 function say(message=''){status.textContent=message;}
 async function request(action,values={},post=false){
  const url=post?api:api+'?'+new URLSearchParams({action,...values});
  const res=await fetch(url,{credentials:'same-origin',cache:'no-store',...(post?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,...values})}:{})});
  const data=await res.json().catch(()=>null);
  if(!res.ok||!data?.ok)throw new Error(data?.message||(res.status===401?'Sesja wygasła. Zaloguj się ponownie.':'Nie udało się połączyć z czatem. Spróbuj ponownie.'));
  return data;
 }
 function updateDot(n){unread=Number(n)||0;dot.hidden=unread===0;trigger.setAttribute('aria-label',unread?'Otwórz czat: '+unread+' nieprzeczytanych':'Otwórz czat');}
 function open(){panel.hidden=false;panel.classList.remove('is-entering');void panel.offsetWidth;panel.classList.add('is-entering');fit();trigger.setAttribute('aria-expanded','true');document.dispatchEvent(new Event('dnct:close-notifications'));const legacy=document.getElementById('dnct-support-chat-panel');if(legacy){legacy.classList.remove('is-open');legacy.setAttribute('aria-hidden','true');}refresh();if(current)loadThread();else search.focus();}
 function close(){transitionId++;panel.getAnimations().forEach(a=>a.cancel());panel.classList.remove('is-entering');panel.hidden=true;trigger.setAttribute('aria-expanded','false');}
 trigger.addEventListener('click',event=>{event.preventDefault();event.stopImmediatePropagation();if(legacy?.classList.contains('is-open')){legacy.classList.remove('is-open');legacy.setAttribute('aria-hidden','true');trigger.setAttribute('aria-expanded','false');}else panel.hidden?open():close();},true);
 get('[data-dm-close]').onclick=()=>{close();trigger.focus();};
 document.addEventListener('dnct:close-chat',close);
 document.getElementById('main-header')?.addEventListener('click',close);
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden){close();trigger.focus();}});
 get('[data-dm-back]').onclick=()=>{if(current)drafts.set(current.id,text.value);current=null;panel.classList.remove('has-conversation');get('[data-dm-active]').hidden=true;get('[data-dm-welcome]').hidden=false;search.focus();};
 get('[data-dm-support]')?.addEventListener('click',async()=>{
  const legacy=document.getElementById('dnct-support-chat-panel');if(!legacy)return;
  const version=++transitionId,rect=panel.getBoundingClientRect();
  const target=Math.min(390,window.innerWidth-(window.innerWidth<=640?24:32));
  const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
  const animation=panel.animate([{width:rect.width+'px'},{width:target+'px'}],{duration:reduced?0:320,easing:'cubic-bezier(.22,1,.36,1)',fill:'forwards'});
  const fade=panel.querySelector('.dm-body').animate([{opacity:1},{opacity:0}],{duration:reduced?0:200,fill:'forwards'});
  try{await animation.finished;}catch{fade.cancel();return;}
  if(version!==transitionId){fade.cancel();return;}
  legacy.style.maxHeight=panel.style.maxHeight;
  legacy.style.transition='none';legacy.style.transform='none';
  legacy.style.boxSizing='border-box';legacy.style.height=rect.height+'px';
  legacy.querySelector('[data-tab="support"]')?.click();
  // close() przywraca aria-expanded na false; dlatego otwieramy panel ticketu dopiero po nim.
  close();fade.cancel();
  legacy.classList.add('is-open');legacy.setAttribute('aria-hidden','false');trigger.setAttribute('aria-expanded','true');
  legacy.focus({preventScroll:true});
  requestAnimationFrame(()=>requestAnimationFrame(()=>{legacy.style.removeProperty('transition');legacy.style.removeProperty('transform');}));
 });
 get('[data-dm-support-mobile]')?.addEventListener('click',()=>get('[data-dm-support]')?.click());
 document.addEventListener('dnct:return-chat',()=>{
  const legacy=document.getElementById('dnct-support-chat-panel');if(!legacy)return;
  const width=legacy.getBoundingClientRect().width;
  legacy.classList.remove('is-open');legacy.setAttribute('aria-hidden','true');open();
  const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
  panel.animate([{width:width+'px',opacity:.6},{width:panel.getBoundingClientRect().width+'px',opacity:1}],{duration:reduced?0:320,easing:'cubic-bezier(.22,1,.36,1)'});
 });
 // Stare zgłoszenia pozostają dostępne w zakładce Wsparcie.
 const legacy=document.getElementById('dnct-support-chat-panel');legacy?.querySelector('[data-tab="alerts"]')?.remove();
 function presenceBadge(el,state='offline'){
  const labels={online:'Dostępny',away:'Zaraz wracam',offline:'Offline'};state=labels[state]?state:'offline';
  let badge=el.querySelector('.dm-presence');if(!badge){badge=document.createElement('span');badge.className='dm-presence';badge.setAttribute('role','img');el.append(badge);}
  badge.dataset.state=state;badge.title=labels[state];badge.setAttribute('aria-label',labels[state]);
 }
 function avatar(name,state){const el=document.createElement('span');el.className='dm-avatar';el.textContent=Array.from(name||'?').slice(0,1).join('').toLocaleUpperCase('pl');presenceBadge(el,state);return el;}
 function person(name,detail,action,n=0,presence='offline'){
  const button=document.createElement('button');button.type='button';button.className='dm-person';
  const copy=document.createElement('span');copy.className='dm-person-copy';const title=document.createElement('strong');title.textContent=name;const sub=document.createElement('small');sub.textContent=detail;
  copy.append(title,sub);button.append(avatar(name,presence),copy);if(n){const badge=document.createElement('span');badge.className='dm-counter';badge.textContent=n;button.append(badge);button.classList.add('is-unread');}
  button.onclick=action;return button;
 }
 function renderInbox(){
  conversations.replaceChildren();const q=search.value.trim().toLocaleLowerCase('pl');
  const filtered=inbox.filter(c=>!q||(c.display_name+' '+c.username).toLocaleLowerCase('pl').includes(q));
  for(const c of filtered){const button=person(c.display_name,c.preview||'Rozpocznij rozmowę',()=>select(c),c.unread,c.presence);button.classList.toggle('is-active',current?.id===c.id);conversations.append(button);}
  if(!filtered.length){const empty=document.createElement('p');empty.className='dm-empty';empty.textContent=q?'Nie masz jeszcze rozmowy z tą osobą.':'Tu pojawią się Twoje rozmowy. Wyszukaj osobę, aby napisać pierwszą wiadomość.';conversations.append(empty);}
 }
 async function refresh(force=false){
  if(inboxTask){await inboxTask;if(!force)return;}
  if(document.hidden)return;
  inboxTask=(async()=>{
   try{const data=await request('inbox');inbox=data.conversations;me=data.me;updateDot(data.unread);renderInbox();if(current){current=inbox.find(c=>c.id===current.id)||current;presenceBadge(get('[data-dm-avatar]'),current.presence);renderMessages(false);}if(!panel.hidden)say();}
   catch(e){if(!panel.hidden)say(e.message);}
   finally{inboxTask=null;}
  })();
  return inboxTask;
 }
 async function select(c){
  if(current)drafts.set(current.id,text.value);
  current=c;rows=[];threadSignature='';older=null;lastRead=0;threadBusy=false;replyTo=null;editingId=null;emojiFor=null;renderReplyPreview();
  panel.classList.add('has-conversation');get('[data-dm-welcome]').hidden=true;get('[data-dm-active]').hidden=false;
  get('[data-dm-peer]').textContent=c.display_name;get('[data-dm-avatar]').textContent=Array.from(c.display_name||'?')[0];presenceBadge(get('[data-dm-avatar]'),c.presence);
  get('[data-dm-peer-detail]').textContent=c.is_admin?'Administrator · @'+c.username:'@'+c.username;
  text.value=drafts.get(c.id)||'';messages.replaceChildren();get('[data-dm-older]').hidden=true;renderInbox();
  await loadThread();if(current?.id===c.id)text.focus();
 }
 function time(value){const date=new Date(String(value||'').replace(' ','T'));return Number.isNaN(date.getTime())?'':date.toLocaleString('pl-PL',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});}
 const favourite=()=>{try{return JSON.parse(localStorage.getItem('dnct-chat-favourite-emoji')||'[]').filter(emoji=>allEmoji.includes(emoji)).slice(0,6);}catch{return [];}};
 const remember=emoji=>localStorage.setItem('dnct-chat-favourite-emoji',JSON.stringify([emoji,...favourite().filter(value=>value!==emoji)].slice(0,6)));
 const actionButton=(label,kind,id)=>{const button=document.createElement('button');button.type='button';button.className='dm-message-action dm-message-action--'+kind;button.dataset[kind]=String(id);button.setAttribute('aria-label',label);button.title=label;button.textContent=kind==='react'?'☺':kind==='reply'?(rows.find(row=>row.id===id)?.mine?'→':'←'):'⋯';return button;};
 function emojiPopover(id,composer=false){const popover=document.createElement('span');popover.className='dm-emoji-popover';const options=[...new Set([...favourite(),...standardEmoji])].slice(0,6);const grid=document.createElement('span');grid.className='dm-emoji-grid';for(const emoji of options){const button=document.createElement('button');button.type='button';button.dataset.emoji=emoji;button.dataset.emojiTarget=String(id||0);button.dataset.emojiComposer=composer?'1':'';button.textContent=emoji;grid.append(button);}popover.append(grid);const more=document.createElement('button');more.type='button';more.className='dm-emoji-more';more.dataset.emojiMore=composer?'composer':String(id);more.textContent='＋ Więcej emoji';popover.append(more);return popover;}
 function renderReplyPreview(){const preview=get('[data-dm-reply-preview]');if(!preview)return;if(!replyTo){preview.hidden=true;preview.replaceChildren();return;}preview.hidden=false;const copy=document.createElement('span');const author=replyTo.mine?'Ty':(replyTo.display_name||current?.display_name||'Użytkownik');copy.innerHTML='<b>Odpowiadasz: </b>';copy.append(document.createTextNode(author+' — '+replyTo.body));const cancel=document.createElement('button');cancel.type='button';cancel.dataset.cancelReply='1';cancel.setAttribute('aria-label','Anuluj odpowiedź');cancel.textContent='×';preview.replaceChildren(copy,cancel);}
 function renderMessages(scroll=true){
  if(!current)return;
  const signature=JSON.stringify(rows)+':'+current.peer_read_id+':'+String(editingId)+':'+String(emojiFor);
  if(threadSignature===signature)return;threadSignature=signature;
  const nearBottom=messages.scrollHeight-messages.scrollTop-messages.clientHeight<70;
  const priorTop=messages.scrollTop;messages.replaceChildren();
  for(const m of rows){
   const el=document.createElement('article');el.className='dm-message'+(m.mine?' mine':'');el.dataset.id=m.id;
   if(!m.mine){const author=document.createElement('strong');author.className='dm-message-author';author.textContent=m.display_name||'Użytkownik';el.append(author);}
   if(m.reply){const quote=document.createElement('p');quote.className='dm-reply-quote';quote.textContent=(m.reply.author_name||'Użytkownik')+' — '+m.reply.body;el.append(quote);}
   const line=document.createElement('div');line.className='dm-message-line';
   const bubble=document.createElement('div');bubble.className='dm-bubble';
   if(editingId===m.id){const editor=document.createElement('textarea');editor.className='dm-inline-editor';editor.maxLength=4000;editor.value=m.body;bubble.append(editor);const controls=document.createElement('span');controls.className='dm-inline-edit-actions';for(const [label,action] of [['Anuluj','cancel-edit'],['Zapisz','save-edit']]){const button=document.createElement('button');button.type='button';button.dataset[action]=String(m.id);button.textContent=label;controls.append(button);}bubble.append(controls);}else{const body=document.createElement('p');body.textContent=m.body;if(m.is_withdrawn)body.classList.add('is-withdrawn');bubble.append(body);if(m.edited_at&&!m.is_withdrawn){const edited=document.createElement('em');edited.textContent='Edytowano';bubble.append(edited);}}
   if(!m.is_withdrawn){line.append(bubble);const actions=document.createElement('span');actions.className='dm-message-actions';actions.append(actionButton('Dodaj reakcję','react',m.id),actionButton('Odpowiedz','reply',m.id));if(m.mine)actions.append(actionButton('Więcej opcji','more',m.id));if(emojiFor===m.id)actions.append(emojiPopover(m.id));line.append(actions);}else line.append(bubble);
   el.append(line);if(Array.isArray(m.reactions)&&m.reactions.length){const chips=document.createElement('span');chips.className='dm-reaction-chips';for(const reaction of m.reactions){const chip=document.createElement('button');chip.type='button';chip.className='dm-reaction-chip'+(reaction.mine?' is-mine':'');chip.dataset.reactEmoji=reaction.emoji;chip.dataset.reactMessage=String(m.id);chip.textContent=reaction.emoji+' '+reaction.count;chips.append(chip);}el.append(chips);}
   const info=document.createElement('small');info.textContent=time(m.created_at)+(m.mine?' · '+(Number(current.peer_read_id)>=m.id?'Przeczytano':'Wysłano'):'');el.append(info);if(animatedMessages.has(m.id)){el.classList.add('is-entering');animatedMessages.delete(m.id);}messages.append(el);
  }
  if(!rows.length){const empty=document.createElement('p');empty.className='dm-empty';empty.textContent='Przywitaj się i rozpocznij rozmowę.';messages.append(empty);}
  if(scroll&&nearBottom)messages.scrollTop=messages.scrollHeight;else messages.scrollTop=priorTop;
 }
 async function loadThread(before=null){
  if(!current||panel.hidden||document.hidden)return;
  if(threadBusy){threadQueued=true;return;}
  threadBusy=true;const id=current.id,oldHeight=messages.scrollHeight;
  try{
   const data=await request('messages',{id,...(before?{before}:{})});if(current?.id!==id)return;
   const first=rows.length===0;const known=new Set(rows.map(m=>m.id));if(!first&&!before)for(const m of data.messages)if(!known.has(m.id))animatedMessages.add(m.id);
   const combined=new Map(rows.map(m=>[m.id,m]));for(const m of data.messages)combined.set(m.id,m);rows=[...combined.values()].sort((a,b)=>a.id-b.id);
   if(first||before){older=data.before_id;get('[data-dm-older]').hidden=!data.has_more;}
   renderMessages();
   if(before)messages.scrollTop=messages.scrollHeight-oldHeight;else if(first)messages.scrollTop=messages.scrollHeight;
   markRead();say();
  }catch(e){if(current?.id===id)say(e.message);}
  finally{if(current?.id===id){threadBusy=false;if(threadQueued){threadQueued=false;loadThread();}}}
 }
 async function markRead(){
  if(!current||panel.hidden||document.hidden||readBusy||messages.scrollHeight-messages.scrollTop-messages.clientHeight>70)return;
  const id=current.id,last=rows.at(-1)?.id;if(!last||last<=lastRead)return;
  readBusy=true;
  try{await request('read',{id,last_id:last},true);if(current?.id===id)lastRead=last;await refresh();}
  catch(e){say(e.message);}finally{readBusy=false;}
 }
 messages.addEventListener('scroll',()=>markRead(),{passive:true});
 get('[data-dm-older]').onclick=()=>{if(older)loadThread(older);};
 search.addEventListener('input',()=>{
  clearTimeout(searchTimer);const version=++searchVersion;renderInbox();
  const q=search.value.trim();directory.replaceChildren();directory.hidden=q.length<2;
  if(q.length<2)return;
  const wait=document.createElement('p');wait.className='dm-empty';wait.textContent='Szukam osób…';directory.append(wait);
  searchTimer=setTimeout(async()=>{
   try{const data=await request('search',{q});if(version!==searchVersion)return;directory.replaceChildren();
    for(const p of data.people)directory.append(person(p.name,(p.is_admin?'Administrator · ':'')+'@'+p.username,async()=>{
     try{const result=await request('start',{username:p.username},true);search.value='';directory.hidden=true;++searchVersion;await refresh(true);const c=inbox.find(c=>c.id===result.id);if(c)select(c);}
     catch(e){say(e.message);}
    },0,p.presence));
    if(!data.people.length){const empty=document.createElement('p');empty.className='dm-empty';empty.textContent='Nie znaleziono osoby. Spróbuj wpisać pełny e-mail lub nazwę.';directory.append(empty);}
   }catch(e){if(version===searchVersion){directory.replaceChildren();say(e.message);}}
  },300);
 });
 async function react(messageId,emoji){if(!current)return;remember(emoji);await request('react',{id:current.id,message_id:messageId,emoji},true);emojiFor=null;await loadThread();}
 async function saveEdit(messageId){const editor=messages.querySelector('[data-id="'+String(messageId)+'"] .dm-inline-editor');const body=editor?.value.trim()||'';if(!body){say('Edytowana wiadomość nie może być pusta.');editor?.focus();return;}await request('edit',{id:current.id,message_id:messageId,message:body},true);editingId=null;await loadThread();}
 let withdrawTarget=0;
 const withdrawDialog=document.createElement('dialog');withdrawDialog.className='dm-withdraw-dialog';withdrawDialog.innerHTML='<div class="dm-dialog-head"><strong>Cofnąć wysłanie wiadomości?</strong><button type="button" data-dm-withdraw-cancel aria-label="Zamknij">×</button></div><label><input type="radio" name="dm-withdraw-scope" value="everyone" checked> Cofnij wysłanie do wszystkich</label><label><input type="radio" name="dm-withdraw-scope" value="me"> Ukryj tylko dla mnie</label><div class="dm-dialog-actions"><button type="button" data-dm-withdraw-cancel>Anuluj</button><button type="button" data-dm-withdraw-confirm>Usuń</button></div>';
 document.body.append(withdrawDialog);
 const closeWithdraw=()=>{withdrawTarget=0;withdrawDialog.close?.();withdrawDialog.removeAttribute('open');};
 withdrawDialog.addEventListener('click',async event=>{if(event.target===withdrawDialog||event.target.closest('[data-dm-withdraw-cancel]')){closeWithdraw();return;}if(!event.target.closest('[data-dm-withdraw-confirm]')||!withdrawTarget||!current)return;const confirm=withdrawDialog.querySelector('[data-dm-withdraw-confirm]');confirm.disabled=true;try{const scope=withdrawDialog.querySelector('input[name="dm-withdraw-scope"]:checked')?.value==='me'?'me':'everyone';await request('withdraw',{id:current.id,message_id:withdrawTarget,scope},true);closeWithdraw();await loadThread();}catch(error){say(error.message);}finally{confirm.disabled=false;}});
 messages.addEventListener('click',async event=>{
  const emoji=event.target.closest('[data-emoji]');if(emoji){try{if(emoji.dataset.emojiComposer==='1'){const start=text.selectionStart||0,end=text.selectionEnd||0;text.setRangeText(emoji.dataset.emoji,start,end,'end');text.dispatchEvent(new Event('input',{bubbles:true}));composerEmoji=false;get('[data-dm-composer-emoji]').hidden=true;text.focus();}else await react(Number(emoji.dataset.emojiTarget),emoji.dataset.emoji);}catch(error){say(error.message);}return;}
  const moreEmoji=event.target.closest('[data-emoji-more]');if(moreEmoji){const popover=moreEmoji.closest('.dm-emoji-popover');popover.replaceChildren();const input=document.createElement('input');input.type='search';input.placeholder='Wyszukaj emoji';input.setAttribute('aria-label','Wyszukaj emoji');const grid=document.createElement('span');grid.className='dm-emoji-grid';const target=moreEmoji.dataset.emojiMore;const fill=query=>{grid.replaceChildren(...allEmoji.filter(value=>!query||value.includes(query)).map(value=>{const button=document.createElement('button');button.type='button';button.dataset.emoji=value;button.dataset.emojiTarget=target==='composer'?'0':target;button.dataset.emojiComposer=target==='composer'?'1':'';button.textContent=value;return button;}));};input.addEventListener('input',()=>fill(input.value));fill('');popover.append(input,grid);input.focus();return;}
  const reaction=event.target.closest('[data-react-emoji]');if(reaction){try{await react(Number(reaction.dataset.reactMessage),reaction.dataset.reactEmoji);}catch(error){say(error.message);}return;}
  const reply=event.target.closest('[data-reply]');if(reply){replyTo=rows.find(row=>row.id===Number(reply.dataset.reply))||null;renderReplyPreview();text.focus();return;}
  const addReaction=event.target.closest('[data-react]');if(addReaction){emojiFor=emojiFor===Number(addReaction.dataset.react)?null:Number(addReaction.dataset.react);renderMessages(false);return;}
  const more=event.target.closest('[data-more]');if(more){const id=Number(more.dataset.more);const menu=messages.querySelector('[data-id="'+String(id)+'"] .dm-message-actions');if(!menu)return;let options=menu.querySelector('.dm-message-menu');if(options){options.remove();return;}options=document.createElement('span');options.className='dm-message-menu';for(const [label,action] of [['Edytuj','edit-message'],['Cofnij wysłanie','withdraw-message']]){const button=document.createElement('button');button.type='button';button.dataset[action]=String(id);button.textContent=label;options.append(button);}menu.append(options);return;}
  const edit=event.target.closest('[data-edit-message]');if(edit){editingId=Number(edit.dataset.editMessage);emojiFor=null;renderMessages(false);messages.querySelector('[data-id="'+String(editingId)+'"] .dm-inline-editor')?.focus();return;}
  const cancel=event.target.closest('[data-cancel-edit]');if(cancel){editingId=null;renderMessages(false);return;}
  const save=event.target.closest('[data-save-edit]');if(save){try{await saveEdit(Number(save.dataset.saveEdit));}catch(error){say(error.message);}return;}
  const withdraw=event.target.closest('[data-withdraw-message]');if(withdraw){withdrawTarget=Number(withdraw.dataset.withdrawMessage);withdrawDialog.querySelector('input[value="everyone"]').checked=true;if(withdrawDialog.showModal)withdrawDialog.showModal();else withdrawDialog.setAttribute('open','');}
 });
 get('[data-dm-reply]').onclick=()=>{if(replyTo){text.focus();}else say('Wybierz strzałkę przy wiadomości, na którą chcesz odpowiedzieć.');};
 get('[data-dm-emoji]').onclick=()=>{composerEmoji=!composerEmoji;const popover=get('[data-dm-composer-emoji]');popover.hidden=!composerEmoji;if(composerEmoji){popover.replaceChildren(emojiPopover(0,true));popover.firstElementChild?.classList.add('dm-composer-emoji-content');}};
 get('[data-dm-composer-emoji]').addEventListener('click',event=>{const emoji=event.target.closest('[data-emoji]');if(emoji){const start=text.selectionStart||0,end=text.selectionEnd||0;text.setRangeText(emoji.dataset.emoji,start,end,'end');text.dispatchEvent(new Event('input',{bubbles:true}));composerEmoji=false;event.currentTarget.hidden=true;text.focus();return;}const more=event.target.closest('[data-emoji-more]');if(!more)return;const popover=more.closest('.dm-emoji-popover');popover.replaceChildren();const input=document.createElement('input');input.type='search';input.placeholder='Wyszukaj emoji';input.setAttribute('aria-label','Wyszukaj emoji');const grid=document.createElement('span');grid.className='dm-emoji-grid';const fill=query=>{grid.replaceChildren(...allEmoji.filter(value=>!query||value.includes(query)).map(value=>{const button=document.createElement('button');button.type='button';button.dataset.emoji=value;button.dataset.emojiComposer='1';button.textContent=value;return button;}));};input.addEventListener('input',()=>fill(input.value));fill('');popover.append(input,grid);input.focus();});
 get('[data-dm-reply-preview]').addEventListener('click',event=>{if(event.target.closest('[data-cancel-reply]')){replyTo=null;renderReplyPreview();text.focus();}});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!current||sending||!text.value.trim())return;
  const id=current.id,body=text.value.trim();let attempt=attempts.get(id);if(!attempt||attempt.body!==body){attempt={body,key:key()};attempts.set(id,attempt);}
  sending=true;form.querySelector('button').disabled=true;
  try{
   const sent=await request('send',{id,message:body,client_key:attempt.key,reply_to_id:replyTo?.id||0},true);animatedMessages.add(Number(sent.id));attempts.delete(id);drafts.delete(id);
   if(current?.id===id&&text.value.trim()===body){text.value='';text.style.height='auto';replyTo=null;renderReplyPreview();}
   await refresh();if(current?.id===id){await loadThread();messages.scrollTop=messages.scrollHeight;markRead();}say();
  }catch(e){say(e.message);}
  finally{sending=false;form.querySelector('button').disabled=false;}
 });
 text.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing){e.preventDefault();form.requestSubmit();}});
 text.addEventListener('input',()=>{if(current)drafts.set(current.id,text.value);text.style.height='auto';text.style.height=Math.min(110,text.scrollHeight)+'px';});
 async function connect(){
  if(connecting||(ws && ws.readyState<WebSocket.CLOSING)||Date.now()<reconnectAt)return;connecting=true;
  try{
   const data=await request('token',{},true);const url=new URL(data.ws_path,location.href);url.protocol=location.protocol==='https:'?'wss:':'ws:';
   const socket=new WebSocket(url);ws=socket;const timeout=setTimeout(()=>socket.close(),8000);
   socket.onopen=()=>socket.send(JSON.stringify({type:'auth',scope:'chat',token:data.token}));
   socket.onmessage=event=>{let data;try{data=JSON.parse(event.data);}catch{return;}
    if(data.type==='ready'){clearTimeout(timeout);wsReady=true;connection.textContent='Połączono';refresh();if(current)loadThread();}
    if(data.type==='chat_changed'){refresh();if(current&&Number(data.conversation_id)===current.id)loadThread();}
   };
   socket.onclose=()=>{clearTimeout(timeout);if(ws===socket){ws=null;wsReady=false;connection.textContent='Czat aktywny';reconnectAt=Date.now()+30000;}};
   socket.onerror=()=>socket.close();
  }catch{reconnectAt=Date.now()+30000;connection.textContent='Czat aktywny';}
  finally{connecting=false;}
 }
 let lastPoll=0;
 setInterval(()=>{if(document.hidden)return;const interval=wsReady?15000:panel.hidden?5000:2000;if(Date.now()-lastPoll>=interval){lastPoll=Date.now();refresh();if(current&&!panel.hidden)loadThread();}connect();},1000);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden){refresh();if(current)loadThread();connect();}});
 window.addEventListener('pagehide',()=>ws?.close());
 const presenceClient=key();let lastActivity=Date.now(),lastHeartbeat=0,presenceBusy=false;
 async function heartbeat(force=false){
  if(presenceBusy||(!force&&Date.now()-lastHeartbeat<25000))return;
  presenceBusy=true;lastHeartbeat=Date.now();
  try{await request('presence',{client_id:presenceClient,visible:document.hidden?'0':'1',idle_seconds:String(Math.floor((Date.now()-lastActivity)/1000))},true);}catch{}finally{presenceBusy=false;}
 }
 const activity=()=>{if(document.hidden)return;const wasAway=Date.now()-lastActivity>=600000;lastActivity=Date.now();if(wasAway)heartbeat(true);};
 ['pointerdown','pointermove','keydown','scroll','touchstart'].forEach(name=>document.addEventListener(name,activity,{passive:true}));
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)lastActivity=Date.now();heartbeat(true);});
 window.addEventListener('pagehide',()=>{
  const body=JSON.stringify({action:'presence',client_id:presenceClient,visible:'0',idle_seconds:'0',leaving:'1',csrf_token:csrf});
  navigator.sendBeacon?.(api,new Blob([body],{type:'application/json'}));
 });
 window.addEventListener('pageshow',()=>heartbeat(true));setInterval(()=>heartbeat(),25000);
 fit();refresh();connect();heartbeat(true);
})();
