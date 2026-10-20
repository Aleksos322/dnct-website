(() => {
  'use strict';
  const configNode = document.getElementById('dnct-support-config'); if (!configNode || !window.DNCTChat) return;
  const config = JSON.parse(configNode.textContent), admin = Boolean(config.admin), {el,button,key} = DNCTChat;
  const panel = admin ? document.querySelector('.adm-support-chat-pane') : document.getElementById('dnct-support-chat-panel'); if (!panel) return;
  let ticket = null, rows = [], view = null, before = null, hasMore = false, pending = null, revision = 0, socket = null, socketTicket = 0, creating = false, freshForm = false, receipts = [], lastRead = 0, readBusy = false;
  let previewTimer = null, previewDeadline = 0, previewBadge;
  let body, title, subtitle, footer, closeTicketButton, createKey = key();
  const trigger = document.getElementById('dnct-support-chat-btn');
  if (admin) {
    panel.querySelector(':scope > form')?.remove(); panel.querySelector('.adm-modal-history')?.remove();
    body = el('div','dc-admin-chat'); const utility = panel.querySelector('.adm-modal-utility'); panel.insertBefore(body,utility || null);
  } else {
    const top = el('header','dc-support-top'), back = button('←', () => { document.dispatchEvent(new Event('dnct:return-chat')); }, 'dc-shell-back'); back.setAttribute('aria-label','Wróć do rozmów'); back.hidden = !config.loggedIn;
    const heading = el('div'); title = el('strong','','Zgłoś problem'); subtitle = el('small','','Porozmawiaj z zespołem DNCT'); const titleRow = el('div','dc-ticket-title'); previewBadge = el('span','dc-preview-timer'); previewBadge.hidden = true; titleRow.append(title,previewBadge); heading.append(titleRow,subtitle);
    const dismiss = button('×',() => close(),'dc-shell-close'); dismiss.setAttribute('aria-label','Zamknij czat'); top.append(back,heading,dismiss);
    body = el('div','dc-support-body'); footer = el('div','dc-support-footer'); footer.hidden = true; closeTicketButton = button('Zamknij zgłoszenie'); footer.append(closeTicketButton);
    panel.replaceChildren(top,body,footer); panel.setAttribute('aria-label','Wsparcie DNCT');
    window.DNCTSupport = {open,close,isOpen:() => panel.classList.contains('is-open')};
    if (!config.loggedIn) trigger?.addEventListener('click',() => panel.classList.contains('is-open') ? close() : open());
    document.addEventListener('keydown',e => { if (e.key === 'Escape' && !e.defaultPrevented && !document.querySelector('.dc-dialog[open]') && panel.classList.contains('is-open')) close(); });
    document.addEventListener('dnct:close-chat',() => close());
    window.addEventListener('resize',fit); window.addEventListener('scroll',fit,{passive:true});
  }
  const visible = () => !document.hidden && (admin || panel.classList.contains('is-open'));
  function fit() { if (admin) return; const nav = document.querySelector('#main-header nav')?.getBoundingClientRect(); const top = Math.max(12,nav && nav.bottom > 0 ? nav.bottom+12 : 12); const bottom = Math.max(12,innerHeight - trigger.getBoundingClientRect().top + 12); panel.style.bottom = bottom+'px'; panel.style.maxHeight = Math.max(160,innerHeight-top-bottom)+'px'; }
  function open() { document.dispatchEvent(new Event('dnct:support-open')); document.dispatchEvent(new Event('dnct:close-notifications')); fit(); panel.classList.add('is-open'); panel.setAttribute('aria-hidden','false'); trigger?.setAttribute('aria-expanded','true'); refresh(); }
  function close(focus = true) { if (admin) return; if (ticket && !freshForm && !['new','open'].includes(ticket.status)) document.dispatchEvent(new Event('dnct:support-finished')); view?.closePopover(); closeTopics(); panel.classList.remove('is-open'); panel.setAttribute('aria-hidden','true'); trigger?.setAttribute('aria-expanded','false'); if (focus) trigger?.focus(); }
  async function createTicket(values,requestKey) {
    try { return await DNCTChat.request(config.api,{action:'create',request_key:requestKey,...values},config.csrf); }
    catch (error) {
      // A lost response is not proof that the transaction failed. Verify this exact attempt.
      if (error.status >= 400 && error.status < 500) throw error;
      const url=new URL(config.api,location.href);
      try {
        const response=await fetch(url,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(10000)});
        const data=await response.json();
        if(response.ok&&data.ok&&data.ticket?.request_key===requestKey)return data;
      } catch (_) { /* Preserve the original failure and the idempotency key for retry. */ }
      throw error;
    }
  }
  function makeView() {
    if (view) return; closeTopics(); body.replaceChildren(); view = new DNCTChat.Conversation(body,{
      maxLength:2000,media:true,composerReply:false,mediaLimits:config.mediaLimits,
      send:async (values,files,progress) => { const data = await post('message',{message:values.message,message_key:values.key,reply_to_id:values.reply_to_id},files,progress); apply(data); },
      action:async (action,values) => { const data = await post(({edit:'edit_message',withdraw:'withdraw_message'})[action] || action,values); if (action === 'withdraw' && values.scope === 'me') rows = rows.filter(m => m.id !== values.message_id); apply(data);if(action==='react'&&ticket){const id=ticket.id,url=new URL(config.api,location.href);url.searchParams.set(admin?'ticket':'ticket_id',id);url.searchParams.set('before_id',Number(values.message_id)+1);const response=await fetch(url,{credentials:'same-origin',cache:'no-store'}),page=await response.json();if(response.ok&&page.ok&&ticket?.id===id){const updated=page.messages?.find(m=>Number(m.id)===Number(values.message_id));if(updated){rows=rows.map(m=>m.id===Number(updated.id)?{...m,...updated,id:Number(updated.id)}:m);render();}}} },
      older:() => refresh(before), read:id => markRead(id), typing:() => socket?.typing(true), failure:e => { if (e.status === 404 || e.status === 409) refresh(); },
    });
  }
  async function post(action,values = {},files = [],progress) {
    revision++;
    return DNCTChat.request(config.api,{action,ticket_id:ticket?.id || config.ticketId,...values},config.csrf,files,progress);
  }
  let alertsStarted=false,alertTicket=0,alertMessage=0,alertPollBusy=false;
  function ticketAlerts(data,older=false){
    if(admin||older||!window.DNCTAlerts)return;
    const t=data.ticket;
    if(!t){window.DNCTAlerts.ticket(0);alertsStarted=true;return;}
    const messages=data.messages||[],read=Math.max(0,...(data.receipts||[]).filter(r=>r.participant_role==='customer').map(r=>Number(r.last_message_id)));
    window.DNCTAlerts.ticket(data.customer_unread??messages.filter(m=>m.author_role==='admin'&&Number(m.id)>read).length);
    const incoming=messages.filter(m=>m.author_role==='admin'&&!m.withdrawn_at&&!m.is_withdrawn);
    const previous=alertTicket===Number(t.id)?alertMessage:0;
    if(alertsStarted)for(const m of incoming)if(Number(m.id)>previous)window.DNCTAlerts.message('ticket:'+t.id+':'+m.id,m.author_name,m.body,()=>open(),false,()=>panel.classList.contains('is-open')&&panel.getAttribute('aria-hidden')!=='true'&&Number(ticket?.id)===Number(t.id)&&!freshForm);
    alertMessage=Math.max(previous,0,...incoming.map(m=>Number(m.id)));alertTicket=Number(t.id);alertsStarted=true;
  }
  document.addEventListener('dnct:read-all-support',async()=>{if(admin||!alertTicket||!alertMessage)return;try{await DNCTChat.request(config.realtime,{action:'read',ticket_id:alertTicket,last_message_id:alertMessage},config.csrf);lastRead=Math.max(lastRead,alertMessage);window.DNCTAlerts?.ticket(0);}catch{}});
  async function pollTicketAlerts(){
    if(admin||!config.loggedIn||alertPollBusy||creating||visible())return;
    alertPollBusy=true;
    try{const response=await fetch(config.api,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)});const data=await response.json();if(response.ok&&data.ok)ticketAlerts(data);}catch{}finally{alertPollBusy=false;}
  }
  if(!admin&&config.loggedIn){pollTicketAlerts();setInterval(pollTicketAlerts,10000);}
  function apply(data, older = false) {
    ticketAlerts(data,older);
    if (!data.ticket) {
      if (!admin) { startNewTicket(); return; }
      if (ticket || config.ticketId) { ticket = {...ticket,id:ticket?.id || config.ticketId,status:'closed'}; makeView(); render(); }
      else if (!freshForm && !creating) showCreate();
      return;
    }
    if (freshForm && ['closed','archived'].includes(data.ticket.status)) return;
    if (ticket && Number(ticket.id) !== Number(data.ticket.id)) { rows = []; before = null; view?.reset(); lastRead = 0; }
    const joined = !admin && ticket && Number(ticket.id) === Number(data.ticket.id) && data.ticket.assigned_admin && ticket.assigned_admin !== data.ticket.assigned_admin;
    ticket = data.ticket; freshForm = false; makeView();
    if (joined) { body.querySelector('.dc-admin-joined')?.remove(); const notice = el('div','dc-admin-joined',ticket.assigned_admin + ' dołącza do rozmowy. Możesz już napisać.'); notice.setAttribute('role','status'); body.prepend(notice); setTimeout(() => { notice.classList.add('is-leaving'); setTimeout(() => notice.remove(),250); },6500); }

    const incoming = (data.messages || []).map(m => ({...m,id:Number(m.id),mine:m.mine ?? (admin ? m.author_role === 'admin' && m.author_name?.toLowerCase() === config.name.toLowerCase() : m.author_role === 'customer')}));
    if (older) { rows = [...new Map([...incoming,...rows].map(m => [m.id,m])).values()].sort((a,b) => a.id-b.id); before = data.next_before_id; hasMore = Boolean(data.has_more); }
    else { const min = incoming[0]?.id || 0; rows = [...(data.has_more ? rows.filter(m => m.id < min) : []),...incoming]; if (!before || !data.has_more) { before = data.next_before_id; hasMore = Boolean(data.has_more); } }
    receipts = data.receipts || receipts; render(older); connect();
  }
  function render(older = false) {
    if (!ticket || !view) return;
    const closed = !['new','open'].includes(ticket.status); const writable = ticket.status === 'open' && (!admin || ticket.can_write);
    const peerRead = Math.max(0,...receipts.filter(r => r.participant_role !== (admin ? 'admin' : 'customer')).map(r => Number(r.last_message_id)));
    view.update(rows.map(m => ({...m,receipt:peerRead >= m.id ? 'Przeczytano' : 'Wysłano'})),{readers:receipts.filter(r=>r.participant_role !== (admin ? 'admin' : 'customer')),closed,closedBy:ticket.closed_by_name || (ticket.closed_by_user ? ticket.customer_name : ''),writable,actions:!closed && (!admin || ticket.can_write),reason:closed ? ticket.close_reason || '' : ticket.status === 'new' ? 'Zgłoszenie jest w kolejce. Powiadomimy Cię, gdy administrator dołączy.' : !writable ? 'Podgląd rozmowy — pisze przypisany administrator.' : '',hasMore,prepend:older});
    if (!admin) { title.textContent = ticket.category_label || 'Zgłoszenie'; subtitle.textContent = closed ? 'Rozmowa zakończona' : ticket.assigned_admin ? 'Rozmawiasz z ' + ticket.assigned_admin : 'Czekamy na administratora'; footer.hidden = false; closeTicketButton.textContent = closed ? 'Zgłoś nowy problem' : 'Zamknij zgłoszenie'; closeTicketButton.onclick = closed ? startNewTicket : closeTicket; syncPreview(closed); }
    else { const close = panel.querySelector('[data-close-ticket]'); if (close) close.hidden = closed; const badge = panel.querySelector('.adm-modal-status'); if (badge) badge.textContent = closed ? 'ZAMKNIĘTE' : writable ? 'PRZYPISANY DO CIEBIE' : 'PODGLĄD'; }
    if (closed) { socket?.close(); socket = null; socketTicket = 0; }
  }
  function startNewTicket() {
    if (admin) return;
    revision++; freshForm = true; clearInterval(previewTimer); previewTimer = null; previewDeadline = 0;
    socket?.close(); socket = null; socketTicket = 0; view?.destroy(); view = null;
    rows = []; before = null; hasMore = false; receipts = []; lastRead = 0;
    showCreate();
  }
  function syncPreview(closed) {
    clearInterval(previewTimer); previewTimer = null; previewBadge.hidden = !closed;
    if (!closed) { previewDeadline = 0; return; }
    const remaining = Number(ticket.preview_remaining_seconds);
    if (!Number.isFinite(remaining) || remaining <= 0) { startNewTicket(); return; }
    previewDeadline = Date.now() + Math.min(300,remaining)*1000;
    const tick = () => {
      const seconds = Math.max(0,Math.ceil((previewDeadline-Date.now())/1000));
      if (!seconds) { startNewTicket(); return; }
      previewBadge.textContent = 'Czas na podgląd ' + Math.floor(seconds/60) + ':' + String(seconds%60).padStart(2,'0');
    };
    tick(); previewTimer = setInterval(tick,250);
  }
  async function refresh(older = null) {
    if (!visible() || (freshForm && ticket)) return;
    if (pending) { if (!older) return pending; await pending; }
    const version = revision;
    const url = new URL(config.api,location.href); const id = ticket?.id || config.ticketId; if (id) url.searchParams.set(admin ? 'ticket' : 'ticket_id',id); if (older) url.searchParams.set('before_id',older);
    const task = (async () => { try { const response = await fetch(url,{credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(15000)}); const data = await response.json(); if (version !== revision) return; if (response.status === 404 && id) { apply({ticket:null}); return; } if (!response.ok || !data.ok) throw Error(data.message || 'Nie udało się wczytać rozmowy.'); const visibleId=!older&&view&&!view.nearBottom()?view.visibleMessageId():0;if(visibleId&&data.messages?.length&&visibleId<Number(data.messages[0].id)){const historyUrl=new URL(url);historyUrl.searchParams.set('before_id',visibleId+1);const hr=await fetch(historyUrl,{credentials:'same-origin',cache:'no-store'}),history=await hr.json();if(version!==revision)return;if(hr.ok&&history.ok){const changed=new Map((history.messages||[]).map(m=>[Number(m.id),m]));rows=rows.map(m=>changed.has(m.id)?{...m,...changed.get(m.id),id:m.id}:m);}}apply(data,Boolean(older)); } catch(e) { if (version === revision) { if (view) view.say('Nie udało się odświeżyć rozmowy. Ponawiam połączenie…'); else if (!body.firstChild) { const error = el('p','dc-error',e.message); body.append(error,button('Spróbuj ponownie',() => { body.replaceChildren(); refresh(); })); } } } })();
    pending = task; try { await task; } finally { if (pending === task) pending = null; }
  }
  function connect() {
    if (!ticket || !['new','open'].includes(ticket.status) || (admin && !ticket.can_write) || !window.DNCTSupportRealtime) return;
    if (socket && socketTicket === ticket.id) return; socket?.close(); socketTicket = ticket.id;
    socket = new DNCTSupportRealtime({tokenUrl:config.realtime+(admin ? '&ticket='+ticket.id : ''),onReady:() => refresh(),onEvent:e => { if (e.type === 'typing') { if (view) view.hint.textContent = e.active ? 'Rozmówca pisze…' : 'Enter — wyślij · Shift + Enter — nowa linia'; } else refresh(); }}); socket.connect();
  }
  async function markRead(id) {
    if (!id || id <= lastRead || !visible() || readBusy || ticket?.status !== 'open' || (admin && !ticket.can_write)) return;
    if (socket?.read(id)) { lastRead = id; if(!admin)window.DNCTAlerts?.ticket(rows.filter(m=>m.author_role==='admin'&&m.id>id).length); return; }
    readBusy = true;
    try { await DNCTChat.request(config.realtime,{action:'read',ticket_id:ticket.id,last_message_id:id},config.csrf); lastRead = id; if(!admin)window.DNCTAlerts?.ticket(rows.filter(m=>m.author_role==='admin'&&m.id>id).length); } catch {} finally { readBusy = false; }
  }
  function closeTicket() {
    if (!view || !ticket) return; const dialog = view.dialog('Zamknij zgłoszenie');
    const label = el('label','','Dlaczego zamykasz zgłoszenie?'); const reason = el('textarea'); reason.required = true; reason.minLength = 3; reason.maxLength = 500; reason.placeholder = 'Np. problem został rozwiązany'; reason.setAttribute('aria-label','Powód zamknięcia'); label.append(reason);
    const error = el('p','dc-error'), actions = el('div','dc-dialog-actions'); const confirm = button('Zamknij zgłoszenie',async () => { if (!reason.reportValidity()) return; confirm.disabled = true; try { const data = await post('close_user',{reason:reason.value.trim()}); apply(data); dialog.close(); } catch(e) { error.textContent = e.message; } finally { confirm.disabled = false; } },'dc-primary'); actions.append(button('Anuluj',() => dialog.close()),confirm); dialog.append(label,error,actions); dialog.showModal(); reason.focus();
  }
  let topicDrawer = null, topicTimer = null, topicAnchor = null;
  function closeTopics() { clearTimeout(topicTimer); if (!topicDrawer) return; const drawer = topicDrawer; if (drawer.contains(document.activeElement)) topicAnchor?.focus({preventScroll:true}); topicDrawer = null; topicAnchor?.setAttribute('aria-expanded','false'); drawer.classList.add('is-leaving'); setTimeout(() => { drawer.remove(); if (!topicDrawer) panel.style.removeProperty('transform'); },320); }
  function openTopics(anchor,select) {
    if (topicDrawer) { closeTopics(); return; }
    topicAnchor = anchor; const drawer = el('div','dc-topic-drawer'); topicDrawer = drawer; drawer.dataset.lenisPrevent = ''; drawer.setAttribute('role','dialog'); drawer.setAttribute('aria-label','Wybierz temat zgłoszenia');
    const head = el('header'); const dismiss = button('×',closeTopics,'dc-shell-close'); dismiss.setAttribute('aria-label','Zamknij listę tematów'); head.append(el('strong','','Wybierz temat'),dismiss); drawer.append(head);
    for (const [group,categories] of Object.entries(config.groups || {})) { drawer.append(el('h4','',group)); for (const [value,name] of Object.entries(categories)) { const option = button(name,() => { select.value = value; anchor.textContent = name; anchor.removeAttribute('aria-invalid'); drawer.querySelectorAll('[aria-pressed]').forEach(n => n.setAttribute('aria-pressed','false')); option.setAttribute('aria-pressed','true'); drawer.classList.remove('is-selected'); void drawer.offsetWidth; drawer.classList.add('is-selected'); clearTimeout(topicTimer); topicTimer = setTimeout(closeTopics,3000); }); option.setAttribute('aria-pressed',String(select.value === value)); drawer.append(option); } }
    panel.style.removeProperty('transform');
    const r = panel.getBoundingClientRect(), width = Math.min(270,innerWidth-112), left = Math.max(12,r.left-width-12);
    const shift = Math.max(0,left+width+12-r.left);
    drawer.style.setProperty('--topic-travel',(width+12)+'px');
    if (shift) panel.style.transform = 'translateX('+shift+'px)';
    document.body.append(drawer); drawer.style.width = width+'px'; drawer.style.left = left+'px'; drawer.style.top = Math.max(12,r.top+36)+'px'; drawer.style.maxHeight = Math.max(120,Math.min(r.height-48,innerHeight-r.top-48))+'px'; anchor.setAttribute('aria-expanded','true'); dismiss.focus({preventScroll:true});
    drawer.addEventListener('keydown', e => { if(e.key==='Escape'){e.stopPropagation();closeTopics();anchor.focus();} });
  }
  document.addEventListener('pointerdown',e => { if(topicDrawer && !topicDrawer.contains(e.target) && !topicAnchor?.contains(e.target)) closeTopics(); });
  window.addEventListener('resize',closeTopics);
  function showCreate() {
    if (admin || body.querySelector('.dc-support-form')) return;
    if (previewBadge) previewBadge.hidden = true;
    title.textContent = 'Zgłoś problem'; subtitle.textContent = 'Porozmawiaj z zespołem DNCT'; footer.hidden = true;
    const form = el('form','dc-support-form'); form.noValidate = true; form.dataset.lenisPrevent = ''; form.append(el('p','','Opisz, co się dzieje. Zespół wsparcia odpowie w tej rozmowie.'));
    const input = (name,label,type,value) => { const row=el('label','',label+' *'),field=el('input'); field.type=type; field.name=name; field.required=true; field.value=value || ''; row.append(field); form.append(row); return field; };
    input('name','Imię','text',config.name).maxLength=100; input('email','Adres e-mail','email',config.email);
    const label=el('label','','Potrzebuję pomocy *'),select=el('select'); select.name='category'; select.required=true; const placeholder=el('option','','Wybierz temat'); placeholder.value=''; placeholder.disabled=true; placeholder.selected=true; select.append(placeholder);
    for (const [group,categories] of Object.entries(config.groups || {})) { const optgroup=el('optgroup'); optgroup.label=group; for (const [value,name] of Object.entries(categories)) { const option=el('option','',name); option.value=value; optgroup.append(option); } select.append(optgroup); } label.append(select); form.append(label);
    select.classList.add('dc-native-topic'); select.tabIndex = -1; select.setAttribute('aria-hidden','true');
    const topicButton = button('Wybierz temat', () => openTopics(topicButton,select),'dc-topic-button'); topicButton.setAttribute('aria-expanded','false'); label.append(topicButton);

    const messageLabel=el('label','dc-problem-label','Wiadomość *'),message=el('textarea'); message.name='message'; message.required=true; message.minLength=20; message.maxLength=2000; message.placeholder='Opisz problem (minimum 20 znaków)…'; messageLabel.append(message); form.append(messageLabel);
    const error=el('p','dc-error'); error.setAttribute('role','status'); const submit=button('Rozpocznij rozmowę'); submit.type='submit'; form.append(error,submit); body.replaceChildren(form);
    form.onsubmit=async e => { e.preventDefault(); if (creating) return;
      form.querySelectorAll('[data-field-error]').forEach(node=>node.remove());
      for (const field of [message,form.elements.name]) field.setCustomValidity(field.value.trim().length < (field === message ? 20 : 1) ? 'Uzupełnij pole.' : '');
      const invalid = Array.from(form.elements).filter(field => field.willValidate && !field.checkValidity());
      if (invalid.length) { error.textContent = 'Uzupełnij wymagane pola. Opis musi mieć co najmniej 20 znaków.'; for (const field of invalid) { const target = field === select ? topicButton : field; target.classList.remove('dc-invalid'); void target.offsetWidth; target.classList.add('dc-invalid'); target.setAttribute('aria-invalid','true'); setTimeout(() => { target.classList.remove('dc-invalid'); target.removeAttribute('aria-invalid'); },1600); } (invalid[0] === select ? topicButton : invalid[0]).focus(); return; }
      closeTopics(); creating=true; submit.disabled=true; error.textContent=''; revision++;
      try { const values=Object.fromEntries(new FormData(form)); const data=await createTicket(values,createKey); createKey=key(); freshForm=false; ticket=null; rows=[]; before=null; apply(data); }
      catch(e) {
        error.textContent=e.message; let first=null;
        for(const name of ['name','email','category','message']) {
          const text=e.fields?.[name];if(typeof text!=='string')continue;
          const field=form.elements[name],target=field===select?topicButton:field;
          const hint=el('small','dc-error',text);hint.dataset.fieldError=name;target.after(hint);
          target.setAttribute('aria-invalid','true');target.classList.add('dc-invalid');first ||= target;
          const clear=()=>{hint.remove();target.removeAttribute('aria-invalid');target.classList.remove('dc-invalid');};
          field.addEventListener('input',clear,{once:true});field.addEventListener('change',clear,{once:true});
        }
        first?.focus();
      } finally { creating=false; submit.disabled=false; }
    };
  }
  setInterval(() => { if (visible()) refresh(); },2500);
  document.addEventListener('visibilitychange',() => { if (visible()) refresh(); }); window.addEventListener('online',() => refresh()); window.addEventListener('pagehide',() => socket?.close());
  if (admin) refresh();else if(new URLSearchParams(location.search).get('wsparcie')==='1')open();
  document.addEventListener('dnct:notification-support',()=>{if(!admin)open();});
})();
