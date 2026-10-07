import { createServer } from 'node:http';
import { readFileSync } from 'node:fs';
import crypto from 'node:crypto';
import mysql from 'mysql2/promise';
import { WebSocketServer, WebSocket } from 'ws';

const PORT = Number(process.env.SUPPORT_REALTIME_PORT || 8981);
const ENV_PATH = process.env.DNCT_ENV_FILE || '/var/www/dnct.pl/config/beta.env';
const ORIGINS = new Set((process.env.SUPPORT_WS_ALLOWED_ORIGINS || 'https://beta.dnct.pl,http://admin.lan.dnct.pl,https://admin.lan.dnct.pl').split(',').map(value => value.trim()).filter(Boolean));
const EVENT_INTERVAL_MS = 350;
const MAX_BODY = 2000;
const RATE_WINDOW_MS = 30000;
const RATE_MAX_MESSAGES = 10;

function envFile(path) {
  const result = {};
  for (const sourceLine of readFileSync(path, 'utf8').split(/\r?\n/)) {
    const line = sourceLine.trim();
    if (!line || line.startsWith('#') || !line.includes('=')) continue;
    const position = line.indexOf('=');
    let value = line.slice(position + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) value = value.slice(1, -1);
    result[line.slice(0, position).trim()] = value;
  }
  return result;
}

const config = envFile(ENV_PATH);
const database = mysql.createPool({
  host: config.MY_DB_HOST,
  port: Number(config.MY_DB_PORT || 3306),
  database: config.MY_DB_DATABASE,
  user: config.MY_DB_USERNAME,
  password: config.MY_DB_PASSWORD,
  waitForConnections: true,
  connectionLimit: 10,
  enableKeepAlive: true,
  keepAliveInitialDelay: 0,
  charset: 'utf8mb4',
});

const rooms = new Map();
let eventCursor = 0;

function send(socket, payload) {
  if (socket.readyState === WebSocket.OPEN) socket.send(JSON.stringify(payload));
}
function roomFor(ticketId) {
  const key = String(ticketId);
  if (!rooms.has(key)) rooms.set(key, new Set());
  return rooms.get(key);
}
function broadcast(ticketId, payload, except = null) {
  for (const socket of roomFor(ticketId)) if (socket !== except) send(socket, payload);
}
function onlineRole(ticketId, role) {
  for (const socket of roomFor(ticketId)) if (socket.support?.role === role && socket.readyState === WebSocket.OPEN) return true;
  return false;
}
function publishPresence(ticketId, role, online) {
  broadcast(ticketId, { type: 'presence', participant_role: role, online });
}
function parseMessage(raw) {
  try { const data = JSON.parse(raw.toString()); return data && typeof data === 'object' ? data : null; } catch { return null; }
}
function validMessageKey(value) { return typeof value === 'string' && /^[a-f0-9]{64}$/.test(value); }
function validBody(value) { return typeof value === 'string' && value.trim().length > 0 && [...value.trim()].length <= MAX_BODY; }
function allowedToWrite(ticket, support) {
  return ticket.status === 'open' && ((support.role === 'admin' && ticket.assigned_admin === support.participantKey) || support.role === 'customer');
}

async function authenticate(token) {
  if (typeof token !== 'string' || token.length < 32 || token.length > 128) return null;
  const tokenHash = crypto.createHash('sha256').update(token).digest('hex');
  const [rows] = await database.execute(
    `SELECT rt.ticket_id, rt.participant_role, rt.participant_key, st.status, st.assigned_admin
       FROM support_realtime_tokens rt JOIN support_tickets st ON st.id = rt.ticket_id
      WHERE rt.token_hash = ? AND rt.expires_at > NOW() LIMIT 1`, [tokenHash]
  );
  const support = rows[0];
  if (!support) return null;
  if (support.participant_role === 'admin' && (support.status !== 'open' || support.assigned_admin !== support.participant_key)) return null;
  if (support.participant_role === 'customer' && !['new', 'open'].includes(support.status)) return null;
  return { ticketId: Number(support.ticket_id), role: support.participant_role, participantKey: support.participant_key };
}

