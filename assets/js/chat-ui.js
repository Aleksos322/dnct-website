(() => {
  'use strict';
  if (window.DNCTChat) return;
  const icons = {
    smile: '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.2 3 4 3 4-3 4-3M8 9h.01M16 9h.01"/>',
    reply: '<path d="m9 5-6 6 6 6M3 11h11a7 7 0 0 1 7 7"/>',
    send: '<path d="m21 3-6 18-4-8-8-4 18-6ZM11 13l10-10"/>',
    more: '<circle cx="5" cy="12" r=".7"/><circle cx="12" cy="12" r=".7"/><circle cx="19" cy="12" r=".7"/>',
    attach: '<path d="m8 13 7-7a3 3 0 0 1 4 4L9 20a5 5 0 0 1-7-7L13 2M6 15 16 5"/>',
  };
  const el = (tag, className, text) => { const n = document.createElement(tag); if (className) n.className = className; if (text != null) n.textContent = text; return n; };
  const button = (label, fn, className = '') => { const b = el('button', className, label); b.type = 'button'; if (fn) b.addEventListener('click', fn); return b; };
  const icon = (kind, label, fn) => { const b = button('', fn, 'dc-icon'); b.title = label; b.setAttribute('aria-label', label); b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true">' + icons[kind] + '</svg>'; return b; };
  const key = () => Array.from(crypto.getRandomValues(new Uint8Array(32)), b => b.toString(16).padStart(2, '0')).join('');
  const normal = s => String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pl').replace(/ł/g, 'l');
  // Database timestamps without an offset are UTC, never browser-local time.
  const date = value => {
    let text=String(value || '').trim().replace(' ', 'T');
    if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(text))text+='Z';
    return new Date(text);
  };
  const time = value => { const d = date(value); return Number.isNaN(d.getTime()) ? '' : d.toLocaleString('pl-PL', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'}); };
  const age = value => {
    const stamp=typeof value==='number'?value*1000:date(value).getTime();
    const seconds=Math.max(0,Math.floor((Date.now()-stamp)/1000));
    if(!Number.isFinite(seconds)||seconds<60)return 'teraz';
    const units=[[31536000,'rok','lata','lat'],[2592000,'miesiąc','miesiące','miesięcy'],[604800,'tydzień','tygodnie','tygodni'],[86400,'dzień','dni','dni'],[3600,'godz.','godz.','godz.'],[60,'min','min','min']];
    for(const [size,one,few,many] of units)if(seconds>=size){const n=Math.floor(seconds/size);return n+' '+(n===1?one:n%10>=2&&n%10<=4&&(n%100<12||n%100>14)?few:many);}
  };
  const defaults = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
  let memoryFavourites = [...defaults];
  const favourites = () => { try { const v = JSON.parse(localStorage.getItem('dnct-chat-quick-reactions')); return Array.isArray(v) && v.length === 6 && v.every(x => typeof x === 'string') ? v : [...memoryFavourites]; } catch { return [...memoryFavourites]; } };
  let emojiData;
  async function catalogue() {
    if (!emojiData) emojiData = fetch('/assets/data/emoji-pl.json', {cache:'force-cache'}).then(r => { if (!r.ok) throw Error('Nie udało się wczytać emoji.'); return r.json(); }).catch(e => { emojiData = null; throw e; });
    return emojiData;
  }
  async function request(url, values, csrf, files = [], progress) {
    const body = new FormData();
    Object.entries(values).forEach(([k,v]) => body.append(k, String(v ?? '')));
    body.append('csrf_token', csrf);
    files.forEach(f => body.append('attachments[]', f, f.name));
    const parse = (status, raw) => { let data; try { data = JSON.parse(raw); } catch { throw Error(status === 413 ? 'Pliki są zbyt duże. Zdjęcie: do 10 MB, film: do 50 MB.' : 'Nie udało się odczytać odpowiedzi serwera. Spróbuj ponownie.'); } if (status < 200 || status >= 300 || !data.ok) { const e = Error(data.message || 'Nie udało się zapisać zmian.'); e.status = status; e.fields = data.errors && typeof data.errors === 'object' ? data.errors : {}; e.retryAfter = Number(data.retry_after) || 0; throw e; } return data; };
    if (files.length) return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest(); xhr.open('POST', url); xhr.setRequestHeader('X-CSRF-Token', csrf); xhr.timeout = 120000;
      xhr.upload.onprogress = e => { if (e.lengthComputable) progress?.(e.loaded / e.total); };
      xhr.onload = () => { try { resolve(parse(xhr.status, xhr.responseText)); } catch(e) { reject(e); } };
      xhr.onerror = xhr.ontimeout = () => reject(Error('Przesyłanie przerwane. Pliki i wiadomość zostały zachowane — spróbuj ponownie.'));
      xhr.send(body);
    });
    const res = await fetch(url, {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'X-CSRF-Token':csrf}, body, signal:AbortSignal.timeout(20000)});
    return parse(res.status, await res.text());
  }
  class Conversation {
    constructor(mount, options = {}) {
      this.options = options; this.rows = []; this.nodes = new Map(); this.reply = null; this.files = []; this.writable = true; this.actions = true; this.sending = false; this.attempt = null; this.editing = null;
      this.root = el('div', 'dc-chat'); this.root.dataset.lenisPrevent = '';
      this.older = button('Wczytaj starsze wiadomości', () => this.run(() => options.older?.()), 'dc-older'); this.older.hidden = true;
      this.feed = el('div', 'dc-feed'); this.feed.setAttribute('role', 'log'); this.feed.setAttribute('aria-label', 'Wiadomości'); this.feed.setAttribute('aria-live', 'polite'); this.feed.dataset.lenisPrevent = '';
      this.system = el('div', 'dc-system'); this.system.setAttribute('role', 'status'); this.system.hidden = true;
      this.bottom = el('div', 'dc-bottom'); this.preview = el('div', 'dc-reply-preview'); this.preview.hidden = true; this.previews = el('div', 'dc-previews'); this.previews.hidden = true;
      this.form = el('form', 'dc-composer'); this.text = el('textarea'); this.text.rows = 1; this.text.maxLength = options.maxLength || 4000; this.text.placeholder = 'Napisz wiadomość…'; this.text.setAttribute('aria-label', 'Wiadomość'); this.form.append(this.text);
      if (options.media) {
        this.fileInput = el('input'); this.fileInput.type = 'file'; this.fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime'; this.fileInput.multiple = true; this.fileInput.hidden = true;
        this.fileInput.onchange = () => { this.addFiles(this.fileInput.files); this.fileInput.value = ''; };
        this.attach = icon('attach', 'Załącz zdjęcie lub film', () => this.fileInput.click()); this.form.append(this.attach, this.fileInput);
      }
      this.replyButton = icon('reply', 'Odpowiedz na wiadomość', () => this.chooseReply());
      this.emojiButton = icon('smile', 'Wstaw emoji', () => this.emoji(this.emojiButton, value => { const start = this.text.selectionStart, end = this.text.selectionEnd; this.text.setRangeText(value, start, end, 'end'); this.text.dispatchEvent(new Event('input')); this.text.focus(); }, true));
      this.sendButton = icon('send', 'Wyślij wiadomość'); this.sendButton.type = 'submit'; this.sendButton.classList.add('dc-send'); if (options.composerReply !== false) this.form.append(this.replyButton); this.form.append(this.emojiButton, this.sendButton);
      this.progress = el('progress', 'dc-upload-progress'); this.progress.max = 1; this.progress.hidden = true;
      this.hint = el('p', 'dc-hint'); this.hint.hidden=true; this.error = el('p', 'dc-error'); this.error.setAttribute('role', 'status'); this.error.hidden = true;
      this.bottom.append(this.preview, this.previews, this.form, this.progress, this.hint, this.error);
      this.newButton = button('↓', () => this.scrollBottom(true), 'dc-new-message'); this.newButton.setAttribute('aria-label','Przejdź do najnowszych wiadomości'); this.newButton.hidden = true;this.newButton.tabIndex=-1;
      this.receiptTimer=setInterval(()=>this.updateReceipt(),15000); this.root.append(this.feed, this.bottom, this.newButton); mount.replaceChildren(this.root);
      this.form.onsubmit = e => { e.preventDefault(); this.send(); };
      this.text.onkeydown = e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.form.requestSubmit(); } if (e.key === 'Escape' && this.reply) { e.stopPropagation(); this.setReply(null); } };
      this.text.oninput = () => { this.text.style.height = 'auto'; this.text.style.height = Math.min(126, this.text.scrollHeight) + 'px'; options.draft?.(this.text.value); options.typing?.(); };
      this.followBottom=true;this.feedResize=new ResizeObserver(()=>{if(this.followBottom&&!this.loadingOlder&&!this.scrollingLatest)this.scrollBottom();});this.feedResize.observe(this.feed);this.feed.addEventListener('load',()=>{if(this.followBottom)requestAnimationFrame(()=>{if(this.followBottom)this.scrollBottom();});},true);this.feed.addEventListener('loadedmetadata',()=>{if(this.followBottom)this.scrollBottom();},true);
      this.feed.addEventListener('scroll', () => { this.followBottom=this.nearBottom(); this.newButton.hidden=this.nearBottom();this.newButton.tabIndex=this.newButton.hidden?-1:0; if(this.feed.scrollTop<80)this.loadOlder(); if (this.nearBottom()) { this.newButton.hidden = true;this.newButton.tabIndex=-1; options.read?.(this.rows.at(-1)?.id); } }, {passive:true});
      if (options.media) {
        this.limits = options.mediaLimits || {image:10*1024*1024,video:50*1024*1024,files:4};
        const drop = el('div','dc-drop-overlay'); drop.setAttribute('aria-hidden','true');
        drop.innerHTML = '<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><rect x="1" y="1" width="98" height="98" rx="5" /></svg>';
        const copy = el('div'); copy.append(el('strong','','Upuść pliki tutaj'), el('small','',`Zdjęcia: maksymalnie ${this.limits.image/1024/1024} MB · filmy: maksymalnie ${this.limits.video/1024/1024} MB`)); drop.append(copy); this.root.append(drop);
        this.text.addEventListener('paste', e => { if (e.clipboardData?.files.length) { e.preventDefault(); this.addFiles(e.clipboardData.files); } });
        this.root.addEventListener('dragover', e => { if (Array.from(e.dataTransfer?.types || []).includes('Files')) { e.preventDefault(); if (this.writable && !this.sending) this.root.classList.add('is-dragover'); } });
        this.root.addEventListener('dragleave', e => { if (!this.root.contains(e.relatedTarget)) this.root.classList.remove('is-dragover'); });
        this.root.addEventListener('drop', e => { e.preventDefault(); this.root.classList.remove('is-dragover'); this.addFiles(e.dataTransfer?.files || []); });
      }
    }
    say(message = '') { this.error.textContent = message; this.error.hidden = !message; }
    async run(fn) { try { this.say(); return await fn(); } catch(e) { this.say(e.message || 'Nie udało się wykonać operacji.'); this.options.failure?.(e); } }
    visibleMessageId(){const bounds=this.feed.getBoundingClientRect();let id=0;for(const node of this.feed.querySelectorAll('[data-message-id]')){const r=node.getBoundingClientRect();if(r.bottom>=bounds.top&&r.top<=bounds.bottom)id=Number(node.dataset.messageId);}return id;}
    nearBottom() { return this.feed.scrollHeight - this.feed.scrollTop - this.feed.clientHeight < 90; }
    scrollBottom(smooth = false) {
      this.followBottom=true;
      if(this.scrollingLatest && !smooth)return;
      cancelAnimationFrame(this.scrollFrame);
      if(smooth && !matchMedia('(prefers-reduced-motion: reduce)').matches){
        const start=this.feed.scrollTop,began=performance.now();this.scrollingLatest=true;
        const step=now=>{const t=Math.min(1,(now-began)/450),ease=1-Math.pow(1-t,3);const target=Math.max(0,this.feed.scrollHeight-this.feed.clientHeight);this.feed.scrollTop=start+(target-start)*ease;if(t<1)this.scrollFrame=requestAnimationFrame(step);else{this.scrollingLatest=false;this.newButton.hidden=true;this.options.read?.(this.rows.at(-1)?.id);}};
        this.scrollFrame=requestAnimationFrame(step);
      }else{this.feed.scrollTop=this.feed.scrollHeight;this.newButton.hidden=true;this.options.read?.(this.rows.at(-1)?.id);}
    }
    async loadOlder() {
      if(this.scrollingLatest || !this.hasMore || this.loadingOlder || Date.now()<(this.olderRetryAt||0))return;
      this.loadingOlder=true;
      try{await this.options.older?.();}catch(e){this.say('Nie udało się pobrać starszych wiadomości. Przewiń, aby ponowić.');this.olderRetryAt=Date.now()+3000;}finally{this.loadingOlder=false;}
    }
    updateReceipt() {
      const boundaries=new Map();
      for(const reader of this.readers||[]){
        const row=this.rows.findLast(m=>m.id<=Number(reader.last_message_id));
        if(!row)continue;
        if(!boundaries.has(row.id))boundaries.set(row.id,[]);
        boundaries.get(row.id).push(reader);
      }
      for(const [id,node] of this.nodes){
        const people=boundaries.get(id)||[], signature=JSON.stringify(people);
        let markers=node.querySelector('.dc-read-receipts');
        if(!people.length){markers?.remove();continue;}
        if(markers?.dataset.readers===signature)continue;
        markers?.remove();markers=el('div','dc-read-receipts');markers.dataset.readers=signature;
        for(const person of people){
          const name=person.display_name||person.username||'Użytkownik';
          const face=el('span','dc-read-avatar',Array.from(name)[0]);
          face.title='Odczytano: '+name;face.setAttribute('aria-label',face.title);face.setAttribute('role','img');
          if(person.username)window.DNCTChat.avatar(face,person.username);
          markers.append(face);
        }
        node.append(markers);
      }
      const latest=this.rows.at(-1);
      for(const [id,node] of this.nodes){const label=node.querySelector('.dc-time');if(!label)continue;label.hidden=!(latest?.mine && latest.id===id) || boundaries.has(id);
        if(!label.hidden){const elapsed=age(latest.created_timestamp?Number(latest.created_timestamp):latest.created_at);label.textContent=latest.receipt==='Przeczytano'?'Przeczytano':'Wysłano'+(elapsed==='teraz'?'':' '+elapsed+' temu');label.title=time(latest.created_at);}}
    }
    setDraft(value) { this.text.value = value || ''; this.text.dispatchEvent(new Event('input')); }
    reset() { this.feedResize?.disconnect();this.feedResize?.observe(this.feed);this.followBottom=true;this.newButton.hidden=true;this.newButton.tabIndex=-1;cancelAnimationFrame(this.scrollFrame);this.scrollingLatest=false;this.closePopover(); this.rows = []; this.readers=[]; this.nodes.clear(); this.feed.replaceChildren(); this.setReply(null); this.editing = null; this.setDraft(''); this.clearFiles(); this.attempt = null; this.say(); }
    update(rows, {composerNotice = '', readers = [], closed = false, closedBy = '', reason = '', writable = true, actions = writable, hasMore = false, prepend = false} = {}) {
      const stick = this.nearBottom() || !this.rows.length, height = this.feed.scrollHeight, top = this.feed.scrollTop;
      const newMessage = rows.some(m => !this.nodes.has(Number(m.id))); const oldActions = this.actions;
      this.writable = writable && !closed; this.actions = actions && !closed;
      this.rows = rows.map(m => ({...m, id:Number(m.id)})); this.readers=readers;
      this.older.hidden = true; this.hasMore=hasMore;
      const ids = new Set(this.rows.map(m => m.id));
      for (const [id, node] of this.nodes) if (!ids.has(id)) { node.remove(); this.nodes.delete(id); }
      this.feed.querySelector('.dc-empty')?.remove(); this.feed.querySelectorAll('.dc-day').forEach(n => n.remove());
      let previous = null, lastDay = '', lastHour = '';
      for (const m of this.rows) {
        let node = this.nodes.get(m.id); const signature = JSON.stringify({...m,reactions:undefined}) + ':' + this.actions;
        if (!node || (node.dataset.signature !== signature && this.editing !== m.id)) {
          const next = this.message(m); next.dataset.signature = signature;
          if (node) node.replaceWith(next); node = next; this.nodes.set(m.id, node);
        } else if (oldActions !== this.actions && !this.actions) {
          this.editing = null; const next = this.message(m); next.dataset.signature = signature; node.replaceWith(next); node = next; this.nodes.set(m.id, node);
        }
        this.feedResize?.observe(node);this.syncReactions(node,m,true);
        const d = date(m.created_at); const day = Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('pl-PL', {day:'numeric', month:'long', year:'numeric'});
        if (day && day !== lastDay) { const heading = el('p', 'dc-day', day); this.feed.insertBefore(heading, previous ? previous.nextSibling : this.feed.firstChild); previous = heading; lastDay = day; }
        const hour=Number.isNaN(d.getTime())?'':d.toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'}); const hourKey=day+':'+d.getHours();
        if(hour && hourKey!==lastHour){const heading=el('p','dc-day dc-hour',hour);this.feed.insertBefore(heading,previous?previous.nextSibling:this.feed.firstChild);previous=heading;lastHour=hourKey;}
        if (node.previousSibling !== previous || node.parentNode !== this.feed) this.feed.insertBefore(node, previous ? previous.nextSibling : this.feed.firstChild);
        previous = node;
      }
      if (!this.rows.length) this.feed.append(el('p', 'dc-empty', 'Przywitaj się i rozpocznij rozmowę.'));
      const notice = closed ? 'Ticket został zamknięty' + (closedBy ? ' przez ' + closedBy : '') + (reason ? '\nPowód: ' + reason : '') : (!writable ? reason : '');
      if (this.system.textContent !== notice) this.system.textContent = notice;
      this.system.hidden = !notice; this.feed.append(this.system);
      for (const control of this.form.querySelectorAll('textarea,button,input')) control.disabled = !this.writable || this.sending;
      if(!this.blockNotice){this.blockNotice=el('div','dc-block-notice');this.blockNotice.setAttribute('role','status');this.form.before(this.blockNotice);}
      this.blockNotice.textContent=composerNotice;this.blockNotice.hidden=!composerNotice;this.form.hidden=Boolean(composerNotice);
      if(composerNotice){this.closePopover();this.clearFiles();this.setReply(null);}
      this.text.placeholder = closed ? 'Ticket został zamknięty' : writable ? 'Napisz wiadomość…' : 'Rozmowa tylko do odczytu';
      if (closed) { this.closePopover(); this.setReply(null); }
      if (this.reply && !this.rows.some(m => m.id === this.reply.id && !m.is_withdrawn)) this.setReply(null);
      this.updateReceipt();
      if (prepend) this.feed.scrollTop = top + this.feed.scrollHeight - height;
      else if (stick) this.scrollBottom();
      else { if(!this.scrollingLatest)this.feed.scrollTop = top; if (newMessage) this.newButton.hidden = false; }
    }
    message(m) {
      if(m.system){const notice=el('article','dc-message dc-system-event',m.body);notice.dataset.messageId=m.id;return notice;}
      const node = el('article', 'dc-message' + (m.mine ? ' mine' : '')); node.dataset.messageId = m.id;
      if (this.options.showAuthors !== false && !m.mine && (!m.reply || m.is_withdrawn)) node.append(el('div', 'dc-author', m.author_name || m.display_name || 'Użytkownik'));
      const line = el('div', 'dc-line'), bubble = el('div', 'dc-bubble');
      const avatar = el('span','dc-avatar',Array.from(m.author_name || m.display_name || m.username || '?')[0]);
      if (!m.mine && m.username) window.DNCTChat.avatar(avatar,m.username);
      if (m.edited_at && !m.is_withdrawn) bubble.append(el('small','dc-edited','Edytowano'));

      if (m.reply && !m.is_withdrawn) {
        const q = button('', () => { const target = this.nodes.get(Number(m.reply.id)); if (target) { target.scrollIntoView({block:'center', behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'}); target.classList.remove('dc-highlight'); void target.offsetWidth; target.classList.add('dc-highlight'); } else this.say('Ta wiadomość jest w starszej części rozmowy. Wczytaj starsze wiadomości.'); }, 'dc-quote');
        const original = this.rows.find(row => row.id === Number(m.reply.id));
        const recipient = original?.display_name || original?.author_name || m.reply.author_name || 'Użytkownik';
        const author = m.display_name || m.author_name || 'Użytkownik';
        const selfReply = m.reply.self ?? (original ? original.mine === m.mine : false);
        const replyLabel = m.mine
          ? (selfReply ? 'Odpowiedziałeś sobie' : 'Odpowiedziałeś ' + recipient)
          : author + ' odpowiedział(a) ' + (selfReply ? 'sobie' : (m.reply.mine ?? original?.mine) === false ? recipient : 'Ci');
        q.append(el('b', '', '↶ ' + replyLabel), el('span', '', m.reply.is_withdrawn || m.reply.withdrawn_at ? 'Wiadomość została cofnięta.' : m.reply.body || 'Załącznik')); node.append(q);
      }
      const body = el('p', 'dc-body' + (m.is_withdrawn ? ' dc-withdrawn' : ''));
      const content = m.is_withdrawn ? 'Wiadomość została cofnięta.' : m.body || '';
      const parts = content.split(/(https?:\/\/[^\s<>]+)/g);
      parts.forEach((part, i) => { if (i % 2) { const a = el('a', '', part); a.href = part; a.target = '_blank'; a.rel = 'noopener noreferrer'; body.append(a); } else body.append(document.createTextNode(part)); });
      bubble.append(body);
      if (!m.is_withdrawn && m.attachments?.length) {
        const media = el('div', 'dc-attachments');
        for (const a of m.attachments) {
          const url = this.mediaUrl(a.url); if (!url) continue; const video = (a.media_type || a.kind || a.mime_type || '').includes('video');
          const card = el('div', 'dc-media' + (video ? ' dc-media-video' : '')); card.tabIndex = 0; card.setAttribute('role','group');
          const openMedia = () => { if (video) preview.pause(); this.lightbox(a, video, video && !preview.ended ? preview.currentTime : 0,m); };
          card.onclick = e => { if (!e.target.closest('.dc-media-play,.dc-video-controls')) openMedia(); };
          card.onkeydown = e => { if (e.target === card && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openMedia(); } };
          const preview = el(video ? 'video' : 'img'); preview.src = url;
          if (video) { preview.muted = true; preview.playsInline = true; preview.preload = 'metadata'; } else { preview.loading = 'lazy'; preview.alt = a.original_name || 'Zdjęcie'; }
          preview.addEventListener('load', () => { if (this.nearBottom()) this.scrollBottom(); }, {once:true});
          card.append(preview); if(!video)preview.addEventListener('load',()=>{card.style.width=Math.min(280,preview.naturalWidth)+'px';},{once:true});
          if (video) {
            const play = button('▶', () => start(), 'dc-media-play'); play.setAttribute('aria-label','Odtwórz film');
            const duration = el('span','dc-video-duration','0:00');
            const controls = el('div','dc-video-controls'); controls.hidden = true; controls.addEventListener('click', e => e.stopPropagation());
            const toggle = button('', () => preview.paused ? start() : preview.pause(), 'dc-video-toggle'); toggle.setAttribute('aria-label','Wstrzymaj film');
            const seek = el('input','dc-video-seek'); seek.type = 'range'; seek.min = '0'; seek.max = '100'; seek.step = '.1'; seek.value = '0'; seek.setAttribute('aria-label','Pozycja odtwarzania');
            seek.oninput = () => { if (Number.isFinite(preview.duration) && preview.duration > 0) preview.currentTime = Number(seek.value) / 100 * preview.duration; };
            const sound = button('♪', () => { preview.muted = !preview.muted; sync(); }, 'dc-video-sound');
            const volumeWrap = el('div','dc-video-volume');
            const volumePanel = el('div','dc-volume-panel');
            const volume = el('input','dc-volume-slider'); volume.type = 'range'; volume.min = '0'; volume.max = '100'; volume.step = '1'; volume.value = '100'; volume.setAttribute('aria-label','Głośność filmu'); volume.setAttribute('aria-orientation','vertical');
            volume.oninput = () => { preview.volume = Number(volume.value)/100; preview.muted = preview.volume === 0; };
            volumePanel.append(volume); volumeWrap.append(sound,volumePanel);
            const stamp = seconds => { const n = Math.max(0,Math.floor(Number.isFinite(seconds) ? seconds : 0)); return Math.floor(n/60)+':'+String(n%60).padStart(2,'0'); };
            const sync = () => {
              const ended = preview.ended;
              play.hidden = !preview.paused; play.textContent = ended ? '↻' : '▶'; play.setAttribute('aria-label',ended ? 'Odtwórz ponownie' : 'Odtwórz film');
              controls.hidden = ended || (preview.paused && preview.currentTime === 0);
              duration.hidden = !controls.hidden; duration.textContent = stamp(preview.duration);
              toggle.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true">' + (preview.paused ? '<path d="m8 5 11 7-11 7Z"/>' : '<rect x="6" y="5" width="4" height="14" rx=".5"/><rect x="14" y="5" width="4" height="14" rx=".5"/>') + '</svg>'; toggle.setAttribute('aria-label',preview.paused ? 'Wznów film' : 'Wstrzymaj film');
              sound.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4Z"/>' + (preview.muted ? '<path d="m16 9 5 6m0-6-5 6"/>' : '<path d="M15 8a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/>') + '</svg>'; sound.setAttribute('aria-label',preview.muted ? 'Włącz dźwięk' : 'Wycisz film'); sound.setAttribute('aria-pressed',String(preview.muted));
              volume.value = String(preview.muted ? 0 : Math.round(preview.volume*100));
              volume.setAttribute('aria-valuetext',volume.value+'%');
              seek.value = String(preview.duration > 0 ? preview.currentTime / preview.duration * 100 : 0);
              volume.style.setProperty('--dc-progress',volume.value+'%');
              seek.style.setProperty('--dc-progress',seek.value+'%');
              card.classList.toggle('is-playing',!preview.paused);
            };
            const start = () => { if (preview.ended) preview.currentTime = 0; if (!card.dataset.started) { preview.muted = false; card.dataset.started = '1'; } preview.play().catch(() => this.say('Nie udało się odtworzyć filmu. Spróbuj otworzyć pełny podgląd.')); };
            for (const event of ['loadedmetadata','play','pause','ended','timeupdate','volumechange']) preview.addEventListener(event,sync);
            controls.append(toggle,seek,volumeWrap); card.append(play,duration,controls); sync();
          }
 card.setAttribute('aria-label', 'Podgląd: ' + (a.original_name || 'załącznik')); media.append(card);
        }
        bubble.classList.add('dc-has-media');if(!content)bubble.classList.add('dc-media-only');bubble.append(media);
      }
      if (!m.mine) line.append(avatar);
      line.append(bubble);
      if (this.actions && !m.is_withdrawn) {
        const actions = el('div', 'dc-actions');
        const react = icon('smile', 'Dodaj reakcję', () => this.emoji(react, emoji => this.act('react', {message_id:m.id, emoji})));
        const reply = icon('reply', 'Odpowiedz', () => this.setReply(m)); if (m.mine) reply.classList.add('dc-reply-self');
        actions.append(react, reply);
        if (m.mine) { const more = icon('more', 'Więcej opcji', () => this.menu(m, more)); actions.append(more); }
        line.append(actions);
      }
      node.append(line);
      this.syncReactions(node,m,false);
      const receipt=el('small','dc-time');receipt.hidden=true;node.append(receipt);node.title=time(m.created_at);
      return node;
    }
    syncReactions(node,m,animate=true){
      if(m.system)return;let chips=node.querySelector('.dc-chips');const reactions=m.is_withdrawn?[]:(m.reactions||[]);if(!chips){if(!reactions.length)return;chips=el('div','dc-chips');node.insertBefore(chips,node.querySelector('.dc-time'));}
      const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches,active=new Set(reactions.map(r=>r.emoji));
      for(const chip of chips.children){if(active.has(chip.dataset.emoji)||chip.dataset.leaving)continue;chip.dataset.leaving='1';chip.disabled=true;if(animate&&!reduced){const motion=chip.animate([{opacity:1,transform:'scale(1)'},{opacity:0,transform:'scale(.65)'}],{duration:180,fill:'forwards'});motion.onfinish=()=>chip.remove();}else chip.remove();}
      for(const r of reactions){let chip=Array.from(chips.children).find(c=>c.dataset.emoji===r.emoji&&!c.dataset.leaving);if(!chip){chip=button('',()=>this.act('react',{message_id:m.id,emoji:r.emoji}),'dc-chip');chip.dataset.emoji=r.emoji;chip.append(el('span','',r.emoji),el('span','dc-reaction-count',String(r.count)));chips.append(chip);if(animate&&!reduced)chip.animate([{opacity:0,transform:'scale(.6) translateY(4px)'},{opacity:1,transform:'scale(1) translateY(0)'}],{duration:220,easing:'cubic-bezier(.2,.8,.2,1)'});}
      const count=chip.querySelector('.dc-reaction-count');if(count.textContent!==String(r.count)){const up=Number(r.count)>Number(count.textContent);count.textContent=String(r.count);if(animate&&!reduced)count.animate([{opacity:0,transform:'translateY('+(up?8:-8)+'px)'},{opacity:1,transform:'translateY(0)'}],{duration:220,easing:'ease-out'});}
      chip.classList.toggle('is-mine',Boolean(r.mine));chip.disabled=!this.actions;chip.setAttribute('aria-pressed',String(Boolean(r.mine)));chip.setAttribute('aria-label',(r.mine?'Usuń reakcję ':'Reakcja ')+r.emoji+', '+r.count);}
    }
    async act(action, values) { return this.run(async () => { await this.options.action?.(action, values); }); }
    setReply(m) {
      this.reply = m; this.preview.replaceChildren(); this.preview.hidden = !m;
      if (m) { const copy = el('span', '', m.body || 'Załącznik'); copy.prepend(el('b', '', 'Odpowiadasz: ' + (m.mine ? 'sobie' : m.author_name || m.display_name || 'Użytkownik'))); const cancel = button('×', () => this.setReply(null), 'dc-icon'); cancel.setAttribute('aria-label', 'Anuluj odpowiedź'); this.preview.append(copy, cancel); this.text.focus(); }
    }
    chooseReply() {
      const candidates = this.rows.filter(m => !m.is_withdrawn).slice(-8).reverse();
      if (!candidates.length) { this.say('Nie ma jeszcze wiadomości, na którą można odpowiedzieć.'); return; }
      const menu = this.popover(this.replyButton, 'dc-menu');if(!menu)return;
      menu.append(el('p', 'dc-popover-note', 'Wybierz wiadomość'));
      for (const m of candidates) menu.append(button((m.mine ? 'Ty' : m.author_name || m.display_name || 'Użytkownik') + ': ' + (m.body || 'Załącznik').slice(0, 64), () => { this.closePopover(); this.setReply(m); }));
      this.positionPopover();
    }
    menu(m, anchor) {
      const menu = this.popover(anchor, 'dc-menu');if(!menu)return;
      if (this.canEdit(m)) menu.append(button('Edytuj wiadomość', () => { this.closePopover(); this.edit(m); }));
      menu.append(button('Cofnij wysłanie', () => { this.closePopover(); this.withdraw(m); }, 'dc-danger')); this.positionPopover();
    }
    canEdit(m) { return Date.now() < (Number(m.edit_expires_at)*1000 || date(m.created_at).getTime()+300000); }
    edit(m) {
      if (!this.canEdit(m)) { this.say("Minęło 5 minut — tej wiadomości nie można już edytować."); return; }
      if (this.editing) this.cancelEdit(); this.editing = m.id;
      const bubble = this.nodes.get(m.id)?.querySelector('.dc-bubble'); if (!bubble) return;
      const editor = el('textarea', 'dc-editor'); editor.value = m.body; editor.maxLength = this.options.maxLength || 4000; editor.setAttribute('aria-label', 'Edytowana wiadomość');
      const tools = el('div', 'dc-edit-tools'); const save = button('Zapisz', () => this.run(async () => { const value = editor.value.trim(); if (!this.canEdit(m)) { this.say('Minęło 5 minut — tej wiadomości nie można już edytować.'); this.cancelEdit(); return; } if (!value) { this.say('Wpisz treść wiadomości.'); return; } save.disabled = true; try { await this.options.action?.('edit', {message_id:m.id, message:value}); this.cancelEdit(); } finally { save.disabled = false; } }));
      tools.append(button('Anuluj', () => this.cancelEdit()), save); bubble.replaceChildren(editor, tools); editor.focus(); editor.setSelectionRange(editor.value.length, editor.value.length);
      editor.onkeydown = e => { if (e.key === 'Escape') { e.stopPropagation(); this.cancelEdit(); } if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); save.click(); } };
    }
    cancelEdit() { const id = this.editing; this.editing = null; const m = this.rows.find(m => m.id === id), old = this.nodes.get(id); if (m && old) { const next = this.message(m); next.dataset.signature = JSON.stringify(m) + ':' + this.actions; old.replaceWith(next); this.nodes.set(id, next); } }
    dialog(title) {
      const dialog = el('dialog', 'dc-dialog');
      const nativeClose = dialog.close.bind(dialog); let closing = false;
      dialog.close = () => { if (closing) return; closing = true; dialog.querySelectorAll('video').forEach(v => v.pause()); if (matchMedia('(prefers-reduced-motion: reduce)').matches) { nativeClose(); return; } dialog.classList.add('is-closing'); setTimeout(nativeClose,180); };
      dialog.addEventListener('cancel', e => { e.preventDefault(); dialog.close(); }); const close = button('×', () => dialog.close(), 'dc-dialog-close'); close.setAttribute('aria-label', 'Zamknij'); dialog.append(el('h3', '', title), close);
      (this.root.closest('dialog') || document.body).append(dialog);
      dialog.addEventListener('click', e => { if (e.target === dialog) { const r = dialog.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close(); } });
      dialog.addEventListener('close', () => { dialog.querySelectorAll('video').forEach(v => v.pause()); dialog.remove(); }, {once:true});
      return dialog;
    }
    withdraw(m) {
      const dialog = this.dialog('Cofnąć wysłanie wiadomości?');
      dialog.append(el('p', '', 'Wybierz, kto przestanie widzieć tę wiadomość.'));
      for (const [value, label] of [['everyone','Cofnij wysłanie dla wszystkich'],['me','Ukryj tylko dla mnie']]) { const row = el('label'); const radio = el('input'); radio.type = 'radio'; radio.name = 'withdraw'; radio.value = value; radio.checked = value === 'everyone'; row.append(radio, document.createTextNode(label)); dialog.append(row); }
      const error = el('p', 'dc-error'); const actions = el('div', 'dc-dialog-actions');
      const confirm = button('Cofnij wysłanie', async () => { confirm.disabled = true; try { const scope = dialog.querySelector('input:checked').value; await this.options.action?.('withdraw', {message_id:m.id, scope}); dialog.close(); } catch(e) { error.textContent = e.message; } finally { confirm.disabled = false; } }, 'dc-danger');
      actions.append(button('Anuluj', () => dialog.close()), confirm); dialog.append(error, actions); dialog.showModal();
    }
    closePopover() { if(this.popup){const p=this.popup;this.popup=null;this.closingPopup?.remove();this.closingPopup=p;p.style.pointerEvents='none';if(matchMedia('(prefers-reduced-motion: reduce)').matches)p.remove();else{const a=p.animate([{opacity:1,transform:'translateY(0)'},{opacity:0,transform:'translateY(5px)'}],{duration:140,easing:'ease-in',fill:'forwards'});a.onfinish=()=>p.remove();}}this.popAbort?.abort();this.anchor?.setAttribute('aria-expanded','false'); }
    popover(anchor, className = '') {
      if(this.popup && this.anchor===anchor){this.closePopover();return null;}
      this.closePopover();this.closingPopup?.remove();this.closingPopup=null; this.anchor = anchor; anchor.setAttribute('aria-expanded','true');
      const p = el('div', 'dc-popover ' + className); p.dataset.lenisPrevent = ''; p.setAttribute('popover','manual'); p.setAttribute('role','dialog'); p.setAttribute('aria-label', className === 'dc-menu' ? 'Opcje wiadomości' : 'Emoji'); this.popup = p;
      (anchor.closest('dialog') || this.root.closest('dialog') || document.body).append(p); p.style.visibility = 'hidden'; p.showPopover(); requestAnimationFrame(() => { if (this.popup === p) { this.positionPopover(); p.style.visibility = ''; } }); this.popAbort = new AbortController(); const signal = this.popAbort.signal;
      document.addEventListener('pointerdown', e => { if (!p.contains(e.target) && !anchor.contains(e.target)) this.closePopover(); }, {signal});
      document.addEventListener('keydown', e => { if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); this.closePopover(); anchor.focus(); } }, {signal, capture:true});
      window.addEventListener('resize', () => this.closePopover(), {signal}); if(this.feed.contains(anchor))this.feed.addEventListener('scroll', () => this.closePopover(), {signal, passive:true});
      this.positionPopover(); return p;
    }
    positionPopover() { if (!this.popup || !this.anchor?.isConnected) return; const r = this.anchor.getBoundingClientRect(), p = this.popup; const left = Math.max(12, Math.min(innerWidth - p.offsetWidth - 12, r.right - p.offsetWidth)); const above = r.top - p.offsetHeight - 8; p.style.left = left + 'px'; p.style.top = Math.max(12, Math.min(innerHeight - p.offsetHeight - 12, above > 12 ? above : r.bottom + 8)) + 'px'; }
    emoji(anchor, choose, full = false) {
      const pop = this.popover(anchor);if(!pop)return; const quick = el('div', 'dc-emoji-quick'); let custom = false, loaded = null;
      const pick = value => { if (custom) { const f = favourites(); const i = f.indexOf(value), slot = Number(select.value); if (i === slot) return; if (i >= 0) f[i] = f[slot]; f[slot] = value; memoryFavourites = f; try { localStorage.setItem('dnct-chat-quick-reactions', JSON.stringify(f)); } catch {} drawQuick(); render(search.value); return; } this.closePopover(); this.run(() => choose(value)); };
      const drawQuick = () => { quick.replaceChildren(); for (const value of favourites()) { const b = button(value, () => pick(value)); b.setAttribute('aria-label','Emoji ' + value); quick.append(b); } const more = button('+', expand, 'dc-emoji-more'); more.setAttribute('aria-label','Więcej emoji'); quick.append(more); };
      const search = el('input', 'dc-emoji-search'); search.type = 'search'; search.placeholder = 'Wyszukaj emoji'; search.setAttribute('aria-label','Wyszukaj emoji');
      const grid = el('div', 'dc-emoji-grid'); grid.dataset.lenisPrevent = ''; grid.tabIndex = 0; grid.setAttribute('aria-label','Lista emoji'); const note = el('p', 'dc-popover-note'); const select = el('select', 'dc-emoji-search'); select.setAttribute('aria-label','Miejsce ulubionej reakcji'); select.hidden = true;
      for (let i = 0; i < 6; i++) { const o = el('option', '', 'Zastąp reakcję ' + (i + 1)); o.value = i; select.append(o); }
      let matches = [], shown = 0;
      const appendEmoji = () => { const batch = matches.slice(shown, shown + 168); shown += batch.length; grid.append(...batch.map(([value,name]) => { const b = button(value, () => pick(value)); b.title = name; b.setAttribute('aria-label',name); if (custom) b.setAttribute('aria-pressed',String(favourites().includes(value))); return b; })); };
      grid.addEventListener('scroll', () => { if (grid.scrollTop + grid.clientHeight >= grid.scrollHeight - 70 && shown < matches.length) appendEmoji(); }, {passive:true});
      const render = query => { if (!loaded) return; const terms = normal(query).trim().split(/\s+/).filter(Boolean); matches = loaded.filter(e => !terms.length || terms.every(term => normal(e[1]).includes(term)) || e[0].includes(query)); shown = 0; grid.replaceChildren(); grid.scrollTop = 0; appendEmoji(); note.textContent = matches.length ? (custom ? 'Wybierz miejsce, a potem nowe emoji.' : matches.length + ' emoji') : 'Brak wyników. Spróbuj innej nazwy.'; this.positionPopover(); };
      const customize = button('Dostosuj 6 szybkich reakcji', () => { custom = !custom; select.hidden = !custom; customize.textContent = custom ? 'Gotowe — wróć do wyboru' : 'Dostosuj 6 szybkich reakcji'; render(search.value); }, 'dc-emoji-customize');
      const expand = async () => {
        if (pop.contains(search)) { search.focus(); return; }
        pop.classList.add('dc-emoji-full'); pop.append(search, select, grid, note, customize); note.textContent = 'Wczytywanie emoji…'; this.positionPopover(); search.focus({preventScroll:true});
        try { loaded = await catalogue(); render(search.value); } catch(e) { note.textContent = e.message; }
      };
      search.oninput = () => render(search.value); drawQuick(); pop.append(quick); this.positionPopover(); if (full) expand();
    }
    mediaUrl(value) { try { const url = new URL(value, location.href); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } }
    lightbox(attachment, video, startAt = 0, message = {}) {
      const name=message.display_name || message.author_name || message.username || 'Użytkownik';
      const d=this.dialog(name);d.classList.add('dc-lightbox');d.querySelector('h3').remove();
      const header=el('header','dc-media-sender'),picture=el('span','dc-avatar',Array.from(name)[0]),copy=el('div');
      window.DNCTChat.avatar(picture,message.username);copy.append(el('strong','',name));
      const sent=date(message.created_at),today=new Date(),yesterday=new Date();yesterday.setDate(today.getDate()-1);
      const day=sent.toDateString()===today.toDateString()?'Dzisiaj':sent.toDateString()===yesterday.toDateString()?'Wczoraj':sent.toLocaleDateString('pl-PL');
      copy.append(el('small','',Number.isNaN(sent.getTime())?'':day+' · '+sent.toLocaleTimeString('pl-PL',{hour:'2-digit',minute:'2-digit'})));header.append(picture,copy);d.append(header);
      const viewport=el('div','dc-media-viewport'),media=el(video?'video':'img');media.src=this.mediaUrl(attachment.url);viewport.append(media);d.append(viewport);
      let fittedWidth=0,fittedHeight=0,zoom=false,zoomFrame=0;
      const fit=()=>{cancelAnimationFrame(zoomFrame);const w=video?media.videoWidth:media.naturalWidth,h=video?media.videoHeight:media.naturalHeight;if(!w||!h)return;const preferred=video?1:Math.max(1,Math.min(640/w,480/h));const scale=Math.min(preferred,(innerWidth-60)/w,(innerHeight-130)/h);fittedWidth=w*scale;fittedHeight=h*scale;d.style.width=Math.max(240,fittedWidth+28)+'px';viewport.style.width=fittedWidth+'px';viewport.style.height=fittedHeight+'px';zoom=false;media.classList.remove('is-zoomed');media.style.width=fittedWidth+'px';media.style.height=fittedHeight+'px';media.setAttribute('aria-pressed','false');};
      if(video){media.controls=true;media.playsInline=true;media.addEventListener('loadedmetadata',()=>{fit();if(startAt>0&&startAt<media.duration)media.currentTime=startAt;},{once:true});}
      else {media.alt=attachment.original_name || 'Zdjęcie';media.tabIndex=0;media.setAttribute('role','button');media.setAttribute('aria-label','Powiększ zdjęcie');media.onload=fit;
        const toggle=(x,y)=>{if(!fittedWidth)return;cancelAnimationFrame(zoomFrame);zoom=!zoom;
          const startWidth=parseFloat(media.style.width),startHeight=parseFloat(media.style.height),left=viewport.scrollLeft,top=viewport.scrollTop;
          const scale=zoom?1.75:1,endWidth=fittedWidth*scale,endHeight=fittedHeight*scale;
          const endLeft=zoom?Math.max(0,Math.min(endWidth-viewport.clientWidth,x*endWidth-viewport.clientWidth/2)):0;
          const endTop=zoom?Math.max(0,Math.min(endHeight-viewport.clientHeight,y*endHeight-viewport.clientHeight/2)):0;
          media.classList.toggle('is-zoomed',zoom);media.setAttribute('aria-label',zoom?'Pomniejsz zdjęcie':'Powiększ zdjęcie');media.setAttribute('aria-pressed',String(zoom));
          const began=performance.now(),duration=matchMedia('(prefers-reduced-motion: reduce)').matches?0:320;
          const step=now=>{const t=duration?Math.min(1,(now-began)/duration):1,ease=t*t*(3-2*t);media.style.width=(startWidth+(endWidth-startWidth)*ease)+'px';media.style.height=(startHeight+(endHeight-startHeight)*ease)+'px';viewport.scrollLeft=left+(endLeft-left)*ease;viewport.scrollTop=top+(endTop-top)*ease;if(t<1)zoomFrame=requestAnimationFrame(step);};zoomFrame=requestAnimationFrame(step);
        };
        let pan=null,suppressClick=false;media.draggable=false;viewport.addEventListener('pointerdown',e=>{if(!zoom||e.button!==0)return;cancelAnimationFrame(zoomFrame);pan={x:e.clientX,y:e.clientY,left:viewport.scrollLeft,top:viewport.scrollTop,moved:false};viewport.setPointerCapture(e.pointerId);viewport.classList.add('is-panning');e.preventDefault();});viewport.addEventListener('pointermove',e=>{if(!pan)return;const dx=e.clientX-pan.x,dy=e.clientY-pan.y;if(Math.abs(dx)+Math.abs(dy)>4)pan.moved=true;viewport.scrollLeft=pan.left-dx;viewport.scrollTop=pan.top-dy;});const endPan=()=>{if(pan)suppressClick=pan.moved;pan=null;viewport.classList.remove('is-panning');};viewport.addEventListener('pointerup',endPan);viewport.addEventListener('pointercancel',endPan);
        viewport.onclick=e=>{if(suppressClick){suppressClick=false;return;}const r=media.getBoundingClientRect();toggle((e.clientX-r.left)/r.width,(e.clientY-r.top)/r.height);};media.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle(.5,.5);}};
      }
      window.addEventListener('resize',fit);d.addEventListener('close',()=>{cancelAnimationFrame(zoomFrame);window.removeEventListener('resize',fit);},{once:true});d.showModal();fit();if(video)media.play().catch(()=>{});
    }
    addFiles(files) {
      if (!this.writable || this.sending) return;
      for (const file of Array.from(files)) { const video = file.type.startsWith('video/'); if (!/^(image|video)\//.test(file.type)) { this.say('Możesz wysłać zdjęcie albo film.'); continue; } if (this.files.length >= this.limits.files) { this.say('Do jednej wiadomości możesz dodać ' + this.limits.files + ' pliki.'); break; } if (file.size > (video ? this.limits.video : this.limits.image)) { this.say('Plik ' + file.name + ' jest za duży. Limit: ' + ((video ? this.limits.video : this.limits.image)/1024/1024) + ' MB.'); continue; } this.files.push({file, url:URL.createObjectURL(file)}); }
      this.renderFiles();
    }
    renderFiles() { this.previews.replaceChildren(); this.previews.hidden = !this.files.length; this.files.forEach((f, i) => { const card = el('div', 'dc-preview'), media = el(f.file.type.startsWith('video/') ? 'video' : 'img'); media.src = f.url; media.muted = true; const remove = button('×', () => { URL.revokeObjectURL(f.url); this.files.splice(i, 1); this.renderFiles(); }); remove.setAttribute('aria-label','Usuń załącznik ' + f.file.name); card.append(media, el('small','', f.file.name), remove); this.previews.append(card); }); }
    clearFiles() { this.files.forEach(f => URL.revokeObjectURL(f.url)); this.files = []; this.renderFiles(); }
    async send() {
      if (!this.writable || this.sending) return; if (Date.now() < (this.cooldownUntil || 0)) { this.say('Wysyłasz wiadomości zbyt szybko. Odczekaj ' + Math.ceil((this.cooldownUntil - Date.now()) / 1000) + ' s.'); return; } const body = this.text.value.trim(); if (!body && !this.files.length) { this.text.focus(); return; }
      const signature = JSON.stringify([body, this.reply?.id || 0, this.files.map(f => [f.file.name, f.file.size, f.file.lastModified])]); if (this.attempt?.signature !== signature) this.attempt = {signature, key:key()};
      this.sending = true; this.closePopover(); this.say(); for (const control of this.form.querySelectorAll('textarea,button,input')) control.disabled = true; this.progress.hidden = !this.files.length; this.progress.value = 0;
      try { await this.options.send?.({message:body, reply_to_id:this.reply?.id || 0, key:this.attempt.key}, this.files.map(f => f.file), value => { this.progress.value = value; }); this.attempt = null; this.setDraft(''); this.setReply(null); this.clearFiles(); this.scrollBottom(); }
      catch(e) { if (e.status === 429) this.cooldownUntil = Date.now() + Math.max(3, e.retryAfter || 3) * 1000; this.say(e.message); this.options.failure?.(e); }
      finally { this.sending = false; this.progress.hidden = true; for (const control of this.form.querySelectorAll('textarea,button,input')) control.disabled = !this.writable; if (this.writable) this.text.focus(); }
    }
    destroy() { this.feedResize?.disconnect();cancelAnimationFrame(this.scrollFrame);clearInterval(this.receiptTimer);this.closePopover(); this.clearFiles(); this.root.remove(); }
  }
  const avatarImages = new Map();
  function avatar(target, username, className = '') {
    if (!username) return;
    target.dataset.avatarUser = username;
    let entry = avatarImages.get(username);
    if (!entry) {
      const img = new Image(); img.referrerPolicy = 'no-referrer'; img.alt = '';
      entry = {img,ready:false};
      entry.promise = new Promise(resolve => { img.onload = async () => { try { await img.decode(); } catch {} entry.ready=true;resolve(true); }; img.onerror = () => { avatarImages.delete(username);resolve(false); }; });
      avatarImages.set(username,entry); img.src='https://account.dnct.pl/avatar/'+encodeURIComponent(username);
    }
    const attach = () => {
      if (target.dataset.avatarUser !== username) return;
      const img=entry.img.cloneNode();img.className=className;
      target.querySelectorAll('img').forEach(old=>old.remove());target.append(img);
    };
    if(entry.ready)attach();else entry.promise.then(ok=>{if(ok)attach();});
  }
  window.DNCTChat = {Conversation, request, key, el, button, icon, avatar, age};
})();

