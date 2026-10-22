/* Konfigurator slotów: pasek "Twoja ekipa" i przekładnia Vintage Story.
   Słucha cfg:render z initConfigurator() (cennik.js) - ten sam próg, który
   liczy cenę, więc ozdoba nie może się rozjechać z wyborem. Postacie
   zapalają się falą od miejsca, w którym był poprzedni wybór. */
(function () {
    'use strict';

    document.querySelectorAll('.cfg').forEach(function (root) {
        var ludzie = Array.prototype.slice.call(root.querySelectorAll('.cfg-ekipa-ludzie i'));
        var ile = root.querySelector('[data-role="ekipaIle"]');
        var tryby = root.querySelector('[data-role="tryby"]');
        var poprzednio = null;

        ludzie.forEach(function (el, k) { el.style.setProperty('--k', k); });
        // Stan startowy z PHP (cennik.js wysłał już pierwsze cfg:render, zanim ten plik się wczytał).
        if (tryby) tryby.style.setProperty('--obrot', (root.querySelectorAll('.cfg-ekipa-ludzie i.on').length * 6) + 'deg');

        root.addEventListener('cfg:render', function (e) {
            var tier = e.detail.tier, slots = tier.slots;
            var od = poprzednio === null ? slots : poprzednio;
            ludzie.forEach(function (el, k) {
                var zmiana = (k < slots) !== el.classList.contains('on');
                // Fala: im dalej od poprzedniej granicy, tym później.
                el.style.setProperty('--w', zmiana ? Math.min(600, Math.abs(k - od) * 14) + 'ms' : '0ms');
                el.classList.toggle('on', k < slots);
                el.classList.toggle('is-out', k < slots && !!tier.soldOut);
            });
            if (ile) ile.textContent = slots;
            if (tryby) tryby.style.setProperty('--obrot', (slots * 6) + 'deg');
            poprzednio = slots;
        });
    });
})();
