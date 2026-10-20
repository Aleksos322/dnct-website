import { createServer } from 'node:http';
import { readFileSync } from 'node:fs';
import crypto from 'node:crypto';
import {rateHit,consumeGrant,sessionValid,configureIdentity,securityAudit} from './messaging-security.mjs';
import mysql from '../realtime/node_modules/mysql2/promise.js';
import { WebSocketServer, WebSocket } from '../realtime/node_modules/ws/wrapper.mjs';

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
configureIdentity(config);
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
let chatCursor = 0;
const chatClients = new Map();
function chatRoom(id) { if (!chatClients.has(id)) chatClients.set(id, new Set()); return chatClients.get(id); }
let pollingChat = false;
async function pollChat() {
  if (pollingChat) return;
  pollingChat = true;
  try {
    const [rows] = await database.execute('SELECT id,recipient_id,conversation_id FROM chat_events WHERE id>? ORDER BY id LIMIT 500',[chatCursor]);
    for (const row of rows) {
      chatCursor = Number(row.id);
      for (const socket of chatClients.get(row.recipient_id) || []) send(socket, {type:'chat_changed',conversation_id:Number(row.conversation_id)});
    }
  } catch (error) { console.error('messaging_security: chat_poll_failed'); }
  finally { pollingChat = false; }
}

