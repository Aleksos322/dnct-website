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
           Rozwijanie paneli — naraz otwarty jest najwyżej jeden.
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
            elPrice.textContent = p.prices[state.mode] || '—';
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
                if (!window.confirm('Wyłączyć automatyczne odnawianie?\n\nSerwer działa do końca opłaconego okresu — nic nie tracisz.')) {
                    return;
                }
                post('/api/subscription/cancel.php', { order_id: orderId }, btnCancel, 'Zrezygnuj z odnawiania');
            });
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
