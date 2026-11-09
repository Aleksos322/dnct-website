/* Kalkulator zasobów Minecrafta (components/mc-kalkulator.php).
 *
 * MODEL - świadomie prosty i jawny, bo ma dać rząd wielkości, nie pomiar:
 *
 *   RAM = silnik + gracze + pluginy/mody   (wszystko z zapasem na GC Javy)
 *     silnik   = baza silnika x mnożnik wersji (1.12 jest lżejsze niż 26.x)
 *     gracze   = 0,1 GB na gracza przy view-distance 10; część tej kwoty
 *                rośnie z polem widzenia ((2vd+1)^2 chunków na gracza),
 *                powyżej 20 graczy liczymy 80%, bo ludzie dzielą chunki
 *     dodatki  = średnio 25 MB na plugin, 22-24 MB na mod
 *   CPU (vCore) = główny wątek + gracze (zależnie od view-distance)
 *                 + pierwiastek z liczby dodatków (koszt rośnie wolniej
 *                 niż liniowo: biblioteki i drobne mody prawie nie liczą)
 *
 * Kalibracja (2026-11-09) na znanych przypadkach: Paper 26.x, 20 graczy,
 * 30 pluginów -> ok. 5 GB i 2 vCore; ATM9 (Forge 1.20.1, ~430 modów,
 * 10 graczy) -> ok. 13,5 GB i 4 vCore; RLCraft (Forge 1.12.2, ~170 modów)
 * -> ok. 5 GB i 2 vCore. Zmieniasz współczynniki - przelicz te trzy
 * przypadki jeszcze raz.
 *
 * Pakiet: najtańszy z co najmniej 0,5 GB zapasu ponad szacunek i z liczbą
 * vCore nie mniejszą niż szacunek (HEADROOM niżej).
 */
