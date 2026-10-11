/* Koła zębate w cenniku Vintage Story - obrót razem z przewijaniem.
   Ustawia --vs-turn (deg) na zestawie; każde koło mnoży go przez własne
   przełożenie (--k w components/vs-world.php), więc zazębione pary kręcą
   się w przeciwne strony. W dół zgodnie z ruchem, w górę wstecz. */
(function () {
    'use strict';

    var scene = document.querySelector('[data-vs-scene]');
    if (!scene) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var lastY = window.scrollY, turn = 0, pending = false;

    window.addEventListener('scroll', function () {
        var y = window.scrollY;
        turn += (y - lastY) * 0.3;
        lastY = y;
        if (pending) return;
        pending = true;
        requestAnimationFrame(function () {
            pending = false;
            scene.style.setProperty('--vs-turn', turn.toFixed(1) + 'deg');
        });
    }, { passive: true });
})();
