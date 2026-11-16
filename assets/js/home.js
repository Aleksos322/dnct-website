/* Strona główna (index.php): konsola w karcie serwera, przechył karty
   i wejście kafli "O hostingu" w kadr. Wygląd: assets/css/home.css.

   Konsola ma WŁASNY znacznik (data-home-console) - panel-band.js szuka
   pierwszego [data-role="consoleBody"] na stronie i drugi taki element
   zabrałby mu konsolę z pasma "Zobacz, zanim zapłacisz". */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --- Konsola w karcie ---------------------------------------------- */
    var LINES = [
        ['12:04:01', 'Starting minecraft server version 26.2', ''],
        ['12:04:01', 'Loading properties', ''],
        ['12:04:02', 'Preparing level "world"', ''],
        ['12:04:05', 'Preparing spawn area: 84%', ''],
        ['12:04:06', 'Done (6.2s)! For help, type "help"', 'ok'],
        ['12:04:31', 'Kasia joined the game', ''],
        ['', '> say Witajcie na serwerze!', 'cmd'],
        ['12:04:40', '[Server] Witajcie na serwerze!', ''],
        ['12:05:12', 'Kopia zapasowa gotowa (412 MB)', 'ok']
    ];

    var box = document.querySelector('[data-home-console]');
    if (box) {
        var cursor = document.createElement('span');
        cursor.className = 'cur';

        var line = function (e) {
            var row = document.createElement('div');
            if (e[0]) {
                var t = document.createElement('span');
                t.className = 't';
                t.textContent = '[' + e[0] + '] ';
                row.appendChild(t);
            }
            var m = document.createElement('span');
            if (e[2]) m.className = e[2];
            m.textContent = e[1];
            row.appendChild(m);
            box.appendChild(row);
            box.scrollTop = box.scrollHeight;
        };

        if (reduce) {
            LINES.forEach(line);
        } else {
            var i = 0, timer = null, running = false;
            var step = function () {
                if (i >= LINES.length) {
                    timer = setTimeout(function () { box.textContent = ''; i = 0; step(); }, 3800);
                    return;
                }
                if (cursor.parentNode) cursor.parentNode.removeChild(cursor);
                line(LINES[i]);
                box.lastChild.appendChild(cursor);
                i += 1;
                timer = setTimeout(step, LINES[i - 1][2] === 'cmd' ? 900 : 450 + Math.random() * 400);
            };
            // Poza ekranem konsola stoi - nic nie liczy w tle.
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (en) {
                    if (en[0].isIntersecting && !running) { running = true; step(); }
                    else if (!en[0].isIntersecting && running) { running = false; clearTimeout(timer); }
                }).observe(box);
            } else {
                step();
            }
        }
    }

    /* --- Przechył karty ------------------------------------------------- */
    /* Ten sam wzór co attachChromaTilt w cennik.js, łącznie z przeliczeniem
       na scroll: Lenis potrafi dojechać kartę pod nieruchomy kursor, a wtedy
       bez tego przechył zostawał zamrożony na starej wartości. */
    var card = document.querySelector('[data-home-tilt]');
    if (card && !reduce && window.matchMedia('(pointer: fine)').matches) {
        var lastX = null, lastY = null, queued = false, MAX = 5;
        var apply = function () {
            queued = false;
            if (lastX === null) return;
            var r = card.getBoundingClientRect();
            var inside = lastX >= r.left && lastX <= r.right && lastY >= r.top && lastY <= r.bottom;
            if (!inside) { card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); return; }
            var px = (lastX - r.left) / r.width, py = (lastY - r.top) / r.height;
            card.style.setProperty('--ry', ((px - 0.5) * 2 * MAX).toFixed(2) + 'deg');
            card.style.setProperty('--rx', ((0.5 - py) * 2 * MAX).toFixed(2) + 'deg');
        };
        var schedule = function () { if (!queued) { queued = true; requestAnimationFrame(apply); } };
        card.addEventListener('mousemove', function (e) { lastX = e.clientX; lastY = e.clientY; schedule(); });
        card.addEventListener('mouseleave', function () { lastX = null; card.style.setProperty('--rx', '0deg'); card.style.setProperty('--ry', '0deg'); });
        window.addEventListener('scroll', function () { if (lastX !== null) schedule(); }, { passive: true });
    }

    /* --- Plany w 3D (sekcja #pricing, [data-plans3d]) ------------------ */
    /* Sekcja jest wysoka na (n-1) * 36vh ponad ekran i przypięta (sticky).
       Postęp przewijania przez nią = pozycja karuzeli: 0 -> pierwszy plan,
       1 -> ostatni. Każda karta dostaje transform z odległości od pozycji
       (d): przesunięcie w bok, w głąb i obrót wokół osi Y. Liczone w rAF,
       tylko gdy sekcja jest blisko ekranu. */
    var p3d = document.querySelector('[data-plans3d]');

    /* TELEFON (2026-11-16, prośba właściciela): przypięta karuzela 3D
       zmieniała plany przewijaniem w dół, a kartę zmniejszała do wysokości
       ekranu - mała i nie było wiadomo, że to w ogóle karuzela. Do 860 px
       zostaje zwykły rząd kart w pełnym rozmiarze, przesuwany palcem
       (scroll-snap, CSS .is-swipe), kropki pod spodem i jednorazowe
       "szturchnięcie" kart przy pierwszym pokazaniu. Start na DEVeloperskim,
       Proxy wystaje po lewej. Tryb wybierany przy wczytaniu strony. */
    var homeSwipe = !!p3d && window.matchMedia('(max-width: 860px)').matches;
    if (homeSwipe) {
        (function () {
            var cards = Array.prototype.slice.call(p3d.querySelectorAll('[data-p3d]'));
            var stage = p3d.querySelector('.home-p3d-stage');
            var nowEl = p3d.querySelector('[data-p3d-now]');
            var n = cards.length, active = -1, queued = false;
            if (!stage || !n) return;
            p3d.classList.add('is-swipe');
            var startIdx = (function () {
                var names = cards.map(function (c) { var h = c.querySelector('h3, .pcard-name'); return h ? h.textContent : ''; });
                var dev = names.findIndex(function (t) { return /developer/i.test(t); });
                if (dev >= 0) return dev;
                var free = cards.findIndex(function (c) { return !c.querySelector('.pcard.is-soldout'); });
                return free >= 0 ? free : 0;
            })();
            var dots = document.createElement('div');
            dots.className = 'home-swipe-dots';
            dots.setAttribute('aria-hidden', 'true');
            cards.forEach(function () { dots.appendChild(document.createElement('span')); });
            stage.insertAdjacentElement('afterend', dots);
            var center = function (i) { return cards[i].offsetLeft - (stage.clientWidth - cards[i].offsetWidth) / 2; };
            var go = function (i, instant) {
                stage.scrollTo({ left: center(i), behavior: instant || reduce ? 'instant' : 'smooth' });
            };
            var mark = function (i) {
                if (i === active) return;
                active = i;
                cards.forEach(function (c, k) { c.classList.toggle('is-front', k === i); });
                Array.prototype.forEach.call(dots.children, function (d, k) { d.classList.toggle('is-on', k === i); });
                if (nowEl) nowEl.textContent = (i < 9 ? '0' : '') + (i + 1);
            };
            var track = function () {
                queued = false;
                var best = 0, bestD = Infinity;
                cards.forEach(function (c, k) { var d = Math.abs(center(k) - stage.scrollLeft); if (d < bestD) { bestD = d; best = k; } });
                mark(best);
            };
            stage.addEventListener('scroll', function () { if (!queued) { queued = true; requestAnimationFrame(track); } }, { passive: true });
            // Stuknięcie w wystającą kartę obok: najpierw ją wyśrodkuj.
            cards.forEach(function (c, k) {
                c.addEventListener('click', function (e) {
                    if (k === active) return;
                    e.preventDefault(); e.stopPropagation();
                    go(k);
                }, true);
            });
            var started = false;
            var start = function () {
                if (started || !stage.clientWidth) return;
                started = true;
                go(startIdx, true);
                mark(startIdx);
            };
            start();
            requestAnimationFrame(start);
            window.addEventListener('load', start);
            if (!reduce && 'IntersectionObserver' in window) {
                var nudge = new IntersectionObserver(function (en) {
                    if (!en[0].isIntersecting) return;
                    nudge.disconnect();
                    stage.classList.add('is-nudge');
                    setTimeout(function () { stage.classList.remove('is-nudge'); }, 1400);
                }, { threshold: 0.6 });
                nudge.observe(stage);
            }
        })();
    }

    if (p3d && !reduce && !homeSwipe) {
        var cards = Array.prototype.slice.call(p3d.querySelectorAll('[data-p3d]'));
        var goBtns = Array.prototype.slice.call(p3d.querySelectorAll('[data-p3d-go]'));
        var nowEl = p3d.querySelector('[data-p3d-now]');
        var stage = p3d.querySelector('.home-p3d-stage');
        var n = cards.length, lastActive = -1, queuedP = false, near = false;

        p3d.classList.add('is-3d');

        /* Pętla i start jak w cenniku (cennik-showcase.js, 2026-11-11): karty po
           okręgu, po lewej od najtańszej (Proxy) za przedziałką "od nowa" stoją
           najdroższe. Kolejność przewijania zostaje cenowa, ale wjazd w sekcję
           z góry ustawia DEVeloperski (inaczej pierwszy niewyprzedany), więc
           Proxy jest kółkiem w górę. */
        var looped = n > 2;
        var startIdx = (function () {
            var names = cards.map(function (c) { var h = c.querySelector('h3, .pcard-name'); return h ? h.textContent : ''; });
            var dev = names.findIndex(function (t) { return /developer/i.test(t); });
            if (dev >= 0) return dev;
            var free = cards.findIndex(function (c) { return !c.querySelector('.pcard.is-soldout'); });
            return free >= 0 ? free : 0;
        })();
        var wrapI = function (i) { return ((i % n) + n) % n; };
        var circ = function (d) { return looped ? wrapI(d + n / 2) - n / 2 : d; };
        var SEAM_GAP = 0.55;
        // Dopóki gość nie dojechał do DEVeloperskiego, karuzela go trzyma
        // (nad sekcją i w trakcie przeskoku nie mignie Proxy). Po dojechaniu
        // kółko w górę odsłania Proxy; wyjazd nad sekcję zakłada blokadę znowu.
        var startLock = true;
        var seam = null;
        if (looped) {
            seam = document.createElement('div');
            seam.className = 'home-p3d-seam';
            seam.setAttribute('aria-hidden', 'true');
            seam.innerHTML = '<span>od nowa</span>';
            stage.appendChild(seam);
        }

        var progress = function () {
            var r = p3d.getBoundingClientRect();
            var total = p3d.offsetHeight - window.innerHeight;
            var k = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;
            return k * (n - 1);
        };

        var renderP3d = function () {
            queuedP = false;
            var pos = progress();
            if (startLock) {
                if (pos >= startIdx - 0.02) startLock = false;
                else pos = startIdx;
            }
            var narrow = window.innerWidth < 960;
            var cw = cards[0].offsetWidth;
            var step = cw * (narrow ? 0.86 : 0.8);
            // Karta wyższa niż scena (telefon) - zmniejszamy całą karuzelę.
            var fit = Math.min(1, stage.clientHeight / (cards[0].offsetHeight || 1));
            // Przedziałka między najdroższą (n-1) a najtańszą (0) kartą.
            var seamD = circ(n - 0.5 - pos);
            var layoutD = function (d) {
                if (!looped) return d;
                if (seamD > 0 && d > seamD) return d + SEAM_GAP;
                if (seamD < 0 && d < seamD) return d - SEAM_GAP;
                return d;
            };
            if (seam) {
                var sd = seamD + Math.sign(seamD) * SEAM_GAP / 2, sa = Math.abs(sd);
                var scl = Math.max(-2.2, Math.min(2.2, sd));
                var sx = scl * step + (sa > 2.2 ? Math.sign(sd) * (sa - 2.2) * step * 0.25 : 0);
                seam.style.transform = 'translate(-50%, -50%) translate3d(' + sx.toFixed(1) + 'px, 0, ' + (-Math.min(sa, 3) * (narrow ? 200 : 260)).toFixed(1) + 'px) rotateY(' + (-scl * (narrow ? 30 : 34)).toFixed(2) + 'deg) scale(' + (fit * (1 - Math.min(sa, 3) * 0.04)).toFixed(3) + ')';
                seam.style.opacity = sa > 2.6 ? '0' : (1 - Math.max(0, sa - 1.2) * 0.65).toFixed(3);
                seam.style.height = ((cards[0].offsetHeight || 500) * 0.8) + 'px';
            }
            cards.forEach(function (c, i) {
                var d = layoutD(circ(i - pos)), ad = Math.abs(d);
                var clamped = Math.max(-2.2, Math.min(2.2, d));
                var x = clamped * step + (ad > 2.2 ? Math.sign(d) * (ad - 2.2) * step * 0.25 : 0);
                var z = -Math.min(ad, 3) * (narrow ? 200 : 260);
                var ry = -clamped * (narrow ? 30 : 34);
                var sc = fit * (1 - Math.min(ad, 3) * 0.04);
                c.style.transform = 'translate(-50%, -50%) translate3d(' + x.toFixed(1) + 'px, 0, ' + z.toFixed(1) + 'px) rotateY(' + ry.toFixed(2) + 'deg) scale(' + sc.toFixed(3) + ')';
                c.style.opacity = ad > 2.6 ? 0 : (1 - Math.max(0, ad - 1.2) * 0.65).toFixed(3);
                c.style.zIndex = String(100 - Math.round(ad * 10));
                c.classList.toggle('is-front', ad < 0.5);
            });
            var active = Math.max(0, Math.min(n - 1, Math.round(pos)));
            if (active !== lastActive) {
                lastActive = active;
                goBtns.forEach(function (b, i) { b.classList.toggle('is-active', i === active); });
                if (nowEl) nowEl.textContent = (active < 9 ? '0' : '') + (active + 1);
            }
        };
        var queueP3d = function () { if (near && !queuedP) { queuedP = true; requestAnimationFrame(renderP3d); } };

        // Przewiń stronę tak, żeby karuzela stanęła na planie i.
        var goTo = function (i) {
            var total = p3d.offsetHeight - window.innerHeight;
            var y = p3d.getBoundingClientRect().top + window.scrollY + (n > 1 ? total * i / (n - 1) : 0);
            if (window.dnctLenis) window.dnctLenis.scrollTo(y, { duration: 1.1 });
            else window.scrollTo({ top: y, behavior: 'smooth' });
        };
        goBtns.forEach(function (b) { b.addEventListener('click', function () { goTo(+b.getAttribute('data-p3d-go')); }); });
        // "Pomiń plany": przewija do pierwszej sekcji za karuzelą.
        var skip = p3d.querySelector('[data-p3d-skip]');
        if (skip) skip.addEventListener('click', function () {
            var y = p3d.getBoundingClientRect().top + window.scrollY + p3d.offsetHeight - 40;
            if (window.dnctLenis) window.dnctLenis.scrollTo(y, { duration: 1.2 });
            else window.scrollTo({ top: y, behavior: 'smooth' });
        });
        cards.forEach(function (c, i) {
            c.addEventListener('click', function () { if (!c.classList.contains('is-front')) goTo(i); });
        });

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) { near = en[0].isIntersecting; if (near) queueP3d(); }, { rootMargin: '200px 0px' }).observe(p3d);
        } else {
            near = true;
        }
        window.addEventListener('scroll', queueP3d, { passive: true });
        window.addEventListener('resize', queueP3d);
        near = true;
        renderP3d();

        /* Wjazd w sekcję z góry -> od razu DEVeloperski (jak start cennika).
           Nie przy kliknięciach w listę/karty/strzałkę - wtedy cel wybrał gość. */
        var secTop = function () { return p3d.getBoundingClientRect().top + window.scrollY; };
        var stepPx = function () { return n > 1 ? (p3d.offsetHeight - window.innerHeight) / (n - 1) : 0; };
        var startY = function () { return secTop() + stepPx() * startIdx; };
        var skipY = function () { return secTop() + p3d.offsetHeight - 40; };
        var suppressSnap = 0, prevY = window.scrollY;
        var smoothTo = function (y, dur) {
            suppressSnap = Date.now() + 1600;
            if (window.dnctLenis) window.dnctLenis.scrollTo(y, { duration: dur || 0.9 });
            else window.scrollTo({ top: y, behavior: 'smooth' });
        };
        p3d.addEventListener('click', function () { suppressSnap = Date.now() + 1600; }, true);
        window.addEventListener('scroll', function () {
            var y = window.scrollY, top = secTop();
            if (y < top - 2) startLock = true;
            if (startIdx > 0 && Date.now() > suppressSnap && prevY < top - 2 && y >= top - 2 && y < startY() - 2) {
                smoothTo(startY(), 0.6);
            }
            // Kliknięcie w listę/kartę (np. Proxy) z góry: gość wybrał cel sam.
            if (Date.now() <= suppressSnap) startLock = false;
            prevY = y;
        }, { passive: true });

        /* Strzałka jak w cenniku: w planach w dół (pomiń), pod nimi w górę
           (do DEVeloperskiego). Nad sekcją ukryta. */
        var arrow = document.createElement('button');
        arrow.type = 'button';
        arrow.className = 'home-p3d-arrow';
        arrow.hidden = true;
        arrow.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
        document.body.appendChild(arrow);
        var arrowDown = null;
        var updateArrow = function () {
            var y = window.scrollY, top = secTop();
            if (y < top - window.innerHeight * 0.5) { arrow.hidden = true; return; }
            arrow.hidden = false;
            var down = y < skipY() - window.innerHeight;
            if (down === arrowDown) return;
            arrowDown = down;
            arrow.classList.toggle('is-down', down);
            var label = down ? 'Pomiń plany' : 'Wróć do planów';
            arrow.setAttribute('aria-label', label);
            arrow.title = label;
        };
        arrow.addEventListener('click', function () { smoothTo(arrowDown ? skipY() : startY()); });
        window.addEventListener('scroll', updateArrow, { passive: true });
        window.addEventListener('resize', updateArrow);
        updateArrow();
    }

    /* --- Pasek opinii: pisanie, gwiazdki i rozwinięcie (tylko komputer) --- */
    /* Na telefonie pasek zostaje taki, jaki jest (prośba właściciela).
       1. Karta, która wjeżdża w kadr taśmy, "pisze się" znak po znaku
          z kursorem, a gwiazdki zapalają się po kolei - każda karta raz.
       2. Najechanie na kartę otwiera nad nią okienko z pełną treścią,
          rosnące w górę (taśma ma overflow:hidden, więc sama karta nie może
          urosnąć). Taśma stoi, dopóki okienko jest otwarte. */
    var pasek = document.querySelector('.op-pasek');
    var desktop = window.matchMedia('(min-width: 961px) and (pointer: fine)').matches;
    if (pasek && desktop && !reduce && 'IntersectionObserver' in window) {
        var tasma = pasek.querySelector('.op-pasek-tasma');
        var karty = Array.prototype.slice.call(pasek.querySelectorAll('.op-pasek-karta'));
        var ZNAK_MS = 18;

        var pisz = function (karta) {
            var p = karta.querySelector('p');
            var tekst = karta.getAttribute('data-tekst') || '';
            var kursor = document.createElement('span');
            kursor.className = 'op-kursor';
            kursor.setAttribute('aria-hidden', 'true');
            p.textContent = '';
            p.appendChild(kursor);
            karta.classList.remove('is-czeka');
            karta.classList.add('is-pisze');
            // Widać 3 linie (~140 znaków) - dalej pisze szybciej, żeby nie trwało wieki.
            var i = 0;
            (function krok() {
                if (i >= tekst.length) { setTimeout(function () { kursor.remove(); }, 1400); return; }
                var ile = i > 140 ? 6 : (tekst.length > 200 ? 2 : 1);
                kursor.insertAdjacentText('beforebegin', tekst.substr(i, ile));
                i += ile;
                setTimeout(krok, ZNAK_MS);
            })();
        };

        var wjazd = new IntersectionObserver(function (wpisy) {
            wpisy.forEach(function (w) {
                if (!w.isIntersecting) return;
                wjazd.unobserve(w.target);
                pisz(w.target);
            });
        }, { root: tasma, threshold: 0.6 });

        karty.forEach(function (k) {
            var p = k.querySelector('p');
            k.setAttribute('data-tekst', p.textContent);
            p.textContent = '';
            k.classList.add('is-czeka');
            wjazd.observe(k);
        });

        // Okienko z pełną treścią
        var popBox = pasek.parentNode;   // .home-p3d-opinie (position: relative); NIE 'box' - to już konsola hero
        var pop = null, zamknij = null;
        var schowaj = function () {
            if (!pop) return;
            var stary = pop;
            pop = null;
            stary.classList.add('is-zamyka');
            stary.style.height = stary.getAttribute('data-h0') + 'px';
            setTimeout(function () { stary.remove(); }, 320);
            tasma.classList.remove('is-paused');
        };
        karty.forEach(function (k) {
            k.addEventListener('mouseenter', function () {
                clearTimeout(zamknij);
                if (pop) { pop.remove(); pop = null; }
                var r = k.getBoundingClientRect(), b = popBox.getBoundingClientRect();
                pop = document.createElement('div');
                pop.className = 'op-pasek-pop';
                pop.setAttribute('aria-hidden', 'true');   // treść i tak jest w karcie
                pop.appendChild(k.querySelector('.op-pasek-autor').cloneNode(true));
                var p = document.createElement('p');
                p.textContent = k.getAttribute('data-tekst');
                pop.appendChild(p);
                var left = Math.max(0, Math.min(r.left - b.left, b.width - r.width));
                pop.style.left = left + 'px';
                pop.style.bottom = (b.bottom - r.bottom) + 'px';
                pop.style.width = r.width + 'px';
                pop.style.height = r.height + 'px';
                pop.setAttribute('data-h0', String(r.height));
                popBox.appendChild(pop);
                tasma.classList.add('is-paused');
                var pelna = Math.max(r.height, pop.scrollHeight);
                requestAnimationFrame(function () { if (pop) pop.style.height = pelna + 'px'; });
                pop.addEventListener('mouseleave', function () { zamknij = setTimeout(schowaj, 80); });
                pop.addEventListener('mouseenter', function () { clearTimeout(zamknij); });
            });
        });
    }

    /* --- Discord: scena społeczności ([data-dcx]) ----------------------- */
    /* 1. Po wejściu w kadr avatary wyskakują po kolei, licznik liczy od zera.
       2. Co 1,4-2,4 s losowy avatar "mówi" (zielona fala). Nigdy dwa razy
          z rzędu ten sam; poza ekranem i na ukrytej karcie pętla stoi. */
    var dcx = document.querySelector('[data-dcx]');
    if (dcx && !reduce && 'IntersectionObserver' in window) {
        var dcxAv = Array.prototype.slice.call(dcx.querySelectorAll('.dcx-av:not(.dcx-av-more)'));
        var dcxNum = dcx.querySelector('[data-dcx-count]');
        var dcxCel = dcxNum ? parseInt(dcxNum.getAttribute('data-dcx-count'), 10) || 0 : 0;
        var dcxWidac = false, dcxMowi = null, dcxOstatni = -1;

        dcx.classList.add('is-czeka');
        if (dcxNum) dcxNum.textContent = '0';

        var dcxLicz = function () {
            if (!dcxNum || !dcxCel) return;
            var t0 = performance.now();
            (function f(t) {
                var k = Math.min(1, (t - t0) / 1100);
                dcxNum.textContent = String(Math.round(dcxCel * (1 - Math.pow(1 - k, 3))));
                if (k < 1) requestAnimationFrame(f);
            })(t0);
        };
        var dcxMow = function () {
            clearTimeout(dcxMowi);
            if (!dcxWidac || document.hidden || dcxAv.length < 2) return;
            var widoczne = dcxAv.filter(function (a) { return a.offsetParent !== null; });
            if (widoczne.length < 2) return;
            var i = Math.floor(Math.random() * widoczne.length);
            if (i === dcxOstatni) i = (i + 1) % widoczne.length;
            dcxOstatni = i;
            var a = widoczne[i];
            a.classList.add('is-speaking');
            setTimeout(function () { a.classList.remove('is-speaking'); }, 1300);
            dcxMowi = setTimeout(dcxMow, 1400 + Math.random() * 1000);
        };

        new IntersectionObserver(function (en) {
            var byl = dcxWidac;
            dcxWidac = en[0].isIntersecting;
            if (dcxWidac && dcx.classList.contains('is-czeka')) {
                dcx.classList.remove('is-czeka');
                dcx.classList.add('is-in');
                dcxLicz();
            }
            if (dcxWidac && !byl) dcxMowi = setTimeout(dcxMow, 1200); else if (!dcxWidac) clearTimeout(dcxMowi);
        }, { threshold: 0.3 }).observe(dcx);
        document.addEventListener('visibilitychange', function () { if (!document.hidden && dcxWidac) dcxMow(); });

    }

    /* --- Discord: kanał #pomoc w pętli ([data-dc-chat]) ------------------ */
    /* Rozmowa po rozmowie: pytanie gracza -> "DNCT Support pisze..." ->
       odpowiedź -> reakcja. Najstarsze wiadomości odjeżdżają w górę, gdy
       brakuje miejsca. Pętla stoi poza ekranem i na ukrytej karcie.
       Przy reduced-motion: od razu jedna rozmowa, bez pętli.
       Odpowiedzi zgadzają się z tym, jak naprawdę działa panel i strona. */
    var dc = document.querySelector('[data-dc-chat]');
    if (dc) {
        var dcBody = dc.querySelector('[data-dc-body]');
        var ROZMOWY = [
            { gracz: ['kuba_mc', '#3ba55c'], pyt: 'Jak wgrać plugin na serwer?',
              odp: 'Wrzuć plik .jar do folderu plugins w zakładce Pliki i zrestartuj serwer 👌', reakcja: '❤️' },
            { gracz: ['ola.pz', '#eb459e'], pyt: 'Da się dodać mody ze Steam Workshop do Zomboida?',
              odp: 'Tak - Workshop ID wpisujesz w konfiguracji serwera w panelu, po restarcie mody pobiorą się same.', reakcja: '🔥' },
            { gracz: ['tomek_vs', '#faa61a'], pyt: 'Mogę zmienić pakiet w trakcie okresu?',
              odp: 'Jasne - w „Twoich serwerach” kliknij „Zmień pakiet”, dopłacasz tylko proporcjonalną różnicę.', reakcja: '👍' },
            { gracz: ['mati2010', '#5865f2'], pyt: 'Jak dać znajomemu dostęp do konsoli bez podawania hasła?',
              odp: 'W panelu zakładka Udostępnij - zapraszasz go i zaznaczasz, co może robić. Hasło zostaje u Ciebie.', reakcja: '🙏' },
            { gracz: ['wiktoria', '#ed4245'], pyt: 'Zmieniłam wersję i świat zniknął 😭',
              odp: 'Spokojnie - w zakładce Backupy przywrócisz kopię sprzed zmiany jednym kliknięciem.', reakcja: '❤️' }
        ];
        var typing = document.createElement('div');
        typing.className = 'dc-typing';
        typing.setAttribute('aria-hidden', 'true');
        typing.innerHTML = '<span class="dc-dots"><i></i><i></i><i></i></span><span><b>DNCT Support</b> pisze...</span>';
        dc.insertBefore(typing, dc.querySelector('.dc-chat-join'));

        var godzina = function (plus) {
            var d = new Date(Date.now() + plus * 60000);
            return 'Dziś o ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');
        };
        var wiadomosc = function (kto, kolor, tekst, czas, staff) {
            var m = document.createElement('div');
            m.className = 'dc-msg';
            var av = document.createElement('span');
            av.className = 'dc-av';
            if (staff) {
                var img = document.createElement('img');
                img.src = '/assets/media/icons/favicon.png';
                img.alt = '';
                av.appendChild(img);
            } else {
                av.style.background = kolor;
                av.textContent = kto.charAt(0).toUpperCase();
            }
            var meta = document.createElement('div');
            meta.className = 'dc-meta';
            meta.innerHTML = '<span class="dc-name' + (staff ? ' is-staff' : '') + '"></span>' + (staff ? '<span class="dc-tag">SUPPORT</span>' : '') + '<span class="dc-time"></span>';
            meta.querySelector('.dc-name').textContent = kto;
            meta.querySelector('.dc-time').textContent = czas;
            var p = document.createElement('p');
            p.className = 'dc-text';
            p.textContent = tekst;
            var tresc = document.createElement('div');
            tresc.appendChild(meta);
            tresc.appendChild(p);
            m.appendChild(av);
            m.appendChild(tresc);
            dcBody.appendChild(m);
            // Za mało miejsca: najstarsze wiadomości odjeżdżają w górę. Mierzymy
            // samą treść (od pierwszej do ostatniej wiadomości) - scrollHeight
            // przy justify-content: flex-end nie mówi prawdy o przepełnieniu.
            var miejsce = dcBody.clientHeight - 24;
            var zywe = Array.prototype.filter.call(dcBody.children, function (el) { return !el.classList.contains('is-out'); });
            while (zywe.length > 1) {
                var gora = zywe[0];
                var zajete = m.offsetTop + m.offsetHeight + 40 - gora.offsetTop;   // +40 na reakcję
                if (zajete <= miejsce) break;
                gora.classList.add('is-out');
                setTimeout(function (el) { return function () { el.remove(); }; }(gora), 380);
                zywe.shift();
            }
            return tresc;
        };

        var nr = 0, dcTimer = null, dcOn = false;
        var czekaj = function (ms, fn) { dcTimer = setTimeout(function () { if (dcOn) fn(); }, ms); };
        var rozmowa = function () {
            var r = ROZMOWY[nr % ROZMOWY.length];
            nr += 1;
            wiadomosc(r.gracz[0], r.gracz[1], r.pyt, godzina(0), false);
            czekaj(1200, function () {
                typing.classList.add('is-on');
                czekaj(2200, function () {
                    typing.classList.remove('is-on');
                    var odp = wiadomosc('DNCT Support', '', r.odp, godzina(1), true);
                    czekaj(1100, function () {
                        var re = document.createElement('span');
                        re.className = 'dc-react';
                        re.textContent = r.reakcja + ' 1';
                        odp.appendChild(re);
                        czekaj(3200, rozmowa);
                    });
                });
            });
        };

        if (reduce) {
            wiadomosc(ROZMOWY[0].gracz[0], ROZMOWY[0].gracz[1], ROZMOWY[0].pyt, godzina(0), false);
            wiadomosc('DNCT Support', '', ROZMOWY[0].odp, godzina(1), true);
        } else if ('IntersectionObserver' in window) {
            var dcWidac = false;
            var dcStart = function () { if (!dcOn) { dcOn = true; rozmowa(); } };
            var dcStop = function () { dcOn = false; clearTimeout(dcTimer); typing.classList.remove('is-on'); };
            new IntersectionObserver(function (en) {
                dcWidac = en[0].isIntersecting;
                if (dcWidac && !document.hidden) dcStart(); else dcStop();
            }, { threshold: 0.3 }).observe(dc);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) dcStop(); else if (dcWidac) dcStart();
            });
        }
    }

    /* --- Wejście kafli w kadr ------------------------------------------ */
    var tiles = document.querySelectorAll('.home-reveal');
    if (tiles.length && !reduce && 'IntersectionObserver' in window) {
        document.documentElement.classList.add('home-js');
        var io = new IntersectionObserver(function (en) {
            en.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.classList.add('is-in');
                io.unobserve(e.target);
            });
        }, { threshold: 0.15 });
        tiles.forEach(function (t) { io.observe(t); });
    }
})();
