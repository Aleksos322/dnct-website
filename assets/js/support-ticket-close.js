(() => {
  'use strict';
  const panel = document.getElementById('dnct-support-chat-panel');
  if (!panel) return;
  let ticket = null, busy = false, scheduled = false;
  const request = async (method = 'GET', payload = null) => {
    const csrf = panel.dataset.csrf || '';
    const response = await fetch('/api/support/ticket.php', {method, credentials: 'same-origin', cache: 'no-store', headers: method === 'POST' ? {'Content-Type': 'application/json', 'X-CSRF-Token': csrf} : {}, body: payload ? JSON.stringify({...payload, csrf_token: csrf}) : undefined});
    const data = await response.json().catch(() => null);
    if (!response.ok || !data?.ok) throw new Error(data?.message || 'Nie udało się zapisać zmian.');
    return data;
  };
  const dialog = document.createElement('dialog');
  dialog.className = 'dnct-ticket-close-dialog';
  dialog.innerHTML = '<form method="dialog"><header><strong>Zamknąć zgłoszenie?</strong><button value="cancel" aria-label="Zamknij">×</button></header><label>Powód zamknięcia<textarea name="reason" minlength="3" maxlength="500" required placeholder="Opisz krótko powód zamknięcia…"></textarea></label><p data-close-error hidden></p><footer><button value="cancel">Anuluj</button><button value="confirm">Zamknij zgłoszenie</button></footer></form>';
  document.body.append(dialog);
  const showClosed = () => { const state = panel.querySelector('.dnct-ticket-state'); if (state) state.textContent = 'Ticket został zamknięty'; panel.querySelector('#dnct-ticket-reply')?.remove(); panel.querySelector('.dnct-ticket-close-request')?.remove(); };
  const render = () => {
    if (!ticket) return;
    if (ticket.status === 'closed') { showClosed(); return; }
    if (!['new', 'open'].includes(ticket.status) || panel.querySelector('.dnct-ticket-close-request')) return;
    const conversation = panel.querySelector('.dnct-ticket-conversation'); if (!conversation) return;
    const button = document.createElement('button'); button.type = 'button'; button.className = 'dnct-ticket-close-request'; button.textContent = 'Zamknij zgłoszenie';
    button.onclick = () => { dialog.querySelector('textarea').value = ''; dialog.querySelector('[data-close-error]').hidden = true; dialog.showModal?.(); if (!dialog.open) dialog.setAttribute('open', ''); dialog.querySelector('textarea').focus(); };
    conversation.append(button);
  };
  const refresh = async () => { if (busy || panel.getAttribute('aria-hidden') === 'true') return; busy = true; try { const data = await request(); ticket = data.ticket || null; render(); } catch (_) {} finally { busy = false; } };
  const schedule = () => { if (scheduled) return; scheduled = true; requestAnimationFrame(() => { scheduled = false; refresh(); }); };
  new MutationObserver(schedule).observe(panel, {childList: true, subtree: true});
  dialog.addEventListener('submit', async event => {
    event.preventDefault(); const submitter = event.submitter; if (submitter?.value !== 'confirm') { dialog.close?.(); dialog.removeAttribute('open'); return; }
    const reason = dialog.querySelector('textarea').value.trim(), error = dialog.querySelector('[data-close-error]');
    if (reason.length < 3) { error.textContent = 'Podaj powód zamknięcia.'; error.hidden = false; return; }
    submitter.disabled = true;
    try { const data = await request('POST', {action: 'close_user', reason}); ticket = data.ticket || {status: 'closed'}; dialog.close?.(); dialog.removeAttribute('open'); showClosed(); }
    catch (exception) { error.textContent = exception.message; error.hidden = false; }
    finally { submitter.disabled = false; }
  });
  panel.addEventListener('click', () => setTimeout(refresh, 0)); setInterval(refresh, 2500);
})();
