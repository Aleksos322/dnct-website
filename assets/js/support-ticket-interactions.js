(() => {
  'use strict';
  const panel = document.getElementById('dnct-support-chat-panel');
  if (!panel) return;
  const standard = ['👍','❤️','😂','😮','😢','🙏'];
  const all = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😍','🥰','😘','😋','😎','🤔','🥹','😮','😢','😭','😡','👍','👎','❤️','🔥','🎉','🙏','✅','👀','💯','🚀','🤝','✨','💜','👏'];
  let payload = null, openFor = 0, loading = false, scheduled = false, withdrawId = 0;
  const esc = value => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const request = async (action = '', fields = null) => {
    const csrf = panel.dataset.csrf || '';
    const response = await fetch('/api/support/ticket.php', fields ? {method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}, body: JSON.stringify({action, ...fields, csrf_token: csrf})} : {credentials: 'same-origin', cache: 'no-store'});
    const data = await response.json().catch(() => null);
    if (!response.ok || !data?.ok) throw new Error(data?.message || 'Nie udało się zapisać zmian.');
    return data;
  };
  const message = id => payload?.messages?.find(item => Number(item.id) === Number(id)) || null;
  const favourites = () => { try { return JSON.parse(localStorage.getItem('dnct-ticket-favourite-emoji') || '[]').filter(emoji => all.includes(emoji)).slice(0, 6); } catch (_) { return []; } };
  const remember = emoji => localStorage.setItem('dnct-ticket-favourite-emoji', JSON.stringify([emoji, ...favourites().filter(item => item !== emoji)].slice(0, 6)));
  const popup = id => '<span class="dnct-ticket-emoji-popover"><span class="dnct-ticket-emoji-grid">'+[...new Set([...favourites(), ...standard])].slice(0,6).map(emoji => '<button type="button" data-ticket-emoji="'+emoji+'" data-ticket-message="'+id+'">'+emoji+'</button>').join('')+'</span><button type="button" class="dnct-ticket-emoji-more" data-ticket-emoji-more="'+id+'">＋ Więcej emoji</button></span>';
  const actions = item => '<span class="dnct-ticket-message-actions"><button type="button" class="dnct-ticket-message-react" data-ticket-react-open="'+item.id+'" aria-label="Dodaj reakcję" title="Dodaj reakcję">☺</button>'+((item.author_role === 'customer' && !item.is_withdrawn) ? '<button type="button" class="dnct-ticket-message-more" data-ticket-more="'+item.id+'" aria-label="Opcje wiadomości" title="Opcje wiadomości">⋯</button>' : '')+(openFor === Number(item.id) ? popup(item.id) : '')+'</span>';
  const chips = item => (item.reactions || []).map(reaction => '<button type="button" class="dnct-ticket-reaction-chip'+(reaction.mine ? ' is-mine' : '')+'" data-ticket-emoji="'+reaction.emoji+'" data-ticket-message="'+item.id+'">'+reaction.emoji+' '+reaction.count+'</button>').join('');
  const render = () => {
    if (!payload?.ticket) return;
    for (const item of payload.messages || []) {
      const row = panel.querySelector('[data-message-id="'+item.id+'"]'); if (!row) continue;
      const line = row.querySelector('.dnct-ticket-message-line'); if (!line) continue;
      line.querySelectorAll('.dnct-ticket-message-actions').forEach(node => node.remove());
      row.querySelectorAll('.dnct-ticket-reactions').forEach(node => node.remove());
      const bubble = line.querySelector('.dnct-ticket-bubble'); if (!bubble || item.is_withdrawn) continue;
      line.insertAdjacentHTML(item.author_role === 'customer' ? 'afterbegin' : 'beforeend', actions(item));
      const reactionHtml = chips(item); if (reactionHtml) line.insertAdjacentHTML('afterend', '<span class="dnct-ticket-reactions">'+reactionHtml+'</span>');
    }
  };
  const load = async () => {
    if (loading || panel.getAttribute('aria-hidden') === 'true' || !panel.querySelector('.dnct-ticket-thread')) return;
    loading = true; try { payload = await request(); render(); } catch (_) {} finally { loading = false; }
  };
  const schedule = () => { if (scheduled) return; scheduled = true; requestAnimationFrame(() => { scheduled = false; load(); }); };
  new MutationObserver(schedule).observe(panel, {childList: true, subtree: true});
  const dialog = document.createElement('dialog'); dialog.className = 'dnct-ticket-withdraw-dialog';
  dialog.innerHTML = '<form method="dialog"><header><strong>Cofnąć wysłanie wiadomości?</strong><button value="cancel" aria-label="Zamknij">×</button></header><label><input type="radio" name="ticket-withdraw-scope" value="everyone" checked> Cofnij wysłanie do wszystkich</label><label><input type="radio" name="ticket-withdraw-scope" value="me"> Ukryj tylko dla mnie</label><footer><button value="cancel">Anuluj</button><button value="confirm">Usuń</button></footer></form>';
  document.body.append(dialog);
  const closeDialog = () => { withdrawId = 0; dialog.close?.(); dialog.removeAttribute('open'); };
  const updateBubble = item => { const row = panel.querySelector('[data-message-id="'+item.id+'"]'); const bubble = row?.querySelector('.dnct-ticket-bubble'); if (!bubble) return; bubble.replaceChildren(); const text = document.createTextNode(item.body); bubble.append(text); if (item.edited_at && !item.is_withdrawn) { const note = document.createElement('small'); note.className = 'dnct-ticket-edited'; note.textContent = 'Edytowano'; bubble.append(note); } };
  dialog.addEventListener('submit', async event => { event.preventDefault(); if (event.submitter?.value !== 'confirm') { closeDialog(); return; } const scope = dialog.querySelector('input[name="ticket-withdraw-scope"]:checked')?.value === 'me' ? 'me' : 'everyone'; try { const result = await request('withdraw_message', {message_id: withdrawId, scope}); payload = result; const item = message(withdrawId); if (scope === 'me') panel.querySelector('[data-message-id="'+withdrawId+'"]')?.remove(); else if (item) { updateBubble(item); render(); } closeDialog(); } catch (error) { alert(error.message); } });
  panel.addEventListener('click', async event => {
    const open = event.target.closest('[data-ticket-react-open]'); if (open) { openFor = openFor === Number(open.dataset.ticketReactOpen) ? 0 : Number(open.dataset.ticketReactOpen); render(); return; }
    const full = event.target.closest('[data-ticket-emoji-more]'); if (full) { const popover = full.closest('.dnct-ticket-emoji-popover'); popover.innerHTML = '<input type="search" placeholder="Wyszukaj emoji" aria-label="Wyszukaj emoji"><span class="dnct-ticket-emoji-grid"></span>'; const input = popover.querySelector('input'), grid = popover.querySelector('.dnct-ticket-emoji-grid'); const fill = query => grid.innerHTML = all.filter(emoji => !query || emoji.includes(query)).map(emoji => '<button type="button" data-ticket-emoji="'+emoji+'" data-ticket-message="'+full.dataset.ticketEmojiMore+'">'+emoji+'</button>').join(''); input.addEventListener('input', () => fill(input.value)); fill(''); input.focus(); return; }
    const emoji = event.target.closest('[data-ticket-emoji]'); if (emoji) { try { remember(emoji.dataset.ticketEmoji); payload = await request('react', {message_id: Number(emoji.dataset.ticketMessage), emoji: emoji.dataset.ticketEmoji}); openFor = 0; render(); } catch (error) { alert(error.message); } return; }
    const more = event.target.closest('[data-ticket-more]'); if (more) { const host = more.closest('.dnct-ticket-message-actions'); let menu = host.querySelector('.dnct-ticket-message-menu'); if (menu) { menu.remove(); return; } menu = document.createElement('span'); menu.className = 'dnct-ticket-message-menu'; menu.innerHTML = '<button type="button" data-ticket-edit="'+more.dataset.ticketMore+'">Edytuj</button><button type="button" data-ticket-withdraw="'+more.dataset.ticketMore+'">Cofnij wysłanie</button>'; host.append(menu); return; }
    const edit = event.target.closest('[data-ticket-edit]'); if (edit) { const item = message(edit.dataset.ticketEdit), row = panel.querySelector('[data-message-id="'+edit.dataset.ticketEdit+'"]'), bubble = row?.querySelector('.dnct-ticket-bubble'); if (!item || !bubble) return; bubble.innerHTML = '<textarea class="dnct-ticket-inline-editor" maxlength="2000"></textarea><span><button type="button" data-ticket-cancel-edit>Anuluj</button><button type="button" data-ticket-save-edit="'+item.id+'">Zapisz</button></span>'; bubble.querySelector('textarea').value = item.body; bubble.querySelector('textarea').focus(); return; }
    const cancel = event.target.closest('[data-ticket-cancel-edit]'); if (cancel) { const item = message(cancel.closest('[data-message-id]')?.dataset.messageId); if (item) updateBubble(item); render(); return; }
    const save = event.target.closest('[data-ticket-save-edit]'); if (save) { const row = panel.querySelector('[data-message-id="'+save.dataset.ticketSaveEdit+'"]'), body = row?.querySelector('textarea')?.value.trim(); if (!body) return; try { payload = await request('edit_message', {message_id: Number(save.dataset.ticketSaveEdit), message: body}); const item = message(save.dataset.ticketSaveEdit); if (item) updateBubble(item); render(); } catch (error) { alert(error.message); } return; }
    const withdraw = event.target.closest('[data-ticket-withdraw]'); if (withdraw) { withdrawId = Number(withdraw.dataset.ticketWithdraw); dialog.querySelector('input[value="everyone"]').checked = true; dialog.showModal?.(); if (!dialog.open) dialog.setAttribute('open',''); }
  });
  panel.addEventListener('click', () => setTimeout(load, 0)); setInterval(load, 2500);
})();
