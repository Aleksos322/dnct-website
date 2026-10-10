/* Pasmo "Bez gwiazdek i dopłat" / "Ryzykujesz mniej" (cennik.php, data-split).
 *
 * Lewa kolumna: po wjeździe w kadr wiersze "odhaczają się" po kolei, a rachunek
 * pod listą kręci cyframi jak licznik i staje na 0 zł (losowe cyfry, nie żadna
 * "cena u konkurencji" - nie podajemy kwoty, której nie da się obronić).
 * Prawa kolumna: aktywny punkt przełącza się co CYKL_MS jak w "Jak to działa"
 * (jak-to-dziala.js) - pasek postępu, pauza pod kursorem, klik przeskakuje,
 * poza ekranem nic nie chodzi. Przy prefers-reduced-motion: stan końcowy, bez pętli.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-split]');
    if (!root) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce || !('IntersectionObserver' in window)) return;

    /* --- Lewa: odhaczanie i rachunek -------------------------------------- */
    var lista = root.querySelector('[data-odhacz]');
    var rachunek = root.querySelector('.cnk-rachunek');
    var kwota = root.querySelector('[data-rachunek]');
    if (lista) {
        var wiersze = lista.querySelectorAll('li');
        for (var n = 0; n < wiersze.length; n++) wiersze[n].style.setProperty('--n', n);
        lista.classList.add('is-czeka');
        if (kwota) { kwota.textContent = '--,--'; rachunek.classList.add('is-liczy'); }

        new IntersectionObserver(function (wpisy, obs) {
            if (!wpisy[0].isIntersecting) return;
            obs.disconnect();
            lista.classList.remove('is-czeka');
            if (!kwota) return;
            // Liczenie w dół rusza, gdy ostatni ptaszek jest już narysowany.
            var start = null, CZAS = 1100, OPOZNIENIE = wiersze.length * 160 + 300;
            setTimeout(function krok(t) {
                requestAnimationFrame(function klatka(ts) {
                    if (start === null) start = ts;
                    var p = Math.min(1, (ts - start) / CZAS);
                    var v = Math.random() * 99 * Math.pow(1 - p, 2);
                    kwota.textContent = v.toFixed(2).replace('.', ',');
                    if (p < 1) { requestAnimationFrame(klatka); return; }
                    kwota.textContent = '0,00';
                    rachunek.classList.remove('is-liczy');
                    rachunek.classList.add('is-zero');
                });
            }, OPOZNIENIE);
        }, { threshold: 0.4 }).observe(lista);
    }

    /* --- Prawa: cykl punktów ---------------------------------------------- */
    var cykl = root.querySelector('[data-cykl]');
    if (!cykl) return;
    var CYKL_MS = 3800;
    cykl.style.setProperty('--cykl-ms', CYKL_MS + 'ms');
    var kroki = cykl.querySelectorAll('[data-krok]');
    var licznik = cykl.querySelector('[data-role="count"]');
    var cel = licznik ? parseInt(licznik.textContent, 10) : 0;
    var biezacy = 0, timer = null, widoczny = false, pod = false, odliczanie = null;

    function odlicz() {
        if (!licznik || !cel) return;
        clearInterval(odliczanie);
        var v = 1;
        licznik.textContent = '1';
        odliczanie = setInterval(function () {
            v += 1;
            licznik.textContent = String(v);
            if (v >= cel) { clearInterval(odliczanie); odliczanie = null; }
        }, 900 / cel);
    }

    function pokaz(i) {
        biezacy = (i + kroki.length) % kroki.length;
        for (var k = 0; k < kroki.length; k++) kroki[k].classList.remove('is-active');
        void cykl.offsetWidth;   // restart animacji CSS w wracającym punkcie
        kroki[biezacy].classList.add('is-active');
        if (kroki[biezacy].contains(licznik)) odlicz();
    }

    function stop() { clearTimeout(timer); timer = null; cykl.classList.remove('is-playing'); }
    function graj() {
        stop();
        if (!widoczny || pod || document.hidden) return;
        cykl.classList.add('is-playing');
        timer = setTimeout(function () { pokaz(biezacy + 1); graj(); }, CYKL_MS);
    }

    for (var k = 0; k < kroki.length; k++) {
        kroki[k].addEventListener('click', (function (i) { return function () { pokaz(i); graj(); }; })(k));
    }
    cykl.addEventListener('mouseenter', function () { pod = true; cykl.classList.add('is-paused'); stop(); });
    cykl.addEventListener('mouseleave', function () { pod = false; cykl.classList.remove('is-paused'); graj(); });
    document.addEventListener('visibilitychange', graj);

    new IntersectionObserver(function (wpisy) {
        var byl = widoczny;
        widoczny = wpisy[0].isIntersecting;
        if (widoczny && !byl) { pokaz(biezacy); graj(); } else if (!widoczny) { stop(); }
    }, { threshold: 0.35 }).observe(cykl);
})();
