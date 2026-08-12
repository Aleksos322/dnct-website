/**
 * Cennik: przełączanie gier, konfigurator slotów i zaznaczanie wyboru.
 *
 * Skrypt jest DODATKIEM, nie warunkiem działania strony. Bez JavaScriptu
 * widoczny zostaje pierwszy panel gry, a ceny planów są w HTML-u — cennik
 * nadal się czyta. Znika tylko przełączanie i konfigurator.
 *
 * Progi slotów i okresy przychodzą z PHP w atrybutach data-*, prosto z bazy.
 * Nie ma tu ani jednej ceny zapisanej na sztywno — w starej wersji ta sama
 * tabela żyła w trzech miejscach naraz i to jest dokładnie ten błąd, którego
 * tu nie powtarzamy.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Zakładki gier
       ------------------------------------------------------------------ */
    function initTabs() {
        var tabs = document.querySelectorAll('.cennik-tab');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var targetId = tab.getAttribute('data-target');

                document.querySelectorAll('.cennik-tab').forEach(function (t) {
                    var on = t === tab;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });

                document.querySelectorAll('.cennik-panel').forEach(function (p) {
                    p.classList.toggle('is-active', p.id === targetId);
                });

                // Dekoracje boczne idą za zakładką. Zestawy wszystkich gier są
                // już w HTML-u, więc przełączenie to sama zmiana klasy - nic
                // się nie dociąga i nic nie mruga.
                document.querySelectorAll('.cnk-decor-set').forEach(function (d) {
                    d.classList.toggle('is-active', d.getAttribute('data-decor-for') === targetId);
                });

                // Zmiana gry unieważnia wcześniejszy wybór - inaczej w pasku
                // zostaje plan z gry, której użytkownik już nie ogląda.
                clearPick();
            });
        });
    }

    /* ------------------------------------------------------------------
       Pasek podsumowania wyboru
       ------------------------------------------------------------------ */
    var bar = document.getElementById('pick-bar');

    function showPick(name, price, per, url) {
        if (!bar) return;
        bar.querySelector('[data-role="pickName"]').textContent = name;
        bar.querySelector('[data-role="pickPrice"]').textContent = price;
        bar.querySelector('[data-role="pickPer"]').textContent = per || '';

        // Przycisk w pasku istnieje tylko przy włączonej sprzedaży (PHP go nie
        // wypisuje inaczej), więc podpinamy adres wyłącznie gdy jest co podpiąć.
        var go = bar.querySelector('[data-role="pickGo"]');
        if (go && url) go.setAttribute('href', url);

        bar.classList.add('is-visible');
    }

    function clearPick() {
        if (bar) bar.classList.remove('is-visible');
        document.querySelectorAll('.plan-pick.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
        });
    }

    if (bar) {
        var clearBtn = bar.querySelector('[data-role="pickClear"]');
        if (clearBtn) clearBtn.addEventListener('click', clearPick);
    }

    /* ------------------------------------------------------------------
       Zaznaczanie gotowych planów
       ------------------------------------------------------------------ */
    function initPlanPicks() {
        document.querySelectorAll('.plan-pick').forEach(function (card) {
            function pick(e) {
                if (card.getAttribute('data-plan-soldout') === '1') {
                    if (e) e.preventDefault();
                    return;
                }

                // Kliknięcie W PRZYCISK karty ma przejść do zamówienia, więc
                // nie blokujemy zdarzenia. Wcześniej preventDefault leciało
                // bezwarunkowo i zjadało nawigację razem z całą ścieżką zakupu:
                // przycisk zaznaczał plan i nic więcej.
                var cta = e && e.target && e.target.closest
                    ? e.target.closest('a.pcard-cta')
                    : null;

                if (!cta) {
                    // Karta jest klikalna jako całość (role="button"), więc bez
                    // tego spacja przewinęłaby stronę zamiast zaznaczyć plan.
                    if (e) e.preventDefault();
                }

                document.querySelectorAll('.plan-pick').forEach(function (c) {
                    c.classList.toggle('is-selected', c === card);
                });

                showPick(
                    card.getAttribute('data-plan-game') + ' · ' + card.getAttribute('data-plan-name'),
                    card.getAttribute('data-plan-price'),
                    '/ mies.',
                    '/zamowienie?plan=' + encodeURIComponent(card.getAttribute('data-plan-id'))
                );
            }

            card.addEventListener('click', pick);
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') pick(e);
            });
        });
    }

    /* ------------------------------------------------------------------
       Konfigurator slotów
       ------------------------------------------------------------------ */

    /** Podpowiedź dobrana do liczby slotów — klient nietechniczny nie musi zgadywać. */
    function slotHint(slots) {
        if (slots <= 5)  return 'W sam raz na granie ze znajomymi';
        if (slots <= 15) return 'Dobre na małą, stałą ekipę';
        if (slots <= 30) return 'Społeczność z regularnym ruchem';
        if (slots <= 50) return 'Duży serwer publiczny';
        return 'Bardzo duży serwer — napisz, dobierzemy sprzęt';
    }

    function money(v) {
        // Ceny z bazy bywają całkowite; grosze pokazujemy tylko gdy są.
        return (Math.round(v * 100) / 100).toFixed(2).replace(/\.00$/, '');
    }

    function initConfigurator(root) {
        var tiers, periods;
        try {
            tiers   = JSON.parse(root.getAttribute('data-tiers'));
            periods = JSON.parse(root.getAttribute('data-periods'));
        } catch (err) {
            return; // uszkodzone dane - lepiej nie pokazywać nic niż bzdury
        }
        if (!tiers.length || !periods.length) return;

        var range   = root.querySelector('[data-role="range"]');
        var elSlots = root.querySelector('[data-role="slots"]');
        var elRam   = root.querySelector('[data-role="ram"]');
        var elCpu   = root.querySelector('[data-role="cpu"]');
        var elPrice = root.querySelector('[data-role="price"]');
        var elOld   = root.querySelector('[data-role="oldPrice"]');
        var elPer   = root.querySelector('[data-role="pricePer"]');
        var elSave  = root.querySelector('[data-role="save"]');
        var elHint  = root.querySelector('[data-role="hint"]');
        var btnSel  = root.querySelector('[data-role="select"]');

        var current = periods.find(function (p) { return p.days === 30; }) || periods[0];

        // Ten sam wzór co backend/lib/pricing.php: zaokrąglamy w dół TYLKO gdy
        // okres ma własną końcówkę (adjust). Progi slotowe mają ceny całkowite,
        // więc tutaj nic to nie zmienia - ale rozjazd wzorów między stroną
        // a serwerem kończy się inną kwotą na ekranie niż na rachunku.
        function priceFor(tier, period) {
            var scaled = tier.price * period.multiplier;
            if (period.adjust > 0) return Math.floor(scaled) + period.adjust;
            return Math.round(scaled * 100) / 100;
        }

        function render() {
            var tier = tiers[parseInt(range.value, 10)] || tiers[0];
            var total = priceFor(tier, current);

            elSlots.textContent = tier.slots;
            elRam.textContent   = tier.ram + ' GB';
            elCpu.textContent   = tier.cpu + ' vCore';
            elPrice.textContent = money(total);
            elHint.textContent  = slotHint(tier.slots);

            // Ile PEŁNYCH miesięcy obejmuje okres. Dzielenie przez 30 dawało dla
            // roku 12.17 miesiąca, przez co cena odniesienia rosła do 790.83 zł,
            // a "oszczędzasz" pokazywało 75.83 zł przy odznace "1 miesiąc gratis"
            // i cenie 65 zł/mies. Liczby przeczyły obietnicy. Rok to 12 miesięcy,
            // więc oszczędność ma wyjść równo jednym miesiącem.
            var months = current.days >= 30
                ? Math.round(current.days / 30.44)
                : current.days / 30;
            elPer.textContent = current.days === 30
                ? 'za miesiąc'
                : 'za ' + current.label.toLowerCase() + ' · ' + money(total / months) + ' zł/mies.';

            // Odniesienie: ile kosztowałby ten sam czas kupowany miesiąc po
            // miesiącu. Pokazujemy TYLKO gdy dłuższy okres faktycznie jest
            // tańszy - "oszczędność" 0 zł podważa wiarygodność reszty strony.
            var reference = tier.price * months;
            var saving = reference - total;
            if (saving > 0.5) {
                elOld.textContent  = money(reference) + ' zł';
                elSave.textContent = 'Oszczędzasz ' + money(saving) + ' zł';
                elOld.style.display = '';
                elSave.style.display = '';
            } else {
                elOld.style.display = 'none';
                elSave.style.display = 'none';
            }

            if (root.classList.contains('is-selected')) select();
        }

        function configUrl(tier) {
            return '/zamowienie?game=' + encodeURIComponent(root.getAttribute('data-type') || '')
                 + '&slots=' + encodeURIComponent(tier.slots);
        }

        function select() {
            var tier = tiers[parseInt(range.value, 10)] || tiers[0];
            root.classList.add('is-selected');
            document.querySelectorAll('.plan-pick').forEach(function (c) { c.classList.remove('is-selected'); });
            showPick(
                tier.slots + ' slotów · ' + tier.ram + ' GB RAM',
                money(priceFor(tier, current)),
                '/ ' + current.label.toLowerCase(),
                configUrl(tier)
            );
        }

        /* Kliknięcie przycisku ma PRZEJŚĆ do zamówienia. select() zostaje osobno,
           bo woła go też render() przy każdym ruchu suwaka — gdyby to on
           przenosił na inną stronę, konfigurator uciekałby przy pierwszym
           przesunięciu. */
        function chooseConfig() {
            select();
            if (document.querySelector('[data-role="pickGo"]')) {
                window.location.href = configUrl(tiers[parseInt(range.value, 10)] || tiers[0]);
            }
        }

        range.addEventListener('input', render);

        root.querySelectorAll('.cfg-period').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var days = parseInt(btn.getAttribute('data-days'), 10);
                current = periods.find(function (p) { return p.days === days; }) || current;
                root.querySelectorAll('.cfg-period').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                render();
            });
        });

        if (btnSel) btnSel.addEventListener('click', chooseConfig);

        render();
    }

    /* ------------------------------------------------------------------
       Paralaksa dekoracji bocznych

       Klocki przesuwają się wolniej niż strona, przez co margines dostaje
       głębię, a nie tylko ruch. Wartość ląduje w zmiennej --p, którą CSS
       wstawia do `translate` - dzięki temu skrypt nie dotyka `transform`
       i nie kasuje animacji unoszenia.
       ------------------------------------------------------------------ */
    function initParallax() {
        if (reducedMotion()) return;

        var items = document.querySelectorAll('.cnk-decor-item');
        if (!items.length) return;

        // Każdy element dostaje własne tempo, inaczej cała warstwa jedzie
        // jak jeden sztywny obrazek i efektu głębi nie ma.
        var speeds = [0.06, -0.05, 0.09, -0.07, 0.05, -0.08];

        // Ograniczenie przesunięcia. Cennik ma ok. 5000 px wysokości, więc bez
        // tego najszybszy element odjechałby o 450 px od miejsca, w którym
        // ustawił go CSS - czyli poza swoje pasmo i pod obcięcie warstwy.
        var LIMIT = 110;
        var ticking = false;

        function apply() {
            var y = window.scrollY;
            items.forEach(function (el, i) {
                var shift = y * speeds[i % speeds.length];
                shift = Math.max(-LIMIT, Math.min(LIMIT, shift));
                el.style.setProperty('--p', shift.toFixed(1) + 'px');
            });
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            // Bez tego bloku przeglądarka liczyłaby pozycje przy każdym
            // zdarzeniu przewijania, a nie raz na klatkę.
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(apply);
        }, { passive: true });

        apply();
    }

    function reducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    initTabs();
    initPlanPicks();
    initParallax();
    document.querySelectorAll('.cfg').forEach(initConfigurator);
})();
