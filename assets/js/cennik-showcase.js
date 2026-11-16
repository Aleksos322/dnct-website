/* Katalog planów: przewijanie przypiętej sekcji steruje kartami, jak na głównej.
   Zakup i wycena pozostają w cennik.js. Na telefonie: przyciski i gest w bok. */
(function () {
    'use strict';
    var catalogue = document.querySelector('.cnk-catalogue');
    if (!catalogue) return;
    var scrollArea = catalogue.parentElement;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var backTop = document.querySelector('[data-catalogue-top]');
    /* Cele strzałki (2026-11-11): ustawia je aktywna karuzela w syncLayout.
       W pakietach strzałka w dół = pomiń pakiety; pod nimi strzałka w górę =
       wróć do karty startowej (DEVeloperski). */
    var catalogueNav = null;
    function skipY() {
        if (catalogueNav) return catalogueNav.skip();
        return scrollArea.getBoundingClientRect().bottom + window.scrollY - 88;
    }
    function startY() {
        if (catalogueNav) return catalogueNav.start();
        return 0;
    }
    if (backTop) {
        // Poza kontekstem warstw main: stopka nie może zasłaniać przycisku.
        document.body.appendChild(backTop);
        var selection = document.getElementById('pick-bar');
        if (selection) {
            var updateSelectionOffset = function () {
                backTop.classList.toggle('is-above-selection', selection.classList.contains('is-visible'));
            };
            new MutationObserver(updateSelectionOffset).observe(selection, { attributes: true, attributeFilter: ['class'] });
            updateSelectionOffset();
        }
        var backDown = null;
        function updateBackTop() {
            var down = window.scrollY < skipY() - 40;
            backTop.hidden = false;
            if (down === backDown) return;
            backDown = down;
            backTop.classList.toggle('is-down', down);
            var label = down ? 'Pomiń pakiety' : 'Wróć do pakietów';
            backTop.setAttribute('aria-label', label);
            backTop.title = label;
        }
        backTop.addEventListener('click', function () {
            var target = backDown ? skipY() : startY();
            if (window.dnctLenis && !reduce.matches) window.dnctLenis.scrollTo(target, { duration: 0.9 });
            else window.scrollTo({ top: target, behavior: reduce.matches ? 'instant' : 'smooth' });
            if (!backDown) {
                var heading = catalogue.querySelector('h1');
                if (heading) {
                    heading.tabIndex = -1;
                    heading.focus({ preventScroll: true });
                }
            }
        });
        window.addEventListener('scroll', updateBackTop, { passive: true });
        updateBackTop();
    }

    catalogue.querySelectorAll('[data-catalogue-carousel]').forEach(function (root) {
        var stage = root.querySelector('.cnk-plan-stage');
        var cards = Array.from(root.querySelectorAll('[data-catalogue-card]'));
        var buttons = Array.from(root.querySelectorAll('[data-catalogue-go]'));
        var count = root.querySelector('[data-catalogue-count]');
        var prev = root.querySelector('[data-catalogue-prev]');
        var next = root.querySelector('[data-catalogue-next]');
        var skip = root.querySelector('[data-catalogue-skip]');
        var hint = root.querySelector('.cnk-plan-navigation p');
        var active = 0, position = 0, queued = false, lastSwipe = 0, start = null, renderedActive = -1;
        var pinned = false, scrollDistance = 0;
        var pinTop = 88;
        if (!stage || !cards.length) return;

        /* TELEFON (2026-11-16): karuzela 3D była na palcu niewygodna - karty
           zmieniało się krótkim machnięciem w poprzek sceny, a pionowy ruch na
           niej łatwo trafiał obok. Do 860 px zostaje natywny rząd kart
           (bez .is-enhanced, przewijanie w bok z przyciąganiem, CSS), sąsiednie
           karty wystają po bokach. JS tylko śledzi, która karta jest na środku
           (licznik, strzałki, lista), i przewija do niej przy strzałkach. */
        var swipeMedia = window.matchMedia('(max-width: 860px)');
        var swipe = false, swipeStarted = false, swipeQueued = false, swipeCentered = false;
        function swipeCenter(index) {
            var card = cards[index];
            return card.offsetLeft - (stage.clientWidth - card.offsetWidth) / 2;
        }
        function swipeTo(index, immediate) {
            stage.scrollTo({ left: swipeCenter(index), behavior: immediate || reduce.matches ? 'instant' : 'smooth' });
        }
        function swipeTrack() {
            swipeQueued = false;
            if (!swipe || !stage.clientWidth) return;
            var best = 0, bestD = Infinity;
            cards.forEach(function (card, index) {
                var d = Math.abs(swipeCenter(index) - stage.scrollLeft);
                if (d < bestD) { bestD = d; best = index; }
            });
            if (best !== active) { active = best; position = best; render(); }
        }
        function setSwipe(on) {
            if (on === swipe && (on || root.classList.contains('is-enhanced'))) return;
            swipe = on;
            root.classList.toggle('is-enhanced', !on);
            root.classList.toggle('is-swipe', on);
            renderedActive = -1;
            swipeCentered = false;
            if (on) {
                cards.forEach(function (card) {
                    card.style.transform = card.style.opacity = card.style.visibility = card.style.zIndex = '';
                });
            }
        }
        // Start na karcie startowej (DEVeloperski) - Proxy wystaje po lewej.
        // Dopiero gdy rząd ma szerokość: karuzela w ukrytej zakładce ma 0 px.
        function swipeCenterOnce() {
            if (!swipe || swipeCentered || !stage.clientWidth) return;
            swipeCentered = true;
            swipeTo(active, true);
            swipeStarted = true;
            renderState();
        }
        stage.addEventListener('scroll', function () {
            if (swipe && swipeStarted && !swipeQueued) { swipeQueued = true; requestAnimationFrame(swipeTrack); }
        }, { passive: true });

        /* Pętla (2026-11-11, prośba właściciela). Karty leżą po okręgu: po lewej
           od najtańszej (Proxy) stoją - za przedziałką "od nowa" - najdroższe.
           Kolejność przewijania zostaje cenowa (Proxy -> ... -> najdroższy), ale
           strona startuje przewinięta na DEVeloperskim (inaczej pierwszy
           niewyprzedany plan), więc kółkiem w górę da się cofnąć do Proxy.
           Strzałki na końcach zawijają. offset zostaje 0 - pozycja w pętli
           = indeks karty. */
        var count_ = cards.length;
        var offset = 0;
        var startIdx = (function () {
            var picks = cards.map(function (c) { return c.querySelector('.plan-pick'); });
            var dev = picks.findIndex(function (p) { return p && /developer/i.test(p.dataset.planName || ''); });
            if (dev >= 0) return dev;
            var free = picks.findIndex(function (p) { return p && p.dataset.planSoldout !== '1'; });
            return free >= 0 ? free : 0;
        })();
        var looped = count_ > 2;
        function wrapIndex(i) { return ((i % count_) + count_) % count_; }
        function toLoop(index) { return wrapIndex(index - offset); }
        // Odległość po okręgu: -n/2 .. n/2.
        function circ(d) { return looped ? wrapIndex(d + count_ / 2) - count_ / 2 : d; }
        var seam = null;
        if (looped) {
            seam = document.createElement('div');
            seam.className = 'cnk-loop-seam';
            seam.setAttribute('aria-hidden', 'true');
            seam.innerHTML = '<span>od nowa</span>';
            stage.appendChild(seam);
        }
        var SEAM_GAP = 0.55; // dodatkowy odstęp (w krokach karty) w miejscu przedziałki
        active = position = startIdx;
        var startApplied = false;

        function scrollTo(top, immediate) {
            if (window.dnctLenis) window.dnctLenis.scrollTo(top, { immediate: !!immediate, duration: 0.65 });
            else window.scrollTo({ top: top, behavior: immediate || reduce.matches ? 'instant' : 'smooth' });
        }
        function scrollStart() {
            return scrollArea.getBoundingClientRect().top + window.scrollY - pinTop;
        }
        function syncLayout() {
            setSwipe(swipeMedia.matches);
            swipeCenterOnce();
            var selectedPanel = catalogue.querySelector('.cennik-panel.is-active');
            catalogue.classList.toggle('has-plan-cards', !!(selectedPanel && selectedPanel.querySelector('[data-catalogue-carousel]')));
            catalogue.classList.toggle('has-slot-configurator', !!(selectedPanel && selectedPanel.querySelector('.cfg')));
            var tableHead = selectedPanel && selectedPanel.querySelector('.cfg-table > summary');
            catalogue.style.setProperty('--cfg-tail', tableHead
                ? (tableHead.offsetHeight + 2 + (parseFloat(getComputedStyle(catalogue).rowGap) || 0)) + 'px'
                : '0px');
            var height = catalogue.offsetHeight;
            // Sekcja ma się zablokować od razu przy pierwszym scrollu, a nie
            // dopiero po przewinięciu odstępu nad nią - przypinamy ją tam, gdzie
            // leży naturalnie (jeśli się mieści), a 88 px zostaje zapasem.
            var natural = Math.round(scrollArea.getBoundingClientRect().top + window.scrollY);
            pinTop = natural > 0 && natural <= window.innerHeight - height ? natural : 88;
            scrollArea.style.setProperty('--cnk-pin-top', pinTop + 'px');
            // Kilka karuzel (Minecraft, Boty Discord) dzieli jeden scrollArea.
            // Nieaktywna nie może go ruszać - inaczej ta, która wykona się
            // później, zdejmowała przypięcie aktywnej i cennik przewijał
            // stronę razem z kartami (2026-11-11).
            if (!root.closest('.cennik-panel').classList.contains('is-active')) {
                pinned = false;
                // Aktywna zakładka bez karuzeli (konfigurator slotów): sprząta
                // przypięcie każda nieaktywna, bo nie ma kto inny.
                if (!selectedPanel || !selectedPanel.querySelector('[data-catalogue-carousel]')) {
                    catalogueNav = null;
                    scrollArea.classList.remove('is-scroll-driven');
                    scrollArea.style.height = '';
                }
                queueRender();
                return;
            }
            var wasPinned = pinned;
            var startY = scrollStart();
            var wasVisible = catalogue.getBoundingClientRect().bottom > pinTop && scrollArea.getBoundingClientRect().top <= pinTop;
            pinned = root.closest('.cennik-panel').classList.contains('is-active') &&
                window.innerWidth > 860 && !reduce.matches && cards.length > 1 && height <= window.innerHeight - pinTop;
            scrollDistance = (cards.length - 1) * window.innerHeight * 0.36;
            scrollArea.classList.toggle('is-scroll-driven', pinned);
            scrollArea.style.height = pinned ? (height + scrollDistance) + 'px' : '';
            hint.firstChild.nodeValue = pinned ? 'Przewiń, aby zmienić plan' : swipe ? 'Przesuń karty palcem' : 'Wybierz plan strzałkami';
            hint.querySelector('span').textContent = pinned ? 'lub wybierz go z listy' : swipe ? 'lub użyj strzałek' : 'lub przesuń karty';
            if (wasPinned && !pinned && wasVisible) scrollTo(Math.max(0, startY), true);
            // Pierwsze przypięcie: przewiń od razu na kartę startową. Tylko gdy
            // strona stoi na górze (nie po odświeżeniu w połowie, nie z kotwicą).
            catalogueNav = {
                skip: function () { return pinned ? scrollStart() + scrollArea.offsetHeight : scrollArea.getBoundingClientRect().bottom + window.scrollY - 88; },
                start: function () { return pinned ? scrollStart() + scrollDistance * startIdx / (cards.length - 1) : Math.max(0, catalogue.getBoundingClientRect().top + window.scrollY - 88); }
            };
            if (!startApplied && pinned) {
                startApplied = true;
                if (startIdx > 0 && window.scrollY < 5 && !location.hash) {
                    scrollTo(scrollStart() + scrollDistance * startIdx / (cards.length - 1), true);
                }
            }
            queueRender();
        }
        function render() {
            queued = false;
            if (!stage.clientWidth) return;
            if (swipe) { renderState(); return; }
            if (pinned) {
                position = Math.max(0, Math.min(cards.length - 1, (window.scrollY - scrollStart()) / scrollDistance * (cards.length - 1)));
                active = wrapIndex(offset + Math.round(position));
            }
            var absPos = offset + position;
            // Przedziałka leży między ostatnią (najdroższą) a pierwszą kartą.
            var seamD = circ(count_ - 0.5 - absPos);
            var width = cards[active].offsetWidth;
            var height = cards[active].offsetHeight;
            // Ten sam układ, obrót i zanikanie co renderP3d w home.js.
            var scale = Math.min(1, stage.clientHeight / Math.max(1, height));
            var narrow = window.innerWidth < 960;
            var step = width * (narrow ? 0.86 : 0.8);
            function layoutDistance(d) {
                if (!looped) return d;
                if (seamD > 0 && d > seamD) return d + SEAM_GAP;
                if (seamD < 0 && d < seamD) return d - SEAM_GAP;
                return d;
            }
            if (seam) {
                var sd = seamD + Math.sign(seamD) * SEAM_GAP / 2;
                var sa = Math.abs(sd);
                var sc = Math.max(-2.2, Math.min(2.2, sd));
                var sx = sc * step + (sa > 2.2 ? Math.sign(sd) * (sa - 2.2) * step * 0.25 : 0);
                var sz = -Math.min(sa, 3) * (narrow ? 200 : 260);
                seam.style.transform = 'translate(-50%, -50%) translate3d(' + sx.toFixed(1) + 'px, 0, ' + sz.toFixed(1) + 'px) rotateY(' + (-sc * (narrow ? 30 : 34)).toFixed(2) + 'deg) scale(' + (scale * (1 - Math.min(sa, 3) * 0.04)).toFixed(3) + ')';
                seam.style.opacity = sa > 2.6 ? '0' : (1 - Math.max(0, sa - 1.2) * 0.65).toFixed(3);
                seam.style.height = (height * 0.8) + 'px';
            }
            cards.forEach(function (card, index) {
                var distance = layoutDistance(circ(index - absPos));
                var absolute = Math.abs(distance);
                var clamped = Math.max(-2.2, Math.min(2.2, distance));
                var x = clamped * step + (absolute > 2.2 ? Math.sign(distance) * (absolute - 2.2) * step * 0.25 : 0);
                var z = -Math.min(absolute, 3) * (narrow ? 200 : 260);
                var rotation = -clamped * (narrow ? 30 : 34);
                var cardScale = scale * (1 - Math.min(absolute, 3) * 0.04);
                card.style.transform = 'translate(-50%, -50%) translate3d(' + x.toFixed(1) + 'px, 0, ' + z.toFixed(1) + 'px) rotateY(' + rotation.toFixed(2) + 'deg) scale(' + cardScale.toFixed(3) + ')';
                card.style.opacity = absolute > 2.6 ? '0' : (1 - Math.max(0, absolute - 1.2) * 0.65).toFixed(3);
                var vis = absolute > 2.6 ? 'hidden' : 'visible';
                if (card.style.visibility !== vis) card.style.visibility = vis;
                card.style.zIndex = String(100 - Math.round(absolute * 10));
            });
            // Stan aktywnej karty (inert, aria, lista, licznik) tylko przy zmianie,
            // jak lastActive w home.js. Przestawianie inert/aria-hidden na każdej
            // karcie w każdej klatce przewijania przeliczało style całego
            // katalogu i cennik przewijał się ciężej niż główna (2026-11-11).
            renderState();
        }
        function renderState() {
            if (active === renderedActive) return;
            renderedActive = active;
            cards.forEach(function (card, index) {
                card.classList.toggle('is-front', index === active);
                // W rzędzie na telefonie każda widoczna karta jest klikalna.
                card.setAttribute('aria-hidden', swipe || index === active ? 'false' : 'true');
                var pick = card.querySelector('.plan-pick');
                if (pick) pick.inert = !swipe && index !== active;
            });
            buttons.forEach(function (button, index) {
                button.classList.toggle('is-active', index === active);
                button.setAttribute('aria-pressed', index === active ? 'true' : 'false');
            });
            count.textContent = String(toLoop(active) + 1).padStart(2, '0');
            prev.disabled = !looped && active === 0;
            next.disabled = !looped && active === cards.length - 1;
        }
        function queueRender() {
            if (queued) return;
            queued = true;
            requestAnimationFrame(render);
        }
        function show(index) {
            index = looped ? wrapIndex(index) : Math.max(0, Math.min(cards.length - 1, index));
            var loopPos = looped ? toLoop(index) : index;
            if (swipe) {
                active = position = index;
                swipeTo(index);
                renderState();
                return;
            }
            if (pinned) {
                scrollTo(scrollStart() + scrollDistance * loopPos / (cards.length - 1));
                return;
            }
            active = index;
            position = loopPos;
            queueRender();
        }
        buttons.forEach(function (button) {
            button.addEventListener('click', function () { show(Number(button.dataset.catalogueGo)); });
        });
        prev.addEventListener('click', function () { show(active - 1); });
        next.addEventListener('click', function () { show(active + 1); });
        skip.addEventListener('click', function () {
            if (pinned) scrollTo(scrollStart() + scrollArea.offsetHeight);
        });
        cards.forEach(function (card, index) {
            card.addEventListener('click', function (event) {
                if (index !== active || Date.now() - lastSwipe < 400) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (Date.now() - lastSwipe >= 400) show(index);
                }
            }, true);
        });
        stage.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                show(active + (event.key === 'ArrowRight' ? 1 : -1));
            }
        });
        stage.addEventListener('pointerdown', function (event) {
            if (event.pointerType !== 'mouse' && !swipe) start = { x: event.clientX, y: event.clientY };
        }, { passive: true });
        stage.addEventListener('pointerup', function (event) {
            if (!start) return;
            var dx = event.clientX - start.x, dy = event.clientY - start.y;
            start = null;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.3) {
                lastSwipe = Date.now();
                show(active + (dx < 0 ? 1 : -1));
            }
        }, { passive: true });
        stage.addEventListener('pointercancel', function () { start = null; });
        var wheelAmount = 0, lastWheel = 0;
        // Niski ekran nie mieści całej sekcji sticky. Wtedy kółko nad CAŁYM
        // katalogiem nadal wybiera plany, a na krańcach oddaje scroll stronie.
        catalogue.addEventListener('wheel', function (event) {
            if (pinned || event.ctrlKey || event.metaKey || window.innerWidth <= 860 ||
                !root.closest('.cennik-panel').classList.contains('is-active')) return;
            var delta = (Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY) *
                (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? window.innerHeight : 1);
            var direction = Math.sign(delta);
            if (!direction || (direction < 0 && toLoop(active) === 0) || (direction > 0 && toLoop(active) === cards.length - 1)) return;
            event.preventDefault();
            event.stopPropagation();
            if (Date.now() - lastWheel < 350) return;
            if (Math.sign(wheelAmount) !== direction) wheelAmount = 0;
            wheelAmount += delta;
            if (Math.abs(wheelAmount) < 35) return;
            wheelAmount = 0;
            lastWheel = Date.now();
            show(active + direction);
        }, { passive: false, capture: true });
        if ('ResizeObserver' in window) {
            var observer = new ResizeObserver(syncLayout);
            observer.observe(stage);
            observer.observe(catalogue);
            cards.forEach(function (card) { observer.observe(card); });
        }
        window.addEventListener('scroll', function () { if (pinned) queueRender(); }, { passive: true });
        window.addEventListener('resize', syncLayout);
        reduce.addEventListener('change', syncLayout);
        catalogue.querySelectorAll('.cennik-tab').forEach(function (tab) { tab.addEventListener('click', syncLayout); });
        if (document.fonts) document.fonts.ready.then(syncLayout);
        swipeMedia.addEventListener('change', syncLayout);
        syncLayout();
    });

    // Pełna opinia nad paskiem, jak na głównej (home.js). Okienko jest
    // poza maskowaną taśmą, więc długi tekst nie zostaje przez nią ucięty.
    var reviewBox = catalogue.querySelector('.cnk-catalogue-reviews');
    var reviewTape = reviewBox && reviewBox.querySelector('.op-pasek-tasma');
    if (reviewTape) {
        var reviewDesktop = window.matchMedia('(min-width: 861px) and (hover: hover) and (pointer: fine)');
        var reviewPopup = null, reviewClose = null;
        function hideReview(immediate) {
            clearTimeout(reviewClose);
            if (!reviewPopup) return;
            var old = reviewPopup;
            reviewPopup = null;
            reviewTape.classList.remove('is-paused');
            if (immediate || reduce.matches) { old.remove(); return; }
            old.classList.add('is-zamyka');
            old.style.height = old.getAttribute('data-h0') + 'px';
            setTimeout(function () { old.remove(); }, 320);
        }
        function scheduleReviewClose() { reviewClose = setTimeout(function () { hideReview(false); }, 80); }
        function showReview(card) {
            if (!reviewDesktop.matches) return;
            hideReview(true);
            var r = card.getBoundingClientRect(), b = reviewBox.getBoundingClientRect();
            var pop = document.createElement('div');
            pop.className = 'op-pasek-pop';
            pop.setAttribute('aria-hidden', 'true'); // Pełny tekst pozostaje też w oryginalnej karcie.
            pop.appendChild(card.querySelector('.op-pasek-autor').cloneNode(true));
            var text = document.createElement('p');
            text.textContent = card.querySelector('p').textContent;
            pop.appendChild(text);
            pop.style.left = Math.max(0, Math.min(r.left - b.left, b.width - r.width)) + 'px';
            pop.style.bottom = (b.bottom - r.bottom) + 'px';
            pop.style.width = r.width + 'px';
            pop.style.height = r.height + 'px';
            pop.setAttribute('data-h0', String(r.height));
            reviewBox.appendChild(pop);
            reviewPopup = pop;
            reviewTape.classList.add('is-paused');
            var fullHeight = Math.max(r.height, pop.scrollHeight);
            requestAnimationFrame(function () { if (reviewPopup === pop) pop.style.height = fullHeight + 'px'; });
            pop.addEventListener('mouseleave', scheduleReviewClose);
            pop.addEventListener('mouseenter', function () { clearTimeout(reviewClose); });
        }
        reviewTape.querySelectorAll('.op-pasek-karta').forEach(function (card) {
            card.addEventListener('mouseenter', function () { showReview(card); });
            card.addEventListener('mouseleave', scheduleReviewClose);
            if (!card.hasAttribute('aria-hidden')) {
                card.tabIndex = 0;
                card.addEventListener('focus', function () { showReview(card); });
                card.addEventListener('blur', function () { hideReview(false); });
                card.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') { event.preventDefault(); hideReview(false); }
                });
            }
        });
        window.addEventListener('resize', function () { hideReview(true); });
        window.addEventListener('scroll', function () { hideReview(true); }, { passive: true });
        catalogue.querySelectorAll('.cennik-tab').forEach(function (tab) {
            tab.addEventListener('click', function () { hideReview(true); });
        });
    }

    // Dostępne z klawiatury zakładki oraz powroty po logowaniu (#cfg-...).
    var tabs = Array.from(catalogue.querySelectorAll('.cennik-tab'));
    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (item) { item.tabIndex = item === tab ? 0 : -1; });
        });
        tab.addEventListener('keydown', function (event) {
            var target;
            if (event.key === 'ArrowRight') target = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') target = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') target = 0;
            if (event.key === 'End') target = tabs.length - 1;
            if (target === undefined) return;
            event.preventDefault();
            tabs[target].click();
            tabs[target].focus();
        });
    });
    function selectHashTab() {
        var target = window.location.hash.slice(1).replace(/^cfg-/, 'game-');
        var tab = tabs.find(function (item) { return item.dataset.target === target; });
        if (tab && !tab.classList.contains('is-active')) tab.click();
        var selected = tabs.find(function (item) { return item.classList.contains('is-active'); });
        tabs.forEach(function (item) { item.tabIndex = item === selected ? 0 : -1; });
    }
    window.addEventListener('hashchange', selectHashTab);
    selectHashTab();
})();
