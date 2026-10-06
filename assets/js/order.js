/**
 * Formularz zamówienia: wybór okresu i modelu rozliczenia + start płatności.
 *
 * Skrypt NIE LICZY CEN. Wszystkie warianty przyszły policzone z serwera
 * w <script id="ord-data">, a tutaj jest tylko wybieranie z gotowej listy.
 * Mnożenie po stronie przeglądarki oznaczałoby drugi wzór obok tego
 * w backend/lib/pricing.php - i pewność, że kiedyś się rozjadą.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('ord-data');
    if (!dataEl) return;

    var data = JSON.parse(dataEl.textContent);

    var state = { days: null, mode: 'payment' };

    var elPrice  = document.querySelector('[data-role="sumPrice"]');
    var elPer    = document.querySelector('[data-role="sumPer"]');
    var elPeriod = document.querySelector('[data-role="sumPeriod"]');
    var elHint   = document.querySelector('[data-role="methodsHint"]');
    var elError  = document.querySelector('[data-role="error"]');
    var btnPay   = document.querySelector('[data-role="pay"]');
    var elTerms  = document.querySelector('[data-role="terms"]');
    var elImmediateStart = document.querySelector('[data-role="immediateStart"]');
    var elPromo  = document.querySelector('[data-role="promo"]');

    function availableDays() {
        return Object.keys(data.quotes).map(Number).sort(function (a, b) { return a - b; });
    }

    function quote() {
        var byMode = data.quotes[state.days];
        return byMode ? byMode[state.mode] : null;
    }

    function render() {
        var q = quote();

        // Abonament nie istnieje dla każdego okresu (Stripe nie ma odpowiednika
        // dowolnej liczby dni). Wtedy przycisk trybu jest wyłączony, a nie
        // klikalny i cicho nieskuteczny.
        document.querySelectorAll('[data-role="modes"] .ord-option').forEach(function (btn) {
            var mode = btn.getAttribute('data-mode');
            var has  = !!(data.quotes[state.days] && data.quotes[state.days][mode]);
            btn.disabled = !has;
            btn.classList.toggle('is-active', has && mode === state.mode);
        });

        document.querySelectorAll('[data-role="periods"] .ord-option').forEach(function (btn) {
            btn.classList.toggle('is-active', Number(btn.getAttribute('data-days')) === state.days);
        });

        if (!q) {
            elPrice.textContent = '-';
            if (btnPay) btnPay.disabled = true;
            return;
        }

        elPrice.textContent  = q.amount;
        elPeriod.textContent = q.label;
        elPer.textContent    = state.mode === 'subscription' ? '/ ' + q.label.toLowerCase() : 'jednorazowo';

        /* Nie wyliczamy tu wszystkich metod co do jednej. Listę ustala panel
           Stripe'a (patrz api/checkout/create.php) i zmienia się bez udziału
           tego pliku - obiecywanie tu Przelewów24, których konto nie ma
           włączonych, kończyło się rozczarowaniem w Checkoucie. Mówimy więc
           o tym, co dla klienta naprawdę istotne: czy BLIK w ogóle wchodzi
           w grę, bo przy abonamencie nie wchodzi. */
        elHint.textContent = state.mode === 'subscription'
            ? 'Abonament wymaga karty - BLIK i przelewy nie obsługują płatności cyklicznych.'
            : 'Zapłacisz kartą lub BLIK-iem. Pełną listę metod zobaczysz w Checkoucie.';

        if (btnPay && !btnPay.hasAttribute('data-locked')) {
            btnPay.disabled = !data.sales_enabled || !elTerms || !elTerms.checked;
        }
    }

    document.querySelectorAll('[data-role="periods"] .ord-option').forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.days = Number(btn.getAttribute('data-days'));
            // Okres bez abonamentu cofa wybór na płatność jednorazową, żeby
            // podsumowanie nigdy nie pokazało pustego miejsca.
            if (!data.quotes[state.days] || !data.quotes[state.days][state.mode]) state.mode = 'payment';
            render();
        });
    });

    if (elTerms) {
        elTerms.addEventListener('change', render);
    }
    if (elImmediateStart) {
        elImmediateStart.addEventListener('change', function () {
            // Bez natychmiastowego startu nie zakładamy subskrypcji, która
            // mogłaby odnowić się zanim usługa w ogóle ruszy.
            if (!elImmediateStart.checked && state.mode === 'subscription') state.mode = 'payment';
            render();
        });
    }

    document.querySelectorAll('[data-role="modes"] .ord-option').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            state.mode = btn.getAttribute('data-mode');
            render();
        });
    });

    if (btnPay) {
        btnPay.addEventListener('click', function () {
            if (btnPay.disabled) return;

            if (!elTerms || !elTerms.checked) {
                fail('Zaakceptuj Regulamin, aby przejść do płatności.');
                return;
            }

            // Blokada na czas żądania. Bez niej podwójne kliknięcie zakłada
            // dwie sesje Stripe'a i dwa wiersze w tabeli zamówień.
            btnPay.disabled = true;
            btnPay.setAttribute('data-locked', '1');
            btnPay.textContent = 'Przekierowuję do płatności…';
            if (elError) elError.hidden = true;

            fetch('/api/checkout/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token:   data.csrf,
                    game_type:    data.game,
                    plan_id:      data.plan_id || 0,
                    slots:        data.slots || 0,
                    period_days:  state.days,
                    billing_mode: state.mode,
                    terms_accepted: true,
                    immediate_service_start: !!(elImmediateStart && elImmediateStart.checked),
                    promo_code:   elPromo ? elPromo.value.trim() : ''
                })
            })
                .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                .then(function (res) {
                    if (res.ok && res.body.url) {
                        window.location.href = res.body.url;
                        return;
                    }
                    fail(res.body.message || 'Nie udało się rozpocząć płatności.');
                })
                .catch(function () {
                    fail('Brak połączenia z serwerem. Sprawdź internet i spróbuj ponownie.');
                });
        });
    }

    function fail(message) {
        if (elError) {
            elError.textContent = message;
            elError.hidden = false;
        }
        btnPay.removeAttribute('data-locked');
        btnPay.disabled = !data.sales_enabled || !elTerms || !elTerms.checked;
        btnPay.textContent = 'Przejdź do płatności';
    }

    // Start: miesiąc, jeśli jest - to najczęstszy wybór i domyślny w cenniku.
    var days = availableDays();
    // Okres i model przekazane z konfiguratora slotów (?days=&mode=) - nie pytamy drugi raz.
    var params = new URLSearchParams(window.location.search);
    var wantDays = parseInt(params.get('days') || '', 10);
    state.days = days.indexOf(wantDays) !== -1 ? wantDays : (days.indexOf(30) !== -1 ? 30 : days[0]);
    if (params.get('mode') === 'payment' || params.get('mode') === 'subscription') {
        state.mode = params.get('mode');
    }
    render();
})();
