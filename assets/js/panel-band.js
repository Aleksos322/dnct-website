/**
 * Pasmo "Zobacz, zanim zapłacisz": animowana konsola i podgląd zwiastuna.
 *
 * Wydzielone z cennik.js, bo pasmo żyje teraz na DWÓCH stronach - w cenniku
 * i na stronie głównej (components/panel-band.php). Ładowanie całego skryptu
 * cennika na stronie głównej ciągnęłoby ze sobą zakładki gier, konfigurator
 * slotów i paralaksę dekoracji, czyli kod, który nie ma tam czego obsługiwać.
 */
(function () {
    'use strict';

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    var CONSOLE_LINES = [
        ['12:04:01', 'Starting minecraft server version 26.2', ''],
        ['12:04:01', 'Loading properties', ''],
        ['12:04:02', 'Preparing level "world"', ''],
        ['12:04:05', 'Preparing spawn area: 84%', ''],
        ['12:04:08', 'Done (6.812s)! For help, type "help"', 'ok'],
        ['12:04:19', 'Steve joined the game', 'i'],
        ['12:05:02', 'Saving chunks for level "world"', ''],
        ['12:05:02', 'Backup ukończony - 412 MB', 'ok']
    ];

    function initConsole() {
        var body = document.querySelector('[data-role="consoleBody"]');
        if (!body) return;

        function line(entry) {
            var row = document.createElement('div');

            var time = document.createElement('span');
            time.className = 't';
            time.textContent = '[' + entry[0] + '] ';
            row.appendChild(time);

            var msg = document.createElement('span');
            if (entry[2]) msg.className = entry[2];
            // textContent, nie innerHTML - treść jest nasza, ale wstawianie
            // znaczników do konsoli to nawyk, który kiedyś się zemści.
            msg.textContent = entry[1];
            row.appendChild(msg);

            body.appendChild(row);
        }

        if (reducedMotion()) {
            CONSOLE_LINES.forEach(line);
            return;
        }

        var i = 0;
        var cursor = document.createElement('span');
        cursor.className = 'cur';

        (function step() {
            if (i >= CONSOLE_LINES.length) {
                // Pętla: po chwili konsola zaczyna od nowa, żeby pasmo nie
                // zastygało po pierwszym przejściu.
                window.setTimeout(function () {
                    body.textContent = '';
                    i = 0;
                    step();
                }, 4000);
                return;
            }

            if (cursor.parentNode) cursor.parentNode.removeChild(cursor);
            line(CONSOLE_LINES[i]);
            body.lastChild.appendChild(cursor);
            i += 1;

            window.setTimeout(step, 600 + Math.random() * 500);
        })();
    }

    function initTrailer() {
        var btn = document.querySelector('.pnl-trailer');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-yt');
            if (!id) return;

            var frame = document.createElement('iframe');
            frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
            frame.title = 'Zwiastun DNCT Hosting';
            frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
            frame.setAttribute('allowfullscreen', '');

            btn.textContent = '';
            btn.appendChild(frame);
            btn.classList.add('is-playing');
        }, { once: true });
    }

    /**
     * Makieta panelu z zakładkami (components/panel-band.php, [data-pnl-mock]).
     *
     * Zakładki przełączają się same co PNL_MS. Kliknięcie wstrzymuje pętlę na
     * PNL_PAUZA_MS, żeby nikt nie tracił zakładki spod palca, a potem pętla
     * wraca sama. Wcześniej klik zatrzymywał ją na zawsze i po jednym
     * przypadkowym kliknięciu (np. "Start") makieta wyglądała na zepsutą.
     * Pod kursorem pętla stoi, poza ekranem nic nie liczy. Scenka rusza od
     * początku przy każdym wejściu: zdjęcie .is-active + reflow + założenie.
     */
    var PNL_MS = 6000;
    var PNL_PAUZA_MS = 8000;

    function initMock() {
        var mock = document.querySelector('[data-pnl-mock]');
        if (!mock) return;

        var tabs = Array.prototype.slice.call(mock.querySelectorAll('[data-pnl-tab]'));
        var scenes = mock.querySelectorAll('[data-pnl-scene]');
        var title = mock.querySelector('[data-pnl-title]');
        var points = document.querySelectorAll('[data-pnl-goto]');
        var current = 0, timer = null, visible = false, hovered = false, chosen = false, resume = null;

        mock.style.setProperty('--pnl-ms', PNL_MS + 'ms');

        function show(i) {
            current = (i + tabs.length) % tabs.length;
            var key = tabs[current].getAttribute('data-pnl-tab');
            tabs.forEach(function (t, n) {
                t.classList.toggle('is-active', n === current);
                t.setAttribute('aria-selected', n === current ? 'true' : 'false');
            });
            for (var s = 0; s < scenes.length; s++) scenes[s].classList.remove('is-active');
            void mock.offsetWidth;   // restart animacji CSS w wracającej scenie
            var scene = mock.querySelector('[data-pnl-scene="' + key + '"]');
            if (scene) scene.classList.add('is-active');
            if (title) title.textContent = tabs[current].textContent.trim();
            // Na telefonie zakładki to przewijany pasek - aktywna ma być widoczna.
            // scrollTo na samym pasku, nie scrollIntoView: to drugie ruszałoby całą stroną.
            var nav = tabs[current].parentNode;
            if (nav.scrollWidth > nav.clientWidth) {
                nav.scrollTo({ left: tabs[current].offsetLeft - 8, behavior: reducedMotion() ? 'auto' : 'smooth' });
            }
            for (var p = 0; p < points.length; p++) {
                points[p].classList.toggle('is-linked', points[p].getAttribute('data-pnl-goto') === key);
            }
        }

        function stop() { clearTimeout(timer); timer = null; mock.classList.remove('is-playing'); }
        function play() {
            stop();
            if (chosen || reducedMotion() || !visible || hovered || document.hidden) return;
            void mock.offsetWidth;
            mock.classList.add('is-playing');
            timer = setTimeout(function () { show(current + 1); play(); }, PNL_MS);
        }
        function choose(i) {
            chosen = true;
            stop();
            show(i);
            clearTimeout(resume);
            resume = setTimeout(function () { chosen = false; play(); }, PNL_PAUZA_MS);
        }

        tabs.forEach(function (t, n) {
            t.addEventListener('click', function () { choose(n); });
        });
        // Klawiatura w tablist: strzałki góra/dół i lewo/prawo.
        mock.querySelector('[role="tablist"]').addEventListener('keydown', function (e) {
            var d = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
            if (!d) return;
            e.preventDefault();
            choose(current + d);
            tabs[current].focus();
        });
        for (var p = 0; p < points.length; p++) {
            points[p].addEventListener('click', function () {
                var key = this.getAttribute('data-pnl-goto');
                var n = tabs.findIndex(function (t) { return t.getAttribute('data-pnl-tab') === key; });
                if (n >= 0) choose(n);
            });
        }

        /* Start / Restart / Stop naprawdę coś robią w makiecie: przełączają na
           konsolę i dopisują do niej, co by się stało na serwerze. */
        var POWER = {
            start:   [['Starting minecraft server version 26.2', ''], ['Done (6.2s)! For help, type "help"', 'ok']],
            restart: [['Stopping the server', 'i'], ['Saving chunks for level "world"', ''], ['Starting minecraft server version 26.2', ''], ['Done (5.9s)! For help, type "help"', 'ok']],
            stop:    [['Stopping the server', 'i'], ['Saving chunks for level "world"', ''], ['Serwer zatrzymany', 'i']]
        };
        mock.querySelectorAll('[data-pnl-power]').forEach(function (b) {
            b.addEventListener('click', function () {
                choose(0);
                var body = mock.querySelector('[data-role="consoleBody"]');
                if (!body) return;
                (POWER[b.getAttribute('data-pnl-power')] || []).forEach(function (l, k) {
                    setTimeout(function () {
                        var row = document.createElement('div');
                        var t = document.createElement('span');
                        t.className = 't';
                        t.textContent = '[' + new Date().toLocaleTimeString('pl-PL') + '] ';
                        var m = document.createElement('span');
                        if (l[1]) m.className = l[1];
                        m.textContent = l[0];
                        row.appendChild(t);
                        row.appendChild(m);
                        body.appendChild(row);
                        body.scrollTop = body.scrollHeight;
                    }, k * 450);
                });
            });
        });

        mock.addEventListener('mouseenter', function () { hovered = true; mock.classList.add('is-paused'); stop(); });
        mock.addEventListener('mouseleave', function () { hovered = false; mock.classList.remove('is-paused'); play(); });
        document.addEventListener('visibilitychange', play);

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) {
                var was = visible;
                visible = en[0].isIntersecting;
                if (visible && !was) { show(current); play(); } else if (!visible) { stop(); }
            }, { threshold: 0.35 }).observe(mock);
        } else {
            visible = true;
            play();
        }
    }

    initConsole();
    initTrailer();
    initMock();
})();