/* Shared message indicators and delivery effects. No private content is persisted. */
(() => {
 'use strict';
 if(window.DNCTAlerts || !['game.dnct.pl','dnct.pl','beta.dnct.pl'].includes(location.hostname))return;
 let config={};try{config=JSON.parse(document.getElementById('dnct-support-config')?.textContent||'{}');}catch{}
 const scope='dnct:alerts:'+String(config.name||'guest');
 const read=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(scope+key))??fallback;}catch{return fallback;}};
 const write=(key,value)=>{try{localStorage.setItem(scope+key,JSON.stringify(value));}catch{}};
 const style=document.createElement('style');style.textContent=`
 .dnct-unread-counter{position:absolute!important;right:-5px!important;top:-5px!important;left:auto!important;bottom:auto!important;min-width:21px;height:21px;padding:0 5px;box-sizing:border-box;display:grid;place-items:center;border-radius:99px;background:#ef4444;color:#fff;font:700 11px/1 system-ui;border:2px solid #21162f;pointer-events:none;z-index:5;opacity:0;transform:scale(.3);transform-origin:center;transition:opacity .24s,transform .3s cubic-bezier(.2,.8,.2,1)}
 .dnct-unread-counter.is-visible{opacity:1;transform:scale(1);animation:dnct-count-pulse 2.4s ease-in-out infinite}
 [data-dm-support],[data-dm-support-mobile]{position:relative!important;overflow:visible!important}.dnct-ticket-dot{position:absolute;right:2px;top:2px;width:12px;height:12px;border:2px solid #21162f;border-radius:50%;background:#ef4444;pointer-events:none;opacity:0;transform:scale(0);transition:opacity .24s,transform .3s}.dnct-ticket-dot.is-visible{opacity:1;transform:scale(1)}
 .dnct-desktop-permission{border:1px solid #a78bfa33;background:#a78bfa0d;color:#c4b5fd;border-radius:8px;padding:7px 10px;font:11px system-ui;cursor:pointer;margin:5px 0}.dnct-desktop-permission:hover{background:#a78bfa23}.dnct-desktop-permission:disabled{opacity:.6;cursor:default}
 @keyframes dnct-count-pulse{0%,100%{box-shadow:0 0 0 0 #ef444400}50%{box-shadow:0 0 0 5px #ef444425}}
 @media(prefers-reduced-motion:reduce){.dnct-unread-counter,.dnct-ticket-dot{animation:none!important;transition:none!important}}

 [data-dm-support]::after,[data-dm-support-mobile]::after{content:'';position:absolute;inset:-2px;border-radius:inherit;pointer-events:none;border:1px solid #f87171;background:#ef444422;box-shadow:0 0 14px #ef444444;opacity:0;transition:opacity .9s ease;animation:dnct-ticket-pulse 2.8s ease-in-out infinite}
 [data-dm-support].dnct-ticket-unread::after,[data-dm-support-mobile].dnct-ticket-unread::after{opacity:1}
 @keyframes dnct-ticket-pulse{0%,100%{background:#ef44440a;box-shadow:0 0 3px #ef444422}50%{background:#ef44443a;box-shadow:0 0 16px #ef444466}}
 @media(prefers-reduced-motion:reduce){[data-dm-support]::after,[data-dm-support-mobile]::after{animation:none}}
 `;document.head.append(style);
 let chatCount=0,ticketCount=0,ctx=null,notificationBaseline=null,pending=[],lastAudio=0;
 const acknowledged=new Set();const seenMessages=new Set();
 function enabled(){try{return localStorage.getItem('dnct_sound_enabled')!=='0' && (!window.DnctSound || DnctSound.isEnabled());}catch{return true;}}
 function unlock(){const Audio=window.AudioContext||window.webkitAudioContext;if(!Audio)return;try{
   // The home page may have already received a gesture before this async asset loaded.
   const shared=window.DnctSound?.ctx;
   if(shared&&shared.state!=='closed')ctx=shared;
   if(!ctx||ctx.state==='closed')ctx=new Audio();
   if(ctx.state==='suspended'||ctx.state==='interrupted')ctx.resume().catch(()=>{});
 }catch{}}
 document.addEventListener('pointerdown',unlock,{passive:true,capture:true});document.addEventListener('keydown',unlock,{capture:true});
 document.addEventListener('dnct-sound-toggled',e=>{if(e.detail?.enabled)unlock();});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&navigator.userActivation?.hasBeenActive)unlock();});
 if(window.DnctSound?.ctx?.state==='running'||navigator.userActivation?.hasBeenActive)unlock();
 const recipes={info:[[0,740,.13]],success:[[0,620,.15],[.15,930,.22]],warning:[[0,420,.22],[.28,420,.24]],error:[[0,880,.18],[.2,660,.18],[.4,880,.3]],system:[[0,520,.1],[.09,780,.12],[.19,650,.2]],message:[[0,660,.13],[.13,880,.18]]};
 function play(type){if(enabled()&&(!ctx||ctx.state!=='running')&&(window.DnctSound?.ctx?.state==='running'||navigator.userActivation?.hasBeenActive))unlock();if(!enabled()||!ctx||ctx.state!=='running'||Date.now()-lastAudio<700)return false;lastAudio=Date.now();document.dispatchEvent(new Event('dnct:notification-sound'));for(const [delay,freq,duration] of recipes[type]||recipes.system){const o=ctx.createOscillator(),g=ctx.createGain(),start=ctx.currentTime+delay;o.type=type==='error'?'triangle':'sine';o.frequency.value=freq;g.gain.setValueAtTime(.0001,start);g.gain.exponentialRampToValueAtTime(.08,start+.015);g.gain.exponentialRampToValueAtTime(.0001,start+duration);o.connect(g);g.connect(ctx.destination);o.start(start);o.stop(start+duration+.03);o.onended=()=>{o.disconnect();g.disconnect();};}return true;}
 async function exclusive(fn){if(navigator.locks)return navigator.locks.request(scope,{mode:'exclusive'},fn);return fn();}
 function badges(){const trigger=document.getElementById('dnct-support-chat-btn');if(trigger){let badge=trigger.querySelector('.dnct-unread-counter');if(!badge){badge=document.createElement('span');badge.className='dm-unread-dot dnct-unread-counter';badge.setAttribute('aria-hidden','true');trigger.append(badge);}const count=chatCount+ticketCount;badge.textContent=count>99?'99+':String(count);badge.classList.toggle('is-visible',count>0);trigger.classList.toggle('has-unread',count>0);trigger.setAttribute('aria-label',count?'Czat: '+count+' nieprzeczytanych wiadomości':'Otwórz czat');}
 document.querySelectorAll('[data-dm-support],[data-dm-support-mobile]').forEach(button=>{button.querySelector('.dnct-ticket-dot')?.remove();button.classList.toggle('dnct-ticket-unread',ticketCount>0);button.title=ticketCount?'Zgłoś problem — nowa odpowiedź w zgłoszeniu':'Zgłoś problem';});}

 function permissionButton(){const host=document.querySelector('#dnct-messenger .dm-connection');if(!host||document.querySelector('.dnct-desktop-permission'))return;const b=document.createElement('button');b.type='button';b.className='dnct-desktop-permission';
 const sync=()=>{const supported='Notification' in window,on=supported&&Notification.permission==='granted'&&read(':desktopEnabled',true);b.textContent=!supported?'Powiadomienia systemowe niedostępne':on?'Powiadomienia systemowe: ON':'Powiadomienia systemowe: OFF';b.setAttribute('aria-pressed',String(Boolean(on)));b.disabled=!supported;b.title=supported&&Notification.permission==='denied'?'Odblokuj powiadomienia w ustawieniach przeglądarki':'';};
 b.onclick=async()=>{if(Notification.permission==='granted')write(':desktopEnabled',!read(':desktopEnabled',true));else if(Notification.permission==='default'){try{const result=await Notification.requestPermission();write(':desktopEnabled',result==='granted');}catch{}}else b.title='Odblokuj powiadomienia w ustawieniach przeglądarki';sync();};host.after(b);sync();addEventListener('storage',sync);}

 async function message(key,name,body,open,muted=false,isViewing=()=>false){if(muted||seenMessages.has(key))return;seenMessages.add(key);await exclusive(()=>{const seen=read(':seen',{}),now=Date.now();if(seen[key])return;for(const id in seen)if(now-seen[id]>86400000)delete seen[id];seen[key]=now;write(':seen',seen);play('message');if(!document.hidden&&isViewing())return;if(!('Notification' in window)||Notification.permission!=='granted'||read(':desktopEnabled',true)===false)return;try{const n=new Notification(key.startsWith('ticket:')?'Zgłoszenie | DNCT.PL':'Czaty | DNCT.PL',{body:String(name||'Użytkownik')+'\n'+String(body||'Załącznik'),icon:document.querySelector('link[rel~="icon"]')?.href||new URL('/'+'assets/media/icons/favicon.png',location.origin).href,tag:scope+':'+key,silent:true});n.onclick=()=>{window.focus();open?.();n.close();};}catch{}});}
 const notificationKey=n=>String(n.audience||n.target_type||'normal')+':'+n.id;
 const kind=n=>(n.author||n.category==='announcements'||n.category==='announcement'||n.category==='announcements_marketing')?(recipes[n.type]?n.type:'info'):'system';
 async function soundFor(items,reminder=false){if(!items.length)return;await exclusive(()=>{const ack=read(':opened',{});items=items.filter(n=>!ack[notificationKey(n)]);if(!items.length||Date.now()-read(':soundAt',0)<(reminder?30000:2500))return;const types=[...new Set(items.map(kind))];if(play(types[Math.floor(Math.random()*types.length)]))write(':soundAt',Date.now());});}
 function notifications(items){const unread=items.filter(n=>!n.is_read);const newest=Math.max(0,...items.map(n=>Number(n.id)||0));const fresh=notificationBaseline===null?[]:unread.filter(n=>Number(n.id)>notificationBaseline);notificationBaseline=Math.max(notificationBaseline||0,newest);pending=unread.filter(n=>!acknowledged.has(notificationKey(n)));if(fresh.length)soundFor(fresh);}
 function opened(items){const ack=read(':opened',{}),now=Date.now();for(const key in ack)if(now-ack[key]>2592000000)delete ack[key];for(const n of items){acknowledged.add(notificationKey(n));ack[notificationKey(n)]=now;}write(':opened',ack);pending=pending.filter(n=>!acknowledged.has(notificationKey(n)));}
 const reminder=setInterval(()=>{if(!document.hidden&&pending.length&&Date.now()-read(':soundAt',0)>=30000)soundFor(pending,true);},1000);

 window.DNCTAlerts={message,notifications,opened,chat:n=>{chatCount=Math.max(0,Number(n)||0);badges();permissionButton();},ticket:n=>{ticketCount=Math.max(0,Number(n)||0);badges();},play};
})();
