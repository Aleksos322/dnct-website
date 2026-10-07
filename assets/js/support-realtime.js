/* global indexedDB */
(() => {
  'use strict';

  const DB_NAME = 'dnct-support-outbox';
  const STORE = 'messages';
  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

  class OfflineOutbox {
    constructor(scope) { this.scope = scope; this.dbPromise = null; }
    db() {
      if (!('indexedDB' in window)) return Promise.resolve(null);
      if (this.dbPromise) return this.dbPromise;
      this.dbPromise = new Promise(resolve => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
          const db = request.result;
          if (!db.objectStoreNames.contains(STORE)) db.createObjectStore(STORE, { keyPath: 'key' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
      });
      return this.dbPromise;
    }
    async put(item) {
      const db = await this.db(); if (!db) return;
      await new Promise(resolve => { const tx = db.transaction(STORE, 'readwrite'); tx.objectStore(STORE).put({ ...item, scope: this.scope, queuedAt: Date.now() }); tx.oncomplete = tx.onerror = tx.onabort = resolve; });
    }
    async remove(key) {
      const db = await this.db(); if (!db) return;
      await new Promise(resolve => { const tx = db.transaction(STORE, 'readwrite'); tx.objectStore(STORE).delete(key); tx.oncomplete = tx.onerror = tx.onabort = resolve; });
    }
    async list() {
      const db = await this.db(); if (!db) return [];
      return new Promise(resolve => { const tx = db.transaction(STORE, 'readonly'); const request = tx.objectStore(STORE).getAll(); request.onsuccess = () => resolve((request.result || []).filter(item => item.scope === this.scope)); request.onerror = () => resolve([]); });
    }
  }

  class SupportRealtime {
    constructor(options) {
      this.options = options;
      this.socket = null;
      this.ready = false;
      this.stopped = false;
      this.retry = 0;
      this.timer = null;
      this.heartbeat = null;
      this.typingTimer = null;
    }
    async connect() {
      if (this.stopped || this.socket || !navigator.onLine) return;
      try {
        const response = await fetch(this.options.tokenUrl, { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.ok || !data.token || !data.ws_url) throw new Error(data.message || 'Nie udało się pobrać biletu czatu.');
        const socket = new WebSocket(data.ws_url);
        this.socket = socket;
        socket.onopen = () => socket.send(JSON.stringify({ type: 'auth', token: data.token }));
        socket.onmessage = event => this.onMessage(event);
        socket.onerror = () => socket.close();
        socket.onclose = () => this.onClose();
      } catch (_) { this.scheduleReconnect(); }
    }
    onMessage(event) {
      let data;
      try { data = JSON.parse(event.data); } catch (_) { return; }
      if (data.type === 'ready') {
        this.ready = true; this.retry = 0;
        this.heartbeat = window.setInterval(() => this.send({ type: 'ping' }), 25000);
        if (typeof this.options.onReady === 'function') this.options.onReady(data);
        return;
      }
      if (data.type === 'error' && typeof this.options.onError === 'function') this.options.onError(data.message || 'Błąd kanału rozmowy.');
      if (typeof this.options.onEvent === 'function') this.options.onEvent(data);
    }
    onClose() {
      this.socket = null; this.ready = false;
      if (this.heartbeat) window.clearInterval(this.heartbeat);
      this.heartbeat = null;
      if (!this.stopped) this.scheduleReconnect();
    }
    scheduleReconnect() {
      if (this.stopped || this.timer) return;
      const delay = Math.min(10000, 500 * (2 ** this.retry++));
      this.timer = window.setTimeout(() => { this.timer = null; this.connect(); }, delay);
    }
    send(payload) {
      if (!this.ready || !this.socket || this.socket.readyState !== WebSocket.OPEN) return false;
      this.socket.send(JSON.stringify(payload)); return true;
    }
    sendMessage(message, clientMessageKey) { return this.send({ type: 'send_message', message, client_message_key: clientMessageKey }); }
    typing(active) {
      this.send({ type: 'typing', active: Boolean(active) });
      if (this.typingTimer) window.clearTimeout(this.typingTimer);
      if (active) this.typingTimer = window.setTimeout(() => this.typing(false), 3500);
    }
    read(lastMessageId) { return this.send({ type: 'read_receipt', last_message_id: lastMessageId }); }
    close() {
      this.stopped = true;
      if (this.timer) window.clearTimeout(this.timer);
      if (this.typingTimer) window.clearTimeout(this.typingTimer);
      if (this.heartbeat) window.clearInterval(this.heartbeat);
      if (this.socket) this.socket.close();
    }
  }

  window.DNCTSupportRealtime = SupportRealtime;
  window.DNCTSupportOutbox = OfflineOutbox;
  window.DNCTSupportSleep = sleep;
})();