(function () {
    'use strict';

    var dialog = document.getElementById('mc-calc');
    var trigger = document.querySelector('[data-mc-calc-open]');
    if (!dialog) return;

    var plans = [];
    try { plans = JSON.parse(document.getElementById('mc-calc-plans').textContent) || []; } catch (e) { plans = []; }
    if (!plans.length) return;
    plans.sort(function (a, b) { return a.price - b.price; });

    // Okienko poza katalogiem: .cnk-catalogue łapie kółko myszy w fazie capture.
    document.body.appendChild(dialog);

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    // Wersja EN/DE: i18n.js definiuje DNCT_T tylko poza polskim, a ładuje się
    // niezależnie od tego pliku - dlatego pytamy przy każdym użyciu.
    function T(t) { return window.DNCT_T ? window.DNCT_T(t) : t; }
    var EN = !!(window.DNCT_I18N && window.DNCT_I18N.lang === 'en');

    /* ---------- model ---------- */
    var VERSIONS = {
        '26':   { ram: 1.10, cpu: 1.05, max: 2600 },
        '1.21': { ram: 1.05, cpu: 1.05, max: 121 },
        '1.20': { ram: 1.00, cpu: 1.00, max: 120 },
        '1.19': { ram: 0.97, cpu: 1.00, max: 119 },
        '1.18': { ram: 0.95, cpu: 1.00, max: 118 },
        '1.17': { ram: 0.85, cpu: 0.85, max: 117 },
        '1.16': { ram: 0.80, cpu: 0.85, max: 116 },
        '1.13': { ram: 0.75, cpu: 0.90, max: 115 },
        '1.12': { ram: 0.60, cpu: 0.70, max: 112 },
        '1.8':  { ram: 0.50, cpu: 0.60, max: 111 },
        '1.7':  { ram: 0.50, cpu: 0.60, max: 107 }
    };
    // base/cpuBase: sam serwer; pl/cpu: mnożnik kosztu gracza (Vanilla i Spigot
    // nie mają optymalizacji Papera); addRam/addCpu: koszt jednego dodatku.
    var ENGINES = {
        paper:    { base: 1.40, pl: 1.00, cpuBase: 1.00, cpu: 1.00, addon: 'plugins', addRam: 0.025, addCpu: 0.06,
                    hint: 'Najpopularniejszy silnik pod pluginy. Szybszy od Spigota przy tych samych pluginach.' },
        purpur:   { base: 1.45, pl: 1.00, cpuBase: 1.00, cpu: 1.00, addon: 'plugins', addRam: 0.025, addCpu: 0.06, since: 114,
                    hint: 'Fork Papera z dodatkowymi opcjami. Zużycie praktycznie takie jak na Paperze.',
                    old: 'Purpur działa od 1.14 - na starszej wersji wybierz Paper albo Spigot.' },
        spigot:   { base: 1.45, pl: 1.05, cpuBase: 1.00, cpu: 1.20, addon: 'plugins', addRam: 0.025, addCpu: 0.06,
                    hint: 'Klasyka pod pluginy, ale wolniejsza od Papera - liczymy więcej procesora.' },
        vanilla:  { base: 1.50, pl: 1.10, cpuBase: 1.00, cpu: 1.35, addon: null, addRam: 0, addCpu: 0,
                    hint: 'Czysty serwer od Mojangu, bez pluginów i modów. Bez optymalizacji Papera.' },
        fabric:   { base: 1.40, pl: 1.00, cpuBase: 1.00, cpu: 1.15, addon: 'mods', addRam: 0.022, addCpu: 0.08, since: 114,
                    hint: 'Lekki loader modów. Mody wydajnościowe (Lithium, FerriteCore) obniżają zużycie.',
                    old: 'Fabric działa od 1.14 - starsze modpacki są zwykle na Forge.' },
        forge:    { base: 2.00, pl: 1.10, cpuBase: 1.20, cpu: 1.30, addon: 'mods', addRam: 0.024, addCpu: 0.09,
                    hint: 'Klasyczny loader modpacków. Sam Forge zajmuje więcej pamięci niż Fabric.' },
        neoforge: { base: 1.90, pl: 1.10, cpuBase: 1.15, cpu: 1.25, addon: 'mods', addRam: 0.023, addCpu: 0.09, since: 120,
                    hint: 'Następca Forge na nowszych wersjach, zużycie zbliżone.',
                    old: 'NeoForge działa od 1.20.1 - na starszej wersji wybierz Forge.' }
    };

    function estimate(s, viewOverride) {
        var v = VERSIONS[s.version] || VERSIONS['26'];
        var e = ENGINES[s.engine] || ENGINES.paper;
        var view = viewOverride || s.view;
        var area = Math.pow((2 * view + 1) / 21, 2);           // chunki gracza względem vd 10
        var players = s.players <= 20 ? s.players : 20 + (s.players - 20) * 0.8;
        var addons = e.addon ? s.addons : 0;
        var parts = {
            server: e.base * v.ram,
            players: players * 0.1 * (0.35 + 0.65 * area) * e.pl * v.ram,
            addons: addons * e.addRam * (1 + v.ram) / 2
        };
        var ram = parts.server + parts.players + parts.addons;
        var cpu = v.cpu * (e.cpuBase + players * 0.03 * e.cpu * (0.6 + 0.4 * area)) + e.addCpu * Math.sqrt(addons);
        return {
            parts: parts, ram: ram, cpu: cpu,
            // Pamięć w górę do 0,5 GB, procesor do pełnego rdzenia z małą tolerancją.
            ramNeed: Math.max(1.5, Math.ceil(ram * 2 - 1e-9) / 2),
            cpuNeed: Math.max(1, Math.ceil(cpu - 0.2))
        };
    }
    // Pakiet musi mieć co najmniej 0,5 GB ponad szacunek. Bez tego 10 graczy
    // z pluginami (szacunek 3 GB) dostawało pakiet 3 GB z zerowym zapasem.
    var HEADROOM = 0.5;
    function fits(plan, est) { return plan.ram + 1e-9 >= est.ramNeed + HEADROOM && plan.cpu + 1e-9 >= est.cpuNeed; }
    function choose(est) {
        var first = null;
        for (var i = 0; i < plans.length; i++) {
            if (!fits(plans[i], est)) continue;
            if (!first) first = plans[i];
            if (!plans[i].soldOut) return { plan: plans[i], skipped: first !== plans[i] ? first : null };
        }
        return { plan: first, skipped: null };
    }
    // Tańszy pakiet przy niższym view-distance. Nie schodzimy poniżej 6 -
    // niżej gracze widzą mgłę tuż za sobą i porada robi więcej szkody niż pożytku.
    function cheaper(s, plan) {
        var lower = null;
        for (var i = plans.indexOf(plan) - 1; i >= 0; i--) {
            if (!plans[i].soldOut) { lower = plans[i]; break; }
        }
        if (!lower) return null;
        for (var view = s.view - 1; view >= 6; view--) {
            if (fits(lower, estimate(s, view))) return { plan: lower, view: view };
        }
        return null;
    }

    /* ---------- pola ---------- */
    var form = dialog.querySelector('[data-mc-calc-form]');
    var STOPS = {
        players: [1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25, 30, 35, 40, 50, 60, 70, 80, 100, 120, 150, 200, 250, 300],
        addons: [0, 1, 2, 3, 5, 8, 10, 12, 15, 20, 25, 30, 40, 50, 60, 80, 100, 120, 150, 200, 250, 300, 400, 500],
        view: []
    };
    for (var vd = 2; vd <= 32; vd++) STOPS.view.push(vd);

    var nums = {};
    Array.prototype.forEach.call(dialog.querySelectorAll('[data-mc-calc-num]'), function (box) {
        var key = box.getAttribute('data-mc-calc-num');
        var input = box.querySelector('input[type="number"]');
        var range = box.querySelector('.mc-calc-range');
        var stops = STOPS[key];
        range.max = String(stops.length - 1);
        nums[key] = { box: box, input: input, range: range, stops: stops };
        range.addEventListener('input', function () {
            input.value = String(stops[Number(range.value)]);
            paintRange(key);
            update();
        });
        input.addEventListener('input', function () { syncRange(key); update(); });
        input.addEventListener('change', function () {
            input.value = String(read(key));
            syncRange(key);
            update();
        });
    });
    function read(key) {
        var n = nums[key], v = parseInt(n.input.value, 10);
        var min = Number(n.input.min), max = Number(n.input.max);
        if (isNaN(v)) v = key === 'view' ? 10 : min;
        return Math.max(min, Math.min(max, v));
    }
    function syncRange(key) {
        var n = nums[key], v = read(key), best = 0;
        n.stops.forEach(function (stop, i) { if (Math.abs(stop - v) < Math.abs(n.stops[best] - v)) best = i; });
        n.range.value = String(best);
        paintRange(key);
    }
    function paintRange(key) {
        var r = nums[key].range;
        r.style.setProperty('--p', (Number(r.value) / Number(r.max)).toFixed(4));
        r.setAttribute('aria-valuetext', nums[key].input.value);
    }
    Object.keys(nums).forEach(syncRange);
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    form.addEventListener('change', function (e) { if (e.target.name === 'engine' || e.target.name === 'version') update(); });

    /* ---------- wynik ---------- */
    function q(sel) { return dialog.querySelector(sel); }
    var out = {
        ram: q('[data-mc-calc-ram]'), cpu: q('[data-mc-calc-cpu]'),
        ramPlan: q('[data-mc-calc-ram-plan]'), cpuPlan: q('[data-mc-calc-cpu-plan]'),
        bar: q('[data-mc-calc-bar]'), cores: q('[data-mc-calc-cores]'),
        engineHint: q('[data-mc-calc-engine-hint]'),
        addonsLabel: q('[data-mc-calc-addons-label]'), addonsHint: q('[data-mc-calc-addons-hint]'), addonsPart: q('[data-mc-calc-addons-part]'),
        plan: q('[data-mc-calc-plan]'), none: q('[data-mc-calc-none]'),
        planName: q('[data-mc-calc-plan-name]'), planPrice: q('[data-mc-calc-plan-price]'),
        planRam: q('[data-mc-calc-plan-ram]'), planCpu: q('[data-mc-calc-plan-cpu]'), planMsg: q('[data-mc-calc-plan-msg]'),
        order: q('[data-mc-calc-order]'), show: q('[data-mc-calc-show]'), extra: q('[data-mc-calc-extra]'), addonNote: q('[data-mc-calc-addon-note]'),
        peek: q('[data-mc-calc-peek]'), peekRam: q('[data-mc-calc-peek-ram]'), peekCpu: q('[data-mc-calc-peek-cpu]'), peekPlan: q('[data-mc-calc-peek-plan]'),
        live: q('[data-mc-calc-live]'), body: q('[data-mc-calc-body]'), result: q('[data-mc-calc-result]')
    };
    var parts = {};
    Array.prototype.forEach.call(dialog.querySelectorAll('[data-mc-calc-part]'), function (el) { parts[el.getAttribute('data-mc-calc-part')] = el; });

    // "4,5" po polsku i niemiecku, "4.5" po angielsku; całe liczby bez ",0".
    function num(v) {
        var s = (Math.round(v * 10) / 10).toFixed(1).replace(/\.0$/, '');
        return EN ? s : s.replace('.', ',');
    }
    function setText(el, text) { if (el && el.textContent !== text) el.textContent = text; }
    function bold(text) { var b = document.createElement('b'); b.textContent = text; return b; }
    function strong(text) { var b = document.createElement('strong'); b.textContent = text; return b; }

    var current = null, liveTimer = null;
    function state() {
        var engine = form.querySelector('input[name="engine"]:checked');
        return {
            version: form.elements.version.value,
            engine: engine ? engine.value : 'paper',
            players: read('players'), addons: read('addons'), view: read('view')
        };
    }

    function update() {
        var s = state();
        var engine = ENGINES[s.engine];
        var est = estimate(s);
        var pick = choose(est);
        var plan = pick.plan;
        current = { state: s, est: est, plan: plan };

        // Silnik: podpowiedź albo ostrzeżenie o wersji.
        var tooOld = engine.since && VERSIONS[s.version].max < engine.since;
        setText(out.engineHint, T(tooOld ? engine.old : engine.hint));
        out.engineHint.classList.toggle('is-warn', !!tooOld);

        // Pluginy / mody / nic (Vanilla).
        var addons = nums.addons;
        var off = !engine.addon;
        addons.input.disabled = off;
        addons.range.disabled = off;
        addons.box.classList.toggle('is-off', off);
        setText(out.addonsLabel, T(engine.addon === 'mods' ? 'Liczba modów' : 'Liczba pluginów'));
        setText(out.addonsHint, T(off ? 'Vanilla nie wczytuje pluginów ani modów.'
            : engine.addon === 'mods' ? 'Liczbę modów w modpacku pokazuje launcher (CurseForge, Modrinth).'
            : 'Policz pliki .jar w folderze plugins.'));
        setText(out.addonsPart, T(engine.addon === 'mods' ? 'Mody' : 'Pluginy'));
        parts.addons.parentNode.hidden = off;
        // Adnotacja mówi o tym, co klient faktycznie wgrywa: modach albo pluginach.
        // Klucze z "- " na początku: tak samo wygląda węzeł tekstowy z PHP,
        // więc jeden wpis w słowniku EN/DE obsługuje oba miejsca.
        var addonNote = engine.addon === 'mods'
            ? ['Mod modowi nierówny', '- jeden Create czy Mekanism zje więcej niż kilkanaście drobnych.']
            : ['Plugin pluginowi nierówny', '- jeden Dynmap zje więcej niż kilkanaście drobnych.'];
        if (out.addonNote.getAttribute('data-kind') !== (engine.addon || 'plugins')) {
            out.addonNote.setAttribute('data-kind', engine.addon || 'plugins');
            out.addonNote.textContent = '';
            out.addonNote.append(strong(T(addonNote[0])), ' ' + T(addonNote[1]));
        }

        // Liczby.
        setText(out.ram, num(est.ramNeed));
        setText(out.cpu, String(est.cpuNeed));
        setText(parts.server, num(est.parts.server) + ' GB');
        setText(parts.players, num(est.parts.players) + ' GB');
        setText(parts.addons, num(est.parts.addons) + ' GB');

        // Pasek pamięci w skali pakietu (albo samego szacunku, gdy pakietu brak).
        var fitsPlan = plan && fits(plan, est);
        var scale = fitsPlan ? plan.ram : est.ram;
        // Skale narastająco - warstwy leżą jedna na drugiej (patrz CSS .mc-calc-bar).
        var segs = out.bar.children, upTo = 0;
        [est.parts.server, est.parts.players, est.parts.addons].forEach(function (part, i) {
            upTo += part;
            segs[i].style.transform = 'scaleX(' + Math.min(1, upTo / scale).toFixed(4) + ')';
        });
        parts.free.parentNode.hidden = !fitsPlan;
        if (fitsPlan) setText(parts.free, num(Math.max(0, plan.ram - est.ram)) + ' GB');
        setText(out.ramPlan, fitsPlan ? num(plan.ram) + ' GB ' + T('w pakiecie') : '');
        setText(out.cpuPlan, fitsPlan ? plan.cpu + ' vCore ' + T('w pakiecie') : '');

        // Rdzenie: tyle kratek, ile ma pakiet; zapalone = potrzebne.
        var total = Math.min(8, fitsPlan ? plan.cpu : est.cpuNeed);
        if (out.cores.children.length !== total) {
            out.cores.textContent = '';
            for (var i = 0; i < total; i++) out.cores.appendChild(document.createElement('i'));
        }
        Array.prototype.forEach.call(out.cores.children, function (el, i) { el.classList.toggle('is-on', i < est.cpuNeed); });

        // Pakiet.
        out.plan.hidden = !fitsPlan;
        out.none.hidden = !!fitsPlan;
        if (fitsPlan) {
            setText(out.planName, T(plan.name));
            out.planName.classList.toggle('is-legend', /\+\s*$/.test(plan.name));
            setText(out.planPrice, plan.label);
            setText(out.planRam, num(plan.ram));
            setText(out.planCpu, String(plan.cpu));
            if (out.order) {
                out.order.href = '/zamowienie?plan=' + plan.id;
                out.order.hidden = !!plan.soldOut;
            }
            out.planMsg.textContent = '';
            var tip = pick.skipped ? null : cheaper(s, plan);
            if (pick.skipped) {
                out.planMsg.append(bold(T(pick.skipped.name)), ' ' + T('jest chwilowo wyprzedany - podpowiadamy następny pakiet.'));
            } else if (plan.soldOut && tip) {
                // Wyprzedany, ale mniejszy i dostępny pakiet wystarczy przy niższym zasięgu.
                out.planMsg.append(T('Ten pakiet jest chwilowo wyprzedany.') + ' ', bold(T(tip.plan.name)), ' (' + num(tip.plan.ram) + ' GB) ' + T('wystarczy przy view-distance') + ' ', bold(String(tip.view)), '.');
            } else if (plan.soldOut) {
                out.planMsg.append(T('Ten pakiet jest chwilowo wyprzedany - napisz do nas na Discordzie.'));
            } else if (tip) {
                out.planMsg.append(T('Taniej:') + ' ', bold(T(tip.plan.name)), ' (' + num(tip.plan.ram) + ' GB) ' + T('wystarczy przy view-distance') + ' ', bold(String(tip.view)), '.');
            }
            out.planMsg.hidden = !out.planMsg.childNodes.length;
        }

        // Dodatkowa uwaga, gdy dane wychodzą poza typowy serwer.
        var extra = s.players > 100
            ? 'Powyżej ok. 100 graczy jeden serwer Minecrafta dławi się na głównym wątku - przy takim ruchu rozważ sieć serwerów (np. Velocity).'
            : s.view > 16 ? 'View-distance powyżej 16 mocno podbija pamięć, a mało kto widzi różnicę powyżej 10-12.' : '';
        setText(out.extra, T(extra));
        out.extra.hidden = !extra;

        // Pasek na telefonie i komunikat dla czytnika ekranu.
        setText(out.peekRam, num(est.ramNeed));
        setText(out.peekCpu, String(est.cpuNeed));
        setText(out.peekPlan, fitsPlan ? T(plan.name) : T('Napisz do nas'));
        clearTimeout(liveTimer);
        liveTimer = setTimeout(function () {
            setText(out.live, T('Szacunek:') + ' ' + num(est.ramNeed) + ' GB RAM, ' + est.cpuNeed + ' vCore. ' +
                (fitsPlan ? T('Pasujący pakiet:') + ' ' + T(plan.name) + '.' : T('Żaden gotowy pakiet tego nie udźwignie.')));
        }, 700);
    }

    /* ---------- okienko ---------- */
    var lock = null, closing = false, afterClose = null, downOnBackdrop = false;

    function lockScroll() {
        var lenis = window.dnctLenis;
        lock = { y: window.scrollY, lenis: lenis, resume: !!lenis && !lenis.isStopped };
        if (lenis) lenis.stop();
        // Tylko <html>: overflow na <body> zrobiłby z niego kontener przewijania
        // i przypięty katalog (position: sticky) skoczyłby pod tłem okna.
        document.documentElement.classList.add('mc-calc-lock');
    }
    function unlockScroll() {
        if (!lock) return;
        var saved = lock;
        lock = null;
        document.documentElement.classList.remove('mc-calc-lock');
        if (Math.abs(window.scrollY - saved.y) > 1) window.scrollTo({ top: saved.y, behavior: 'instant' });
        if (saved.resume) saved.lenis.start();
    }
    function open() {
        if (dialog.open) return;
        update();
        lockScroll();
        dialog.classList.remove('is-closing');
        dialog.showModal();
        out.body.scrollTop = 0;
        watchPeek();
    }
    function close() {
        if (!dialog.open || closing) return;
        if (reduce.matches) { dialog.close(); return; }
        closing = true;
        dialog.classList.add('is-closing');
        setTimeout(function () {
            closing = false;
            dialog.classList.remove('is-closing');
            dialog.close();
        }, 200);
    }
    dialog.addEventListener('close', function () {
        unlockScroll();
        if (location.hash === '#kalkulator' && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search);
        }
        if (trigger && trigger.offsetParent) trigger.focus({ preventScroll: true });
        var next = afterClose;
        afterClose = null;
        if (next) next();
    });
    // Esc: to samo animowane zamknięcie co krzyżyk.
    dialog.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
    // Klik w tło zamyka, ale tylko gdy i wciśnięcie, i puszczenie były na tle -
    // zaznaczanie tekstu w oknie z wyjechaniem kursorem poza nie nie zamyka.
    dialog.addEventListener('pointerdown', function (e) { downOnBackdrop = e.target === dialog; });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog && downOnBackdrop) close();
        downOnBackdrop = false;
    });
    q('[data-mc-calc-close]').addEventListener('click', close);
    if (trigger) trigger.addEventListener('click', open);

    // "Pokaż w cenniku": zamknij i przestaw karuzelę na ten plan.
    out.show.addEventListener('click', function () {
        var plan = current && current.plan;
        if (!plan) return;
        afterClose = function () {
            var tab = document.getElementById('tab-minecraft');
            if (tab && !tab.classList.contains('is-active')) {
                if (tab.getAttribute('data-href')) { location.href = tab.getAttribute('data-href'); return; }
                tab.click();
            }
            var go = document.querySelector('#game-minecraft [data-catalogue-go="' + plan.idx + '"]');
            if (go) go.click();
            var pinned = document.querySelector('.cnk-catalogue-scroll.is-scroll-driven');
            var stage = document.querySelector('#game-minecraft .cnk-plan-stage');
            if (!pinned && stage) {
                var r = stage.getBoundingClientRect();
                if (r.top < 70 || r.bottom > window.innerHeight) {
                    var top = window.scrollY + r.top - Math.max(80, (window.innerHeight - r.height) / 2);
                    if (window.dnctLenis && !reduce.matches) window.dnctLenis.scrollTo(top, { duration: 0.8 });
                    else window.scrollTo({ top: top, behavior: reduce.matches ? 'instant' : 'smooth' });
                }
            }
        };
        close();
    });

    // Telefon: pasek z wynikiem na dole, dopóki sam wynik jest poza widokiem.
    var peekObserver = null;
    function watchPeek() {
        if (!('IntersectionObserver' in window) || peekObserver) return;
        peekObserver = new IntersectionObserver(function (entries) {
            out.peek.hidden = entries[0].isIntersecting;
        }, { root: out.body, threshold: 0.12 });
        peekObserver.observe(out.result);
    }
    out.peek.addEventListener('click', function () {
        out.result.scrollIntoView({ block: 'start', behavior: reduce.matches ? 'instant' : 'smooth' });
    });

    // Link z czatu albo Discorda: /cennik#kalkulator otwiera okno od razu.
    function fromHash() { if (location.hash === '#kalkulator') open(); }
    window.addEventListener('hashchange', fromHash);

    update();
    fromHash();
})();
