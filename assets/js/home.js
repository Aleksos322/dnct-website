/* Strona główna (index.php): konsola w karcie serwera, przechył karty
   i wejście kafli "O hostingu" w kadr. Wygląd: assets/css/home.css.

   Konsola ma WŁASNY znacznik (data-home-console) - panel-band.js szuka
   pierwszego [data-role="consoleBody"] na stronie i drugi taki element
   zabrałby mu konsolę z pasma "Zobacz, zanim zapłacisz". */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --- Konsola w karcie ---------------------------------------------- */
    var LINES = [
        ['12:04:01', 'Starting minecraft server version 26.2', ''],
        ['12:04:01', 'Loading properties', ''],
        ['12:04:02', 'Preparing level "world"', ''],
        ['12:04:05', 'Preparing spawn area: 84%', ''],
        ['12:04:06', 'Done (6.2s)! For help, type "help"', 'ok'],
        ['12:04:31', 'Kasia joined the game', ''],
        ['', '> say Witajcie na serwerze!', 'cmd'],
        ['12:04:40', '[Server] Witajcie na serwerze!', ''],
        ['12:05:12', 'Kopia zapasowa gotowa (412 MB)', 'ok']
    ];

    var box = document.querySelector('[data-home-console]');
    if (box) {
        var cursor = document.createElement('span');
        cursor.className = 'cur';

        var line = function (e) {
            var row = document.createElement('div');
            if (e[0]) {
                var t = document.createElement('span');
                t.className = 't';
                t.textContent = '[' + e[0] + '] ';
                row.appendChild(t);
            }
            var m = document.createElement('span');
            if (e[2]) m.className = e[2];
            m.textContent = e[1];
            row.appendChild(m);
            box.appendChild(row);
            box.scrollTop = box.scrollHeight;
        };

        if (reduce) {
            LINES.forEach(line);
        } else {
            var i = 0, timer = null, running = false;
            var step = function () {
                if (i >= LINES.length) {
                    timer = setTimeout(function () { box.textContent = ''; i = 0; step(); }, 3800);
                    return;
                }
                if (cursor.parentNode) cursor.parentNode.removeChild(cursor);
                line(LINES[i]);
                box.lastChild.appendChild(cursor);
                i += 1;
                timer = setTimeout(step, LINES[i - 1][2] === 'cmd' ? 900 : 450 + Math.random() * 400);
            };
            // Poza ekranem konsola stoi - nic nie liczy w tle.
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (en) {
                    if (en[0].isIntersecting && !running) { running = true; step(); }
                    else if (!en[0].isIntersecting && running) { running = false; clearTimeout(timer); }
                }).observe(box);
            } else {
                step();
            }
        }
    }

    /* --- Przechył karty ------------------------------------------------- */
    /* Ten sam wzór co attachChromaTilt w cennik.js, łącznie z przeliczeniem
       na scroll: Lenis potrafi dojechać kartę pod nieruchomy kursor, a wtedy
       bez tego przechył zostawał zamrożony na starej wartości. */
    var card = document.querySelector('[data-home-tilt]');
    if (card && !reduce && window.matchMedia('(pointer: fine)').matches) {
        var lastX = null, lastY = null, queued = false, MAX = 5;
        var apply = function () {
            queued = false;
            if (lastX === null) return;
            var r = card.getBoundingClientRect();
            var inside = lastX >= r.left && lastX <= r.right && lastY >= r.top && lastY <= r.bottom;
            if (!inside) { card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); return; }
            var px = (lastX - r.left) / r.width, py = (lastY - r.top) / r.height;
            card.style.setProperty('--ry', ((px - 0.5) * 2 * MAX).toFixed(2) + 'deg');
            card.style.setProperty('--rx', ((0.5 - py) * 2 * MAX).toFixed(2) + 'deg');
        };
        var schedule = function () { if (!queued) { queued = true; requestAnimationFrame(apply); } };
        card.addEventListener('mousemove', function (e) { lastX = e.clientX; lastY = e.clientY; schedule(); });
        card.addEventListener('mouseleave', function () { lastX = null; card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); });
        window.addEventListener('scroll', function () { if (lastX !== null) schedule(); }, { passive: true });
    }

    /* --- Wejście kafli w kadr ------------------------------------------ */
    var tiles = document.querySelectorAll('.home-reveal');
    if (tiles.length && !reduce && 'IntersectionObserver' in window) {
        document.documentElement.classList.add('home-js');
        var io = new IntersectionObserver(function (en) {
            en.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.classList.add('is-in');
                io.unobserve(e.target);
            });
        }, { threshold: 0.15 });
        tiles.forEach(function (t) { io.observe(t); });
    }
})();
