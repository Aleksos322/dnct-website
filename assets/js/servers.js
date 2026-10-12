/**
 * Twoje serwery: przedłużanie, zmiana pakietu, rezygnacja z abonamentu.
 *
 * Skrypt NIE LICZY ANI KWOT, ANI DAT. Wszystkie warianty przyszły policzone
 * z serwera w atrybutach `data-periods` i `data-plans`; tutaj jest wyłącznie
 * wybieranie z gotowej listy i wysłanie decyzji. Liczenie po stronie
 * przeglądarki oznaczałoby drugi wzór obok backend/lib/pricing.php i pewność,
 * że kiedyś pokażą co innego.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('srv-data');
    if (!dataEl) return;

    var csrf = JSON.parse(dataEl.textContent).csrf;

    document.querySelectorAll('.srv').forEach(initCard);

    function initCard(card) {
        var orderId = card.getAttribute('data-order');
        var elError = card.querySelector('[data-role="error"]');

        var periods = parse(card.getAttribute('data-periods'));
        var plans   = parse(card.getAttribute('data-plans'));

        var extendPanel = card.querySelector('[data-role="extendPanel"]');
        var plansPanel  = card.querySelector('[data-role="plansPanel"]');

        var state = { days: periods.length ? periods[0].days : null, mode: 'payment' };

        /* ---------------------------------------------------------------
           Rozwijanie paneli - naraz otwarty jest najwyżej jeden.
           Dwa rozwinięte panele na jednej karcie to ekran, z którego nie widać,
           co się właściwie wybiera.
           --------------------------------------------------------------- */
        bindToggle('toggleExtend', extendPanel, plansPanel);
        bindToggle('togglePlans', plansPanel, extendPanel);

        function bindToggle(role, mine, other) {
            var btn = card.querySelector('[data-role="' + role + '"]');
            if (!btn || !mine) return;

            btn.addEventListener('click', function () {
                var opening = mine.hidden;
                if (other) other.hidden = true;
                mine.hidden = !opening;
                btn.classList.toggle('is-open', opening);

                card.querySelectorAll('[data-role="toggleExtend"], [data-role="togglePlans"]').forEach(function (b) {
                    if (b !== btn) b.classList.remove('is-open');
                });

                if (opening) renderExtend();
            });
        }

        /* ---------------------------------------------------------------
           Przedłużanie
           --------------------------------------------------------------- */
        var elDays  = card.querySelector('[data-role="sumDays"]');
        var elUntil = card.querySelector('[data-role="sumUntil"]');
        var elPrice = card.querySelector('[data-role="sumPrice"]');
        var btnPay  = card.querySelector('[data-role="payExtend"]');

        function currentPeriod() {
            for (var i = 0; i < periods.length; i++) {
                if (periods[i].days === state.days) return periods[i];
            }
            return null;
        }

        function renderExtend() {
            var p = currentPeriod();
            if (!p || !elPrice) return;

            // Model niedostępny dla tego okresu (np. brak odpowiednika
            // interwału w Stripe) cofa wybór na płatność jednorazową, żeby
            // podsumowanie nigdy nie pokazało pustego miejsca.
            if (!p.prices[state.mode]) state.mode = 'payment';

            card.querySelectorAll('[data-role="periods"] .srv-opt').forEach(function (b) {
                b.classList.toggle('is-active', Number(b.getAttribute('data-days')) === state.days);
            });
            card.querySelectorAll('[data-role="modes"] .srv-opt').forEach(function (b) {
                var mode = b.getAttribute('data-mode');
                b.disabled = !p.prices[mode];
                b.classList.toggle('is-active', mode === state.mode);
            });

            elDays.textContent  = '+' + p.days + ' dni';
            elUntil.textContent = state.mode === 'subscription'
                ? 'do ' + p.until + ', potem odnawia się sam'
                : 'serwer będzie działał do ' + p.until;
            elPrice.textContent = p.prices[state.mode] || '-';
        }

        card.querySelectorAll('[data-role="periods"] .srv-opt').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.days = Number(btn.getAttribute('data-days'));
                renderExtend();
            });
        });

        card.querySelectorAll('[data-role="modes"] .srv-opt').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                state.mode = btn.getAttribute('data-mode');
                renderExtend();
            });
        });

        if (btnPay) {
            btnPay.addEventListener('click', function () {
                post('/api/checkout/extend.php', {
                    order_id: orderId,
                    period_days: state.days,
                    billing_mode: state.mode
                }, btnPay, 'Przejdź do płatności');
            });
        }

        /* ---------------------------------------------------------------
           Zmiana pakietu
           --------------------------------------------------------------- */
        card.querySelectorAll('.srv-plan').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var planId = btn.getAttribute('data-plan');
                var plan   = find(plans, 'plan_id', Number(planId));

                // Zejście niżej dzieje się OD RĘKI i przestawia zasoby
                // działającego serwera - to restart, więc pytamy.
                if (plan && plan.direction === 'down') {
                    var extra = plan.bonus_days > 0 ? '\n\nDostaniesz ' + plan.bonus_days + ' dni więcej.' : '';
                    if (!window.confirm('Zmienić pakiet na ' + plan.name + '?\n\nSerwer zostanie zrestartowany.' + extra)) {
                        return;
                    }
                }

                post('/api/plan/change.php', { order_id: orderId, plan_id: planId }, btn, btn.textContent);
            });
        });

        /* ---------------------------------------------------------------
           Rezygnacja z odnawiania
           --------------------------------------------------------------- */
        var btnCancel = card.querySelector('[data-role="cancelSub"]');
        if (btnCancel) {
            btnCancel.addEventListener('click', function () {
                if (!window.confirm('Wyłączyć automatyczne odnawianie?\n\nSerwer działa do końca opłaconego okresu - nic nie tracisz.')) {
                    return;
                }
                post('/api/subscription/cancel.php', { order_id: orderId }, btnCancel, 'Zrezygnuj z odnawiania');
            });
        }

        /* ---------------------------------------------------------------
           Bieżący stan usługi

           To osobne, lekkie żądanie GET. Karta z terminem, adresem i
           płatnością jest gotowa od razu, nawet gdy Wings konkretnego
           serwera jest właśnie niedostępny.
           --------------------------------------------------------------- */
        var live = card.querySelector('[data-role="live"]');
        if (live) {
            var btnRefreshLive = live.querySelector('[data-role="refreshLive"]');
            var liveNote = live.querySelector('[data-role="liveNote"]');

            if (btnRefreshLive) {
                btnRefreshLive.addEventListener('click', loadLive);
            }
            loadLive();

            function loadLive() {
                if (btnRefreshLive) btnRefreshLive.disabled = true;
                live.setAttribute('aria-busy', 'true');
                if (liveNote) liveNote.textContent = 'Pobieram dane bezpośrednio z panelu…';

                fetch('/api/server-status.php?order_id=' + encodeURIComponent(orderId), {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (body) { return { ok: r.ok && body.ok, body: body }; }); })
                    .then(function (res) {
                        if (!res.ok) throw new Error(res.body.message || 'Nie udało się pobrać stanu.');
                        renderLive(res.body);
                    })
                    .catch(function () {
                        setLive('state', 'chwilowo niedostępny', 'srv-live-state-warn');
                        setLive('memory', '—'); setLive('cpu', '—'); setLive('disk', '—'); setLive('backups', '—'); setLive('uptime', '—');
                        if (liveNote) liveNote.textContent = 'Nie udało się teraz połączyć z maszyną. Dane o usłudze powyżej pozostają aktualne.';
                    })
                    .finally(function () {
                        live.setAttribute('aria-busy', 'false');
                        if (btnRefreshLive) btnRefreshLive.disabled = false;
                    });
            }

            function renderLive(data) {
                var states = {
                    running: ['działa', 'srv-live-state-ok'],
                    starting: ['uruchamia się', 'srv-live-state-warn'],
                    stopping: ['zatrzymuje się', 'srv-live-state-warn'],
                    offline: ['wyłączony', 'srv-live-state-warn'],
                    suspended: ['zawieszony', 'srv-live-state-bad']
                };
                var state = states[data.state] || ['brak odczytu', 'srv-live-state-warn'];
                setLive('state', state[0], state[1]);

                var r = data.resources;
                setLive('memory', r ? bytes(r.memory_bytes) : 'brak odczytu');
                setLive('cpu', r ? number(r.cpu_absolute, 1) + '%' : 'brak odczytu');
                setLive('disk', r ? bytes(r.disk_bytes) : 'brak odczytu');
                setLive('uptime', r ? duration(r.uptime) : '—');
                setLive('backups', data.backups.used + ' / ' + data.backups.limit);

                var last = live.querySelector('[data-live="backupLast"]');
                if (last) last.textContent = data.backups.last ? 'ostatnia: ' + data.backups.last : 'brak ukończonej kopii';
                if (liveNote) liveNote.textContent = r
                    ? 'Stan odczytany teraz. Kliknij „Odśwież”, aby pobrać go ponownie.'
                    : 'Panel potwierdził usługę, ale maszyna nie podała aktualnego zużycia.';
            }

            function setLive(name, value, tone) {
                var el = live.querySelector('[data-live="' + name + '"]');
                if (!el) return;
                el.textContent = value;
                el.classList.remove('srv-live-state-ok', 'srv-live-state-warn', 'srv-live-state-bad');
                if (tone) el.classList.add(tone);
            }

            function bytes(value) {
                value = Number(value) || 0;
                var units = ['B', 'KB', 'MB', 'GB', 'TB'];
                var i = 0;
                while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
                return number(value, value >= 10 || i === 0 ? 0 : 1) + ' ' + units[i];
            }

            function duration(seconds) {
                seconds = Math.max(0, Number(seconds) || 0);
                if (seconds < 60) return 'mniej niż minutę';
                var minutes = Math.floor(seconds / 60);
                if (minutes < 60) return minutes + ' min';
                var hours = Math.floor(minutes / 60);
                if (hours < 48) return hours + ' h';
                return Math.floor(hours / 24) + ' dni';
            }

            function number(value, digits) {
                return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: digits }).format(Number(value) || 0);
            }
        }

        /* ---------------------------------------------------------------
           Wysyłka
           --------------------------------------------------------------- */
        function post(url, body, btn, originalLabel) {
            if (btn.disabled) return;

            btn.disabled = true;
            btn.textContent = 'Chwileczkę…';
            if (elError) elError.hidden = true;

            body.csrf_token = csrf;

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
                .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        fail(res.body.message || 'Nie udało się wykonać operacji.');
                        return;
                    }
                    // Płatność -> Stripe. Operacja bez płatności (zejście niżej,
                    // rezygnacja) -> przeładowanie, żeby karta pokazała nowy stan.
                    if (res.body.url) {
                        window.location.href = res.body.url;
                        return;
                    }
                    window.location.reload();
                })
                .catch(function () {
                    fail('Brak połączenia z serwerem. Spróbuj ponownie.');
                });

            function fail(message) {
                if (elError) {
                    elError.textContent = message;
                    elError.hidden = false;
                }
                btn.disabled = false;
                btn.textContent = originalLabel;
            }
        }

        renderExtend();
    }

    /* ------------------------------------------------------------------
       Kopiowanie adresu serwera
       ------------------------------------------------------------------ */
    document.querySelectorAll('[data-role="copy"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var value = btn.getAttribute('data-value');

            // clipboard API działa tylko w bezpiecznym kontekście; gdy go nie
            // ma, zaznaczamy tekst, żeby dało się skopiować ręcznie.
            var done = function () {
                var was = btn.textContent;
                btn.textContent = 'Skopiowane';
                window.setTimeout(function () { btn.textContent = was; }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done, selectFallback);
            } else {
                selectFallback();
            }

            function selectFallback() {
                var code = btn.parentNode.querySelector('[data-role="addr"]');
                if (!code) return;
                var range = document.createRange();
                range.selectNodeContents(code);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            }
        });
    });

    function parse(raw) {
        try { return JSON.parse(raw) || []; } catch (e) { return []; }
    }

    function find(list, key, value) {
        for (var i = 0; i < list.length; i++) {
            if (list[i][key] === value) return list[i];
        }
        return null;
    }
})();
