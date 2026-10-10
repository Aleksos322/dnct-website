/*
 * Taśma opinii z Google (components/opinie-google.php).
 *
 * 1. Losowa kolejność przy każdym wejściu (Fisher-Yates).
 * 2. Pętla bez szwu: karty są powielane, aż jedna kopia jest szersza od
 *    ekranu, potem cały zestaw jeszcze raz - animacja CSS przesuwa tor
 *    o dokładnie połowę (--opinie-przesuw) i zaczyna od nowa.
 * 3. Kopie są aria-hidden i bez fokusu - czytnik ekranu czyta każdą opinię raz.
 * Bez JS albo przy prefers-reduced-motion zostaje zwykły rząd do przewijania.
 */
(function () {
    // Dopisek "zostaw nam opinię!" rysuje się dopiero, gdy nagłówek wjedzie w kadr.
    var dopisek = document.querySelector('.opinie-scribble');
    if (dopisek && 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        dopisek.classList.add('is-czeka');
        var obs = new IntersectionObserver(function (wpisy) {
            if (!wpisy[0].isIntersecting) return;
            dopisek.classList.add('is-widoczne');
            obs.disconnect();
        }, { threshold: 0.6 });
        obs.observe(dopisek);
    }

    var tasma = document.querySelector('[data-opinie]');
    var tor = tasma && tasma.querySelector('[data-opinie-tor]');
    if (!tor) return;

    var karty = Array.prototype.slice.call(tor.children);
    for (var i = karty.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = karty[i]; karty[i] = karty[j]; karty[j] = t;
    }
    karty.forEach(function (k) { tor.appendChild(k); });

    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    function kopia(k) {
        var c = k.cloneNode(true);
        c.setAttribute('aria-hidden', 'true');
        c.querySelectorAll('a').forEach(function (a) { a.setAttribute('tabindex', '-1'); });
        return c;
    }

    // Jeden zestaw musi być szerszy od taśmy, inaczej przy 2-3 opiniach widać dziurę.
    var zestaw = karty.slice();
    var guard = 0;
    while (tor.scrollWidth < tasma.clientWidth + 400 && guard++ < 12) {
        karty.forEach(function (k) { var c = kopia(k); tor.appendChild(c); zestaw.push(c); });
    }
    zestaw.forEach(function (k) { tor.appendChild(kopia(k)); });

    var polowa = tor.scrollWidth / 2;
    tor.style.setProperty('--opinie-przesuw', '-' + polowa + 'px');
    // ok. 35 px/s - spokojnie, da się przeczytać kartę, zanim ucieknie.
    tor.style.setProperty('--opinie-czas', Math.round(polowa / 35) + 's');
    tasma.classList.add('is-ruch');
})();
