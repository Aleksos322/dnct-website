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
        ['12:04:01', 'Starting minecraft server version 1.21.4', ''],
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

    initConsole();
    initTrailer();
})();
