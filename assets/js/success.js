/**
 * Strona powrotu: odpytywanie o stan zamówienia.
 *
 * Serwer zakłada webhook, który idzie do nas WŁASNYM kanałem, prosto od
 * Stripe'a. Klient wraca z Checkoutu zwykle szybciej, niż to zdarzenie zdąży
 * dojść - dlatego strona pyta o stan, zamiast od razu ogłaszać wynik.
 */
(function () {
    'use strict';

    var card = document.querySelector('[data-role="card"]');
    if (!card) return;

    var sessionId = card.getAttribute('data-session');

    var elIcon    = card.querySelector('[data-role="icon"]');
    var elTitle   = card.querySelector('[data-role="title"]');
    var elText    = card.querySelector('[data-role="text"]');
    var elActions = card.querySelector('[data-role="actions"]');
    var elMeta    = card.querySelector('[data-role="meta"]');

    // Po tylu próbach przestajemy pytać. Minuta bez odpowiedzi znaczy, że coś
    // wymaga człowieka - kręcący się w nieskończoność pierścień tylko
    // udawałby, że sprawa jest w toku.
    var MAX_TRIES = 40;
    var tries = 0;

    var ICONS = {
        ok:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>'
    };

    function settle(kind, title, text, showActions) {
        card.classList.add(kind === 'ok' ? 'is-ready' : 'is-failed');
        elIcon.innerHTML = ICONS[kind === 'ok' ? 'ok' : 'warn'];
        elTitle.textContent = title;
        elText.textContent  = text;
        if (showActions) elActions.hidden = false;
    }

    function poll() {
        tries += 1;

        fetch('/api/checkout/status.php?session_id=' + encodeURIComponent(sessionId), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (res) {
                if (!res.ok) {
                    // 404 tuż po powrocie znaczy najczęściej, że zamówienia
                    // jeszcze nie widać - próbujemy dalej, a poddajemy się
                    // dopiero po wyczerpaniu prób.
                    return retryOrGiveUp('Nie znaleźliśmy tego zamówienia. Napisz do nas na Discordzie - sprawdzimy ręcznie.');
                }

                var b = res.body;

                if (b.server_ready) {
                    settle('ok', 'Serwer jest gotowy', 'Wszystko działa - serwer czeka w panelu.', true);
                    showMeta(b);
                    return;
                }

                if (b.failed) {
                    settle(
                        'warn',
                        'Płatność przyjęta, serwer w trakcie',
                        'Pieniądze doszły, ale automat nie dokończył zakładania serwera. Wiemy o tym i zajmiemy się tym ręcznie - napisz na Discordzie, jeśli chcesz przyspieszyć.',
                        true
                    );
                    showMeta(b);
                    return;
                }

                retryOrGiveUp('Płatność jest przyjęta, ale serwer nadal się zakłada. Sprawdź panel za chwilę - albo napisz do nas.');
            })
            .catch(function () {
                retryOrGiveUp('Brak połączenia z serwerem. Sprawdź panel za chwilę.');
            });
    }

    function retryOrGiveUp(message) {
        if (tries < MAX_TRIES) {
            window.setTimeout(poll, 1500);
            return;
        }
        settle('warn', 'To trwa dłużej niż zwykle', message, true);
    }

    function showMeta(b) {
        if (!elMeta) return;
        var bits = ['Kwota: ' + b.amount + ' zł'];
        if (b.billing_mode === 'subscription') bits.push('abonament odnawialny');
        if (b.expires_at) bits.push('opłacone do ' + b.expires_at.replace(' ', ', '));
        elMeta.textContent = bits.join(' · ');
        elMeta.hidden = false;
    }

    poll();
})();