function send(socket, payload) {
  if (socket.readyState === WebSocket.OPEN) { if(socket.bufferedAmount>262144){socket.close(4408,'slow consumer');return;} socket.send(JSON.stringify(payload)); }
}
function roomFor(ticketId) {
  const key = String(ticketId);
  if (!rooms.has(key)) rooms.set(key, new Set());
  return rooms.get(key);
}
function broadcast(ticketId, payload, except = null) {
  for (const socket of rooms.get(String(ticketId)) || []) if (socket !== except) send(socket, payload);
}
function onlineRole(ticketId, role) {
  for (const socket of rooms.get(String(ticketId)) || []) if (socket.support?.role === role && socket.readyState === WebSocket.OPEN) return true;
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

async function persistEvent(ticketId, eventType, payload = null) {
  await database.execute('INSERT INTO support_realtime_events(ticket_id, event_type, payload) VALUES(?, ?, ?)', [ticketId, eventType, payload ? JSON.stringify(payload) : null]);
}
async function acceptMessage(socket, data) {
  const support = socket.support;
  const body = typeof data.message === 'string' ? data.message.trim() : '';
  const clientMessageKey = data.client_message_key;
  if (!validBody(body) || !validMessageKey(clientMessageKey)) { send(socket, { type: 'error', code: 'invalid_message', message: 'Wiadomość musi mieć od 1 do 2000 znaków.' }); return; }
  const retry=await rateHit(database,socket.grant.subject_key);
  if(retry){await securityAudit(database,'ws_rate_limited',socket.grant);send(socket,{type:'error',code:'rate_limited',retry_after:retry,message:'Wysyłasz wiadomości zbyt szybko. Odczekaj '+retry+' s.'});return;}
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
    if (!message || message.author_role!==support.role || message.author_name!==authorName || message.body!==body) throw new Error('message key conflict');
    await connection.execute('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?', [support.ticketId]);
    await connection.execute('INSERT INTO support_realtime_events(ticket_id, event_type, payload) VALUES (?, ?, ?)', [support.ticketId, 'message', JSON.stringify({ message_id: Number(message.id) })]);
    await connection.commit();
    const payload = { type: 'message', message: { ...message, id: Number(message.id) } };
    send(socket, { type: 'message_ack', message: payload.message });
    broadcast(support.ticketId, payload, socket);
  } catch (error) {
    try { await connection.rollback(); } catch {}
    console.error('messaging_security: message_failed');
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
  if(websocket.clients.size>=300){socket.write('HTTP/1.1 503 Service Unavailable\r\n\r\n');socket.destroy();return;}
  websocket.handleUpgrade(request, socket, head, ws => websocket.emit('connection', ws));
});
websocket.on('connection', socket => {
  let authenticated = false, authAttempted = false, busy = false;
  socket.frameTimes=[];
  const authTimeout = setTimeout(() => { if (!authenticated) socket.close(4401, 'authentication timeout'); }, 10000);
  socket.on('error',()=>console.error('messaging_security: socket_transport_error'));
  socket.isAlive = true;
  socket.on('pong', () => { socket.isAlive = true; });
  socket.on('message', async raw => {
    const now=Date.now();socket.frameTimes=socket.frameTimes.filter(t=>t>now-1000);
    if(socket.frameTimes.length>=20){socket.close(4408,'frame limit');return;}socket.frameTimes.push(now);
    if(busy){send(socket,{type:'error',code:'busy',message:'Odczekaj chwilę.'});return;}
    busy=true;
    const data = parseMessage(raw); if (!data) {busy=false;socket.close(4400,'invalid payload');return;}
    try {
      if (!authenticated) {
        if(authAttempted){socket.close(4401,'authentication already attempted');return;}authAttempted=true;
        if (data.type !== 'auth') { socket.close(4401, 'authentication required'); return; }
        socket.grant=await consumeGrant(database,data.token,data.scope==='chat'?'chat':'support');
        if(!socket.grant||socket.readyState!==WebSocket.OPEN||!(await sessionValid(database,socket.grant))){await securityAudit(database,'ws_auth_denied');socket.close(4403,'invalid token');return;}
        if([...websocket.clients].filter(s=>s!==socket&&s.grant?.subject_key===socket.grant.subject_key).length>=6){socket.close(4408,'connection limit');return;}
        socket.chatExpiry=setTimeout(()=>socket.close(4401,'renew token'),300000);
        if (data.scope === 'chat') {
          const user = socket.grant.user_id;
          if (!user) { socket.close(4403, 'invalid chat token'); return; }
          authenticated = true; clearTimeout(authTimeout); socket.chatUser = user; chatRoom(user).add(socket);
          // Odnawianie uwierzytelnienia ogranicza czas życia połączenia.

          send(socket, {type:'ready',scope:'chat'});
          return;
        }
        const support={ticketId:Number(socket.grant.ticket_id),role:socket.grant.role,participantKey:socket.grant.participant_key};
        const [[ticket]]=await database.execute('SELECT status,assigned_admin FROM support_tickets WHERE id=?',[support.ticketId]);
        if(!ticket||!['new','open'].includes(ticket.status)||(support.role==='admin'&&!allowedToWrite(ticket,support))){socket.close(4403,'ticket access revoked');return;}
        if (!support) { socket.close(4403, 'invalid token'); return; }
        authenticated = true; clearTimeout(authTimeout); socket.support = support; roomFor(support.ticketId).add(socket);
        send(socket, { type: 'ready', ticket_id: support.ticketId, role: support.role });
        publishPresence(support.ticketId, support.role, true);
        return;
      }
      if(!(await sessionValid(database,socket.grant))){socket.close(4401,'session expired');return;}
      if (socket.support) { const [[t]]=await database.execute('SELECT status,assigned_admin FROM support_tickets WHERE id=?',[socket.support.ticketId]); if(!t||!['new','open'].includes(t.status)||(socket.support.role==='admin'&&!allowedToWrite(t,socket.support))){socket.close(4403,'ticket access revoked');return;} }
      if (data.type === 'ping') { send(socket, { type: 'pong' }); return; }
      if (socket.chatUser) return; // Wiadomości zapisuje API z kontrolą członkostwa i CSRF.
      if (data.type === 'typing') { broadcast(socket.support.ticketId, { type: 'typing', participant_role: socket.support.role, active: Boolean(data.active) }, socket); return; }
      if (data.type === 'read_receipt') { await persistReadReceipt(socket, data); return; }
      if (data.type === 'send_message') { await acceptMessage(socket, data); }
    } catch (error) {
      console.error('messaging_security: socket_failed');
      send(socket, { type: 'error', code: 'server_error', message: 'Chwilowy błąd kanału rozmowy.' });
    } finally {busy=false;}
  });
  socket.on('close', () => {
    clearTimeout(authTimeout);clearTimeout(socket.chatExpiry);
    if (socket.chatUser) {
      clearTimeout(socket.chatExpiry);
      const clients = chatClients.get(socket.chatUser);
      clients?.delete(socket);
      if (clients?.size === 0) chatClients.delete(socket.chatUser);
    }
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

let pollingEvents=false;
async function pollEvents() {
  if(pollingEvents)return;pollingEvents=true;
  try {
    const [rows] = await database.execute('SELECT id, ticket_id, event_type, payload FROM support_realtime_events WHERE id > ? ORDER BY id ASC LIMIT 200', [eventCursor]);
    for (const row of rows) {
      eventCursor = Number(row.id);
      let payload = null; try { payload = row.payload ? JSON.parse(row.payload) : null; } catch {}
      if (row.event_type === 'message') broadcast(Number(row.ticket_id), { type: 'refresh', reason: 'message', ...payload });
      else broadcast(Number(row.ticket_id), { type: row.event_type, ...(payload || {}) });
    }
  } catch (error) { console.error('messaging_security: event_poll_failed'); } finally {pollingEvents=false;}
}

await database.query('SELECT id FROM support_realtime_events ORDER BY id DESC LIMIT 1').then(([rows]) => { eventCursor = Number(rows[0]?.id || 0); });
await database.query('SELECT id FROM chat_events ORDER BY id DESC LIMIT 1').then(([rows]) => { chatCursor = Number(rows[0]?.id || 0); });
let checkingSessions=false;
setInterval(async()=>{
 if(checkingSessions)return;checkingSessions=true;
 try{
  const checked=new Map();
  for(const socket of websocket.clients){
   if(!socket.grant)continue;
   let valid=checked.get(socket.grant.session_hash);
   if(valid===undefined){valid=await sessionValid(database,socket.grant);checked.set(socket.grant.session_hash,valid);}
   if(!valid){await securityAudit(database,'ws_session_revoked',socket.grant);socket.close(4401,'session revoked');continue;}
   if(socket.support){
    const [[t]]=await database.execute('SELECT status,assigned_admin FROM support_tickets WHERE id=?',[socket.support.ticketId]);
    if(!t||!['new','open'].includes(t.status)||(socket.support.role==='admin'&&!allowedToWrite(t,socket.support)))socket.close(4403,'ticket access revoked');
   }
  }
 }catch{for(const socket of websocket.clients)socket.close(1011,'authorization unavailable');}
 finally{checkingSessions=false;}
},2000).unref();
setInterval(pollEvents, EVENT_INTERVAL_MS).unref();
setInterval(pollChat, EVENT_INTERVAL_MS).unref();
httpServer.listen(PORT, '127.0.0.1', () => console.log(`DNCT support realtime listening on 127.0.0.1:${PORT}`));
