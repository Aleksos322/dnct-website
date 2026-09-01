/**
 * Spokojny, "ciężki" scroll na wzór autoclicker.blur009.com - biblioteka Lenis
 * (assets/js/lenis.min.js, wgrana lokalnie jak reszta skryptów, nie z CDN)
 * wygładza kółko myszy/touchpad, żeby przewijanie miało bezwładność zamiast
 * skakać o całą deltę na raz.
 *
 * Celowo BEZ scroll-snap: strona ma długie sekcje (plany, Discord), a
 * "mandatory" snap na takich blokach więzi w połowie treści zamiast pomagać.
 * Efekt "łatwiej wjechać, trudniej wyjechać" z prośby użytkownika dają same
 * ustawienia Lenis (duration + easeOutExpo) - wjazd w sekcję dostaje ten sam
 * spokojny doheg co reszta scrolla, więc nic nie "przeskakuje" pod kursorem.
 */
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (typeof window.Lenis === 'undefined') return;

    var lenis = new Lenis({
        duration: 1.35,
        easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); },
        smoothWheel: true,
        wheelMultiplier: 0.85,
        touchMultiplier: 1.1,
    });

    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    window.dnctLenis = lenis;
})();