async function persistEvent(ticketId, eventType, payload = null) {
  await database.execute('INSERT INTO support_realtime_events(ticket_id, event_type, payload) VALUES(?, ?, ?)', [ticketId, eventType, payload ? JSON.stringify(payload) : null]);
}
async function acceptMessage(socket, data) {
  const support = socket.support;
  const body = typeof data.message === 'string' ? data.message.trim() : '';
  const clientMessageKey = data.client_message_key;
  if (!validBody(body) || !validMessageKey(clientMessageKey)) { send(socket, { type: 'error', code: 'invalid_message', message: 'Wiadomość musi mieć od 1 do 2000 znaków.' }); return; }
  const now = Date.now();
  socket.messageTimes = (socket.messageTimes || []).filter(time => now - time < RATE_WINDOW_MS);
  if (socket.messageTimes.length >= RATE_MAX_MESSAGES) { send(socket, { type: 'error', code: 'rate_limited', message: 'Wysyłasz wiadomości zbyt szybko.' }); return; }
  socket.messageTimes.push(now);
  const connection = await database.getConnection();
  try {
    await connection.beginTransaction();
    const [tickets] = await connection.execute('SELECT id, status, assigned_admin, customer_name FROM support_tickets WHERE id = ? FOR UPDATE', [support.ticketId]);
    const ticket = tickets[0];
    if (!ticket || !allowedToWrite(ticket, support)) { await connection.rollback(); send(socket, { type: 'error', code: 'not_writable', message: 'Ta rozmowa nie jest już aktywna.' }); return; }
    const authorName = support.role === 'admin' ? support.participantKey : ticket.customer_name;
    await connection.execute('INSERT IGNORE INTO support_messages(ticket_id, author_role, author_name, body, client_message_key) VALUES (?, ?, ?, ?, ?)', [support.ticketId, support.role, authorName, body, clientMessageKey]);
    const [messages] = await connection.execute('SELECT id, author_role, author_name, body, client_message_key, created_at FROM support_messages WHERE ticket_id = ? AND client_message_key = ? LIMIT 1', [support.ticketId, clientMessageKey]);
    const message = messages[0];
    if (!message) throw new Error('Wiadomość nie została zapisana.');
    await connection.execute('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?', [support.ticketId]);
    await connection.execute('INSERT INTO support_realtime_events(ticket_id, event_type, payload) VALUES (?, ?, ?)', [support.ticketId, 'message', JSON.stringify({ message_id: Number(message.id) })]);
    await connection.commit();
    const payload = { type: 'message', message: { ...message, id: Number(message.id) } };
    send(socket, { type: 'message_ack', message: payload.message });
    broadcast(support.ticketId, payload, socket);
  } catch (error) {
    try { await connection.rollback(); } catch {}
    console.error('support realtime message error', error);
    send(socket, { type: 'error', code: 'server_error', message: 'Nie udało się zapisać wiadomości.' });
  } finally { connection.release(); }
}
async function persistReadReceipt(socket, data) {
  const support = socket.support;
  const lastMessageId = Number(data.last_message_id);
  if (!Number.isSafeInteger(lastMessageId) || lastMessageId < 1) return;
  const [exists] = await database.execute('SELECT id FROM support_messages WHERE ticket_id = ? AND id = ? LIMIT 1', [support.ticketId, lastMessageId]);
  if (!exists[0]) return;
  await database.execute(
    `INSERT INTO support_message_receipts(ticket_id, participant_role, participant_key, last_message_id, read_at)
     VALUES(?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_message_id = GREATEST(last_message_id, VALUES(last_message_id)), read_at = NOW()`,
    [support.ticketId, support.role, support.participantKey, lastMessageId]
  );
  const payload = { type: 'read_receipt', participant_role: support.role, participant_key: support.participantKey, last_message_id: lastMessageId };
  await persistEvent(support.ticketId, 'read_receipt', payload);
  broadcast(support.ticketId, payload, socket);
}

