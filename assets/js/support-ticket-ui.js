(() => {
  'use strict';
  const panel = document.getElementById('dnct-support-chat-panel');
  const trigger = document.getElementById('dnct-support-chat-btn');
  const config = window.DNCTSupportChat;
  if (!panel || !trigger || !config) return;

  const api = '/api/support/ticket.php';
  const esc = value => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const key = () => [...crypto.getRandomValues(new Uint8Array(32))].map(byte => byte.toString(16).padStart(2, '0')).join('');
  const standard = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
  const allEmoji = ['😀','😄','😂','🥹','😍','😮','😢','😡','👍','👎','❤️','🔥','🎉','🙏','✅','👀','💯','🚀','🤝','✨','💜','🤔','😎','👏'];
  let payload = null, replyTo = null, menuFor = null, files = [], editing = null, lastSignature = '', ended = false;

  function post(action, values = {}, upload = []) {
    const form = new FormData();
    form.append('action', action); form.append('csrf_token', config.csrf);
    Object.entries(values).forEach(([name, value]) => form.append(name, String(value ?? '')));
    upload.forEach(file => form.append('attachments[]', file, file.name));
    return fetch(api, {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': config.csrf, Accept: 'application/json'}, body: form})
      .then(async response => { const data = await response.json().catch(() => null); if (!response.ok || !data?.ok) throw new Error(data?.message || 'Nie udało się zapisać zmian.'); return data; });
  }
  function load() {
    return fetch(api + '?limit=50', {credentials: 'same-origin', cache: 'no-store'})
      .then(response => response.json()).then(data => { if (!data?.ok) throw new Error(data?.message || 'Nie udało się odświeżyć czatu.'); setPayload(data); return data; });
  }
  function setPayload(next) {
    if (payload?.ticket && !next.ticket) ended = true;
    payload = next; const signature = JSON.stringify([next.ticket, next.messages, ended]);
    if (signature !== lastSignature) { lastSignature = signature; render(); }
  }
  function isMine(message) { return message.author_role === 'customer'; }
  function favouriteEmoji() {
    try { return JSON.parse(localStorage.getItem('dnct-support-favourites') || '[]').filter(emoji => allEmoji.includes(emoji)).slice(0, 6); } catch (_) { return []; }
  }
  function remember(emoji) {
    const next = [emoji, ...favouriteEmoji().filter(value => value !== emoji)].slice(0, 6);
    localStorage.setItem('dnct-support-favourites', JSON.stringify(next));
  }
  function attachments(message) {
    return (message.attachments || []).length ? '<div class="sc-attachments">' + message.attachments.map(item => {
      const kind = String(item.media_type || item.kind || '').includes('video') ? 'video' : 'image';
      const url = esc(item.url || ''); const name = esc(item.original_name || item.name || 'Załącznik');
      return '<button type="button" class="sc-media" data-media-url="' + url + '" data-media-kind="' + kind + '" data-media-name="' + name + '">' +
        (kind === 'video' ? '<video src="' + url + '" muted playsinline preload="metadata"></video>' : '<img src="' + url + '" alt="">') + '<span>' + name + '</span></button>';
    }).join('') + '</div>' : '';
  }
  function reactions(message) {
    const existing = (message.reactions || []).map(item => '<button type="button" class="sc-reaction' + (item.mine ? ' is-mine' : '') + '" data-react="' + esc(item.emoji) + '" data-id="' + Number(message.id) + '">' + esc(item.emoji) + ' ' + Number(item.count) + '</button>').join('');
    return existing ? '<div class="sc-reactions">' + existing + '</div>' : '';
  }
  function quote(message) {
    const reply = message.reply;
    if (!reply && !message.reply_to_message_id) return '';
    const author = reply?.author_name || message.reply_author_name || 'Użytkownik';
    const text = reply?.withdrawn_at ? 'Wiadomość została cofnięta.' : (reply?.body || message.reply_body || 'Załącznik');
    return '<div class="sc-reply-quote"><b>' + esc(author) + '</b> · ' + esc(text) + '</div>';
  }
  function emojiMenu(message) {
    const chosen = favouriteEmoji(); const choices = [...new Set([...chosen, ...standard])].slice(0, 6);
    const full = menuFor === 'emoji:' + message.id;
    return '<div class="sc-reaction-picker' + (menuFor === 'react:' + message.id ? ' is-open' : '') + '">' +
      '<button type="button" class="sc-action" data-open-reactions="' + Number(message.id) + '" aria-label="Dodaj reakcję">☺</button>' +
      (menuFor === 'react:' + message.id ? '<div class="sc-emoji-menu"><div class="sc-emoji-row">' + choices.map(emoji => '<button type="button" data-react="' + emoji + '" data-id="' + Number(message.id) + '">' + emoji + '</button>').join('') + '</div><button type="button" class="sc-emoji-more" data-full-emoji="' + Number(message.id) + '">＋ Więcej emoji</button>' + (full ? fullEmoji(message.id) : '') + '</div>' : '') + '</div>';
  }
  function fullEmoji(id) {
    return '<div class="sc-emoji-full"><input type="search" data-emoji-search placeholder="Wyszukaj emoji" aria-label="Wyszukaj emoji"><div class="sc-emoji-row" data-emoji-results>' + allEmoji.map(emoji => '<button type="button" data-react="' + emoji + '" data-id="' + Number(id) + '">' + emoji + '</button>').join('') + '</div></div>';
  }
  function messageMarkup(message) {
    const mine = isMine(message), withdrawn = Boolean(message.withdrawn_at || message.is_withdrawn);
    const author = mine ? '' : '<span class="sc-author">' + esc(message.author_name || 'Administracja') + '</span>';
    const menu = mine && !withdrawn ? '<div class="sc-more"><button type="button" class="sc-action" data-more="' + Number(message.id) + '" aria-label="Opcje wiadomości">⋮</button>' + (menuFor === 'more:' + message.id ? '<div class="sc-menu"><button type="button" data-edit="' + Number(message.id) + '">Edytuj</button><button type="button" class="sc-danger" data-withdraw="' + Number(message.id) + '">Cofnij wysłanie</button></div>' : '') + '</div>' : '';
    const content = editing === message.id ? '<textarea class="sc-edit" data-edit-input="' + Number(message.id) + '">' + esc(message.body) + '</textarea><div class="sc-edit-actions"><button type="button" data-cancel-edit>Anuluj</button><button type="button" data-save-edit="' + Number(message.id) + '">Zapisz</button></div>' : '<div class="sc-bubble' + (withdrawn ? ' sc-bubble--withdrawn' : '') + '">' + esc(withdrawn ? 'Wiadomość została cofnięta.' : message.body) + attachments(message) + '</div>';
    return '<article class="sc-row' + (mine ? ' mine' : '') + '" data-message="' + Number(message.id) + '"><div class="sc-message-wrap">' + author + quote(message) + '<div class="sc-line">' + (mine ? menu + emojiMenu(message) + '<button type="button" class="sc-action sc-reply" data-reply="' + Number(message.id) + '" aria-label="Odpowiedz">↩</button>' + content : content + '<button type="button" class="sc-action sc-reply" data-reply="' + Number(message.id) + '" aria-label="Odpowiedz">↩</button>' + emojiMenu(message)) + '</div>' + reactions(message) + (message.edited_at && !withdrawn ? '<small class="sc-edited">Edytowano</small>' : '') + '<small class="sc-time">' + time(message.created_at) + '</small></div></article>';
  }
  function time(value) { const date = new Date(String(value || '').replace(' ', 'T')); return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString('pl-PL', {hour: '2-digit', minute: '2-digit'}); }
  function renderFiles() {
    return files.length ? '<div class="sc-attach-preview">' + files.map((file, index) => '<div class="sc-file">' + (file.type.startsWith('video/') ? '<video src="' + file.url + '" muted playsinline></video>' : '<img src="' + file.url + '" alt="">') + '<span>' + esc(file.file.name) + '</span><button type="button" data-remove-file="' + index + '" aria-label="Usuń plik">×</button></div>').join('') + '</div>' : '';
  }
  function composer() {
    const reply = replyTo ? '<div class="sc-reply-preview"><span><b>Odpowiadasz: ' + esc(replyTo.author_name || 'Użytkownik') + '</b> · ' + esc(replyTo.body || 'Załącznik') + '</span><button type="button" data-cancel-reply aria-label="Anuluj odpowiedź">×</button></div>' : '';
    return '<form class="sc-compose" data-compose>' + reply + renderFiles() + '<div class="sc-compose-box"><button type="button" class="sc-compose-button" data-quote-last aria-label="Cytuj ostatnią wiadomość">↩</button><button type="button" class="sc-compose-button" data-compose-emoji aria-label="Wstaw emoji">☺</button><textarea name="message" rows="1" maxlength="2000" placeholder="Napisz wiadomość…" aria-label="Wiadomość"></textarea><input type="file" data-files accept="image/*,video/*" multiple hidden><button class="sc-compose-button" data-add-file type="button" aria-label="Dodaj zdjęcie lub film">＋</button><button class="sc-compose-button sc-send" type="submit" aria-label="Wyślij">➤</button></div><p class="sc-compose-hint">Enter — wyślij · Shift + Enter — nowa linia</p></form>';
  }
  function closed(ticket) {
    const reason = ticket.closed_by_user && ticket.close_reason ? '<p>Powód: ' + esc(ticket.close_reason) + '</p>' : '';
    return '<div class="sc-closed"><strong>Ticket został zamknięty</strong>' + reason + '<p>Nie możesz już wysyłać nowych wiadomości w tej rozmowie.</p></div>';
  }
  function form() {
    let options = '<option value="" selected disabled>W czym możemy pomóc?</option>';
    Object.entries(config.categories).forEach(([group, entries]) => { options += '<optgroup label="' + esc(group) + '">' + Object.entries(entries).map(([value, label]) => '<option value="' + esc(value) + '">' + esc(label) + '</option>').join('') + '</optgroup>'; });
    return '<form class="sc-form" data-create><label>Imię<input name="name" required maxlength="60" value="' + esc(config.profile.name) + '"></label><label>Adres e-mail<input name="email" required type="email" value="' + esc(config.profile.email) + '"></label><label>Potrzebuję pomocy<select name="category" required>' + options + '</select></label><label>Wiadomość<textarea name="message" required minlength="20" maxlength="2000" placeholder="Wprowadź swoją wiadomość"></textarea></label><p class="sc-error" hidden></p><button class="sc-primary">Rozpocznij rozmowę</button></form>';
  }
  function render(force = false) {
    const body = panel.querySelector('.dnct-support-chat-body'); if (!body) return;
    if (ended) { body.innerHTML = '<div class="sc-app"><div class="sc-thread">' + closed({}) + '</div></div>'; return; }
    if (!payload?.ticket) { body.innerHTML = form(); return; }
    const ticket = payload.ticket, messages = payload.messages || [];
    const inactive = ticket.status === 'closed' ? closed(ticket) : '<div class="sc-closed"><strong>Zgłoszenie zostało wysłane</strong><p>Poczekaj, aż administrator przypisze zgłoszenie i odpowie.</p></div>';
    const top = '<div class="sc-app"><div class="sc-thread"><div class="sc-messages"><p class="sc-day">Rozmowa</p>' + messages.map(messageMarkup).join('') + (!messages.length ? '<p class="sc-empty">Brak wiadomości.</p>' : '') + '</div>' + (ticket.status === 'open' ? '<div class="sc-ticket-tools"><button type="button" class="sc-close-ticket" data-close-ticket>Zamknij zgłoszenie</button></div>' : '') + (ticket.status === 'open' ? composer() : inactive) + '</div></div>';
    body.innerHTML = top;
    const scroller = body.querySelector('.sc-messages'); if (scroller) scroller.scrollTop = scroller.scrollHeight;
  }
  function addFiles(list) {
    for (const file of [...list]) {
      if (!/^(image|video)\//.test(file.type) || files.length >= 4) continue;
      files.push({file, url: URL.createObjectURL(file)});
    }
    render();
  }
  function removeFile(index) { if (files[index]) URL.revokeObjectURL(files[index].url); files.splice(index, 1); render(); }
  function openLightbox(url, kind, name) {
    const element = document.createElement('div'); element.className = 'sc-lightbox';
    element.innerHTML = '<div class="sc-lightbox-card"><button type="button" class="sc-lightbox-close" aria-label="Zamknij podgląd">×</button>' + (kind === 'video' ? '<video src="' + url + '" controls autoplay playsinline></video>' : '<img src="' + url + '" alt="' + name + '">') + '</div>';
    document.body.append(element); element.addEventListener('click', event => { if (event.target === element || event.target.closest('.sc-lightbox-close')) { element.querySelector('video')?.pause(); element.remove(); } });
  }
  function openCloseDialog() {
    const dialog = document.createElement('div'); dialog.className = 'sc-dialog';
    dialog.innerHTML = '<form class="sc-dialog-card"><h3>Zamknąć zgłoszenie?</h3><textarea required minlength="3" maxlength="500" placeholder="Podaj powód zamknięcia"></textarea><p class="sc-error" hidden></p><div class="sc-dialog-actions"><button type="button" class="sc-secondary" data-dialog-cancel>Anuluj</button><button class="sc-primary">Zamknij zgłoszenie</button></div></form>';
    document.body.append(dialog); dialog.querySelector('[data-dialog-cancel]').onclick = () => dialog.remove();
    dialog.querySelector('form').onsubmit = async event => { event.preventDefault(); const reason = dialog.querySelector('textarea').value.trim(); try { setPayload(await post('close_by_customer', {ticket_id: payload.ticket.id, reason})); dialog.remove(); } catch (error) { const target = dialog.querySelector('.sc-error'); target.textContent = error.message; target.hidden = false; } };
  }
  async function react(id, emoji) { remember(emoji); menuFor = null; setPayload(await post('react', {ticket_id: payload.ticket.id, message_id: id, emoji})); }
  function withdraw(id) {
    const dialog = document.createElement('div'); dialog.className = 'sc-dialog';
    dialog.innerHTML = '<form class="sc-dialog-card"><h3>Cofnąć wysłanie wiadomości?</h3><p class="sc-state">Wiadomość zostanie zastąpiona informacją o cofnięciu u wszystkich uczestników.</p><div class="sc-dialog-actions"><button type="button" class="sc-secondary" data-dialog-cancel>Anuluj</button><button class="sc-primary">Cofnij wysłanie</button></div></form>';
    document.body.append(dialog); dialog.querySelector('[data-dialog-cancel]').onclick = () => dialog.remove();
    dialog.querySelector('form').onsubmit = async event => { event.preventDefault(); try { setPayload(await post('withdraw_message', {ticket_id: payload.ticket.id, message_id: id, scope: 'everyone'})); dialog.remove(); } catch (error) { alert(error.message); } };
  }
  document.addEventListener('click', async event => {
    const target = event.target.closest('button'); if (!target) return;
    try {
      if (target.matches('[data-reply]')) { replyTo = payload.messages.find(message => Number(message.id) === Number(target.dataset.reply)); render(); panel.querySelector('[name="message"]')?.focus(); }
      else if (target.matches('[data-quote-last]')) { replyTo = payload.messages.at(-1) || null; render(); panel.querySelector('[name="message"]')?.focus(); }
      else if (target.matches('[data-cancel-reply]')) { replyTo = null; render(); }
      else if (target.matches('[data-more]')) { menuFor = menuFor === 'more:' + target.dataset.more ? null : 'more:' + target.dataset.more; render(); }
      else if (target.matches('[data-open-reactions]')) { menuFor = menuFor === 'react:' + target.dataset.openReactions ? null : 'react:' + target.dataset.openReactions; render(); }
      else if (target.matches('[data-full-emoji]')) { menuFor = 'emoji:' + target.dataset.fullEmoji; const row = document.querySelector('[data-message="' + target.dataset.fullEmoji + '"]'); if (row) { const picker = row.querySelector('.sc-reaction-picker'); if (picker) picker.insertAdjacentHTML('beforeend', '<div class="sc-emoji-menu">' + fullEmoji(target.dataset.fullEmoji) + '</div>'); } }
      else if (target.matches('[data-react]')) await react(Number(target.dataset.id), target.dataset.react);
      else if (target.matches('[data-edit]')) { editing = Number(target.dataset.edit); menuFor = null; render(); }
      else if (target.matches('[data-cancel-edit]')) { editing = null; render(); }
      else if (target.matches('[data-save-edit]')) { const field = panel.querySelector('[data-edit-input="' + target.dataset.saveEdit + '"]'); setPayload(await post('edit_message', {ticket_id: payload.ticket.id, message_id: target.dataset.saveEdit, message: field.value})); editing = null; }
      else if (target.matches('[data-withdraw]')) withdraw(Number(target.dataset.withdraw));
      else if (target.matches('[data-add-file]')) panel.querySelector('[data-files]')?.click();
      else if (target.matches('[data-remove-file]')) removeFile(Number(target.dataset.removeFile));
      else if (target.matches('[data-close-ticket]')) openCloseDialog();
      else if (target.matches('[data-compose-emoji]')) { const field = panel.querySelector('[name="message"]'); if (field) { field.value += '😀'; field.focus(); } }
      else if (target.matches('.sc-media')) openLightbox(target.dataset.mediaUrl, target.dataset.mediaKind, target.dataset.mediaName);
    } catch (error) { alert(error.message); }
  });
  panel.addEventListener('input', event => {
    if (event.target.matches('[data-files]')) addFiles(event.target.files);
    if (event.target.matches('[data-emoji-search]')) { const query = event.target.value.trim(); const result = event.target.closest('.sc-emoji-full')?.querySelector('[data-emoji-results]'); if (result) result.innerHTML = allEmoji.filter(emoji => !query || emoji.includes(query)).map(emoji => '<button type="button" data-react="' + emoji + '" data-id="' + (menuFor.split(':')[1] || '') + '">' + emoji + '</button>').join('') || '<small>Brak emoji</small>'; }
    if (event.target.matches('[name="message"]')) { event.target.style.height = 'auto'; event.target.style.height = Math.min(110, event.target.scrollHeight) + 'px'; }
  });
  panel.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    event.preventDefault();
    try {
      if (form.matches('[data-create]')) { const result = await post('create', {...Object.fromEntries(new FormData(form)), request_key: key()}); setPayload(result); }
      if (form.matches('[data-compose]')) { const text = form.elements.message.value.trim(); if (!text && !files.length) return; const result = await post('message', {ticket_id: payload.ticket.id, message: text, message_key: key(), reply_to_id: replyTo?.id || 0}, files.map(entry => entry.file)); files.forEach(entry => URL.revokeObjectURL(entry.url)); files = []; replyTo = null; setPayload(result); }
    } catch (error) { alert(error.message); }
  });
  panel.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && event.target.matches('[name="message"]')) { event.preventDefault(); event.target.closest('form').requestSubmit(); } });
  panel.addEventListener('wheel', event => event.stopPropagation(), {passive: true});
  trigger.querySelector('span')?.replaceChildren(document.createTextNode('Czat'));
  const observer = new MutationObserver(() => { if (panel.classList.contains('is-open')) load().catch(() => {}); });
  observer.observe(panel, {attributes: true, attributeFilter: ['class']});
  window.setInterval(() => { if (panel.classList.contains('is-open') && !document.hidden) load().catch(() => {}); }, 2500);
  load().catch(() => {});
})();
