/**
 * Rozsynchronizowanie przebłysku na taflach .glass.
 *
 * Sam efekt rysuje CSS (.glass::after w assets/navibar/navbar.css). Problem był
 * taki, że wszystkie tafle dzieliły jedną animację o stałym czasie, więc
 * błyskały DOKŁADNIE w tej samej sekundzie - navbar, karty planów i panele
 * naraz. Zamiast wrażenia szkła wychodziło mrugnięcie całej strony: oko
 * wyłapuje synchroniczny ruch natychmiast, nawet przy tak delikatnym efekcie.
 *
 * Skrypt nadaje każdej tafli własny czas cyklu i własny punkt startu.
 * Nie tworzy żadnych elementów i nic nie obserwuje - ustawia dwie zmienne
 * i kończy pracę.
 */
(function () {
    'use strict';

    // Bez JavaScriptu (i przy wyłączonych animacjach) CSS ma wartości zapasowe,
    // więc strona działa tak jak wcześniej - po prostu wspólnym rytmem.
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var MIN_CYCLE = 240;   // 4 min
    var MAX_CYCLE = 360;   // 6 min

    document.querySelectorAll('.glass').forEach(function (el) {
        /* RÓŻNE DŁUGOŚCI CYKLU, nie tylko różne opóźnienia. Przy równych czasach
           tafle rozjechane opóźnieniem wróciłyby do zgodnego rytmu po pierwszym
           pełnym cyklu - ten sam problem, tylko odsunięty o kilka minut. */
        var cycle = MIN_CYCLE + Math.random() * (MAX_CYCLE - MIN_CYCLE);

        /* UJEMNE opóźnienie ustawia animację w losowym miejscu cyklu OD RAZU.
           Dodatnie kazałoby czekać kilka minut na pierwszy przebłysk, a przy
           dłuższych cyklach większość odwiedzających nie zobaczyłaby go wcale. */
        el.style.setProperty('--sheen-duration', cycle.toFixed(1) + 's');
        el.style.setProperty('--sheen-delay', (-Math.random() * cycle).toFixed(1) + 's');
    });
})();
