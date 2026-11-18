/*
 * assets/js/push-sw.js - service worker powiadomień systemowych DNCT (Web Push).
 *
 * Odbiera push także przy zamkniętej karcie. Na game.dnct.pl i w panelu jest
 * podawany przez most (/messaging/assets/js/push-sw.js), więc jego zakres to
 * ten katalog - do odbierania push i klikania w powiadomienia to wystarczy.
 * Wysyłkę robi backend/tools/push-worker.php.
 */
'use strict';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));

self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let data;
        try { data = event.data.json(); } catch (e) { return; }
        if (!data || typeof data.title !== 'string') return;
        // Karta tej domeny na wierzchu = widzisz dzwonek i czat, systemowe byłoby dublem.
        // Przeglądarki nie wymagają wtedy pokazania powiadomienia dla push.
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        if (windows.some(c => c.visibilityState === 'visible' && c.focused)) return;
        await self.registration.showNotification(data.title.slice(0, 120), {
            body: String(data.body || '').slice(0, 240),
            tag: String(data.tag || 'dnct'),
            icon: typeof data.icon === 'string' ? data.icon : undefined,
            data: { url: typeof data.url === 'string' ? data.url : '/' },
        });
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        let url;
        try { url = new URL(event.notification.data?.url || '/', self.location.origin); } catch (e) { url = new URL('/', self.location.origin); }
        if (!/^https:$/.test(url.protocol) || !/(^|\.)dnct\.pl$/.test(url.hostname)) url = new URL('/', self.location.origin);
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        // Strona główna danej domeny: wystarczy wyciągnąć na wierzch otwartą kartę (dzwonek/czat są na każdej).
        const home = url.pathname === '/' && !url.search && url.origin === self.location.origin;
        const open = windows.find(c => (home ? new URL(c.url).origin === url.origin : c.url === url.href));
        if (open) { await open.focus(); return; }
        await self.clients.openWindow(url.href);
    })());
});
