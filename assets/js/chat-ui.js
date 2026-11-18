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
  // Linki zamiast gołego adresu: prośba o opinię DNCT w Google i płatność Stripe. Karta tylko dla
  // linków od zespołu (share.google może prowadzić gdziekolwiek, a obcy "przycisk płatności" byłby
  // gotowym oszustwem); stały link opinii DNCT (g.page) - od każdego. Kropka w rogu wraca do linku.
  const OPINIE = {nazwa: 'DNCT Hosting', ocena: '4,9', rodzaj: 'Hosting internetowy', gpage: /^\/r\/CRwe2fxVy-gHEAE(\/|$)/};
  function richLink(href, m) {
    let u; try { u = new URL(href); } catch { return null; }
    const host = u.hostname.replace(/^www\./, ''), staff = Boolean(m.author_admin || m.author_role === 'admin');
    if ((host === 'g.page' && OPINIE.gpage.test(u.pathname)) || (staff && (host === 'share.google' || host === 'g.page'))) return 'opinia';
    if (staff && ((host === 'checkout.stripe.com' && u.pathname.startsWith('/c/pay/')) || host === 'buy.stripe.com')) return 'stripe';
    return null;
  }
  // Kwota z treści wiadomości ("49,99 zł", "1 200 PLN") - sam link Stripe jej nie zdradza.
  function richAmount(text) {
    // Bez \b: w JS "ł" nie jest literą dla \b, więc granicę po "zł" sprawdza lookahead z \p{L}.
    const m = /(\d{1,3}(?:[ \u00a0.]\d{3})+|\d+)(?:[.,](\d{1,2}))?\s*(zł|zl|pln)(?!\p{L})/iu.exec(text || '');
    if (!m) return '';
    const zl = m[1].replace(/[ \u00a0.]/g, ''), gr = (m[2] || '').padEnd(2, '0');
    return Number(zl).toLocaleString('pl-PL') + (gr && gr !== '00' ? ',' + gr : '') + ' zł';
  }
  function richMessage(bubble, body, m) {
    const link = [...body.querySelectorAll('a[href]')].find(a => richLink(a.href, m));
    if (!link) return;
    const kind = richLink(link.href, m), href = link.href, rich = body.cloneNode(true);
    const twin = [...rich.querySelectorAll('a[href]')].find(a => a.href === href);
    let parent = twin.parentNode; twin.remove();
    while (parent && parent !== rich && !parent.textContent.trim()) { const up = parent.parentNode; parent.remove(); parent = up; }
    const walker = document.createTreeWalker(rich, NodeFilter.SHOW_TEXT), texts = []; while (walker.nextNode()) texts.push(walker.currentNode);
    const last = texts.reverse().find(t => t.textContent.trim()); if (last) last.textContent = last.textContent.replace(/[\s:–-]+$/, '');
    rich.classList.add('dc-rich-text'); if (!rich.textContent.trim()) rich.hidden = true;
    const card = el('div', 'dc-rich dc-rich-' + kind);
    if (kind === 'opinia') {
      const a = el('a', 'dc-rich-link'); a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer'; a.setAttribute('aria-label', 'Wystaw opinię: ' + OPINIE.nazwa);
      a.append(el('strong', '', OPINIE.nazwa), el('span', '', OPINIE.ocena + ' ★ · ' + OPINIE.rodzaj));
      card.append(a); if (rich.hidden) { rich.hidden = false; rich.textContent = 'Zostaw opinię o DNCT w Google'; }
    } else {
      const kwota = richAmount(rich.textContent), a = el('a', 'dc-rich-pay', kwota ? 'Zapłać ' + kwota : 'Przejdź do płatności');
      a.href = href; a.target = '_blank'; a.rel = 'noopener noreferrer';
      card.append(a, el('small', '', 'Bezpieczna płatność · Stripe'));
    }
    const dot = button('', () => {
      const raw = bubble.classList.toggle('is-raw'); dot.setAttribute('aria-pressed', String(raw));
      const tip = raw ? 'Ukryj link' : 'Pokaż link'; dot.dataset.tip = tip; dot.setAttribute('aria-label', tip);
    }, 'dc-link-dot');
    dot.dataset.tip = 'Pokaż link'; dot.setAttribute('aria-label', 'Pokaż link'); dot.setAttribute('aria-pressed', 'false');
    body.classList.add('dc-raw-body'); body.after(rich, card); bubble.prepend(dot); bubble.classList.add('dc-rich-bubble', 'is-' + kind);
  }
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
  // Discord-style Markdown. The stored message stays plain text; it is turned into DOM nodes here
  // (never innerHTML), so no markup typed by a user can ever execute.
  const markdown = (() => {
    const MAX_DEPTH = 6, LIMIT_NOTE = '[spoiler]';
    const text = s => document.createTextNode(s);
    const wrap = (tag, className, kids) => { const n = el(tag, className); n.append(...kids); return n; };
    const isWord = c => !!c && /[\p{L}\p{N}]/u.test(c);
    const space = c => c === undefined || /\s/.test(c);
    const DELIMS = [['***', 'bi'], ['**', 'b'], ['__', 'u'], ['~~', 's'], ['||', 'spoiler'], ['*', 'i'], ['_', 'i']];
    const urlRe = /https?:\/\/[^\s<>]+/y;

    function findClose(src, i, d) {
      const n = d.length, from = i + n, after = src[from], before = src[i - 1];
      if (from >= src.length) return -1;
      if (d === '*' && (space(after) || after === '*')) return -1;
      if (d === '_' && (isWord(before) || space(after) || after === '_')) return -1;
      for (let j = src.indexOf(d, from); j !== -1; j = src.indexOf(d, j + 1)) {
        if (j === from) continue;
        const prev = src[j - 1], next = src[j + n];
        if (prev === '\\') continue;
        if (d === '*' && (prev === '*' || next === '*' || space(prev))) continue;
        if (d === '_' && (prev === '_' || next === '_' || space(prev) || isWord(next))) continue;
        return j;
      }
      return -1;
    }

    function build(kind, kids, opts) {
      if (kind === 'b') return wrap('strong', '', kids);
      if (kind === 'i') return wrap('em', '', kids);
      if (kind === 'bi') return wrap('strong', '', [wrap('em', '', kids)]);
      if (kind === 'u') return wrap('u', 'dc-md-u', kids);
      if (kind === 's') return wrap('s', '', kids);
      if (opts.preview) return text(LIMIT_NOTE);
      const spoiler = wrap('span', 'dc-md-spoiler', kids);
      spoiler.tabIndex = 0; spoiler.setAttribute('role', 'button'); spoiler.setAttribute('aria-label', 'Spoiler. Naciśnij, aby odsłonić.');
      const reveal = () => { if (spoiler.classList.contains('is-revealed')) return; spoiler.classList.add('is-revealed'); spoiler.removeAttribute('role'); spoiler.removeAttribute('aria-label'); spoiler.removeAttribute('tabindex'); };
      spoiler.addEventListener('click', reveal);
      spoiler.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); reveal(); } });
      return spoiler;
    }

    function inline(src, depth, opts) {
      const out = []; let buf = '';
      const flush = () => { if (buf) { out.push(text(buf)); buf = ''; } };
      for (let i = 0; i < src.length;) {
        const c = src[i];
        if (c === '\\' && i + 1 < src.length && /[\\`*_~|>#\-\[\]()]/.test(src[i + 1])) { buf += src[i + 1]; i += 2; continue; }
        if (c === '`') {
          let k = 1; while (src[i + k] === '`') k++;
          const fence = '`'.repeat(k); let found = -1;
          for (let j = src.indexOf(fence, i + k); j !== -1; j = src.indexOf(fence, j + 1)) { if (src[j - 1] !== '`' && src[j + k] !== '`') { found = j; break; } }
          if (found > -1 && found > i + k) {
            flush(); let code = src.slice(i + k, found);
            if (code.length > 2 && code[0] === ' ' && code.at(-1) === ' ') code = code.slice(1, -1);
            out.push(el('code', 'dc-md-code', code)); i = found + k; continue;
          }
          buf += fence; i += k; continue;
        }
        if (c === 'h' && !isWord(src[i - 1])) {
          urlRe.lastIndex = i; const m = urlRe.exec(src);
          if (m) {
            let u = m[0]; const cut = u.search(/\|\||\*\*|~~/); if (cut > 0) u = u.slice(0, cut);
            while (/[.,;:!?'"*_~\]]$/.test(u) || (u.endsWith(')') && !u.includes('('))) u = u.slice(0, -1);
            if (/^https?:\/\/[^\/\s]+/.test(u)) {
              flush(); const a = el('a', '', u); a.href = u; a.target = '_blank'; a.rel = 'noopener noreferrer'; out.push(a); i += u.length; continue;
            }
          }
        }
        let done = false;
        for (const [d, kind] of DELIMS) {
          if (!src.startsWith(d, i)) continue;
          const close = findClose(src, i, d); if (close < 0) continue;
          const inner = src.slice(i + d.length, close); flush();
          out.push(build(kind, depth < MAX_DEPTH ? inline(inner, depth + 1, opts) : [text(inner)], opts));
          i = close + d.length; done = true; break;
        }
        if (done) continue;
        buf += c; i++;
      }
      flush(); return out;
    }

    const ITEM = /^( *)([-*]|\d{1,9}[.)]) +(\S.*)$/;
    function blocks(src, opts) {
      const lines = src.split('\n'), out = []; let run = [];
      const put = node => out.push(node);
      const flushRun = () => { if (run.length) { out.push(...inline(run.join('\n'), 0, opts)); run = []; } };
      for (let i = 0; i < lines.length; i++) {
        const line = lines[i]; let m;
        if (/^>>> ?/.test(line)) { flushRun(); put(wrap('blockquote', 'dc-md-quote', inline([line.replace(/^>>> ?/, ''), ...lines.slice(i + 1)].join('\n'), 0, opts))); break; }
        if ((m = /^> ?(.*)$/.exec(line))) {
          flushRun(); const q = [m[1]];
          while (i + 1 < lines.length && !/^>>>/.test(lines[i + 1]) && (m = /^> ?(.*)$/.exec(lines[i + 1]))) { q.push(m[1]); i++; }
          put(wrap('blockquote', 'dc-md-quote', inline(q.join('\n'), 0, opts))); continue;
        }
        if ((m = /^(#{1,3}) +(\S.*)$/.exec(line))) { flushRun(); put(wrap('div', 'dc-md-h dc-md-h' + m[1].length, inline(m[2], 0, opts))); continue; }
        if ((m = /^-# +(\S.*)$/.exec(line))) { flushRun(); put(wrap('div', 'dc-md-sub', inline(m[1], 0, opts))); continue; }
        if ((m = ITEM.exec(line)) && m[1].length < 2) {
          flushRun(); const ordered = /\d/.test(m[2][0]), list = el(ordered ? 'ol' : 'ul', 'dc-md-list'); if (ordered) list.start = parseInt(m[2], 10);
          let sub = null, last = null;
          for (; i < lines.length; i++) {
            const it = ITEM.exec(lines[i]); if (!it) break;
            if (it[1].length < 2 && /\d/.test(it[2][0]) !== ordered) break;
            const li = wrap('li', '', inline(it[3], 0, opts));
            if (it[1].length >= 2 && last) { if (!sub) { sub = el('ul', 'dc-md-list'); last.append(sub); } sub.append(li); }
            else { list.append(li); last = li; sub = null; }
          }
          i--; put(list); continue;
        }
        run.push(line);
      }
      flushRun(); return out;
    }

    function render(source, opts = {}) {
      const frag = document.createDocumentFragment(), src = String(source ?? '').replace(/\r\n?/g, '\n');
      let last = 0;
      const plainText = s => { if (s) frag.append(...blocks(s, opts)); };
      for (const m of src.matchAll(/```(?:([A-Za-z0-9_+#.-]{1,20})\n)?([\s\S]*?)```/g)) {
        const code = m[2].replace(/^\n/, '').replace(/\n$/, '');
        if (!code.trim()) continue;
        plainText(src.slice(last, m.index).replace(/^\n/, '')); last = m.index + m[0].length;
        if (opts.preview) { frag.append(text(' ' + code + ' ')); continue; }
        const pre = el('pre', 'dc-md-pre'); if (m[1]) pre.dataset.lang = m[1]; pre.append(el('code', '', code)); frag.append(pre);
      }
      plainText(src.slice(last).replace(/^\n/, last ? '' : '\n')); return frag;
    }
    const plain = source => { const box = document.createElement('div'); box.append(render(source, {preview: true})); for (const n of box.querySelectorAll('li,blockquote,div,pre,ul,ol')) { n.before(text(' ')); n.after(text(' ')); } return box.textContent.replace(/\s+/g, ' ').trim(); };
    return {render, plain};
  })();
  // Per-conversation theme (colors of each person's messages, background). Text color is always computed
  // from the chosen color, so whatever people pick stays readable.
  const hexRgb = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
  const lumOf = ([r, g, b]) => { const f = v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }; return .2126 * f(r) + .7152 * f(g) + .0722 * f(b); };
  const contrastOf = (a, b) => (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
  const themeBubble = hex => { const L = lumOf(hexRgb(hex)), dark = contrastOf(L, 0) > contrastOf(L, 1); return {bubble: hex, ink: dark ? '#0b0b10' : '#ffffff', line: dark ? 'rgba(0,0,0,.28)' : 'rgba(255,255,255,.28)', code: dark ? 'rgba(0,0,0,.12)' : 'rgba(0,0,0,.35)'}; };
  const rgbaOf = (hex, a) => 'rgba(' + hexRgb(hex).join(',') + ',' + a + ')';
  const themeBackground = bg => { const a = (bg.opacity ?? 100) / 100; return bg.mode === 'gradient' ? 'linear-gradient(' + bg.angle + 'deg,' + rgbaOf(bg.c1, a) + ',' + rgbaOf(bg.c2, a) + ')' : rgbaOf(bg.c1, a); };
  const themeMeta = bg => {
    // The window behind the feed is the dark glass base, so a see-through background is judged on what it looks like over it.
    const a = (bg.opacity ?? 100) / 100, base = [14, 14, 18];
    const stops = [bg.c1, ...(bg.mode === 'gradient' ? [bg.c2] : [])].map(h => lumOf(hexRgb(h).map((v, i) => v * a + base[i] * (1 - a))));
    const worst = lum => Math.min(...stops.map(L => contrastOf(L, lum))), white = worst(1), black = worst(0);
    return Math.max(white, black) < 4.5 ? {ink: '#f8f9fa', chip: true} : {ink: white >= black ? '#f8f9fa' : '#0b0b10', chip: false};
  };
  // End-to-end encryption of 1:1 chats. Identity key: ECDH P-256 kept only in this browser's IndexedDB
  // (non-extractable). Per conversation an AES-256-GCM key is derived (ECDH + HKDF). An optional backup of the
  // identity key is encrypted with a password (PBKDF2) before it ever leaves the page. Server side: chat_e2e.php.
  const e2e = (() => {
    const PREFIX = 'e2e1:', CURVE = {name: 'ECDH', namedCurve: 'P-256'}, ITER = 600000, enc = new TextEncoder(), dec = new TextDecoder();
    const supported = () => Boolean(window.isSecureContext && window.crypto?.subtle && window.indexedDB);
    const bytes = v => v instanceof Uint8Array ? v : new Uint8Array(v);
    const b64 = v => { let t = ''; for (const x of bytes(v)) t += String.fromCharCode(x); return btoa(t); };
    const unb64 = t => Uint8Array.from(atob(t), c => c.charCodeAt(0));
    const b64u = v => b64(v).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const unb64u = t => unb64(t.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - t.length % 4) % 4));
    const openDb = () => new Promise((resolve, reject) => { const r = indexedDB.open('dnct-e2e', 1); r.onupgradeneeded = () => { r.result.createObjectStore('keys'); r.result.createObjectStore('peers'); }; r.onsuccess = () => resolve(r.result); r.onerror = () => reject(r.error); });
    const idb = async (store, mode, fn) => { const db = await openDb(); try { return await new Promise((resolve, reject) => { const tx = db.transaction(store, mode), req = fn(tx.objectStore(store)); tx.oncomplete = () => resolve(req?.result); tx.onerror = tx.onabort = () => reject(tx.error); }); } finally { db.close(); } };
    const idbGet = (store, key) => idb(store, 'readonly', os => os.get(key));
    const idbPut = (store, key, value) => idb(store, 'readwrite', os => os.put(value, key));
    const hex = buf => Array.from(bytes(buf), x => x.toString(16).padStart(2, '0')).join('');
    const fingerprintOf = async raw => hex(await crypto.subtle.digest('SHA-256', raw));
    const groups = (text, n) => text.match(new RegExp('.{1,' + n + '}', 'g')).join(' ');
    // Same code on both sides: hash of the two public keys in a fixed order. Compare it outside the chat.
    async function safetyCode(a, b) {
      const [x, y] = [bytes(a), bytes(b)].sort((p, q) => { for (let i = 0; i < p.length; i++) if (p[i] !== q[i]) return p[i] - q[i]; return 0; });
      const both = new Uint8Array(x.length + y.length); both.set(x); both.set(y, x.length);
      return groups((await fingerprintOf(both)).slice(0, 30), 5);
    }
    async function passwordKey(password, salt, iter) {
      const base = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
      return crypto.subtle.deriveKey({name: 'PBKDF2', salt, iterations: iter, hash: 'SHA-256'}, base, {name: 'AES-GCM', length: 256}, false, ['encrypt', 'decrypt']);
    }
    async function wrap(pkcs8, password) {
      const salt = crypto.getRandomValues(new Uint8Array(16)), iv = crypto.getRandomValues(new Uint8Array(12)), key = await passwordKey(password, salt, ITER);
      return JSON.stringify({v: 1, kdf: 'pbkdf2-sha256', iter: ITER, salt: b64(salt), iv: b64(iv), data: b64(await crypto.subtle.encrypt({name: 'AES-GCM', iv}, key, pkcs8))});
    }
    async function unwrap(backup, password) {
      try { return new Uint8Array(await crypto.subtle.decrypt({name: 'AES-GCM', iv: unb64(backup.iv)}, await passwordKey(password, unb64(backup.salt), backup.iter), unb64(backup.data))); }
      catch (_) { throw Error('Nieprawidłowe hasło.'); }
    }
    async function generate(password) {
      const pair = await crypto.subtle.generateKey(CURVE, true, ['deriveBits']);
      const raw = new Uint8Array(await crypto.subtle.exportKey('raw', pair.publicKey)), pkcs8 = new Uint8Array(await crypto.subtle.exportKey('pkcs8', pair.privateKey));
      return {raw, pkcs8, backup: password ? await wrap(pkcs8, password) : null, fingerprint: await fingerprintOf(raw)};
    }
    async function storeLocal(userId, pkcs8, raw) { await idbPut('keys', userId, {privateKey: await crypto.subtle.importKey('pkcs8', pkcs8, CURVE, false, ['deriveBits']), public: b64(raw)}); }
    async function localKey(userId, publicB64) { const k = await idbGet('keys', userId); return k && k.public === publicB64 ? k.privateKey : null; }
    async function restore(userId, serverPublic, backup, password) {
      const pkcs8 = await unwrap(backup, password); let jwk;
      try { jwk = await crypto.subtle.exportKey('jwk', await crypto.subtle.importKey('pkcs8', pkcs8, CURVE, true, ['deriveBits'])); } catch (_) { throw Error('Kopia klucza jest uszkodzona.'); }
      const raw = new Uint8Array([4, ...unb64u(jwk.x), ...unb64u(jwk.y)]);
      if (b64(raw) !== serverPublic) throw Error('Kopia nie pasuje do klucza zapisanego na koncie.');
      await storeLocal(userId, pkcs8, raw);
    }
    const sharedCache = new Map();
    async function sharedKey(userId, privateKey, convId, peerPublic, myPublic) {
      // The cache is keyed by both public keys: replacing either key must never reuse an old conversation key.
      const id = userId + ':' + convId + ':' + myPublic + ':' + peerPublic; if (sharedCache.has(id)) return sharedCache.get(id);
      const peer = await crypto.subtle.importKey('raw', unb64(peerPublic), CURVE, false, []);
      const bits = await crypto.subtle.deriveBits({name: 'ECDH', public: peer}, privateKey, 256);
      const base = await crypto.subtle.importKey('raw', bits, 'HKDF', false, ['deriveKey']);
      const key = await crypto.subtle.deriveKey({name: 'HKDF', hash: 'SHA-256', salt: enc.encode('dnct-e2e-v1'), info: enc.encode('conv:' + convId)}, base, {name: 'AES-GCM', length: 256}, false, ['encrypt', 'decrypt']);
      sharedCache.set(id, key); return key;
    }
    const aad = convId => enc.encode('dnct-e2e-v1|' + convId);
    // The plaintext carries the sender's username, so a server that rewrites the author is detected on open.
    async function seal(key, convId, username, text) {
      const iv = crypto.getRandomValues(new Uint8Array(12));
      const ct = await crypto.subtle.encrypt({name: 'AES-GCM', iv, additionalData: aad(convId)}, key, enc.encode(JSON.stringify({v: 1, u: username, t: text})));
      return PREFIX + b64u(iv) + '.' + b64u(ct);
    }
    async function unseal(key, convId, envelope) {
      try { const [iv, ct] = envelope.slice(PREFIX.length).split('.'), plain = JSON.parse(dec.decode(await crypto.subtle.decrypt({name: 'AES-GCM', iv: unb64u(iv), additionalData: aad(convId)}, key, unb64u(ct)))); return plain && typeof plain.t === 'string' ? plain : null; } catch (_) { return null; }
    }
    // Trust on first use: the first key seen for a person is pinned; a different key later must be accepted explicitly.
    async function pin(userId, peerId, fingerprint) { const known = await idbGet('peers', userId + ':' + peerId); if (!known) { await idbPut('peers', userId + ':' + peerId, fingerprint); return 'ok'; } return known === fingerprint ? 'ok' : 'changed'; }
    const trust = (userId, peerId, fingerprint) => idbPut('peers', userId + ':' + peerId, fingerprint);
    return {supported, isEnvelope: v => typeof v === 'string' && v.startsWith(PREFIX), generate, storeLocal, localKey, restore, sharedKey, seal, unseal, pin, trust, safetyCode, b64, unb64};
  })();
  const FORMATS = [
    {id: 'bold', label: 'Pogrubienie (Ctrl+B)', open: '**', close: '**', glyph: 'B'},
    {id: 'italic', label: 'Kursywa (Ctrl+I)', open: '*', close: '*', glyph: 'I'},
    {id: 'underline', label: 'Podkreślenie (Ctrl+U)', open: '__', close: '__', glyph: 'U'},
    {id: 'strike', label: 'Przekreślenie (Ctrl+Shift+X)', open: '~~', close: '~~', glyph: 'S'},
    {id: 'spoiler', label: 'Spoiler', open: '||', close: '||', glyph: '◐'},
    {id: 'code', label: 'Kod', open: '`', close: '`', glyph: '</>'},
    {id: 'block', label: 'Blok kodu', open: '```\n', close: '\n```', glyph: '{ }'},
    {id: 'quote', label: 'Cytat', line: '> ', glyph: '❝'},
  ];
  class Conversation {
    constructor(mount, options = {}) {
      this.options = options; this.rows = []; this.nodes = new Map(); this.reply = null; this.files = []; this.writable = true; this.actions = true; this.sending = false; this.attempt = null; this.editing = null; this.editDraftBefore = '';
      this.root = el('div', 'dc-chat'); this.root.dataset.lenisPrevent = '';
      this.older = button('Wczytaj starsze wiadomości', () => this.run(() => options.older?.()), 'dc-older'); this.older.hidden = true;
      this.feed = el('div', 'dc-feed'); this.feed.setAttribute('role', 'log'); this.feed.setAttribute('aria-label', 'Wiadomości'); this.feed.setAttribute('aria-live', 'polite'); this.feed.dataset.lenisPrevent = '';
      this.system = el('div', 'dc-system'); this.system.setAttribute('role', 'status'); this.system.hidden = true;
      this.bottom = el('div', 'dc-bottom'); this.preview = el('div', 'dc-reply-preview'); this.preview.hidden = true; this.editBar = el('div', 'dc-edit-composer'); this.editBar.hidden = true; const editCopy = el('span'); editCopy.append(el('b','','Edytuj wiadomość'),el('small','','Enter zapisuje · Shift+Enter dodaje nową linię')); const editCancel = button('×',()=>this.cancelEdit(),'dc-edit-cancel'); editCancel.setAttribute('aria-label','Anuluj edycję wiadomości'); this.editBar.append(editCopy,editCancel); this.previews = el('div', 'dc-previews'); this.previews.hidden = true;
      this.form = el('form', 'dc-composer'); this.text = el('textarea'); this.text.rows = 1; this.text.maxLength = options.maxLength || 2000; this.text.placeholder = 'Napisz wiadomość…'; this.text.setAttribute('aria-label', 'Wiadomość'); this.form.append(this.text);
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
      this.bottom.append(this.preview, this.editBar, this.previews, this.form, this.progress, this.hint, this.error);
      this.newButton = button('↓', () => this.scrollBottom(true), 'dc-new-message'); this.newButton.setAttribute('aria-label','Przejdź do najnowszych wiadomości'); this.newButton.hidden = true;this.newButton.tabIndex=-1;
      this.receiptTimer=setInterval(()=>this.updateReceipt(),15000); this.root.append(this.feed, this.bottom, this.newButton); mount.replaceChildren(this.root);
      this.form.onsubmit = e => { e.preventDefault(); this.editing ? this.saveEdit() : this.send(); };
      this.format = el('div', 'dc-format'); this.format.hidden = true; this.format.setAttribute('role', 'toolbar'); this.format.setAttribute('aria-label', 'Formatowanie tekstu');
      for (const f of FORMATS) {
        const b = button(f.glyph, () => this.applyFormat(f), 'dc-format-btn'); b.dataset.format = f.id; b.title = f.label; b.setAttribute('aria-label', f.label.replace(/ \(.*\)$/, ''));
        b.addEventListener('pointerdown', e => e.preventDefault()); this.format.append(b);
      }
      this.root.append(this.format);
      for (const type of ['select', 'selectionchange', 'mouseup', 'keyup', 'touchend', 'scroll', 'blur']) this.text.addEventListener(type, () => this.updateFormat());
      this.text.onkeydown = e => {
        if ((e.ctrlKey || e.metaKey) && !e.altKey) {
          const id = (e.shiftKey ? {x: 'strike'} : {b: 'bold', i: 'italic', u: 'underline'})[e.key.toLowerCase()];
          if (id) { e.preventDefault(); this.applyFormat(FORMATS.find(f => f.id === id)); return; }
        }
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.form.requestSubmit(); } if (e.key === 'Escape' && this.editing) { e.preventDefault(); e.stopPropagation(); this.cancelEdit(); } else if (e.key === 'Escape' && this.reply) { e.stopPropagation(); this.setReply(null); } };
      this.text.oninput = () => { this.text.style.height = 'auto'; this.text.style.height = Math.min(126, this.text.scrollHeight) + 'px'; if (!this.editing) options.draft?.(this.text.value); options.typing?.(); this.updateCounter(); this.updateFormat(); };
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
    setTheme(theme) {
      const signature = JSON.stringify(theme || null);
      if (this.themeSignature === signature) return;
      this.themeSignature = signature; this.theme = theme || null; this.applyTheme();
    }
    applyTheme() {
      const bg = this.theme?.bg || null, feed = this.feed;
      feed.classList.toggle('has-theme-bg', Boolean(bg));
      if (bg) { const meta = themeMeta(bg); feed.style.setProperty('--dcx-feed', themeBackground(bg)); feed.style.setProperty('--dcx-meta', meta.ink); feed.classList.toggle('dcx-chip-meta', meta.chip); }
      else { feed.style.removeProperty('--dcx-feed'); feed.style.removeProperty('--dcx-meta'); feed.classList.remove('dcx-chip-meta'); }
      for (const m of this.rows) { const node = this.nodes.get(m.id); if (node) this.styleBubble(node, m); }
    }
    styleBubble(node, m) {
      const bubble = node.querySelector?.('.dc-bubble'); if (!bubble) return;
      const color = this.theme?.colors?.[m.username];
      const look = color ? themeBubble(color) : this.theme?.bg ? {bubble: 'rgba(14,14,18,.82)', ink: '#f8f9fa', line: 'rgba(255,255,255,.14)', code: 'rgba(0,0,0,.35)'} : null;
      bubble.classList.toggle('has-theme', Boolean(look));
      for (const [name, key] of [['--dcx-bubble', 'bubble'], ['--dcx-ink', 'ink'], ['--dcx-line', 'line'], ['--dcx-code', 'code']]) { if (look) bubble.style.setProperty(name, look[key]); else bubble.style.removeProperty(name); }
    }
    setMediaEnabled(on) { this.mediaBlocked = !on; if (this.attach) { this.attach.disabled = !on; this.attach.title = on ? 'Załącz zdjęcie lub film' : 'W szyfrowanych rozmowach załączniki są jeszcze niedostępne'; } }
    updateCounter() {
      const used = this.text.value.length, max = this.text.maxLength;
      this.hint.hidden = used < max * 0.9; this.hint.textContent = used + ' / ' + max + ' znaków'; this.hint.classList.toggle('is-over', used >= max);
    }
    caretPoint(index) {
      const t = this.text, cs = getComputedStyle(t), mirror = document.createElement('div');
      for (const p of ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing', 'lineHeight', 'wordSpacing', 'textIndent', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'boxSizing', 'tabSize']) mirror.style[p] = cs[p];
      Object.assign(mirror.style, {position: 'absolute', visibility: 'hidden', top: '0', left: '-9999px', whiteSpace: 'pre-wrap', overflowWrap: 'break-word', width: t.getBoundingClientRect().width + 'px'});
      const marker = el('span', '', '​'); mirror.append(document.createTextNode(t.value.slice(0, index)), marker); document.body.append(mirror);
      const point = {x: marker.offsetLeft, y: marker.offsetTop}; mirror.remove(); return point;
    }
    updateFormat() {
      const t = this.text, s = t.selectionStart, e = t.selectionEnd;
      const show = this.writable && !this.sending && document.activeElement === t && e > s && t.value.slice(s, e).trim() !== '';
      if (!show) { this.format.hidden = true; return; }
      this.format.hidden = false;
      const a = this.caretPoint(s), b = this.caretPoint(e), tr = t.getBoundingClientRect(), rr = this.root.getBoundingClientRect();
      const x = a.y === b.y ? (a.x + b.x) / 2 : a.x, y = Math.max(0, a.y - t.scrollTop);
      const left = tr.left - rr.left + x - this.format.offsetWidth / 2, top = tr.top - rr.top + y - this.format.offsetHeight - 8;
      this.format.style.left = Math.max(6, Math.min(rr.width - this.format.offsetWidth - 6, left)) + 'px'; this.format.style.top = Math.max(6, top) + 'px';
    }
    applyFormat(f) {
      const t = this.text, v = t.value, max = t.maxLength; let s = t.selectionStart, e = t.selectionEnd, from, to, repl, sel;
      if (f.line) {
        const end = e > s && v[e - 1] === '\n' ? e - 1 : e, a = v.lastIndexOf('\n', s - 1) + 1, b = v.indexOf('\n', end) === -1 ? v.length : v.indexOf('\n', end);
        const lines = v.slice(a, b).split('\n'), all = lines.every(l => l.startsWith(f.line));
        repl = lines.map(l => all ? l.slice(f.line.length) : f.line + l).join('\n'); from = a; to = b; sel = [a, a + repl.length];
      } else {
        while (s < e && /\s/.test(v[s])) s++; while (e > s && /\s/.test(v[e - 1])) e--;
        const o = f.open, c = f.close;
        if (s === e) { from = to = s; repl = o + c; sel = [s + o.length, s + o.length]; }
        else {
          let before = 0, after = 0;
          if (o[0] === '*' && c[0] === '*') { while (v[s - 1 - before] === '*') before++; while (v[e + after] === '*') after++; }
          const asterisks = o[0] === '*', run = Math.min(before, after);
          const wrapped = asterisks ? (o.length === 1 ? run % 2 === 1 : run >= 2) : v.slice(s - o.length, s) === o && v.slice(e, e + c.length) === c;
          if (wrapped) { from = s - o.length; to = e + c.length; repl = v.slice(s, e); sel = [from, from + repl.length]; }
          else if (!asterisks && e - s >= o.length + c.length && v.startsWith(o, s) && v.slice(e - c.length, e) === c) { from = s; to = e; repl = v.slice(s + o.length, e - c.length); sel = [s, s + repl.length]; }
          else { from = s; to = e; repl = o + v.slice(s, e) + c; sel = [s + o.length, s + o.length + (e - s)]; }
        }
      }
      if (v.length - (to - from) + repl.length > max) { this.say('Formatowanie przekroczyłoby limit ' + max + ' znaków.'); return; }
      this.say(); t.focus(); t.setSelectionRange(from, to);
      let viaCommand = false; try { viaCommand = document.execCommand('insertText', false, repl); } catch (_) {}
      if (!viaCommand) { t.setRangeText(repl, from, to, 'end'); t.dispatchEvent(new Event('input')); }
      t.setSelectionRange(sel[0], sel[1]); this.updateFormat();
    }
    setDraft(value) { this.text.value = value || ''; this.text.dispatchEvent(new Event('input')); }
    reset() { this.setTheme(null); this.feedResize?.disconnect();this.feedResize?.observe(this.feed);this.followBottom=true;this.newButton.hidden=true;this.newButton.tabIndex=-1;cancelAnimationFrame(this.scrollFrame);this.scrollingLatest=false;this.closePopover(); this.rows = []; this.readers=[]; this.nodes.clear(); this.feed.replaceChildren(); this.setReply(null); this.editing = null; this.editDraftBefore = ''; this.editBar.hidden = true; this.root.classList.remove('is-editing'); this.setDraft(''); this.clearFiles(); this.attempt = null; this.say(); }
    update(rows, {composerNotice = '', readers = [], closed = false, closedBy = '', reason = '', writable = true, actions = writable, hasMore = false, prepend = false} = {}) {
      const stick = this.nearBottom() || !this.rows.length, height = this.feed.scrollHeight, top = this.feed.scrollTop;
      const newMessage = rows.some(m => !this.nodes.has(Number(m.id))); const oldActions = this.actions;
      this.writable = writable && !closed; this.actions = actions && !closed;
      if (this.editing && (!this.writable || !this.actions)) this.cancelEdit();
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
    message(m) { const node = this.buildMessage(m); this.styleBubble(node, m); return node; }
    buildMessage(m) {
      if(m.system){const notice=el('article','dc-message dc-system-event',m.body);notice.dataset.messageId=m.id;if(m.care&&m.care.can_decide){const bar=el('div','dc-care-actions'),hostPanel=document.getElementById('dnct-messenger');[['accept','Tak'],['reject','Nie']].forEach(([decision,label])=>{const b=el('button','dc-care-btn dc-care-'+decision,label);b.type='button';b.addEventListener('click',async()=>{if(decision==='reject'&&!window.confirm('Odrzucić prośbę? Klient dostanie pełny zwrot pieniędzy.'))return;bar.querySelectorAll('button').forEach(x=>x.disabled=true);const note=el('span','dc-care-note','Zapisuję decyzję…');bar.append(note);try{await request(hostPanel.dataset.api,{action:'care_decide',id:m.care.conversation,message_id:m.id,decision},hostPanel.dataset.csrf);note.textContent='Zapisano.';}catch(err){note.textContent=err&&err.message||'Nie udało się zapisać decyzji.';bar.querySelectorAll('button').forEach(x=>x.disabled=false);}});bar.append(b);});notice.append(bar);}return notice;}
      const outgoing = Boolean(m.outgoing ?? m.mine);
      const node = el('article', 'dc-message' + (outgoing ? ' mine' : '')); node.dataset.messageId = m.id; node.dataset.direction = outgoing ? 'outgoing' : 'incoming';
      if (this.options.showAuthors !== false && !outgoing && (!m.reply || m.is_withdrawn)) { const author = el('div', 'dc-author', m.author_name || m.display_name || 'Użytkownik'); if (m.username) { author.dataset.miniUser = m.username; nick(author, m.username); } node.append(author); }
      const line = el('div', 'dc-line'), bubble = el('div', 'dc-bubble');
      const avatar = el('span','dc-avatar',Array.from(m.author_name || m.display_name || m.username || '?')[0]);
      if (!outgoing && m.username) { window.DNCTChat.avatar(avatar,m.username); avatar.dataset.miniUser = m.username; avatar.title = 'Pokaż profil'; }
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
        q.append(el('b', '', '↶ ' + replyLabel), el('span', '', m.reply.is_withdrawn || m.reply.withdrawn_at ? 'Wiadomość została cofnięta.' : markdown.plain(m.reply.body) || 'Załącznik')); node.append(q);
      }
      const body = el('p', 'dc-body' + (m.is_withdrawn ? ' dc-withdrawn' : ''));
      const content = m.is_withdrawn ? 'Wiadomość została cofnięta.' : m.body || '';
      if (m.is_withdrawn) body.textContent = content; else body.append(markdown.render(content));
      bubble.append(body);
      if (!m.is_withdrawn) richMessage(bubble, body, m);
      if (m.silent) node.classList.add('dc-silent');
      if (m.revisions?.length || m.silent) bubble.append(this.revisionBlock(m));
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
      if (!outgoing) { const avatarWrap = el('span', 'dn-av'); avatarWrap.append(avatar); line.append(avatarWrap); }
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
      if (m) { const copy = el('span', '', markdown.plain(m.body) || 'Załącznik'); copy.prepend(el('b', '', 'Odpowiadasz: ' + (m.mine ? 'sobie' : m.author_name || m.display_name || 'Użytkownik'))); const cancel = button('×', () => this.setReply(null), 'dc-icon'); cancel.setAttribute('aria-label', 'Anuluj odpowiedź'); this.preview.append(copy, cancel); this.text.focus(); }
    }
    chooseReply() {
      const candidates = this.rows.filter(m => !m.is_withdrawn).slice(-8).reverse();
      if (!candidates.length) { this.say('Nie ma jeszcze wiadomości, na którą można odpowiedzieć.'); return; }
      const menu = this.popover(this.replyButton, 'dc-menu');if(!menu)return;
      menu.append(el('p', 'dc-popover-note', 'Wybierz wiadomość'));
      for (const m of candidates) menu.append(button((m.mine ? 'Ty' : m.author_name || m.display_name || 'Użytkownik') + ': ' + (markdown.plain(m.body) || 'Załącznik').slice(0, 64), () => { this.closePopover(); this.setReply(m); }));
      this.positionPopover();
    }
    menu(m, anchor) {
      const menu = this.popover(anchor, 'dc-menu');if(!menu)return;
      if (this.canEdit(m)) menu.append(button('Edytuj wiadomość', () => { this.closePopover(); this.edit(m); }));
      menu.append(button('Cofnij wysłanie', () => { this.closePopover(); this.withdraw(m); }, 'dc-danger')); this.positionPopover();
    }
    revisionBlock(m) {
      const secret = t => Boolean(window.DNCTChat?.e2e?.isEnvelope?.(t));
      const box = el('div', 'dc-revisions');
      box.append(el('div', 'dc-rev-head', m.is_withdrawn ? (m.silent ? 'Usunięte bez śladu - widzi tylko admin' : 'Usunięte - widzi tylko admin') : 'Edytowane - historię widzi tylko admin'));
      for (const r of m.revisions || []) {
        const row = el('div', 'dc-rev-row');
        row.append(el('small', '', (r.kind === 'withdraw' ? 'Treść przed usunięciem' : 'Wcześniejsza wersja') + ' · ' + new Date(r.at * 1000).toLocaleString('pl-PL')), el('p', '', secret(r.body) ? 'Zaszyfrowana treść (E2E)' : r.body));
        box.append(row);
      }
      return box;
    }
    canEdit(m) { if ((m.attachments||[]).some(a=>a.media_type==='image'||a.kind==='image'||String(a.mime_type||'').startsWith('image/'))) return false; return Date.now() < (Number(m.edit_expires_at)*1000 || date(m.created_at).getTime()+300000); }
    edit(m) {
      if (!this.canEdit(m)) { this.say("Minęło 5 minut — tej wiadomości nie można już edytować."); return; }
      if (this.editing) this.cancelEdit(); this.editDraftBefore = this.text.value; this.editing = m.id; this.setReply(null); this.setDraft(m.body || ''); this.editBar.hidden = false; this.root.classList.add('is-editing');
      this.nodes.get(m.id)?.classList.add('is-being-edited'); this.text.focus(); this.text.setSelectionRange(this.text.value.length, this.text.value.length);
    }
    async saveEdit() {
      const m = this.rows.find(row => row.id === this.editing), value = this.text.value.trim(); if (!m) return this.cancelEdit();
      if (!this.canEdit(m)) { this.say('Minęło 5 minut — tej wiadomości nie można już edytować.'); this.cancelEdit(); return; }
      if (!value) { this.say('Wpisz treść wiadomości.'); this.text.focus(); return; }
      this.sending = true; this.say(); for (const control of this.form.querySelectorAll('textarea,button,input')) control.disabled = true;
      try { await this.options.action?.('edit', {message_id:m.id, message:value}); this.cancelEdit(); }
      catch(e) { this.say(e.message || 'Nie udało się zapisać zmian w wiadomości.'); this.options.failure?.(e); }
      finally { this.sending = false; for (const control of this.form.querySelectorAll('textarea,button,input')) control.disabled = !this.writable; if (this.writable) this.text.focus(); }
    }
    cancelEdit() { const id = this.editing; if (!id) return; this.editing = null; this.editBar.hidden = true; this.root.classList.remove('is-editing'); this.setDraft(this.editDraftBefore); this.editDraftBefore = ''; const m = this.rows.find(m => m.id === id), old = this.nodes.get(id); if (m && old) { const next = this.message(m); next.dataset.signature = JSON.stringify(m) + ':' + this.actions; old.replaceWith(next); this.nodes.set(id, next); } }
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
      if (this.mediaBlocked) { this.say('W szyfrowanych rozmowach załączniki są jeszcze niedostępne.'); return; }
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
  // Odznaki, kolor nicku i ramka avatara z account.dnct.pl (backend/lib/odznaki.php).
  // Zapytania o wielu rozmówców zbieramy w jedną paczkę; wynik trzymamy 2 minuty.
  // Przez API czatu (ta sama domena); bez komunikatora - wprost z account.dnct.pl.
  const chatApi = () => document.getElementById('dnct-messenger')?.dataset.api || '';
  const lookUrl = (action, n) => chatApi() ? chatApi() + (chatApi().includes('?') ? '&' : '?') + 'action=' + action + '&n=' + n : 'https://account.dnct.pl/api/publiczne/' + (action === 'mini' ? 'mini' : 'wyglad') + '.php?n=' + n;
  const lookCreds = () => chatApi() ? 'same-origin' : 'omit';
  const looks = new Map(), lookQueue = new Set(); let lookTimer = 0;
  function lookFor(username) {
    const known = looks.get(username);
    if (known && Date.now() - known.at < 120000) return known.promise;
    const entry = {at: Date.now()}; entry.promise = new Promise(resolve => { entry.resolve = resolve; });
    looks.set(username, entry); lookQueue.add(username); clearTimeout(lookTimer); lookTimer = setTimeout(flushLooks, 40);
    return entry.promise;
  }
  async function flushLooks() {
    const batch = [...lookQueue].slice(0, 60); batch.forEach(u => lookQueue.delete(u));
    if (lookQueue.size) lookTimer = setTimeout(flushLooks, 40);
    let users = null;
    try { const r = await fetch(lookUrl('look', batch.map(encodeURIComponent).join(',')), {credentials: lookCreds()}); if (r.ok) users = (await r.json()).users || {}; } catch (_) {}
    for (const u of batch) { const entry = looks.get(u); if (!entry) continue; if (!users) entry.at = 0; entry.resolve(users?.[u] || null); }
  }
  function badgeIcon(info, className) {
    const s = el('span', className + ' dn-t--' + info.tier); s.title = info.name + ' - ' + info.desc;
    if (info.brand) s.style.setProperty('--dn-brand', info.brand);
    if (info.img) { const img = new Image(); img.alt = ''; img.src = info.img; s.append(img); return s; }
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'), path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true'); if (info.fill) svg.setAttribute('class', 'dn-fill'); path.setAttribute('d', info.icon); svg.append(path); s.append(svg);
    return s;
  }
  function dressAvatar(target, look) {
    target.classList.forEach(c => { if (c.startsWith('dn-ramka')) target.classList.remove(c); });
    const host = target.parentElement?.classList.contains('dn-av') ? target.parentElement : (getComputedStyle(target).overflow === 'hidden' ? null : target);
    host?.querySelectorAll(':scope > .dn-pin').forEach(p => p.remove());
    if (!look) return;
    // Panel czatu bywa ukryty (hidden), gdy lista się renderuje - offsetWidth = 0 i odznaka/ramka
    // przepadały, a wygląd jest w cache 2 min. Bierzemy wtedy szerokość z CSS (2026-11-11).
    const size = target.offsetWidth || parseFloat(getComputedStyle(target).width) || 0;
    if (look.frame && look.frame !== 'brak' && size >= 26) target.classList.add('dn-ramka', 'dn-ramka--' + look.frame);
    if (look.pin && host && size >= 24) host.append(badgeIcon(look.pin, 'dn-pin'));
  }
  function decorateAvatar(target, username) {
    lookFor(username).then(look => requestAnimationFrame(() => { if (target.dataset.avatarUser === username) dressAvatar(target, look); }));
  }
  function nick(target, username) {
    if (!username) return;
    target.dataset.nickUser = username;
    lookFor(username).then(look => {
      if (target.dataset.nickUser !== username) return;
      target.classList.forEach(c => { if (c.startsWith('dn-nick--')) target.classList.remove(c); });
      if (look?.nick && look.nick !== 'domyslny') target.classList.add('dn-nick--' + look.nick);
    });
  }
  // Mini-profil po kliknięciu w avatar albo nick: baner jako tło całej karty,
  // "poziom" z liczby odznak, wyróżniona (przypięta) odznaka, gablota, akcje.
  let mini = null;
  function closeMini() { if (!mini) return; mini.card.remove(); document.removeEventListener('pointerdown', mini.outside, true); document.removeEventListener('keydown', mini.key, true); mini.anchor?.focus?.({preventScroll: true}); mini = null; }
  async function openMini(anchor, username) {
    closeMini();
    const card = el('div', 'dn-mini'); card.setAttribute('role', 'dialog'); card.setAttribute('aria-label', 'Profil ' + username); card.tabIndex = -1;
    card.append(el('p', 'dn-mini-wait', 'Wczytuję profil...'));
    // W otwartym oknie dialogowym karta musi być w jego warstwie, inaczej zostanie pod nim.
    (anchor.closest('dialog[open]') || document.body).append(card);
    const outside = e => { if (!card.contains(e.target) && !anchor.contains(e.target)) closeMini(); };
    const key = e => { if (e.key === 'Escape') { e.stopPropagation(); closeMini(); } };
    mini = {card, anchor, outside, key};
    document.addEventListener('pointerdown', outside, true); document.addEventListener('keydown', key, true);
    const place = () => {
      const r = anchor.getBoundingClientRect(), w = card.offsetWidth, h = card.offsetHeight, m = 12;
      let left = r.right + 12; if (left + w > innerWidth - m) left = r.left - w - 12; left = Math.max(m, Math.min(left, innerWidth - w - m));
      const top = Math.max(m, Math.min(r.top - 24, innerHeight - h - m));
      card.style.left = left + 'px'; card.style.top = top + 'px';
    };
    place();
    let d;
    try { const r = await fetch(lookUrl('mini', encodeURIComponent(username)), {credentials: lookCreds()}); d = await r.json(); if (!r.ok || !d.ok) throw Error(d.message || 'Nie udało się wczytać profilu.'); }
    catch (e) { if (mini?.card === card) { card.replaceChildren(el('p', 'dn-mini-wait', e.message || 'Nie udało się wczytać profilu.')); place(); } return; }
    if (mini?.card !== card) return;
    card.replaceChildren();
    const tlo = el('div', 'dn-mini-tlo'); tlo.style.cssText = d.banner || '';
    const close = button('', closeMini, 'dn-mini-close'); close.setAttribute('aria-label', 'Zamknij profil');
    close.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
    const head = el('div', 'dn-mini-head'), wrap = el('span', 'dn-av'), face = el('span', 'dn-mini-avatar', d.initials || '?');
    if (d.avatar) { const img = new Image(); img.alt = ''; img.referrerPolicy = 'no-referrer'; img.src = d.avatar; face.append(img); }
    wrap.append(face);
    const who = el('div', 'dn-mini-who'), name = el('strong', 'dn-mini-name', d.name);
    if (d.look?.nick && d.look.nick !== 'domyslny') name.classList.add('dn-nick--' + d.look.nick);
    who.append(name, el('small', '', '@' + d.login));
    const poziom = el('div', 'dn-mini-poziom'); poziom.title = d.badges + ' z ' + d.badges_total + ' odznak';
    poziom.append(el('b', '', String(d.badges)), el('small', '', d.badges === 1 ? 'odznaka' : (d.badges % 10 >= 2 && d.badges % 10 <= 4 && (d.badges % 100 < 12 || d.badges % 100 > 14) ? 'odznaki' : 'odznak')));
    head.append(wrap, who, poziom);
    const body = el('div', 'dn-mini-body');
    const pin = d.look?.pin;
    if (pin) {
      const wyr = el('div', 'dn-mini-wyroznik'), txt = el('div');
      txt.append(el('small', '', 'Przypięta odznaka'), el('strong', '', pin.name), el('span', 'dn-mini-rz', pin.tier_name));
      txt.querySelector('.dn-mini-rz').style.color = pin.color || '';
      wyr.append(badgeIcon(pin, 'dn-med'), txt); body.append(wyr);
    }
    if (d.bio) body.append(el('p', 'dn-mini-bio', d.bio));
    const reszta = (d.look?.showcase || []).filter(b => !pin || b.key !== pin.key);
    if (reszta.length) {
      const show = el('div', 'dn-mini-show');
      for (const b of reszta) { const cell = el('div', 'dn-mini-badge'); cell.title = b.name + ' - ' + b.desc; cell.append(badgeIcon(b, 'dn-med'), el('small', '', b.name)); show.append(cell); }
      body.append(show);
    }
    const foot = el('div', 'dn-mini-foot');
    foot.append(el('small', 'dn-mini-meta', d.joined ? 'W DNCT od ' + d.joined : ''));
    const actions = el('div', 'dn-mini-actions');
    if (d.url) { const a = el('a', 'dn-mini-btn', 'Profil'); a.href = d.url; a.target = '_blank'; a.rel = 'noopener'; actions.append(a); }
    if (document.querySelector('#dnct-messenger')) actions.append(button('Wiadomość', () => { closeMini(); document.dispatchEvent(new CustomEvent('dnct:chat-dm', {detail: {username: d.login}})); }, 'dn-mini-btn dn-mini-main'));
    foot.append(actions);
    card.append(tlo, close, head, body, foot);
    dressAvatar(face, d.look ? {frame: d.look.frame, pin: d.look.pin} : null);
    face.dataset.avatarUser = d.login;
    place(); card.focus({preventScroll: true});
  }
  // Pasek strony (navibar.php): nick, ramka i przypięta odznaka zalogowanego, jak w czacie.
  function dressNavbar() {
    const name = document.querySelector('.profile-magic-wrapper .profile-text-username'), face = document.querySelector('.profile-magic-wrapper .profile-magic-avatar');
    const login = name?.textContent.trim();
    if (!login || !/^[A-Za-z0-9_.@+-]{1,150}$/.test(login)) return;
    nick(name, login);
    if (face) lookFor(login).then(look => requestAnimationFrame(() => {
      face.classList.forEach(c => { if (c.startsWith('dn-ramka')) face.classList.remove(c); });
      face.querySelectorAll(':scope > .dn-pin').forEach(p => p.remove());
      if (!look) return;
      if (look.frame && look.frame !== 'brak') face.classList.add('dn-ramka', 'dn-ramka--' + look.frame);
      if (look.pin) face.append(badgeIcon(look.pin, 'dn-pin'));
    }));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', dressNavbar); else dressNavbar();
  document.addEventListener('click', e => {
    const t = e.target.closest?.('[data-mini-user]');
    if (!t || t.closest('.dn-mini')) return;
    e.preventDefault(); openMini(t, t.dataset.miniUser);
  });
  const avatarImages = new Map();
  function avatar(target, username, className = '') {
    if (!username) return;
    target.dataset.avatarUser = username;
    decorateAvatar(target, username);
    let entry = avatarImages.get(username);
    // Brak awatara (404) pamietamy 10 minut, zeby kazde odrysowanie wiadomosci nie pytalo od nowa.
    if (entry?.failedAt && Date.now() - entry.failedAt > 600000) { avatarImages.delete(username); entry = undefined; }
    if (!entry) {
      const img = new Image(); img.referrerPolicy = 'no-referrer'; img.alt = '';
      entry = {img,ready:false};
      entry.promise = new Promise(resolve => { img.onload = async () => { try { await img.decode(); } catch {} entry.ready=true;resolve(true); }; img.onerror = () => { entry.failedAt = Date.now();resolve(false); }; });
      avatarImages.set(username,entry); img.src='https://account.dnct.pl/avatar/'+encodeURIComponent(username);
    }
    const attach = () => {
      if (target.dataset.avatarUser !== username) return;
      const img=entry.img.cloneNode();img.className=className;
      target.querySelectorAll(':scope > img').forEach(old=>old.remove());target.append(img); /* tylko zdjęcie avatara - img odznaki (.dn-pin > img) zostaje */
    };
    if(entry.ready)attach();else entry.promise.then(ok=>{if(ok)attach();});
  }
  // Resizable chat windows: drag the left/top edges, plus "Powiększ" (fullscreen) and "Zmniejsz" (default size).
  // Fullscreen is shared by every panel on the page; a dragged size is remembered per panel.
  const sizing = (() => {
    const STORE = 'dnct:chat-size:', MARGIN = 12, memory = new Map(), entries = new Map(); let fullscreen = false;
    const small = () => matchMedia('(max-width:640px)').matches;
    const read = k => { if (memory.has(k)) return memory.get(k); try { const v = JSON.parse(localStorage.getItem(STORE + k)); if (v && v.w > 0 && v.h > 0) { memory.set(k, v); return v; } } catch (_) {} return null; };
    const write = (k, v) => { if (v) memory.set(k, v); else memory.delete(k); try { v ? localStorage.setItem(STORE + k, JSON.stringify(v)) : localStorage.removeItem(STORE + k); } catch (_) {} };
    const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
    const svg = d => '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
    const isOpen = panel => !panel.hidden && panel.getAttribute('aria-hidden') !== 'true';

    function paint(entry) {
      const {panel, key} = entry, custom = read(key);
      panel.classList.toggle('dc-fullscreen', fullscreen); panel.classList.toggle('dc-sized', !fullscreen && !!custom);
      if (custom) { panel.style.setProperty('--dc-w', custom.w + 'px'); panel.style.setProperty('--dc-h', custom.h + 'px'); } else { panel.style.removeProperty('--dc-w'); panel.style.removeProperty('--dc-h'); }
      if (entry.shrink) entry.shrink.disabled = !fullscreen && !custom;
      if (entry.expand) entry.expand.disabled = fullscreen;
    }
    const paintAll = () => entries.forEach(paint);
    // Size changes made with the buttons glide to the new size (CSS transition, only while .dc-morph is set,
    // so dragging and the scroll-driven fit() of the controllers are never animated).
    let morphTimer = 0;
    function morph(change) {
      if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        for (const panel of entries.keys()) if (isOpen(panel)) panel.classList.add('dc-morph');
        clearTimeout(morphTimer); morphTimer = setTimeout(() => { for (const panel of entries.keys()) panel.classList.remove('dc-morph'); }, 450);
      }
      change();
    }
    function reset(entry) { morph(() => { fullscreen = false; write(entry.key, null); paintAll(); }); }
    function expand() { morph(() => { fullscreen = true; paintAll(); }); }

    function drag(entry, handle, mode, e) {
      if (e.button !== 0 || fullscreen || small()) return;
      e.preventDefault();
      const {panel, key} = entry, rect = panel.getBoundingClientRect(), x0 = e.clientX, y0 = e.clientY;
      const maxW = Math.max(entry.minW, rect.right - MARGIN), maxH = Math.max(entry.minH, rect.bottom - MARGIN);
      let size = {w: Math.round(rect.width), h: Math.round(rect.height)};
      handle.setPointerCapture(e.pointerId); panel.classList.add('dc-resizing');
      const move = ev => {
        size = {w: mode.includes('l') ? Math.round(clamp(rect.width + x0 - ev.clientX, entry.minW, maxW)) : size.w, h: mode.includes('t') ? Math.round(clamp(rect.height + y0 - ev.clientY, entry.minH, maxH)) : size.h};
        memory.set(key, size); paint(entry);
      };
      const end = () => { handle.removeEventListener('pointermove', move); handle.removeEventListener('pointerup', end); handle.removeEventListener('pointercancel', end); panel.classList.remove('dc-resizing'); write(key, size); paint(entry); };
      handle.addEventListener('pointermove', move); handle.addEventListener('pointerup', end); handle.addEventListener('pointercancel', end);
    }

    function ensure(entry) {
      const {panel} = entry, header = panel.querySelector('.dm-top-actions,.dc-support-top');
      if (header && !header.querySelector('.dc-size-actions')) {
        const box = el('div', 'dc-size-actions');
        entry.shrink = button('', () => reset(entry), 'dc-size-btn'); entry.shrink.innerHTML = svg('<path d="M20 10h-6V4M4 14h6v6M14 10l7-7M3 21l7-7"/>'); entry.shrink.title = 'Zmniejsz czat do domyślnego rozmiaru'; entry.shrink.setAttribute('aria-label', 'Zmniejsz czat do domyślnego rozmiaru');
        entry.expand = button('', expand, 'dc-size-btn'); entry.expand.innerHTML = svg('<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>'); entry.expand.title = 'Powiększ czat na cały ekran'; entry.expand.setAttribute('aria-label', 'Powiększ czat na cały ekran');
        box.append(entry.shrink, entry.expand);
        const close = header.querySelector('.dm-close,.dc-shell-close'); header.insertBefore(box, close && close.parentNode === header ? close : null);
      }
      if (!panel.querySelector(':scope > .dc-resize')) for (const mode of ['l', 't', 'tl']) {
        const handle = el('div', 'dc-resize dc-resize-' + mode); handle.setAttribute('aria-hidden', 'true');
        handle.addEventListener('pointerdown', e => drag(entry, handle, mode, e)); handle.addEventListener('dblclick', () => reset(entry)); panel.append(handle);
      }
      paint(entry);
    }

    function attach(panel, key, minW, minH) {
      if (!panel || entries.has(panel)) return;
      const entry = {panel, key, minW, minH, shrink: null, expand: null}; entries.set(panel, entry); ensure(entry);
      new MutationObserver(() => ensure(entry)).observe(panel, {childList: true});
      // Fullscreen ends together with the chat; a panel switch (one closes, one opens) keeps it.
      new MutationObserver(() => setTimeout(() => { if (fullscreen && ![...entries.keys()].some(isOpen)) { fullscreen = false; paintAll(); } }, 300)).observe(panel, {attributes: true, attributeFilter: ['hidden', 'aria-hidden']});
    }
    // Only the messenger is resizable; the support ticket window keeps its fixed size.
    function attachAll() { attach(document.getElementById('dnct-messenger'), 'dm', 560, 420); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attachAll); else attachAll();
    document.addEventListener('dnct:messaging-ready', attachAll);
    // Switching between the messenger and a ticket: the incoming window starts at the outgoing window's rectangle
    // (inline !important beats the stylesheet's !important sizes) and then glides to its own size.
    function glide(from, to, before, reduced) {
      const done = to.animate([{opacity: 1}, {opacity: 1}], {duration: reduced ? 0 : 400});
      if (reduced) return done;
      const keep = {}, start = {width: before.width, height: before.height, right: innerWidth - before.right, bottom: innerHeight - before.bottom, 'max-height': before.height};
      const radius = getComputedStyle(from).borderTopLeftRadius, border = getComputedStyle(from).borderTopWidth;
      for (const k of [...Object.keys(start), 'border-radius', 'border-width']) keep[k] = [to.style.getPropertyValue(k), to.style.getPropertyPriority(k)];
      for (const [k, v] of Object.entries(start)) to.style.setProperty(k, Math.round(v) + 'px', 'important');
      to.style.setProperty('border-radius', radius, 'important'); to.style.setProperty('border-width', border, 'important');
      void to.offsetWidth; // commit the start rectangle first, without a transition
      to.classList.add('dc-morph');
      for (const k in keep) { to.style.removeProperty(k); if (keep[k][0]) to.style.setProperty(k, keep[k][0], keep[k][1]); }
      const clean = () => to.classList.remove('dc-morph');
      done.addEventListener('finish', clean); done.addEventListener('cancel', clean);
      return done;
    }
    return {attach, expand, paintAll, glide};
  })();
  window.DNCTChat = {Conversation, request, key, el, button, icon, avatar, nick, miniProfile: openMini, age, plain: markdown.plain, sizing, themeBackground, e2e};
})();

/* Shared message indicators and delivery effects. No private content is persisted. */
(() => {
 'use strict';
 if(window.DNCTAlerts || !['game.dnct.pl','dnct.pl','beta.dnct.pl','panel.dnct.pl','beta.lan.panel-dnct.pl','web.dnct.pl','web.panel-dnct.pl','czat.dnct.pl'].includes(location.hostname))return;
 let config={};try{config=JSON.parse(document.getElementById('dnct-support-config')?.textContent||'{}');}catch{}
 const scope='dnct:alerts:'+String(config.name||'guest');
 const read=(key,fallback)=>{try{return JSON.parse(localStorage.getItem(scope+key))??fallback;}catch{return fallback;}};
 const write=(key,value)=>{try{localStorage.setItem(scope+key,JSON.stringify(value));}catch{}};
 const style=document.createElement('style');style.textContent=`
 .dnct-unread-counter{position:absolute!important;right:-5px!important;top:-5px!important;left:auto!important;bottom:auto!important;min-width:21px;height:21px;padding:0 5px;box-sizing:border-box;display:grid;place-items:center;border-radius:99px;background:#ef4444;color:#fff;font:700 11px/1 system-ui;border:2px solid #21162f;pointer-events:none;z-index:5;opacity:0;transform:scale(.3);transform-origin:center;transition:opacity .24s,transform .3s cubic-bezier(.2,.8,.2,1)}
 .dnct-unread-counter.is-visible{opacity:1;transform:scale(1);animation:dnct-count-pulse 2.4s ease-in-out infinite}
 [data-dm-support],[data-dm-support-mobile]{position:relative!important;overflow:visible!important}.dnct-ticket-dot{position:absolute;right:2px;top:2px;width:12px;height:12px;border:2px solid #21162f;border-radius:50%;background:#ef4444;pointer-events:none;opacity:0;transform:scale(0);transition:opacity .24s,transform .3s}.dnct-ticket-dot.is-visible{opacity:1;transform:scale(1)}
 .dnct-desktop-permission{border:1px solid #a78bfa33;background:#a78bfa0d;color:#c4b5fd;border-radius:8px;padding:7px 10px;font:11px system-ui;cursor:pointer;margin:5px 0}.dnct-desktop-permission{transition:background .25s,border-color .25s,box-shadow .25s,color .25s}.dnct-desktop-permission.is-active{background:#7c3aed33;border-color:#a78bfa99;box-shadow:0 0 14px #8b5cf633;color:#ede9fe}.dnct-permission-dot{display:inline-block;width:8px;height:8px;margin-right:7px;border-radius:50%;background:#ef4444;transition:background-color .25s,box-shadow .25s}.dnct-desktop-permission.is-active .dnct-permission-dot{background:#22c55e;box-shadow:0 0 7px #22c55e44}.dnct-desktop-permission:hover{background:#a78bfa23}.dnct-desktop-permission:disabled{opacity:.6;cursor:default}
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
 function play(type){if(enabled()&&(!ctx||ctx.state!=='running')&&(window.DnctSound?.ctx?.state==='running'||navigator.userActivation?.hasBeenActive))unlock();if(!enabled()||!ctx||ctx.state!=='running'||Date.now()-lastAudio<700)return false;lastAudio=Date.now();if(type!=='message')document.dispatchEvent(new Event('dnct:notification-sound'));
 // Jeden zestaw tonów dla całej strony: przepisy z sound.js (toasty, przełącznik). Własne tony tylko awaryjnie.
 if(window.DnctSound?.playIn?.(ctx,{info:'info',success:'success',warning:'warning',error:'error',system:'ring',message:'message'}[type]||'ring'))return true;
 for(const [delay,freq,duration] of recipes[type]||recipes.system){const o=ctx.createOscillator(),g=ctx.createGain(),start=ctx.currentTime+delay;o.type=type==='error'?'triangle':'sine';o.frequency.value=freq;g.gain.setValueAtTime(.0001,start);g.gain.exponentialRampToValueAtTime(.08,start+.015);g.gain.exponentialRampToValueAtTime(.0001,start+duration);o.connect(g);g.connect(ctx.destination);o.start(start);o.stop(start+duration+.03);o.onended=()=>{o.disconnect();g.disconnect();};}return true;}
 async function exclusive(fn){if(navigator.locks)return navigator.locks.request(scope,{mode:'exclusive'},fn);return fn();}
 function badges(){const trigger=document.getElementById('dnct-support-chat-btn');if(trigger){let badge=trigger.querySelector('.dnct-unread-counter');if(!badge){badge=document.createElement('span');badge.className='dm-unread-dot dnct-unread-counter';badge.setAttribute('aria-hidden','true');trigger.append(badge);}const count=chatCount+ticketCount;badge.textContent=count>99?'99+':String(count);badge.classList.toggle('is-visible',count>0);trigger.classList.toggle('has-unread',count>0);trigger.setAttribute('aria-label',count?'Czat: '+count+' nieprzeczytanych wiadomości':'Otwórz czat');}
 document.querySelectorAll('[data-dm-support],[data-dm-support-mobile]').forEach(button=>{button.querySelector('.dnct-ticket-dot')?.remove();button.classList.toggle('dnct-ticket-unread',ticketCount>0);button.title=ticketCount?'Zgłoś problem — nowa odpowiedź w zgłoszeniu':'Zgłoś problem';});}

 // Ten sam przełącznik co w stopce dzwonka (DNCTDesktop, notifications.js); stan trzymany na koncie, więc
 // włączenie na stronie działa też w panelu. Dawniej localStorage per domena i twarda blokada game/panel.
 function permissionButton(){const host=document.querySelector('#dnct-messenger .dm-connection');if(!host||document.querySelector('.dnct-desktop-permission'))return;const b=document.createElement('button');b.type='button';b.className='dnct-desktop-permission';
 const sync=()=>{const i=window.DNCTDesktop?.info();b.hidden=!i||!i.supported;if(!i)return;b.replaceChildren();const dot=document.createElement('span');dot.className='dnct-permission-dot';dot.setAttribute('aria-hidden','true');b.append(dot,document.createTextNode('Powiadomienia systemowe'));b.classList.toggle('is-active',i.on);b.setAttribute('aria-pressed',String(i.on));b.disabled=i.permission==='denied';b.title=i.permission==='denied'?'Odblokuj powiadomienia przy kłódce obok adresu strony':i.on?'Wyłącz powiadomienia systemowe':'Włącz powiadomienia systemowe';};
 b.onclick=async()=>{await window.DNCTDesktop?.toggle();sync();if(!matchMedia('(prefers-reduced-motion: reduce)').matches)b.animate([{transform:'scale(1)'},{transform:'scale(1.04)'},{transform:'scale(1)'}],{duration:320});};host.after(b);sync();document.addEventListener('dnct:desktop-state',sync);}

 async function message(key,name,body,open,muted=false,isViewing=()=>false){if(muted||seenMessages.has(key))return;seenMessages.add(key);await exclusive(()=>{const seen=read(':seen',{}),now=Date.now();if(seen[key])return;for(const id in seen)if(now-seen[id]>86400000)delete seen[id];seen[key]=now;write(':seen',seen);if(!document.hidden&&isViewing())return;play('message');
 // Powiadomienie systemowe i prośba o zgodę: notifications.js (DNCTDesktop) - ten sam stan konta na stronie i w panelu.
 window.DNCTDesktop?.offer();window.DNCTDesktop?.notify(key,{title:key.startsWith('ticket:')?'Zgłoszenie | DNCT.PL':'Czaty | DNCT.PL',body:String(name||'Użytkownik')+'\n'+preview(body),onclick:()=>open?.()});});}
 // `markdown` żyje w innym module (DNCTChat) - dawniej ReferenceError w try{} po cichu blokował powiadomienia czatu.
 function preview(body){const chat=window.DNCTChat;try{if(chat?.e2e?.isEnvelope?.(body))return 'Wiadomość zaszyfrowana';return chat?.plain?.(body)||String(body||'').trim()||'Załącznik';}catch{return 'Nowa wiadomość';}}
 const notificationKey=n=>String(n.audience||n.target_type||'normal')+':'+n.id;
 const kind=n=>(n.author||n.category==='announcements'||n.category==='announcement'||n.category==='announcements_marketing')?(recipes[n.type]?n.type:'info'):'system';
 async function soundFor(items,reminder=false){if(!items.length)return;await exclusive(()=>{const ack=read(':opened',{});items=items.filter(n=>!ack[notificationKey(n)]);if(!items.length||Date.now()-read(':soundAt',0)<(reminder?30000:2500))return;const types=new Set(items.map(kind));if(play(['error','warning','success','info','system'].find(t=>types.has(t))||'system'))write(':soundAt',Date.now());});}
 function notifications(items){const unread=items.filter(n=>!n.is_read);const newest=Math.max(0,...items.map(n=>Number(n.id)||0));const fresh=notificationBaseline===null?[]:unread.filter(n=>Number(n.id)>notificationBaseline);notificationBaseline=Math.max(notificationBaseline||0,newest);pending=unread.filter(n=>!acknowledged.has(notificationKey(n)));if(fresh.length){soundFor(fresh);const desk=window.DNCTDesktop;if(desk){desk.offer();for(const n of fresh.slice(0,3))desk.notify('n:'+Number(n.id),{title:n.title||'Powiadomienie | DNCT.PL',body:n.message,onclick:()=>desk.open(n)});if(fresh.length>3)desk.notify('nsum:'+Number(fresh[3].id),{title:'Powiadomienia | DNCT.PL',body:'Masz jeszcze '+(fresh.length-3)+' nowe powiadomienia.',onclick:()=>desk.open(fresh[3])});}}}
 function opened(items){const ack=read(':opened',{}),now=Date.now();for(const key in ack)if(now-ack[key]>2592000000)delete ack[key];for(const n of items){acknowledged.add(notificationKey(n));ack[notificationKey(n)]=now;}write(':opened',ack);pending=pending.filter(n=>!acknowledged.has(notificationKey(n)));}
 // Bez przypominajki co 30 s: "otwarte" jest zapisywane osobno na każdej domenie (dnct.pl, panel, beta),
 // więc panel pikał w kółko za powiadomienia obejrzane już na stronie. Dźwięk tylko przy nowym.

 window.DNCTAlerts={message,notifications,opened,chat:n=>{chatCount=Math.max(0,Number(n)||0);badges();permissionButton();},ticket:n=>{ticketCount=Math.max(0,Number(n)||0);badges();},play};
})();
