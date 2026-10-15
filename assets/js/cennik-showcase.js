/* Katalog planów: widok 3D jak na głównej, bez przypinania użytkownika
   na kilku ekranach przewijania. Zakup i wycena pozostają w cennik.js. */
(function () {
    'use strict';
    var catalogue = document.querySelector('.cnk-catalogue');
    if (!catalogue) return;

    catalogue.querySelectorAll('[data-catalogue-carousel]').forEach(function (root) {
        var stage = root.querySelector('.cnk-plan-stage');
        var cards = Array.from(root.querySelectorAll('[data-catalogue-card]'));
        var buttons = Array.from(root.querySelectorAll('[data-catalogue-go]'));
        var count = root.querySelector('[data-catalogue-count]');
        var prev = root.querySelector('[data-catalogue-prev]');
        var next = root.querySelector('[data-catalogue-next]');
        var active = 0, queued = false, lastSwipe = 0, start = null;
        if (!stage || !cards.length) return;

        function render() {
            queued = false;
            if (!stage.clientWidth) return;
            var width = cards[active].offsetWidth;
            var height = cards[active].offsetHeight;
            var scale = Math.min(1, (stage.clientHeight - 32) / Math.max(1, height));
            var compact = window.innerWidth <= 860;
            cards.forEach(function (card, index) {
                var distance = index - active;
                var spread = Math.min(3, Math.abs(distance));
                var direction = Math.sign(distance);
                var visible = Math.abs(distance) <= 2;
                var x = direction * spread * width * (compact ? 0.66 : 0.78);
                var z = -spread * (compact ? 170 : 230);
                var rotation = -direction * Math.min(spread, 2.2) * 28;
                card.style.transform = 'translate(-50%, -50%) translate3d(' + x + 'px, 0, ' + z + 'px) rotateY(' + rotation + 'deg) scale(' + (scale * (1 - spread * 0.04)) + ')';
                card.style.opacity = visible ? '1' : '0';
                card.style.visibility = visible ? 'visible' : 'hidden';
                card.style.zIndex = String(10 - spread);
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
            active = Math.max(0, Math.min(cards.length - 1, index));
            queueRender();
        }
        root.classList.add('is-enhanced');
        buttons.forEach(function (button) {
            button.addEventListener('click', function () { show(Number(button.dataset.catalogueGo)); });
        });
        prev.addEventListener('click', function () { show(active - 1); });
        next.addEventListener('click', function () { show(active + 1); });
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
        stage.addEventListener('wheel', function (event) {
            if (event.ctrlKey || event.metaKey || window.innerWidth <= 860) return;
            var delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
            var direction = Math.sign(delta);
            if (!direction || (direction < 0 && active === 0) || (direction > 0 && active === cards.length - 1)) return;
            event.preventDefault();
            if (Date.now() - lastWheel < 350) return;
            if (Math.sign(wheelAmount) !== direction) wheelAmount = 0;
            wheelAmount += delta;
            if (Math.abs(wheelAmount) < 35) return;
            wheelAmount = 0;
            lastWheel = Date.now();
            show(active + direction);
        }, { passive: false });
        if ('ResizeObserver' in window) {
            var observer = new ResizeObserver(queueRender);
            observer.observe(stage);
            cards.forEach(function (card) { observer.observe(card); });
        }
        window.addEventListener('resize', queueRender);
        catalogue.querySelectorAll('.cennik-tab').forEach(function (tab) { tab.addEventListener('click', queueRender); });
        if (document.fonts) document.fonts.ready.then(queueRender);
        queueRender();
    });

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
