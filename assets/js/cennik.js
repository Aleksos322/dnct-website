/**
 * Cennik: przełączanie gier, konfigurator slotów i zaznaczanie wyboru.
 *
 * Skrypt jest DODATKIEM, nie warunkiem działania strony. Bez JavaScriptu
 * widoczny zostaje pierwszy panel gry, a ceny planów są w HTML-u - cennik
 * nadal się czyta. Znika tylko przełączanie i konfigurator.
 *
 * Progi slotów i okresy przychodzą z PHP w atrybutach data-*, prosto z bazy.
 * Nie ma tu ani jednej ceny zapisanej na sztywno - w starej wersji ta sama
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
            // Karty-wizytówki na stronie głównej (components: ta sama .pcard,
            // ale bez data-plan-id) linkują wprost do /cennik przez własny
            // <a class="pcard-cta"> - nie mają czego "zaznaczać". Bez tego
            // guarda klik w taką kartę wołałby showPick() z samymi "null"
            // w tekście, zanim przeglądarka zdążyła przejść pod /cennik.
            if (!card.hasAttribute('data-plan-id')) return;

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

    /** Podpowiedź dobrana do liczby slotów - klient nietechniczny nie musi zgadywać. */
    function slotHint(slots) {
        if (slots <= 5)  return 'W sam raz na granie ze znajomymi';
        if (slots <= 15) return 'Dobre na małą, stałą ekipę';
        if (slots <= 30) return 'Społeczność z regularnym ruchem';
        if (slots <= 50) return 'Duży serwer publiczny';
        return 'Bardzo duży serwer - napisz, dobierzemy sprzęt';
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

        var range    = root.querySelector('[data-role="range"]');
        var elSlots  = root.querySelector('[data-role="slots"]');
        var elPakiet = root.querySelector('[data-role="pakiet"]');
        var elRam    = root.querySelector('[data-role="ram"]');
        var elCpu    = root.querySelector('[data-role="cpu"]');
        var elDysk   = root.querySelector('[data-role="dysk"]');
        var elBackup = root.querySelector('[data-role="backupy"]');
        var elPrice = root.querySelector('[data-role="price"]');
        var elOld   = root.querySelector('[data-role="oldPrice"]');
        var elPer   = root.querySelector('[data-role="pricePer"]');
        var elSave  = root.querySelector('[data-role="save"]');
        var elHint  = root.querySelector('[data-role="hint"]');
        var elScarce = root.querySelector('[data-role="scarce"]');
        var elMethodsHint = root.querySelector('[data-role="methodsHint"]');
        var elError = root.querySelector('[data-role="error"]');
        var btnSel  = root.querySelector('[data-role="select"]');

        var current = periods.find(function (p) { return p.days === 30; }) || periods[0];
        var mode    = 'payment';

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

            elSlots.textContent  = tier.slots;
            elPakiet.textContent = tier.slots + ' slotów';
            elRam.textContent    = tier.ram + ' GB';
            elCpu.textContent    = tier.cpu + ' vCore';
            elDysk.textContent   = (tier.disk / 1024) + ' GB';
            elBackup.textContent = tier.backups;
            elPrice.textContent  = money(total);
            elHint.textContent   = slotHint(tier.slots);

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

            // Ten sam komunikat o BLIK-u co dawniej na /zamowienie - klient ma
            // się o ograniczeniu dowiedzieć PRZED kliknięciem "Przejdź do
            // płatności", nie dopiero w Checkoucie.
            if (elMethodsHint) {
                elMethodsHint.textContent = mode === 'subscription'
                    ? 'Abonament wymaga karty - BLIK i przelewy nie obsługują płatności cyklicznych.'
                    : 'Zapłacisz kartą lub BLIK-iem. Pełną listę metod zobaczysz w Checkoucie.';
            }

            renderAvailability(tier);
        }

        /* Płatność startuje wprost stąd - fetch do tego samego endpointu, co
           dawny /zamowienie (assets/js/order.js). Nie ma już pośredniej
           strony z tymi samymi pytaniami zadanymi drugi raz. */
        function resetButton() {
            if (!btnSel || btnSel.hasAttribute('data-locked')) return;
            btnSel.textContent = root.getAttribute('data-logged-in') === '1'
                ? 'Dalej - podsumowanie'
                : 'Zaloguj się, żeby zamówić';
        }

        /* Dostępność progu - to samo `fits`/`soldOut`, które PHP dolewa przez
           annotate_tier_availability() (backend/lib/capacity.php), więc front
           i backend patrzą na TĘ SAMĄ liczbę wolnego miejsca na węźle. Bez
           tego dało się przesunąć suwak na próg, którego serwer i tak by
           odrzucił dopiero przy kliknięciu "Przejdź do płatności" - tutaj
           widać to od razu, zanim klient w ogóle spróbuje zapłacić. */
        function renderAvailability(tier) {
            var soldOut = !!tier.soldOut;

            if (btnSel && !btnSel.hasAttribute('data-locked')) {
                btnSel.disabled = soldOut;
                if (soldOut) {
                    btnSel.textContent = 'Brak miejsca na tym progu';
                } else {
                    resetButton();
                }
            }

            if (elScarce) {
                if (soldOut) {
                    elScarce.textContent = 'Ta liczba slotów jest chwilowo niedostępna - wybierz inną.';
                    elScarce.style.display = '';
                } else if (tier.fits !== null && tier.fits !== undefined && tier.fits <= 4) {
                    elScarce.textContent = 'Zostały ' + tier.fits + ' wolne miejsca na węźle dla tej liczby slotów.';
                    elScarce.style.display = '';
                } else {
                    elScarce.style.display = 'none';
                }
            }
        }

        function fail(message) {
            if (elError) {
                elError.textContent = message;
                elError.hidden = false;
            }
            if (btnSel) {
                btnSel.removeAttribute('data-locked');
                btnSel.disabled = false;
            }
            resetButton();
        }

        function startCheckout() {
            if (root.getAttribute('data-logged-in') !== '1') {
                window.location.href = root.getAttribute('data-login-url');
                return;
            }
            if (btnSel.disabled) return;

            var tier = tiers[parseInt(range.value, 10)] || tiers[0];

            // Drugi strażnik, nie pierwszy - przycisk jest już disabled przez
            // renderAvailability() dla wyprzedanego progu, ale gdyby ktoś
            // odblokował go z devtools, serwer i tak odrzuci to w
            // pricing_from_tier(). Tu tylko oszczędzamy mu zbędnego żądania.
            if (tier.soldOut) {
                fail('Ta liczba slotów jest już niedostępna - wybierz inną.');
                return;
            }

            /* Zgody (Regulamin, natychmiastowy start) i kod rabatowy są na
               /zamowienie - tak samo jak przy planach. Wcześniej konfigurator
               wysyłał płatność sam, bez zgody, i serwer ją odrzucał. */
            window.location.href = '/zamowienie?' + new URLSearchParams({
                game:  root.getAttribute('data-type') || '',
                slots: String(tier.slots),
                days:  String(current.days),
                mode:  mode
            }).toString();
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

        root.querySelectorAll('.cfg-mode').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.getAttribute('data-mode');
                root.querySelectorAll('.cfg-mode').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                render();
            });
        });

        if (btnSel) btnSel.addEventListener('click', startCheckout);

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


    /* ------------------------------------------------------------------
       Pływająca plakietka z licznikiem

       Pokazujemy ją DOPIERO gdy licznik z nagłówka wyjedzie z ekranu -
       dwie te same liczby naraz to nie jest przypomnienie, tylko bałagan.
       ------------------------------------------------------------------ */
    function initLiveFloat() {
        var badge = document.querySelector('[data-role="liveFloat"]');
        var source = document.querySelector('.cnk-rail .cnk-live');
        if (!badge || !source || !window.IntersectionObserver) return;

        new IntersectionObserver(function (entries) {
            badge.classList.toggle('is-visible', !entries[0].isIntersecting);
        }, { threshold: 0 }).observe(source);
    }

    /* ------------------------------------------------------------------
       Licznik serwerów: naliczanie po wejściu w kadr

       Liczba, która się DOLICZA, czyta się jak odczyt z żywego systemu; ta sama
       liczba wpisana na sztywno wygląda jak element grafiki. Efekt odpala się
       RAZ - powtarzanie przy każdym przewinięciu zamienia dowód w gadżet.

       Wartość docelowa jest już w HTML-u, więc bez JavaScriptu (i przy
       prefers-reduced-motion) po prostu od razu widać prawidłową liczbę.
       ------------------------------------------------------------------ */
    function initLiveCount() {
        var rail = document.querySelector('.cnk-rail');
        if (!rail || !window.IntersectionObserver) return;

        var num = rail.querySelector('[data-count-to]');
        var target = num ? parseInt(num.getAttribute('data-count-to'), 10) : 0;


        var observer = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting) return;
            observer.disconnect();

            if (!num || !target || reducedMotion()) return;

            var started = null;
            var DURATION = 900;

            window.requestAnimationFrame(function step(now) {
                if (started === null) started = now;

                var p = Math.min(1, (now - started) / DURATION);
                // Wyhamowanie na końcu - liczba dobija do celu, zamiast
                // ucinać się w połowie ruchu.
                var eased = 1 - Math.pow(1 - p, 3);

                num.textContent = String(Math.round(target * eased));

                if (p < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    num.textContent = String(target);
                }
            });
        }, { threshold: 0.4 });

        observer.observe(rail);
    }


    /* ------------------------------------------------------------------
       Drobne reakcje na kursor

       Zasada: animacja ma coś ZNACZYĆ, nie tylko się ruszać. Odliczanie do 24
       pokazuje, że chodzi o dobę - liczbę, której nikt nie czyta uważnie,
       dopóki nie zacznie się zmieniać.

       Wszystko odpuszczamy przy prefers-reduced-motion: dla części osób ruch
       na ekranie to nie ozdoba, tylko powód do mdłości.
       ------------------------------------------------------------------ */
    function initHoverBits() {
        if (reducedMotion()) return;

        /* Podmieniamy WYŁĄCZNIE liczbę, nie cały napis. Jednostka siedzi
           w osobnym elemencie o stałej pozycji, więc podczas odliczania nie
           rusza się nic poza cyframi. */
        var num = document.querySelector('[data-role="count"]');
        if (!num) return;

        var target = parseInt(num.textContent, 10);
        if (!target) return;

        var host = num.closest('.cnk-trust-item') || num;
        var timer = null;

        host.addEventListener('mouseenter', function () {
            if (timer) return;   // ponowne najechanie w trakcie nie restartuje

            var value = 1;
            num.textContent = String(value);

            timer = window.setInterval(function () {
                value += 1;
                num.textContent = String(value);

                if (value >= target) {
                    window.clearInterval(timer);
                    timer = null;
                }
            }, 900 / target);   // całość zawsze ok. 0,9 s, niezależnie od liczby
        });

        // Zjechanie kursorem w trakcie odliczania zostawiłoby na ekranie
        // przypadkową liczbę, np. "17 h" - a to już nieprawda o ofercie.
        host.addEventListener('mouseleave', function () {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
            num.textContent = String(target);
        });
    }

    /* ------------------------------------------------------------------
       Chroma na kartach planów: przechył 3D + poświata śledząca kursor

       Obie rzeczy WYŁĄCZNIE pod kursorem - CSS pokazuje poświatę i przechyla
       kartę tylko na ":hover"/":focus-within" (patrz cennik.css), tu liczymy
       jedynie kąty i pozycję. Bez najechania karta zostaje płaska - żadnego
       ambientowego ruchu, którego nikt nie prosił (Reguła Spokojnej
       Powierzchni).

       Kąt liczony od ŚRODKA karty, nie od punktu najechania: kursor w rogu
       daje maksymalny przechył (MAX_TILT), kursor na środku - kartę płaską.
       Oś Y jest odwrócona (górna połowa karty przechyla się "do tyłu", jak
       prawdziwa kartka trzymana i pochylana ku sobie) - bez tego przechył
       wyglądałby na odwrotny do ruchu kursora.

       Gama poświaty to gotowy gradient CTA (ametyst -> indygo -> błękit),
       nie tęcza - "Reguła Jednego Głosu" w DESIGN.md zabrania dokładać nowe
       kolory akcji.
       ------------------------------------------------------------------ */
    /**
     * Podpina przechył 3D + poświatę pod kursor do JEDNEGO elementu. Współdzielona
     * przez karty planów (.pcard) i makietę konsoli/zwiastuna w pasmie "Zobacz,
     * zanim zapłacisz" (.pnl-console/.pnl-trailer) - ten sam wzór CSS (--mx/--my/
     * --rx/--ry), inny tylko maksymalny kąt: duży panel przechylony tak samo mocno
     * jak mała karta wygląda na wypaczony, nie elegancki.
     */
    function attachChromaTilt(el, maxTilt) {
        // Mysz potrafi wysylac mousemove czesciej niz przegladarka rysuje
        // klatki - bez dlawienia KAZDY event od razu przeliczal transform I
        // duzy gradient (.pcard::before, kolo 480px), co dawalo wiecej
        // przeliczen/repaintow na klatke niz przegladarka zdazyla wyswietlic.
        // rAF zbija cala serie eventow miedzy klatkami do JEDNEJ aktualizacji.
        var pendingX = null;
        var pendingY = null;
        var frameScheduled = false;
        var lastX = null; // ostatnia ZNANA pozycja kursora (do przeliczenia po scrollu)
        var lastY = null;

        function apply() {
            frameScheduled = false;
            if (pendingX === null) return;
            var r = el.getBoundingClientRect();
            var px = (pendingX - r.left) / r.width;
            var py = (pendingY - r.top) / r.height;
            pendingX = null;
            pendingY = null;

            el.style.setProperty('--mx', (px * 100) + '%');
            el.style.setProperty('--my', (py * 100) + '%');
            el.style.setProperty('--ry', ((px - 0.5) * 2 * maxTilt).toFixed(2) + 'deg');
            el.style.setProperty('--rx', ((0.5 - py) * 2 * maxTilt).toFixed(2) + 'deg');
        }

        function schedule(x, y) {
            lastX = x;
            lastY = y;
            pendingX = x;
            pendingY = y;
            if (!frameScheduled) {
                frameScheduled = true;
                requestAnimationFrame(apply);
            }
        }

        function move(e) {
            schedule(e.clientX, e.clientY);
        }

        function reset() {
            pendingX = null;
            pendingY = null;
            lastX = null;
            lastY = null;
            el.style.setProperty('--rx', '0deg');
            el.style.setProperty('--ry', '0deg');
        }

        /* Lenis (smooth-scroll.js) potrafi jeszcze przez chwile dojezdzac
           karte bezwladnoscia POD NIERUCHOMYM kursorem - system generuje
           "mousemove" tylko gdy mysz sie fizycznie rusza, wiec --rx/--ry
           zamrazaly sie na starej wartosci, karta wjezdzala pod tilt, a
           dopiero kolejny prawdziwy ruch myszy "odswiezal" go do poprawnej
           wartosci. Wygladalo to jak karta, ktora sie odpycha i sama wraca
           na miejsce. Doliczamy wiec tez scroll, ale tylko gdy kursor juz
           kiedys byl nad karta (lastX/lastY) - inaczej kazdy scroll strony
           liczylby tilt dla kart, ktorych nikt nie dotyka. */
        function onScroll() {
            if (lastX === null) return;
            schedule(lastX, lastY);
        }

        el.addEventListener('mousemove', move);
        el.addEventListener('mouseleave', reset);
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    function initChromaCards() {
        if (reducedMotion()) return;   // ruch śledzący kursor to wciąż ruch

        document.querySelectorAll('.plan-pick .pcard').forEach(function (card) {
            // Wyprzedana karta i tak nie prowadzi do zamówienia - przechył
            // sugerowałby interaktywność, której tu nie ma.
            if (card.classList.contains('is-soldout')) return;
            attachChromaTilt(card, 10);
        });

        document.querySelectorAll('.pnl-console, .pnl-trailer').forEach(function (el) {
            attachChromaTilt(el, 5);
        });

        // Panel podsumowania w konfiguratorze slotów - ten sam przechył co karty
        // planu, tylko łagodniejszy: to panel z przyciskiem płatności, nie ma co
        // go szarpać tak mocno jak kartę do samego oglądania.
        document.querySelectorAll('.cfg-summary').forEach(function (el) {
            attachChromaTilt(el, 6);
        });
    }

    /* ------------------------------------------------------------------
       Naglowek hero: "wpisywany" znak po znaku z migajacym kursorem, a po
       skonczeniu druga linia sama sie podmienia - "nie zwalnia" ->
       "nie ogranicza" -> "nie zawodzi" -> "daje przewagę" (ostatnia podmiana
       kasuje tez "nie", nie tylko slowo koncowe).

       Tekst w HTML (patrz [data-type-text] w cennik.php) zostaje tresci
       SEO/czytnikow ekranu i zrodlem znakow do animacji jednoczesnie - bez
       JS-a widac go od razu, normalnie (Reguła "JS to dodatek" z naglowka
       tego pliku). JS go tylko na chwile czysci i przepisuje z powrotem.
       ------------------------------------------------------------------ */
    function initHeroTypewriter() {
        if (reducedMotion()) return;

        var lines = Array.prototype.slice.call(document.querySelectorAll('.cnk-h1 [data-type-text]'));
        if (!lines.length) return;

        var queue = lines.map(function (el) {
            var text = el.textContent;
            el.textContent = '';
            return { el: el, text: text };
        });

        // Pasek rysowany w CSS (cennik.css), nie znak "|" - ten dziedziczyl
        // font-weight: 900 naglowka i wychodzil gruby jak reszta liter.
        var cursor = document.createElement('span');
        cursor.className = 'cnk-type-cursor';
        cursor.setAttribute('aria-hidden', 'true');

        var CHAR_DELAY = 38;   // ms miedzy wpisywanymi znakami
        var DELETE_DELAY = 32; // ms miedzy kasowanymi znakami
        var CYCLE_PAUSE = 5000; // ms przerwy PO wpisaniu, zanim zacznie sie kasowanie

        // Kazdy krok: ile znakow skasowac OD KONCA linii, i co wpisac w to
        // miejsce. Trzeci krok kasuje CALE "nie zawodzi" (nie tylko
        // "zawodzi"), bo "daje przewagę" juz nie ma przeczenia.
        var CYCLE_STEPS = [
            { deleteCount: 'zwalnia'.length, text: 'ogranicza' },
            { deleteCount: 'ogranicza'.length, text: 'zawodzi' },
            { deleteCount: 'nie zawodzi'.length, text: 'daje przewagę!' }
        ];

        // Kazdy wpisany znak to WLASNY text node (insertAdjacentText nigdy
        // nie skleja go z sasiednim) - dzieki temu kasowanie to zawsze
        // "usun ostatni text node przed kursorem", bez liczenia offsetow
        // w stringu.
        function typeInto(text, onDone) {
            var i = 0;
            function step() {
                if (i < text.length) {
                    cursor.insertAdjacentText('beforebegin', text.charAt(i));
                    i++;
                    setTimeout(step, CHAR_DELAY);
                } else if (onDone) {
                    onDone();
                }
            }
            step();
        }

        function deleteChars(count, onDone) {
            function step(remaining) {
                if (remaining > 0) {
                    var node = cursor.previousSibling;
                    if (node) node.remove();
                    setTimeout(function () { step(remaining - 1); }, DELETE_DELAY);
                } else if (onDone) {
                    onDone();
                }
            }
            step(count);
        }

        function runCycle(stepIndex) {
            if (stepIndex >= CYCLE_STEPS.length) return; // koniec - kursor zostaje migac
            var def = CYCLE_STEPS[stepIndex];
            setTimeout(function () {
                deleteChars(def.deleteCount, function () {
                    typeInto(def.text, function () {
                        runCycle(stepIndex + 1);
                    });
                });
            }, CYCLE_PAUSE);
        }

        function typeLine(index) {
            if (index >= queue.length) {
                // Cala reszta naglowka wpisana - druga (ostatnia) linia
                // zaczyna cykl podmian slowa koncowego.
                runCycle(0);
                return;
            }

            var item = queue[index];

            function start() {
                item.el.appendChild(cursor);
                typeInto(item.text, function () { typeLine(index + 1); });
            }

            // data-type-pause: przerwa PRZED tym segmentem (np. przejscie na
            // druga linie naglowka). Bez atrybutu segment leci od razu dalej -
            // uzywane miedzy "Serwer" i ", ktory" na tej samej linii, zeby nie
            // bylo dziwnej przerwy w srodku zdania.
            var pause = parseInt(item.el.getAttribute('data-type-pause') || '0', 10);
            if (pause > 0) {
                setTimeout(start, pause);
            } else {
                start();
            }
        }

        typeLine(0);
    }

    initTabs();
    initPlanPicks();
    initHoverBits();
    initLiveFloat();
    initLiveCount();
    initParallax();
    initChromaCards();
    initHeroTypewriter();
    document.querySelectorAll('.cfg').forEach(initConfigurator);
})();
