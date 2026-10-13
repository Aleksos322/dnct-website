/**
 * DNCT Hosting - kreator rejestracji (3 kroki).
 *
 * Krok 1  dane identyfikacyjne  -> POST /api/auth/send-verification
 * Krok 2  kod z e-maila (OTP)   -> POST /api/auth/verify-code
 * Krok 3  hasło i zgody         -> POST /api/auth/register
 *
 * Odpowiada za:
 *  - maszynę kroków wraz z animacjami i wskaźnikiem postępu,
 *  - walidację inline (ikony statusu + tooltipy z treścią błędu),
 *  - sprawdzanie dostępności loginu / e-maila w tle,
 *  - obsługę 6 pól kodu (auto-przejście, backspace, wklejanie),
 *  - odliczanie 60 s do ponownej wysyłki kodu,
 *  - miernik siły hasła wraz z szacowanym czasem złamania,
 *  - stany ładowania przycisków.
 *
 * Walidacja tutaj jest wyłącznie dla wygody użytkownika - wiążące reguły
 * pilnuje backend (/backend/lib/validation.php).
 */
(function () {
    'use strict';

    // Wersja EN/DE (assets/js/i18n.js): tekstów sklejanych w JS i atrybutów
    // MutationObserver nie złapie, więc tłumaczymy je tu. Leniwie, bo i18n.js
    // dochodzi na końcu strony, już po tym skrypcie.
    function T(t) { return window.DNCT_T ? window.DNCT_T(t) : t; }

    // ============================================================
    // KONFIGURACJA
    // ============================================================
    var API = {
        availability: '/api/auth/check-availability.php',
        sendCode: '/api/auth/send-verification.php',
        verifyCode: '/api/auth/verify-code.php',
        register: '/api/auth/register.php'
    };

    var USERNAME_RE = /^[A-Za-z0-9]+$/;
    var USERNAME_MIN = 3;
    var USERNAME_MAX = 32;
    var EMAIL_RE = /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/;
    var REMOTE_DEBOUNCE_MS = 450;
    var OTP_LENGTH = 6;
    var RESEND_COOLDOWN = 60;

    // Ikony statusu wstrzykujemy jako gotowy SVG (zamiast lucide), żeby nie
    // przebudowywać ikon przy każdym naciśnięciu klawisza.
    var SVG_ATTRS = 'class="status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    var ICONS = {
        valid: '<svg ' + SVG_ATTRS + '><polyline points="20 6 9 17 4 12"></polyline></svg>',
        invalid: '<svg ' + SVG_ATTRS + '><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        pending: '<svg ' + SVG_ATTRS + ' stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>'
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        var form = document.getElementById('register-form');
        if (!form) {
            return;
        }

        var els = {
            form: form,
            panels: document.getElementById('wizard-panels'),
            progress: document.getElementById('wizard-progress'),
            alert: document.getElementById('form-alert'),
            alertText: document.getElementById('form-alert-text'),
            csrf: document.getElementById('csrf-token'),
            honeypot: document.getElementById('website_hp'),

            // krok 1
            step1Submit: document.getElementById('step-1-submit'),

            // krok 2
            otpWrap: document.getElementById('otp-inputs'),
            otpHidden: document.getElementById('otp-hidden-input'),
            otpEmail: document.getElementById('otp-email'),
            otpExpiry: document.getElementById('otp-expiry'),
            otpResend: document.getElementById('otp-resend'),
            step2Submit: document.getElementById('step-2-submit'),

            // krok 3
            terms: document.getElementById('accept-terms'),
            marketing: document.getElementById('accept-marketing'),
            password: document.getElementById('register-password'),
            passwordConfirm: document.getElementById('register-password-confirm'),
            meter: document.getElementById('pw-meter'),
            meterLabel: document.getElementById('pw-strength-label'),
            crackTime: document.getElementById('pw-crack-time'),
            requirements: document.getElementById('pw-requirements'),
            submit: document.getElementById('register-submit'),
            submitHint: document.getElementById('submit-hint')
        };

        var steps = Array.prototype.slice.call(form.querySelectorAll('.step'));
        var otpBoxes = els.otpWrap
            ? Array.prototype.slice.call(els.otpWrap.querySelectorAll('.otp-box'))
            : [];

        var currentStep = 1;
        var submittedSteps = {};   // krok -> czy próbowano go już zatwierdzić
        var remoteCache = {};      // "username|jan" -> { valid, message }
        var remoteControllers = {};
        var remoteTimers = {};
        var resendTimer = null;
        var expiryTimer = null;
        var resendSecondsLeft = 0;
        var expirySecondsLeft = 0;

        // ============================================================
        // WALIDATORY
        // ============================================================
        function ok() {
            return { valid: true, message: '' };
        }

        function fail(message) {
            return { valid: false, message: message };
        }

        function requiredText(message) {
            return function (value) {
                return value.trim() === '' ? fail(message) : ok();
            };
        }

        function validateUsername(value) {
            var v = value.trim();
            if (v === '') {
                return fail('Podaj nazwę użytkownika.');
            }
            if (!USERNAME_RE.test(v)) {
                return fail('Dozwolone są wyłącznie litery i cyfry (A-Z, 0-9) - bez spacji i znaków specjalnych.');
            }
            if (v.length < USERNAME_MIN || v.length > USERNAME_MAX) {
                return fail('Nazwa użytkownika musi mieć od ' + USERNAME_MIN + ' do ' + USERNAME_MAX + ' znaków.');
            }
            return ok();
        }

        function validateEmail(value) {
            var v = value.trim();
            if (v === '') {
                return fail('Podaj adres e-mail.');
            }
            if (!EMAIL_RE.test(v) || v.length > 190) {
                return fail('Podaj poprawny adres e-mail (np. twoj@email.pl).');
            }
            return ok();
        }

        function passwordChecks(value) {
            return {
                length: value.length >= 8 && value.length <= 200,
                upper: /[A-Z]/.test(value),
                digit: /[0-9]/.test(value),
                special: /[^A-Za-z0-9]/.test(value)
            };
        }

        function validatePassword(value) {
            if (value === '') {
                return fail('Podaj hasło.');
            }
            var checks = passwordChecks(value);
            var missing = [];
            if (!checks.length) {
                missing.push(T('min. 8 znaków'));
            }
            if (!checks.upper) {
                missing.push(T('wielką literę'));
            }
            if (!checks.digit) {
                missing.push(T('cyfrę'));
            }
            if (!checks.special) {
                missing.push(T('znak specjalny'));
            }
            if (missing.length) {
                return fail(T('Hasło musi zawierać:') + ' ' + missing.join(', ') + '.');
            }
            return ok();
        }

        function validatePasswordConfirm(value) {
            if (value === '') {
                return fail('Powtórz hasło.');
            }
            if (value !== (els.password ? els.password.value : '')) {
                return fail('Hasła nie są identyczne.');
            }
            return ok();
        }

        function validateTerms() {
            return els.terms && els.terms.checked
                ? ok()
                : fail('Musisz zaakceptować Regulamin i Politykę Prywatności.');
        }

        // ------------------------------------------------------------
        // Definicje pól (z przypisaniem do kroku)
        // ------------------------------------------------------------
        var fields = {
            first_name: {
                el: document.getElementById('first-name'),
                step: 1,
                label: 'Imię',
                validate: requiredText('Podaj swoje imię.')
            },
            last_name: {
                el: document.getElementById('last-name'),
                step: 1,
                label: 'Nazwisko',
                validate: requiredText('Podaj swoje nazwisko.')
            },
            username: {
                el: document.getElementById('register-username'),
                step: 1,
                label: 'Nazwa użytkownika',
                validate: validateUsername,
                remote: 'username'
            },
            email: {
                el: document.getElementById('register-email'),
                step: 1,
                label: 'E-mail',
                validate: validateEmail,
                remote: 'email'
            },
            password: {
                el: els.password,
                step: 3,
                label: 'Hasło',
                validate: validatePassword
            },
            password_confirm: {
                el: els.passwordConfirm,
                step: 3,
                label: 'Powtórz hasło',
                validate: validatePasswordConfirm
            },
            terms: {
                el: els.terms,
                step: 3,
                label: 'Regulamin',
                validate: validateTerms
            }
        };

        Object.keys(fields).forEach(function (name) {
            var field = fields[name];
            field.name = name;
            field.touched = false;
            field.remoteResult = null; // { valid, message } - wynik sprawdzenia w bazie
            field.statusEl = form.querySelector('[data-status-for="' + name + '"]');
            field.errorEl = form.querySelector('[data-error-for="' + name + '"]');
        });

        function fieldsOfStep(step) {
            return Object.keys(fields).filter(function (name) {
                return fields[name].step === step && fields[name].el;
            }).map(function (name) {
                return fields[name];
            });
        }

        // ============================================================
        // RENDEROWANIE STATUSU POLA
        // ============================================================
        function fieldValue(field) {
            if (!field.el) {
                return '';
            }
            return field.el.type === 'checkbox' ? (field.el.checked ? '1' : '') : field.el.value;
        }

        /** Łączny wynik: walidacja formatu + ewentualny wynik z bazy. */
        function evaluate(field) {
            var result = field.validate(fieldValue(field));
            if (result.valid && field.remoteResult && !field.remoteResult.valid) {
                return field.remoteResult;
            }
            return result;
        }

        function render(field) {
            var result = evaluate(field);
            var showError = !result.valid && (field.touched || submittedSteps[field.step]);
            var isPending = field.pending === true;

            if (field.statusEl) {
                field.statusEl.classList.remove('is-valid', 'is-invalid', 'is-pending');
                field.statusEl.removeAttribute('data-tooltip');

                if (isPending) {
                    field.statusEl.classList.add('is-pending');
                    field.statusEl.innerHTML = ICONS.pending;
                } else if (showError) {
                    field.statusEl.classList.add('is-invalid');
                    field.statusEl.innerHTML = ICONS.invalid;
                    field.statusEl.setAttribute('data-tooltip', result.message);
                    field.statusEl.setAttribute('tabindex', '0');
                } else if (result.valid && fieldValue(field) !== '') {
                    field.statusEl.classList.add('is-valid');
                    field.statusEl.innerHTML = ICONS.valid;
                    field.statusEl.setAttribute('tabindex', '-1');
                } else {
                    field.statusEl.innerHTML = '';
                    field.statusEl.setAttribute('tabindex', '-1');
                }
            }

            if (field.el && field.el.classList.contains('input-field')) {
                field.el.classList.toggle('is-invalid', showError);
                field.el.classList.toggle('is-valid', !showError && !isPending && result.valid && fieldValue(field) !== '');
                field.el.setAttribute('aria-invalid', showError ? 'true' : 'false');
            } else if (field.el && field.el.type === 'checkbox') {
                field.el.classList.toggle('is-invalid', showError);
            }

            if (field.errorEl) {
                field.errorEl.textContent = showError ? result.message : '';
                field.errorEl.classList.toggle('has-error', showError);
            }

            return result;
        }

        /** Błąd zwrócony przez API - przypinamy go do konkretnego pola. */
        function applyServerFieldErrors(errors) {
            if (!errors) {
                return;
            }
            Object.keys(errors).forEach(function (name) {
                var field = fields[name];
                if (!field || !field.el) {
                    return;
                }
                field.touched = true;
                field.remoteResult = { valid: false, message: errors[name] };
                render(field);
            });
        }

        /** Sprawdza wszystkie pola kroku; zwraca pierwsze niepoprawne albo null. */
        function validateStep(step) {
            var firstInvalid = null;
            fieldsOfStep(step).forEach(function (field) {
                field.touched = true;
                var result = render(field);
                if (!result.valid && !firstInvalid) {
                    firstInvalid = field;
                }
            });
            return firstInvalid;
        }

        // ============================================================
        // SPRAWDZANIE DOSTĘPNOŚCI W BAZIE (AJAX)
        // ============================================================
        function csrfToken() {
            return els.csrf ? els.csrf.value : '';
        }

        function cacheKey(field, value) {
            return field.remote + '|' + value.trim().toLowerCase();
        }

        function scheduleRemoteCheck(field, immediate) {
            if (!field.remote) {
                return;
            }

            window.clearTimeout(remoteTimers[field.name]);
            if (remoteControllers[field.name]) {
                remoteControllers[field.name].abort();
                remoteControllers[field.name] = null;
            }

            var value = fieldValue(field).trim();
            var formatResult = field.validate(value);

            // Nie odpytujemy serwera, dopóki format jest niepoprawny.
            if (!formatResult.valid) {
                field.remoteResult = null;
                field.pending = false;
                render(field);
                return;
            }

            var cached = remoteCache[cacheKey(field, value)];
            if (cached) {
                field.remoteResult = cached;
                field.pending = false;
                render(field);
                return;
            }

            var run = function () {
                runRemoteCheck(field, value);
            };

            if (immediate) {
                run();
            } else {
                remoteTimers[field.name] = window.setTimeout(run, REMOTE_DEBOUNCE_MS);
            }
        }

        function runRemoteCheck(field, value) {
            if (typeof window.fetch !== 'function') {
                return; // starsza przeglądarka - walidację i tak wykona backend
            }

            var controller = typeof window.AbortController === 'function' ? new AbortController() : null;
            remoteControllers[field.name] = controller;

            field.pending = true;
            field.remoteResult = null;
            render(field);

            apiPost(API.availability, { field: field.remote, value: value }, controller)
                .then(function (res) {
                    // Odpowiedź na nieaktualne zapytanie ignorujemy.
                    if (fieldValue(field).trim() !== value) {
                        return;
                    }
                    field.pending = false;

                    var data = res.data || {};
                    if (data.status === 'taken') {
                        field.remoteResult = { valid: false, message: data.message };
                        remoteCache[cacheKey(field, value)] = field.remoteResult;
                    } else if (data.status === 'available') {
                        field.remoteResult = { valid: true, message: '' };
                        remoteCache[cacheKey(field, value)] = field.remoteResult;
                    } else if (data.status === 'invalid') {
                        field.remoteResult = { valid: false, message: data.message };
                    } else {
                        // unknown / rate_limited - nie blokujemy użytkownika,
                        // ostateczną decyzję i tak podejmuje backend.
                        field.remoteResult = null;
                    }
                    render(field);
                })
                .catch(function () {
                    if (fieldValue(field).trim() !== value) {
                        return;
                    }
                    field.pending = false;
                    field.remoteResult = null;
                    render(field);
                });
        }

        // ============================================================
        // WARSTWA HTTP
        // ============================================================
        /**
         * Wysyła POST-a do API i zawsze zwraca { ok, status, data }.
         * Błędy sieci również trafiają tutaj jako ok:false, dzięki czemu
         * wywołania nie muszą duplikować obsługi wyjątków.
         */
        function apiPost(url, payload, controller) {
            var body = new URLSearchParams();
            body.set('csrf_token', csrfToken());
            Object.keys(payload).forEach(function (key) {
                body.set(key, payload[key]);
            });

            var options = {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken()
                },
                body: body.toString()
            };
            if (controller) {
                options.signal = controller.signal;
            }

            return window.fetch(url, options).then(function (response) {
                return response.json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        return {
                            ok: response.ok && data && data.ok === true,
                            status: response.status,
                            data: data || {}
                        };
                    });
            });
        }

        /** Wspólna reakcja na odpowiedź błędną (alert + błędy pól + skok kroku). */
        function handleApiFailure(res, fallbackMessage) {
            var data = res.data || {};

            if (res.status === 419) {
                showAlert('Sesja wygasła. Odśwież stronę i spróbuj ponownie.', 'error');
                return;
            }

            showAlert(data.message || fallbackMessage, 'error');
            applyServerFieldErrors(data.errors);

            if (data.reset) {
                resetToFirstStep();
            } else if (data.step) {
                goToStep(data.step, 'back');
            }
        }

        // ============================================================
        // KOMUNIKAT GLOBALNY
        // ============================================================
        function showAlert(message, type) {
            if (!els.alert || !els.alertText) {
                return;
            }
            if (!message) {
                hideAlert();
                return;
            }
            els.alertText.textContent = message;
            els.alert.classList.remove('hidden', 'is-error', 'is-success');
            els.alert.classList.add(type === 'success' ? 'is-success' : 'is-error');

            var icon = els.alert.querySelector('[data-lucide]');
            if (icon) {
                icon.setAttribute('data-lucide', type === 'success' ? 'check-circle-2' : 'alert-circle');
                refreshIcons();
            }
        }

        function hideAlert() {
            if (els.alert) {
                els.alert.classList.add('hidden');
            }
        }

        function refreshIcons() {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        }

        // ============================================================
        // STANY ŁADOWANIA
        // ============================================================
        function setLoading(button, isLoading, loadingText) {
            if (!button) {
                return;
            }
            var textEl = button.querySelector('.btn-text');

            if (isLoading) {
                if (textEl && loadingText) {
                    if (!button.dataset.idleText) {
                        button.dataset.idleText = textEl.textContent;
                    }
                    textEl.textContent = loadingText;
                }
                button.classList.add('is-loading');
                button.disabled = true;
                return;
            }

            button.classList.remove('is-loading');
            button.disabled = false;
            if (textEl && button.dataset.idleText) {
                textEl.textContent = button.dataset.idleText;
            }
            // Przycisk kroku 3 może być zablokowany brakiem zgody na regulamin.
            if (button === els.submit) {
                updateSubmitState();
            }
        }

        // ============================================================
        // MASZYNA KROKÓW
        // ============================================================
        function updateRegistrationScrollState(step) {
            var body = document.body;
            if (!body) {
                return;
            }
            body.classList.toggle('is-step-3', step === 3);
        }

        function goToStep(step, direction) {
            step = Math.min(3, Math.max(1, parseInt(step, 10) || 1));
            if (!direction) {
                direction = step > currentStep ? 'forward' : 'back';
            }
            currentStep = step;
            updateRegistrationScrollState(step);

            if (els.panels) {
                els.panels.setAttribute('data-direction', direction);
            }

            steps.forEach(function (section) {
                section.classList.toggle('is-active', Number(section.getAttribute('data-step')) === step);
            });

            updateProgress(step);
            focusFirstControl(step);

            // Karta bywa wyższa niż ekran - po zmianie kroku wracamy na górę.
            if (window.scrollY > 0) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function updateProgress(step) {
            if (!els.progress) {
                return;
            }
            els.progress.setAttribute('data-active-step', String(step));
            els.progress.setAttribute('aria-label', T('Postęp rejestracji: krok ' + step + ' z 3'));

            Array.prototype.forEach.call(els.progress.querySelectorAll('[data-indicator]'), function (node) {
                var index = Number(node.getAttribute('data-indicator'));
                node.classList.toggle('is-active', index === step);
                node.classList.toggle('is-done', index < step);
            });

            Array.prototype.forEach.call(els.progress.querySelectorAll('.wizard-track'), function (track, index) {
                track.classList.toggle('is-filled', index < step - 1);
            });
        }

        function focusFirstControl(step) {
            if (step === 2) {
                focusFirstEmptyOtpBox();
                return;
            }
            var section = steps.filter(function (s) {
                return Number(s.getAttribute('data-step')) === step;
            })[0];
            if (!section) {
                return;
            }
            var control = section.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
            if (control && window.matchMedia('(min-width: 640px)').matches) {
                control.focus();
            }
        }

        function resetToFirstStep() {
            clearOtp();
            stopResendCountdown();
            stopExpiryCountdown();
            goToStep(1, 'back');
        }

        // ============================================================
        // KROK 1 -> wysyłka kodu
        // ============================================================
        function submitStep1() {
            submittedSteps[1] = true;
            hideAlert();

            var firstInvalid = validateStep(1);
            if (firstInvalid) {
                firstInvalid.el.focus();
                return;
            }

            setLoading(els.step1Submit, true, 'Wysyłanie kodu...');

            apiPost(API.sendCode, {
                mode: 'start',
                first_name: fields.first_name.el.value,
                last_name: fields.last_name.el.value,
                username: fields.username.el.value,
                email: fields.email.el.value,
                website_hp: els.honeypot ? els.honeypot.value : ''
            }).then(function (res) {
                setLoading(els.step1Submit, false);

                if (!res.ok) {
                    handleApiFailure(res, 'Nie udało się wysłać kodu. Spróbuj ponownie.');
                    return;
                }

                var data = res.data;
                if (els.otpEmail) {
                    els.otpEmail.textContent = data.email || fields.email.el.value;
                }

                // Adres był już potwierdzony (powrót do kroku 1) - pomijamy krok 2.
                if (data.verified) {
                    goToStep(3, 'forward');
                    return;
                }

                clearOtp();
                startResendCountdown(typeof data.resend_in === 'number' ? data.resend_in : RESEND_COOLDOWN);
                startExpiryCountdown(data.expires_in || 0);
                goToStep(2, 'forward');
            }).catch(function () {
                setLoading(els.step1Submit, false);
                showAlert('Brak połączenia z serwerem. Sprawdź internet i spróbuj ponownie.', 'error');
            });
        }

        // ============================================================
        // KROK 2 -> pola kodu, weryfikacja, ponowna wysyłka
        // ============================================================
        function otpValue() {
            if (els.otpHidden) {
                return (els.otpHidden.value || '').replace(/\D/g, '');
            }
            return otpBoxes.map(function (box) {
                return box.value.replace(/\D/g, '');
            }).join('');
        }

        function setOtpValue(value) {
            var digits = (value || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
            if (els.otpHidden) {
                els.otpHidden.value = digits;
            }
            otpBoxes.forEach(function (box, index) {
                var digit = digits[index] || '';
                box.value = digit;
                box.classList.toggle('is-filled', digit !== '');
            });
            return digits;
        }

        function syncOtpBoxesFromHidden() {
            var digits = otpValue();
            otpBoxes.forEach(function (box, index) {
                var digit = digits[index] || '';
                box.value = digit;
                box.classList.toggle('is-filled', digit !== '');
            });
        }

        function clearOtp() {
            setOtpValue('');
            if (els.otpWrap) {
                els.otpWrap.classList.remove('is-invalid', 'is-valid');
            }
            setOtpError('');
        }

        function setOtpError(message) {
            var errorEl = form.querySelector('[data-error-for="code"]');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.toggle('has-error', message !== '');
            }
            if (els.otpWrap) {
                els.otpWrap.classList.toggle('is-invalid', message !== '');
            }
        }

        function focusFirstEmptyOtpBox() {
            for (var i = 0; i < otpBoxes.length; i++) {
                if (otpBoxes[i].value === '') {
                    otpBoxes[i].focus();
                    return;
                }
            }
            if (otpBoxes.length) {
                otpBoxes[otpBoxes.length - 1].focus();
            }
        }

        /** Rozkłada ciąg cyfr po polach, zaczynając od wskazanego indeksu. */
        function distributeDigits(digits, startIndex) {
            var chars = Array(OTP_LENGTH).fill('');
            var index = startIndex;
            for (var i = 0; i < digits.length && index < otpBoxes.length; i++, index++) {
                chars[index] = digits[i];
            }
            setOtpValue(chars.join(''));
            var focusIndex = Math.min(index, otpBoxes.length - 1);
            if (otpBoxes[focusIndex]) {
                otpBoxes[focusIndex].focus();
            }
            return index;
        }

        function bindOtpBoxes() {
            if (els.otpHidden) {
                els.otpHidden.addEventListener('input', function () {
                    syncOtpBoxesFromHidden();
                    setOtpError('');
                    if (otpValue().length === OTP_LENGTH) {
                        submitStep2();
                    }
                });

                els.otpHidden.addEventListener('paste', function (event) {
                    event.preventDefault();
                    var text = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = text.replace(/\D/g, '').slice(0, OTP_LENGTH);
                    if (!digits) {
                        return;
                    }
                    distributeDigits(digits, 0);
                    setOtpError('');
                    if (otpValue().length === OTP_LENGTH) {
                        submitStep2();
                    }
                });
            }

            otpBoxes.forEach(function (box, index) {
                box.addEventListener('input', function () {
                    var digits = box.value.replace(/\D/g, '');

                    if (digits.length > 1) {
                        // Wpisano/wklejono kilka cyfr naraz do jednego pola.
                        box.value = '';
                        distributeDigits(digits, index);
                    } else {
                        var chars = otpValue().split('');
                        chars[index] = digits;
                        setOtpValue(chars.join(''));
                        if (digits !== '' && index < otpBoxes.length - 1) {
                            otpBoxes[index + 1].focus();
                        }
                    }

                    setOtpError('');

                    // Komplet cyfr - weryfikujemy od razu, bez klikania przycisku.
                    if (otpValue().length === OTP_LENGTH) {
                        submitStep2();
                    }
                });

                box.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace') {
                        if (box.value === '' && index > 0) {
                            event.preventDefault();
                            otpBoxes[index - 1].value = '';
                            otpBoxes[index - 1].classList.remove('is-filled');
                            otpBoxes[index - 1].focus();
                        } else {
                            box.classList.remove('is-filled');
                        }
                        return;
                    }
                    if (event.key === 'ArrowLeft' && index > 0) {
                        event.preventDefault();
                        otpBoxes[index - 1].focus();
                    }
                    if (event.key === 'ArrowRight' && index < otpBoxes.length - 1) {
                        event.preventDefault();
                        otpBoxes[index + 1].focus();
                    }
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitStep2();
                    }
                });

                box.addEventListener('paste', function (event) {
                    event.preventDefault();
                    var text = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = text.replace(/\D/g, '').slice(0, OTP_LENGTH);
                    if (!digits) {
                        return;
                    }
                    if (digits.length === OTP_LENGTH && index === 0) {
                        setOtpValue(digits);
                    } else {
                        distributeDigits(digits, index);
                    }
                    setOtpError('');
                    if (otpValue().length === OTP_LENGTH) {
                        submitStep2();
                    }
                });

                box.addEventListener('focus', function () {
                    box.select();
                });
            });
        }

        function submitStep2() {
            var code = otpValue();

            if (els.step2Submit && els.step2Submit.classList.contains('is-loading')) {
                return; // weryfikacja już trwa
            }
            if (code.length !== OTP_LENGTH) {
                setOtpError('Wpisz pełny ' + OTP_LENGTH + '-cyfrowy kod.');
                focusFirstEmptyOtpBox();
                return;
            }

            hideAlert();
            setOtpError('');
            setLoading(els.step2Submit, true, 'Weryfikacja...');

            apiPost(API.verifyCode, { code: code }).then(function (res) {
                setLoading(els.step2Submit, false);

                if (!res.ok) {
                    var data = res.data || {};
                    setOtpError(data.message || 'Nieprawidłowy kod.');

                    if (res.status === 419) {
                        showAlert('Sesja wygasła. Odśwież stronę i spróbuj ponownie.', 'error');
                    } else if (data.reset) {
                        showAlert(data.message || 'Sesja rejestracji wygasła.', 'error');
                        resetToFirstStep();
                        return;
                    }

                    // Zły kod: czyścimy pola i wracamy kursorem na początek.
                    clearOtp();
                    if (otpBoxes[0]) {
                        otpBoxes[0].focus();
                    }
                    return;
                }

                if (els.otpWrap) {
                    els.otpWrap.classList.remove('is-invalid');
                    els.otpWrap.classList.add('is-valid');
                }
                stopExpiryCountdown();
                stopResendCountdown();
                goToStep(3, 'forward');
            }).catch(function () {
                setLoading(els.step2Submit, false);
                showAlert('Brak połączenia z serwerem. Sprawdź internet i spróbuj ponownie.', 'error');
            });
        }

        function resendCode() {
            if (resendSecondsLeft > 0 || !els.otpResend || els.otpResend.disabled) {
                return;
            }

            hideAlert();
            els.otpResend.disabled = true;
            els.otpResend.textContent = 'Wysyłanie...';

            apiPost(API.sendCode, { mode: 'resend' }).then(function (res) {
                if (!res.ok) {
                    var data = res.data || {};
                    handleApiFailure(res, 'Nie udało się wysłać kodu ponownie.');
                    // Przy wygasłej sesji wróciliśmy już do kroku 1 - licznik
                    // kroku 2 nie ma wtedy czego odliczać.
                    if (!data.reset) {
                        // Serwer sam pilnuje odstępu - odliczamy tyle, ile każe.
                        startResendCountdown(data.resend_in || RESEND_COOLDOWN);
                    }
                    return;
                }

                clearOtp();
                focusFirstEmptyOtpBox();
                showAlert('Nowy kod został wysłany na Twój adres e-mail.', 'success');
                startResendCountdown(res.data.resend_in || RESEND_COOLDOWN);
                startExpiryCountdown(res.data.expires_in || 0);
            }).catch(function () {
                showAlert('Brak połączenia z serwerem. Spróbuj ponownie.', 'error');
                startResendCountdown(RESEND_COOLDOWN);
            });
        }

        // --- Odliczanie do ponownej wysyłki (60 s) ---
        function startResendCountdown(seconds) {
            stopResendCountdown();
            resendSecondsLeft = Math.max(0, Math.ceil(seconds));

            if (resendSecondsLeft <= 0) {
                renderResendButton();
                return;
            }

            renderResendButton();
            resendTimer = window.setInterval(function () {
                resendSecondsLeft -= 1;
                if (resendSecondsLeft <= 0) {
                    stopResendCountdown();
                }
                renderResendButton();
            }, 1000);
        }

        function stopResendCountdown() {
            if (resendTimer) {
                window.clearInterval(resendTimer);
                resendTimer = null;
            }
            resendSecondsLeft = 0;
        }

        function renderResendButton() {
            if (!els.otpResend) {
                return;
            }
            if (resendSecondsLeft > 0) {
                els.otpResend.disabled = true;
                els.otpResend.textContent = 'Wyślij ponownie za ' + resendSecondsLeft + ' s';
            } else {
                els.otpResend.disabled = false;
                els.otpResend.textContent = 'Wyślij kod ponownie';
            }
        }

        // --- Odliczanie ważności kodu ---
        function startExpiryCountdown(seconds) {
            stopExpiryCountdown();
            expirySecondsLeft = Math.max(0, Math.ceil(seconds));
            if (expirySecondsLeft <= 0) {
                renderExpiry();
                return;
            }
            renderExpiry();
            expiryTimer = window.setInterval(function () {
                expirySecondsLeft -= 1;
                if (expirySecondsLeft <= 0) {
                    stopExpiryCountdown();
                }
                renderExpiry();
            }, 1000);
        }

        function stopExpiryCountdown() {
            if (expiryTimer) {
                window.clearInterval(expiryTimer);
                expiryTimer = null;
            }
            expirySecondsLeft = 0;
        }

        function renderExpiry() {
            if (!els.otpExpiry) {
                return;
            }
            if (expirySecondsLeft <= 0) {
                els.otpExpiry.textContent = '';
                return;
            }
            var minutes = Math.floor(expirySecondsLeft / 60);
            var secs = expirySecondsLeft % 60;
            els.otpExpiry.textContent = 'Kod wygaśnie za ' + minutes + ':' + (secs < 10 ? '0' : '') + secs;
        }

        // ============================================================
        // KROK 3 -> założenie konta
        // ============================================================
        function submitStep3() {
            submittedSteps[3] = true;
            hideAlert();

            var firstInvalid = validateStep(3);
            if (firstInvalid) {
                firstInvalid.el.focus();
                return;
            }

            setLoading(els.submit, true, 'Tworzenie konta...');

            apiPost(API.register, {
                password: els.password.value,
                password_confirm: els.passwordConfirm.value,
                terms: els.terms && els.terms.checked ? '1' : '',
                marketing: els.marketing && els.marketing.checked ? '1' : '',
                website_hp: els.honeypot ? els.honeypot.value : ''
            }).then(function (res) {
                if (!res.ok) {
                    setLoading(els.submit, false);
                    handleApiFailure(res, 'Nie udało się utworzyć konta. Spróbuj ponownie.');
                    return;
                }

                // Sukces - przycisk zostaje zablokowany aż do przekierowania,
                // żeby podwójne kliknięcie nie wysłało formularza drugi raz.
                showAlert('Konto zostało utworzone. Przekierowujemy do logowania...', 'success');
                window.location.href = res.data.redirect || '/login';
            }).catch(function () {
                setLoading(els.submit, false);
                showAlert('Brak połączenia z serwerem. Sprawdź internet i spróbuj ponownie.', 'error');
            });
        }

        // ============================================================
        // SIŁA HASŁA + SZACOWANY CZAS ZŁAMANIA
        // ============================================================
        var COMMON_PATTERNS = [
            'password', 'passw0rd', 'haslo', 'hasło', 'qwerty', 'iloveyou', 'admin',
            'letmein', 'welcome', 'dragon', 'monkey', 'football', 'minecraft', 'dnct',
            'zaq12wsx', 'trustno1', 'superman'
        ];
        var SEQUENCES = [
            '0123', '1234', '2345', '3456', '4567', '5678', '6789',
            'abcd', 'bcde', 'qwer', 'wert', 'asdf', 'zxcv', 'jklm'
        ];
        var STRENGTH_LABELS = ['Bardzo słabe', 'Słabe', 'Średnie', 'Silne', 'Bardzo silne'];
        var GUESSES_PER_SECOND = 1e10; // offline, szybki sprzęt do łamania haseł

        function estimateEntropy(pw) {
            if (!pw) {
                return 0;
            }
            var pool = 0;
            if (/[a-z]/.test(pw)) {
                pool += 26;
            }
            if (/[A-Z]/.test(pw)) {
                pool += 26;
            }
            if (/[0-9]/.test(pw)) {
                pool += 10;
            }
            if (/[^A-Za-z0-9]/.test(pw)) {
                pool += 33;
            }
            if (pool === 0) {
                return 0;
            }

            // Powtórzone znaki wnoszą mniej losowości niż unikalne.
            var unique = {};
            var uniqueCount = 0;
            for (var i = 0; i < pw.length; i++) {
                if (!unique[pw[i]]) {
                    unique[pw[i]] = true;
                    uniqueCount++;
                }
            }
            var effectiveLength = uniqueCount + (pw.length - uniqueCount) * 0.5;
            var entropy = effectiveLength * (Math.log(pool) / Math.LN2);

            var lower = pw.toLowerCase();
            var hasCommon = COMMON_PATTERNS.some(function (word) {
                return lower.indexOf(word) !== -1;
            });
            if (hasCommon) {
                entropy = Math.min(entropy, 18);
            }
            var hasSequence = SEQUENCES.some(function (seq) {
                return lower.indexOf(seq) !== -1;
            });
            if (hasSequence) {
                entropy -= 8;
            }

            return Math.max(0, entropy);
        }

        function scoreFromEntropy(entropy) {
            if (entropy < 28) {
                return 0;
            }
            if (entropy < 40) {
                return 1;
            }
            if (entropy < 56) {
                return 2;
            }
            if (entropy < 72) {
                return 3;
            }
            return 4;
        }

        function crackSeconds(entropy) {
            // Średnio atakujący przeszukuje połowę przestrzeni: 2^(entropy-1) prób.
            var log10Seconds = (entropy - 1) * Math.log(2) / Math.LN10 - Math.log(GUESSES_PER_SECOND) / Math.LN10;
            if (log10Seconds > 30) {
                return Infinity;
            }
            return Math.pow(10, log10Seconds);
        }

        function plural(n, one, few, many) {
            var i = Math.floor(n);
            if (i === 1) {
                return one;
            }
            var mod10 = i % 10;
            var mod100 = i % 100;
            if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
                return few;
            }
            return many;
        }

        function count(n, one, few, many) {
            var i = Math.max(1, Math.round(n));
            return i + ' ' + plural(i, one, few, many);
        }

        function formatYears(years) {
            var scales = [[1e12, 'bln'], [1e9, 'mld'], [1e6, 'mln'], [1e3, 'tys.']];
            for (var i = 0; i < scales.length; i++) {
                if (years >= scales[i][0]) {
                    return Math.round(years / scales[i][0]).toLocaleString('pl-PL') + ' ' + scales[i][1] + ' lat';
                }
            }
            return count(years, 'rok', 'lata', 'lat');
        }

        function formatDuration(seconds) {
            if (!isFinite(seconds) || seconds > 3.15e22) { // > bilion bilionów lat
                return 'praktycznie nie do złamania';
            }
            if (seconds < 1) {
                return 'natychmiast';
            }
            if (seconds < 60) {
                return count(seconds, 'sekunda', 'sekundy', 'sekund');
            }
            var minutes = seconds / 60;
            if (minutes < 60) {
                return count(minutes, 'minuta', 'minuty', 'minut');
            }
            var hours = minutes / 60;
            if (hours < 24) {
                return count(hours, 'godzina', 'godziny', 'godzin');
            }
            var days = hours / 24;
            if (days < 30) {
                return count(days, 'dzień', 'dni', 'dni');
            }
            var months = days / 30.44;
            if (months < 12) {
                return count(months, 'miesiąc', 'miesiące', 'miesięcy');
            }
            return formatYears(days / 365.25);
        }

        function updatePasswordMeter() {
            if (!els.password) {
                return;
            }
            var pw = els.password.value;

            // Wymagania odhaczane na żywo
            if (els.requirements) {
                var checks = passwordChecks(pw);
                Object.keys(checks).forEach(function (key) {
                    var item = els.requirements.querySelector('[data-req="' + key + '"]');
                    if (item) {
                        item.classList.toggle('is-met', checks[key]);
                    }
                });
            }

            if (!els.meter) {
                return;
            }

            if (pw === '') {
                els.meter.setAttribute('data-strength', '');
                if (els.meterLabel) {
                    els.meterLabel.textContent = 'Siła hasła';
                }
                if (els.crackTime) {
                    els.crackTime.textContent = 'Złamanie: -';
                }
                return;
            }

            var entropy = estimateEntropy(pw);
            var score = scoreFromEntropy(entropy);
            els.meter.setAttribute('data-strength', String(score));
            if (els.meterLabel) {
                els.meterLabel.textContent = STRENGTH_LABELS[score];
            }
            if (els.crackTime) {
                els.crackTime.textContent = 'Złamanie: ' + formatDuration(crackSeconds(entropy));
            }
        }

        // ============================================================
        // PODGLĄD HASŁA
        // ============================================================
        function bindPasswordToggle(buttonId, input) {
            var button = document.getElementById(buttonId);
            if (!button || !input) {
                return;
            }
            button.addEventListener('click', function () {
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.innerHTML = reveal
                    ? '<i data-lucide="eye-off" class="w-5 h-5"></i>'
                    : '<i data-lucide="eye" class="w-5 h-5"></i>';
                button.setAttribute('aria-label', reveal ? 'Ukryj hasło' : 'Pokaż hasło');
                button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                refreshIcons();
            });
        }

        // ============================================================
        // BLOKADA WYSYŁKI KROKU 3 DO CZASU AKCEPTACJI REGULAMINU
        // ============================================================
        function updateSubmitState() {
            if (!els.submit || els.submit.classList.contains('is-loading')) {
                return;
            }
            var accepted = !!(els.terms && els.terms.checked);
            els.submit.disabled = !accepted;
            els.submit.setAttribute('aria-disabled', accepted ? 'false' : 'true');
            if (els.submitHint) {
                els.submitHint.classList.toggle('hidden', accepted);
            }
        }

        // ============================================================
        // PODPIĘCIE ZDARZEŃ
        // ============================================================
        Object.keys(fields).forEach(function (name) {
            var field = fields[name];
            if (!field.el) {
                return;
            }

            if (field.el.type === 'checkbox') {
                field.el.addEventListener('change', function () {
                    field.touched = true;
                    render(field);
                    updateSubmitState();
                });
                return;
            }

            // Reakcja natychmiastowa: poprawiony błąd znika bez czekania na blur.
            field.el.addEventListener('input', function () {
                // Każda zmiana unieważnia poprzedni werdykt z serwera.
                field.remoteResult = null;
                if (field.remote) {
                    scheduleRemoteCheck(field, false);
                }
                if (field === fields.password) {
                    updatePasswordMeter();
                    // Zmiana hasła wpływa też na status pola "Powtórz hasło".
                    if (fields.password_confirm.el && fieldValue(fields.password_confirm) !== '') {
                        render(fields.password_confirm);
                    }
                }
                render(field);
            });

            // Walidacja przy opuszczeniu pola.
            field.el.addEventListener('blur', function () {
                field.touched = true;
                if (field.remote) {
                    scheduleRemoteCheck(field, true);
                }
                render(field);
            });
        });

        bindOtpBoxes();
        bindPasswordToggle('toggle-register-password', els.password);
        bindPasswordToggle('toggle-register-password-confirm', els.passwordConfirm);

        // Przyciski kreatora (dalej / weryfikuj / ponów / wróć)
        form.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-action]') : null;
            if (!button || !form.contains(button)) {
                return;
            }

            var action = button.getAttribute('data-action');
            if (action === 'next') {
                submitStep1();
            } else if (action === 'verify') {
                submitStep2();
            } else if (action === 'resend') {
                resendCode();
            } else if (action === 'back') {
                hideAlert();
                goToStep(Number(button.getAttribute('data-target-step')) || 1, 'back');
            }
        });

        // Enter w dowolnym polu = zatwierdzenie bieżącego kroku.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (currentStep === 1) {
                submitStep1();
            } else if (currentStep === 2) {
                submitStep2();
            } else {
                submitStep3();
            }
        });

        // ============================================================
        // STAN POCZĄTKOWY
        // ============================================================
        var resume = { step: 1, email: '', expiresIn: 0, resendIn: 0 };
        var stateEl = document.getElementById('registration-state');
        if (stateEl) {
            try {
                resume = JSON.parse(stateEl.textContent || '{}') || resume;
            } catch (e) {
                // Nieprawidłowy JSON ignorujemy - zaczniemy od kroku 1.
            }
        }

        currentStep = Number(resume.step) || 1;
        updateProgress(currentStep);

        if (currentStep === 2) {
            // Rejestracja wznowiona na kroku 2 - odtwarzamy oba liczniki.
            startResendCountdown(Number(resume.resendIn) || 0);
            startExpiryCountdown(Number(resume.expiresIn) || 0);
        }

        updatePasswordMeter();
        updateSubmitState();
        refreshIcons();

        // Powrót przyciskiem "wstecz" (bfcache) nie może zostawić przycisków
        // w stanie ładowania.
        window.addEventListener('pageshow', function () {
            [els.step1Submit, els.step2Submit, els.submit].forEach(function (button) {
                setLoading(button, false);
            });
            updateSubmitState();
        });
    }
}());
