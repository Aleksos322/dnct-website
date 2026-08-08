/**
 * Przełączanie zakładek gier w cenniku.
 *
 * Skrypt jest opcjonalny: bez JavaScriptu widoczny zostaje pierwszy panel
 * (ma klasę is-active już w HTML-u), więc cennik nadal się czyta. Zakładki
 * dokładają jedynie wygodę przełączania.
 */
(function () {
    'use strict';

    var tabs = document.querySelectorAll('.cennik-tab');
    if (!tabs.length) return;

    function activate(tab) {
        var targetId = tab.getAttribute('data-target');

        document.querySelectorAll('.cennik-tab').forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });

        document.querySelectorAll('.cennik-panel').forEach(function (p) {
            p.classList.toggle('is-active', p.id === targetId);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { activate(tab); });
    });
})();
