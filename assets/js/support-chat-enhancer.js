/* Enhances the existing support widget without taking over its ticket/form UI. */
(() => {
  'use strict';
  const panel = document.querySelector('#dnct-support-chat-panel');
  if (!panel || !window.DNCTSupportRealtime || !window.DNCTSupportOutbox) return;
  let ticket = null, realtime = null, connectedTicket = 0, outbox = null, lastRead = 0, typing = false;
  const esc = value => { const node = document.createElement('span'); node.textContent = String(value || ''); return node.innerHTML; };
  const messageKey = () => { const bytes = new Uint8Array(32); crypto.getRandomValues(bytes); return [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join(''); };
  const csrf = () => panel.dataset.csrf || document.querySelector("meta[name=\"csrf-token\"]")?.content || window.csrfToken || "";
  const request = (url, options = {}) => fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
  const composer = () => panel.querySelector('#dnct-ticket-reply');
  const thread = () => panel.querySelector('.dnct-ticket-thread');
  const ownMessage = (body, key, state = 'Wysyłanie…') => {
    const target = thread(); if (!target || target.querySelector(`[data-message-key="${key}"]`)) return;
    const row = document.createElement('div'); row.className = 'dnct-ticket-row mine'; row.dataset.messageKey = key;
    row.innerHTML = `<div class="dnct-ticket-bubble">${esc(body)}<small class="dnct-message-state">${state}</small></div>`;
    target.append(row); target.scrollTop = target.scrollHeight;
  };
  const acknowledge = message => {
    const target = thread(); if (!target || !message) return;
    const found = message.client_message_key && target.querySelector(`[data-message-key="${message.client_message_key}"]`);
    if (found) { found.dataset.messageId = String(message.id || ''); const status = found.querySelector('.dnct-message-state'); if (status) status.textContent = 'Wysłano'; return; }
    if (message.id && target.querySelector(`[data-message-id="${message.id}"]`)) return;
    const mine = message.author_role === 'customer';
    const row = document.createElement('div'); row.className = `dnct-ticket-row ${mine ? 'mine' : 'admin'}`; row.dataset.messageId = String(message.id || '');
    row.innerHTML = `<div class="dnct-ticket-bubble"><span class="dnct-ticket-author">${mine ? "" : (message.author_name || "Administracja")}</span>${esc(message.body)}</div>`;
    target.append(row); target.scrollTop = target.scrollHeight;
  };
  const notice = text => {
    let element = panel.querySelector('.dnct-live-notice');
    if (!element) { element = document.createElement('p'); element.className = 'dnct-live-notice'; panel.querySelector('#dnct-ticket-body')?.prepend(element); }
    element.textContent = text; element.hidden = !text;
  };
  const typingIndicator = (active, name) => { const target = thread(); if (!target) return; let node = target.querySelector(".dnct-typing-row"); if (!active) { node?.remove(); return; } if (!node) { node = document.createElement("div"); node.className = "dnct-typing-row"; node.innerHTML = '<span class="dnct-ticket-author"></span><span class="dnct-typing-bubble"><i class="dnct-typing-dot"></i><i class="dnct-typing-dot"></i><i class="dnct-typing-dot"></i></span>'; target.append(node); } node.querySelector(".dnct-ticket-author").textContent = name || "Administracja"; target.scrollTop = target.scrollHeight; };
  const markRead = () => {
    const target = thread(); if (!target || !ticket) return;
    const ids = [...target.querySelectorAll('[data-message-id]')].map(node => Number(node.dataset.messageId)).filter(Boolean);
    const newest = Math.max(0, ...ids); if (!newest || newest <= lastRead) return; lastRead = newest;
    if (realtime?.read(newest)) return;
    request('/api/support/realtime.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ action: 'read', ticket_id: ticket.id, last_message_id: newest, csrf_token: csrf() }) }).catch(() => {});
  };
  async function fallbackSend(entry) {
    const response = await request('/api/support/ticket.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ action: 'message', ticket_id: ticket.id, message: entry.body, message_key: entry.key, reply_to_id: Number(entry.reply_to_id || 0), csrf_token: csrf() }) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.message || 'Nie udało się wysłać wiadomości.');
    await outbox.remove(entry.key);
    const row = panel.querySelector(`[data-message-key="${entry.key}"]`); const state = row?.querySelector('.dnct-message-state'); if (state) state.textContent = 'Wysłano'; if (entry.reply_to_id) panel.dispatchEvent(new Event('dnct:reply-sent'));
  }
  async function flushOutbox() {
    if (!ticket || ticket.status !== 'open' || !ticket.assigned_admin || !outbox || !navigator.onLine) return;
    for (const entry of await outbox.list()) {
      if (!entry.reply_to_id && realtime?.sendMessage(entry.body, entry.key)) continue;
      try { await fallbackSend(entry); } catch (_) { const row=panel.querySelector(`[data-message-key="${entry.key}"] .dnct-message-state`); if(row)row.textContent="Nie wysłano — ponawiam…"; window.setTimeout(flushOutbox,2000); break; }
    }
  }
  function connect(currentTicket) {
    if (!currentTicket || connectedTicket === Number(currentTicket.id)) return;
    realtime?.close(); ticket = currentTicket; connectedTicket = Number(ticket.id); outbox = new window.DNCTSupportOutbox(`customer:${ticket.id}`);
    realtime = new window.DNCTSupportRealtime({
      tokenUrl: '/api/support/realtime.php',
      onReady: () => { notice('Połączono z czatem na żywo.'); flushOutbox(); markRead(); },
      onError: text => notice(text),
      onEvent: data => {
        if (data.type === 'message' || data.type === 'message_ack') { acknowledge(data.message); if (data.message?.client_message_key) outbox?.remove(data.message.client_message_key); markRead(); }
        if (data.type === 'refresh' || data.type === 'ticket') request('/api/support/ticket.php?limit=50').then(response => response.json()).then(payload => { ticket = payload.ticket || ticket; }).catch(() => {});
        if (data.type === 'typing' && data.participant_role === 'admin') typingIndicator(Boolean(data.active), data.participant_name || 'Administracja');
        if (data.type === 'presence' && data.participant_role === 'admin') notice(data.online ? 'Administrator jest online.' : 'Administrator jest offline.');
      },
    });
    realtime.connect(); flushOutbox();
  }
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.id !== 'dnct-ticket-reply') return;
    const field = form.querySelector("textarea"); const body = field?.value.trim();
    if (!body || !ticket || !outbox) return;
    event.preventDefault(); event.stopImmediatePropagation();
    const key = messageKey(), entry = { key, body, reply_to_id: Number(form.querySelector("[name=reply_to_id]")?.value || 0) };
    field.value = ''; ownMessage(body, key); if (entry.reply_to_id) { fallbackSend(entry).then(() => panel.dispatchEvent(new Event('dnct:reply-sent'))).catch(() => { const row = panel.querySelector(`[data-message-key="${key}"] .dnct-message-state`); if (row) row.textContent = 'Nie wysłano — ponawiam…'; outbox.put(entry).then(flushOutbox); }); return; } outbox.put(entry).then(() => { if (!realtime?.sendMessage(body, key)) flushOutbox(); });
  }, true);
  document.addEventListener('input', event => {
    if (!(event.target instanceof HTMLTextAreaElement) || !event.target.closest('#dnct-ticket-reply')) return;
    const active = event.target.value.trim().length > 0;
    if (active !== typing) { typing = active; realtime?.typing(active); }
  }, true);
  const observer = new MutationObserver(() => {
    const form = composer(); if (!form) return;
    request('/api/support/ticket.php?limit=50').then(response => response.json()).then(payload => { if (payload.ok && payload.ticket) { ticket = payload.ticket; connect(ticket); markRead(); } }).catch(() => {});
  });
  observer.observe(panel, { childList: true, subtree: true });
  window.addEventListener('online', () => { realtime?.connect(); flushOutbox(); });
})();
