/* Pasmo "Jak to działa" (components/jak-to-dziala.php).
   Przełącza klasę .is-active co STEP_MS, w pętli. Gra tylko, gdy pasmo jest
   na ekranie i karta jest widoczna - poza ekranem nie zużywa nic.
   Klik w krok przeskakuje do niego; najechanie kursorem wstrzymuje pętlę.
   Przy prefers-reduced-motion nie ma pętli, zostają same kliknięcia. */
(function () {
    'use strict';

    var STEP_MS = 4200;   // ten sam czas co --jtd-step-ms w CSS

    var root = document.querySelector('[data-jtd]');
    if (!root) return;

    var steps  = root.querySelectorAll('.jtd-step');
    var url    = root.querySelector('.jtd-url');
    var scenes = root.querySelectorAll('.jtd-scene');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var current = 0, timer = null, visible = false, hovered = false;

    function show(i) {
        current = (i + steps.length) % steps.length;
        for (var n = 0; n < steps.length; n++) {
            var on = n === current;
            steps[n].classList.toggle('is-active', on);
            steps[n].setAttribute('aria-current', on ? 'step' : 'false');
            // Restart animacji CSS: zdjęcie klasy i wymuszenie przeliczenia,
            // inaczej wracająca scenka zostałaby w stanie końcowym.
            scenes[n].classList.remove('is-active');
        }
        void root.offsetWidth;
        scenes[current].classList.add('is-active');
        // Krok 3 i 4 dzieje się już w panelu - pasek adresu idzie za sceną.
        if (url) url.textContent = scenes[current].getAttribute('data-url') || 'dnct.pl';
    }

    function stop() {
        clearTimeout(timer);
        timer = null;
        root.classList.remove('is-playing');
    }

    function play() {
        stop();
        if (reduce || !visible || hovered || document.hidden) return;
        root.classList.add('is-playing');
        timer = setTimeout(function () { show(current + 1); play(); }, STEP_MS);
    }

    for (var n = 0; n < steps.length; n++) {
        steps[n].addEventListener('click', (function (i) {
            return function () { show(i); play(); };
        })(n));
    }

    root.addEventListener('mouseenter', function () { hovered = true; root.classList.add('is-paused'); stop(); });
    root.addEventListener('mouseleave', function () { hovered = false; root.classList.remove('is-paused'); play(); });
    document.addEventListener('visibilitychange', play);

    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            visible = entries[0].isIntersecting;
            if (visible) { show(current); play(); } else { stop(); }
        }, { threshold: 0.35 }).observe(root);
    } else {
        visible = true;
        play();
    }
})();
