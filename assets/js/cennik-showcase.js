/* Katalog planów: przewijanie przypiętej sekcji steruje kartami, jak na głównej.
   Zakup i wycena pozostają w cennik.js. Na telefonie: przyciski i gest w bok. */
(function () {
    'use strict';
    var catalogue = document.querySelector('.cnk-catalogue');
    if (!catalogue) return;
    var scrollArea = catalogue.parentElement;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    var backTop = document.querySelector('[data-catalogue-top]');
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
        function updateBackTop() { backTop.hidden = window.scrollY < 240; }
        backTop.addEventListener('click', function () {
            if (window.dnctLenis && !reduce.matches) window.dnctLenis.scrollTo(0, { duration: 0.9 });
            else window.scrollTo({ top: 0, behavior: reduce.matches ? 'instant' : 'smooth' });
            var heading = catalogue.querySelector('h1');
            if (heading) {
                heading.tabIndex = -1;
                heading.focus({ preventScroll: true });
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
        var active = 0, position = 0, queued = false, lastSwipe = 0, start = null;
        var pinned = false, scrollDistance = 0;
        var pinTop = 88;
        if (!stage || !cards.length) return;

        function scrollTo(top, immediate) {
            if (window.dnctLenis) window.dnctLenis.scrollTo(top, { immediate: !!immediate, duration: 0.65 });
            else window.scrollTo({ top: top, behavior: immediate || reduce.matches ? 'instant' : 'smooth' });
        }
        function scrollStart() {
            return scrollArea.getBoundingClientRect().top + window.scrollY - pinTop;
        }
        function syncLayout() {
            var selectedPanel = catalogue.querySelector('.cennik-panel.is-active');
            catalogue.classList.toggle('has-plan-cards', !!(selectedPanel && selectedPanel.querySelector('[data-catalogue-carousel]')));
            catalogue.classList.toggle('has-slot-configurator', !!(selectedPanel && selectedPanel.querySelector('.cfg')));
            var height = catalogue.offsetHeight;
            var wasPinned = pinned;
            var startY = scrollStart();
            var wasVisible = catalogue.getBoundingClientRect().bottom > pinTop && scrollArea.getBoundingClientRect().top <= pinTop;
            pinned = root.closest('.cennik-panel').classList.contains('is-active') &&
                window.innerWidth > 860 && !reduce.matches && cards.length > 1 && height <= window.innerHeight - pinTop;
            scrollDistance = (cards.length - 1) * window.innerHeight * 0.36;
            scrollArea.classList.toggle('is-scroll-driven', pinned);
            scrollArea.style.height = pinned ? (height + scrollDistance) + 'px' : '';
            hint.firstChild.nodeValue = pinned ? 'Przewiń, aby zmienić plan' : 'Wybierz plan strzałkami';
            hint.querySelector('span').textContent = pinned ? 'lub wybierz go z listy' : 'lub przesuń karty';
            if (wasPinned && !pinned && wasVisible) scrollTo(Math.max(0, startY), true);
            queueRender();
        }
        function render() {
            queued = false;
            if (!stage.clientWidth) return;
            if (pinned) {
                position = Math.max(0, Math.min(cards.length - 1, (window.scrollY - scrollStart()) / scrollDistance * (cards.length - 1)));
                active = Math.round(position);
            }
            var width = cards[active].offsetWidth;
            var height = cards[active].offsetHeight;
            // Ten sam układ, obrót i zanikanie co renderP3d w home.js.
            var scale = Math.min(1, stage.clientHeight / Math.max(1, height));
            var narrow = window.innerWidth < 960;
            var step = width * (narrow ? 0.86 : 0.8);
            cards.forEach(function (card, index) {
                var distance = index - position;
                var absolute = Math.abs(distance);
                var clamped = Math.max(-2.2, Math.min(2.2, distance));
                var x = clamped * step + (absolute > 2.2 ? Math.sign(distance) * (absolute - 2.2) * step * 0.25 : 0);
                var z = -Math.min(absolute, 3) * (narrow ? 200 : 260);
                var rotation = -clamped * (narrow ? 30 : 34);
                var cardScale = scale * (1 - Math.min(absolute, 3) * 0.04);
                card.style.transform = 'translate(-50%, -50%) translate3d(' + x.toFixed(1) + 'px, 0, ' + z.toFixed(1) + 'px) rotateY(' + rotation.toFixed(2) + 'deg) scale(' + cardScale.toFixed(3) + ')';
                card.style.opacity = absolute > 2.6 ? '0' : (1 - Math.max(0, absolute - 1.2) * 0.65).toFixed(3);
                card.style.visibility = absolute > 2.6 ? 'hidden' : 'visible';
                card.style.zIndex = String(100 - Math.round(absolute * 10));
                card.classList.toggle('is-front', index === active);
                card.setAttribute('aria-hidden', index === active ? 'false' : 'true');
                var pick = card.querySelector('.plan-pick');
                if (pick) pick.inert = index !== active;
            });
            buttons.forEach(function (button, index) {
                button.classList.toggle('is-active', index === active);
                button.setAttribute('aria-pressed', index === active ? 'true' : 'false');
            });
            count.textContent = String(active + 1).padStart(2, '0');
            prev.disabled = active === 0;
            next.disabled = active === cards.length - 1;
        }
        function queueRender() {
            if (queued) return;
            queued = true;
            requestAnimationFrame(render);
        }
        function show(index) {
            index = Math.max(0, Math.min(cards.length - 1, index));
            if (pinned) {
                scrollTo(scrollStart() + scrollDistance * index / (cards.length - 1));
                return;
            }
            active = position = index;
            queueRender();
        }
        root.classList.add('is-enhanced');
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
            if (event.pointerType !== 'mouse') start = { x: event.clientX, y: event.clientY };
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
            if (!direction || (direction < 0 && active === 0) || (direction > 0 && active === cards.length - 1)) return;
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
