/* pomoc.js - formularz zgłoszenia na /pomoc.
 * Wysyła akcję "create" do istniejącego /api/support/ticket.php (tego samego,
 * którego używa czat wsparcia). Temat i serwer nie mają pól w bazie, więc
 * doklejamy je na początek opisu - admin widzi je w pierwszej wiadomości. */
(function () {
    'use strict';
    var form = document.getElementById('pomoc-form');
    if (!form) return;

    var LIMIT = 2000; // SUPPORT_MESSAGE_MAX po stronie serwera, razem z nagłówkiem
    var loggedIn = form.dataset.loggedIn === '1';
    var statusBox = document.getElementById('pomoc-status');
    var counter = document.getElementById('pomoc-count');
    var message = form.elements.message;
    var button = form.querySelector('.pomoc-btn');
    var requestKey = randomKey();

    function randomKey() {
        var b = new Uint8Array(32);
        (window.crypto || window.msCrypto).getRandomValues(b);
        return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    }

    function field(name) { return form.elements[name]; }

    function setError(name, text) {
        var p = form.querySelector('[data-error-for="' + name + '"]');
        var input = field(name);
        if (p) p.textContent = text || '';
        if (input && input.setAttribute) {
            if (text) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
        }
    }

    function clearErrors() {
        ['name', 'email', 'subject', 'category', 'message'].forEach(function (n) { setError(n, ''); });
    }

    function showStatus(kind, html) {
        statusBox.className = 'pomoc-status ' + (kind === 'ok' ? 'is-ok' : 'is-err');
        statusBox.innerHTML = html;
        statusBox.hidden = false;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function composeBody() {
        var lines = ['Temat: ' + field('subject').value.trim()];
        var server = field('server') ? field('server').value.trim() : '';
        if (server) lines.push('Serwer: ' + server);
        return lines.join('\n') + '\n\n' + message.value.trim();
    }

    function updateCount() {
        var len = message.value.trim().length;
        var max = Number(message.getAttribute('maxlength')) || 1700;
        counter.textContent = len + ' / ' + max;
        counter.classList.toggle('is-over', len > max);
    }
    message.addEventListener('input', updateCount);
    updateCount();

    function validate() {
        var ok = true;
        clearErrors();
        if (!loggedIn) {
            if (!field('name').value.trim()) { setError('name', 'Podaj imię lub nick.'); ok = false; }
            var mail = field('email').value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) { setError('email', 'Podaj poprawny adres e-mail.'); ok = false; }
        }
        if (field('subject').value.trim().length < 3) { setError('subject', 'Podaj krótki temat.'); ok = false; }
        if (!field('category').value) { setError('category', 'Wybierz kategorię.'); ok = false; }
        var len = message.value.trim().length;
        if (len < 20) { setError('message', 'Opisz problem w co najmniej 20 znakach.'); ok = false; }
        else if (composeBody().length > LIMIT) { setError('message', 'Opis jest za długi - skróć go do ' + (Number(message.getAttribute('maxlength')) || 1700) + ' znaków.'); ok = false; }
        if (!ok) {
            var first = form.querySelector('[aria-invalid="true"]');
            if (first) first.focus();
        }
        return ok;
    }

    function openChat() {
        if (window.DNCTSupport && typeof window.DNCTSupport.open === 'function') window.DNCTSupport.open();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        statusBox.hidden = true;
        if (!validate()) return;

        var data = new FormData();
        data.append('action', 'create');
        data.append('csrf_token', field('csrf_token').value);
        data.append('website', field('website').value);
        data.append('request_key', requestKey);
        data.append('name', loggedIn ? (form.dataset.name || 'Klient') : field('name').value.trim());
        data.append('email', loggedIn ? (form.dataset.email || '') : field('email').value.trim());
        data.append('category', field('category').value);
        data.append('message', composeBody());

        button.disabled = true;
        button.textContent = 'Wysyłanie...';

        fetch('/api/support/ticket.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': field('csrf_token').value },
            body: data
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, message: 'Serwer zwrócił nieoczekiwaną odpowiedź.' }; })
                .then(function (j) { j._status = r.status; return j; });
        }).then(function (res) {
            if (!res.ok) {
                var errs = res.errors || {};
                Object.keys(errs).forEach(function (k) { setError(k, errs[k]); });
                var msg = res._status === 403 ? 'Sesja wygasła. Odśwież stronę i wyślij ponownie.' : (res.message || 'Nie udało się wysłać zgłoszenia.');
                showStatus('err', esc(msg));
                return;
            }
            var t = res.ticket || {};
            var nr = t.public_id ? ' <strong>#' + esc(t.public_id) + '</strong>' : '';
            if (res.already_exists) {
                showStatus('err', 'Masz już otwarte zgłoszenie' + nr + '. Dopisz informacje do niego, zamiast zakładać nowe.' +
                    (loggedIn ? '<br><button type="button" data-open-chat>Otwórz rozmowę</button>' : ' Odpowiemy na Twój e-mail.'));
            } else {
                form.reset();
                updateCount();
                requestKey = randomKey();
                showStatus('ok', 'Zgłoszenie' + nr + ' przyjęte. ' + (loggedIn
                    ? 'Odpowiedź pojawi się w czacie wsparcia i przyjdzie mailem.<br><button type="button" data-open-chat>Otwórz rozmowę</button>'
                    : 'Potwierdzenie i odpowiedzi wyślemy na podany e-mail.'));
            }
            statusBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }).catch(function () {
            showStatus('err', 'Brak połączenia. Sprawdź internet i spróbuj ponownie.');
        }).then(function () {
            button.disabled = false;
            button.textContent = 'Wyślij zgłoszenie';
        });
    });

    statusBox.addEventListener('click', function (e) {
        if (e.target.closest('[data-open-chat]')) openChat();
    });
})();
