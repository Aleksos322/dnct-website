/**
 * Twoje serwery: przedłużanie.
 *
 * Skrypt wysyła wyłącznie NUMER ZAMÓWIENIA i liczbę dni. Kwotę liczy serwer
 * z zapisanych parametrów zamówienia — tak samo jak przy pierwszym zakupie.
 */
(function () {
    'use strict';

    var dataEl = document.getElementById('srv-data');
    if (!dataEl) return;

    var csrf = JSON.parse(dataEl.textContent).csrf;

    document.querySelectorAll('.srv-extend').forEach(function (box) {
        var orderId = box.getAttribute('data-order');
        var card    = box.closest('.srv');
        var elError = card ? card.querySelector('[data-role="error"]') : null;
        var buttons = box.querySelectorAll('.srv-extend-btn');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;

                // Blokujemy WSZYSTKIE przyciski tej karty, nie tylko kliknięty.
                // Inaczej podczas przekierowania da się kliknąć drugi okres
                // i założyć dwie płatności za ten sam serwer.
                buttons.forEach(function (b) { b.disabled = true; });
                var original = btn.textContent;
                btn.textContent = 'Chwileczkę…';
                if (elError) elError.hidden = true;

                fetch('/api/checkout/extend.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token:  csrf,
                        order_id:    orderId,
                        period_days: Number(btn.getAttribute('data-days'))
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
                        fail('Brak połączenia z serwerem. Spróbuj ponownie.');
                    });

                function fail(message) {
                    if (elError) {
                        elError.textContent = message;
                        elError.hidden = false;
                    }
                    buttons.forEach(function (b) { b.disabled = false; });
                    btn.textContent = original;
                }
            });
        });
    });
})();