const httpServer = createServer((_, response) => { response.writeHead(404); response.end(); });
const websocket = new WebSocketServer({ noServer: true, maxPayload: 16 * 1024 });
httpServer.on('upgrade', (request, socket, head) => {
  const url = new URL(request.url || '/', 'http://localhost');
  const origin = String(request.headers.origin || '');
  if (url.pathname !== '/ws/support' || !ORIGINS.has(origin)) { socket.write('HTTP/1.1 403 Forbidden\r\n\r\n'); socket.destroy(); return; }
  websocket.handleUpgrade(request, socket, head, ws => websocket.emit('connection', ws));
});
websocket.on('connection', socket => {
  let authenticated = false;
  const authTimeout = setTimeout(() => { if (!authenticated) socket.close(4401, 'authentication timeout'); }, 10000);
  socket.isAlive = true;
  socket.on('pong', () => { socket.isAlive = true; });
  socket.on('message', async raw => {
    const data = parseMessage(raw); if (!data) return;
    try {
      if (!authenticated) {
        if (data.type !== 'auth') { socket.close(4401, 'authentication required'); return; }
        const support = await authenticate(data.token);
        if (!support) { socket.close(4403, 'invalid token'); return; }
        authenticated = true; clearTimeout(authTimeout); socket.support = support; roomFor(support.ticketId).add(socket);
        send(socket, { type: 'ready', ticket_id: support.ticketId, role: support.role });
        publishPresence(support.ticketId, support.role, true);
        return;
      }
      if (data.type === 'ping') { send(socket, { type: 'pong' }); return; }
      if (data.type === 'typing') { broadcast(socket.support.ticketId, { type: 'typing', participant_role: socket.support.role, active: Boolean(data.active) }, socket); return; }
      if (data.type === 'read_receipt') { await persistReadReceipt(socket, data); return; }
      if (data.type === 'send_message') { await acceptMessage(socket, data); }
    } catch (error) {
      console.error('support realtime socket error', error);
      send(socket, { type: 'error', code: 'server_error', message: 'Chwilowy błąd kanału rozmowy.' });
    }
  });
  socket.on('close', () => {
    clearTimeout(authTimeout);
    if (!socket.support) return;
    const { ticketId, role } = socket.support;
    roomFor(ticketId).delete(socket);
    if (roomFor(ticketId).size === 0) rooms.delete(String(ticketId));
    if (!onlineRole(ticketId, role)) publishPresence(ticketId, role, false);
  });
});

setInterval(async () => {
  for (const socket of websocket.clients) {
    if (!socket.isAlive) { socket.terminate(); continue; }
    socket.isAlive = false; socket.ping();
  }
}, 30000).unref();

async function pollEvents() {
  try {
    const [rows] = await database.execute('SELECT id, ticket_id, event_type, payload FROM support_realtime_events WHERE id > ? ORDER BY id ASC LIMIT 200', [eventCursor]);
    for (const row of rows) {
      eventCursor = Number(row.id);
      let payload = null; try { payload = row.payload ? JSON.parse(row.payload) : null; } catch {}
      if (row.event_type === 'message') broadcast(Number(row.ticket_id), { type: 'refresh', reason: 'message', ...payload });
      else broadcast(Number(row.ticket_id), { type: row.event_type, ...(payload || {}) });
    }
  } catch (error) { console.error('support realtime event poll error', error); }
}

await database.query('SELECT id FROM support_realtime_events ORDER BY id DESC LIMIT 1').then(([rows]) => { eventCursor = Number(rows[0]?.id || 0); });
setInterval(pollEvents, EVENT_INTERVAL_MS).unref();
httpServer.listen(PORT, '127.0.0.1', () => console.log(`DNCT support realtime listening on 127.0.0.1:${PORT}`));
