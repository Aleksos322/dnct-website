<?php
/**
 * errors/404.php
 * Podepnij w .htaccess: ErrorDocument 404 /errors/404.php
 *
 * Panel radaru po lewej jest czystą ozdobą (SVG siatki + obracany stożek
 * CSS) - nic nie liczy, nic nie odpytuje. Wyszukiwarka po prawej jest
 * realna: filtruje na żywo statyczną, ręcznie utrzymywaną listę głównych
 * podstron (JS, bez zapytania do serwera). To nie ma zastępować mapy
 * strony, tylko odesłać kogoś z literówką w adresie w dobre miejsce
 * w dwa kliknięcia.
 */

http_response_code(404);

$pageTitle       = 'Strona nie znaleziona (404) - DNCT Hosting';
$metaDescription = 'Ta strona nie istnieje lub została przeniesiona.';
$centerContent   = true;

ob_start();
?>
<style>
    /* ---------- Karta całości (jak karty planów w cenniku) ---------- */
    /* .glass-content musi być OSOBNYM dzieckiem .glass (tak jak w navbar.php),
       nie ta sama klasa na jednym elemencie - inaczej z-index:1 z .glass-content
       podnosi caly panel wzgledem RODZENSTWA, ale nie ratuje go przed WLASNYM
       ::after (przeblysk szkla), ktory jako pseudo-element generuje sie zawsze
       OSTATNI w kolejnosci drzewa i maluje sie NAD wczesniejsza trescia o tym
       samym z-index. overflow:hidden na .glass dodatkowo tnie tresc do
       zaokraglenia - bez tego backdrop-filter potrafi zostawic kwadratowy cien
       w rogu mimo przycietych rogow. Karta jest jedynym .glass na tej stronie -
       radar w środku to już zwykły, wpuszczony "ekran", nie druga tafla szkła
       (dwa zagnieżdżone backdrop-filtery rozmywają się nawzajem i robią plamę). */
    /* BEZ overflow:hidden - ono obcinałoby też dropdown wyszukiwarki (jest
       position:absolute, dziecko tej karty i normalnie wychodzi poniżej jej
       dolnej krawędzi). border-radius sam w sobie wystarczająco tnie własne
       tło/backdrop-filter/box-shadow karty do zaokrąglonego kształtu w
       dzisiejszych przeglądarkach - overflow:hidden był potrzebny tylko przy
       zagnieżdżonym .glass (radar), nie tutaj. */
    .p404-card {
        position: relative;
        width: 100%;
        max-width: 54rem;
        margin: 0 auto;
        padding: 2rem;
        border-radius: 2.5rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
    }
    @media (min-width: 640px) {
        .p404-card { padding: 2.75rem 3rem; }
    }

    /* .page-main (layout.css) jest CELOWO wyższy niż ekran (100vh + 185px),
       żeby stopka nie było widać bez scrolla nawet na pustej stronie - ale
       przez to justify-content:center centruje kartę względem TEJ nadwyżki,
       nie względem widocznego ekranu, i karta ląduje wyraźnie za nisko.
       Nadpisanie samym `min-height: 100vh` psułoby regułę stopki na innych
       stronach, więc scoped przez :has() tylko tu: ten sam zapas (185px)
       wraca jako padding-bottom, czyli poza strefą centrowania. */
    .page-main:has(.p404-card) {
        min-height: 100vh;
        padding-bottom: 185px;
    }
    /* Na telefonie karta i stopka stały za blisko siebie - stopka ma tu
       więcej własnej treści (linki, social, dane firmy), więc te same 185px
       zapasu, co na desktopie, wizualnie nie wystarczają. */
    @media (max-width: 640px) {
        .page-main:has(.p404-card) { padding-bottom: 280px; }
    }

    .p404-wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2.5rem;
        align-items: start;
        text-align: left;
    }
    @media (min-width: 860px) {
        .p404-wrap { grid-template-columns: 20rem 1fr; }
    }

    /* ---------- Panel radaru (ozdoba) - ekran wpuszczony w kartę ---------- */
    .p404-radar-panel {
        position: relative;
        width: 100%;
        max-width: 20rem;
        border-radius: 1.5rem;
        overflow: hidden;
        margin: 0 auto;
        background:
            radial-gradient(120% 100% at 50% 0%, rgba(139, 92, 246, 0.09), transparent 60%),
            rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.07);
        box-shadow: inset 0 1px 14px rgba(0, 0, 0, 0.45);
        transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.15s ease-out;
        transform: perspective(1000px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
    }
    .p404-radar-panel:hover {
        border-color: rgba(139, 92, 246, 0.35);
        box-shadow:
            inset 0 1px 14px rgba(0, 0, 0, 0.45),
            0 14px 34px rgba(0, 0, 0, 0.4),
            0 0 0 1px rgba(139, 92, 246, 0.25);
        transform: perspective(1000px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg)) translateY(-4px);
    }
    /* Chroma: plama gradientu sledzaca kursor, ten sam wzor co karty planow
       (assets/css/cennik.css .pcard::before) - widoczna wylacznie pod kursorem. */
    .p404-radar-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 0;
        opacity: 0;
        background: radial-gradient(
            420px circle at var(--mx, 50%) var(--my, 50%),
            rgba(139, 92, 246, 0.16), transparent 60%
        );
        transition: opacity 0.35s ease;
        pointer-events: none;
    }
    .p404-radar-panel:hover::before,
    .p404-radar-panel:focus-within::before { opacity: 1; }
    @media (prefers-reduced-motion: reduce) {
        .p404-radar-panel { transform: none !important; }
        .p404-radar-panel:hover { transform: none !important; }
        .p404-radar-panel::before { display: none; }
    }
    .p404-radar-inner {
        position: relative;
        z-index: 1;
        padding: 1.4rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .p404-radar-meta {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.58rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #64748b;
        white-space: nowrap;
    }
    /* aspect-ratio TU, nie na .p404-radar-inner - wcześniej kwadrat wymuszony
       był na całym pudełku (ekran + dwa paski tekstu), więc po odjęciu ich
       wysokości sam ekran wychodził SZERSZY niż wyższy (273x213px zmierzone).
       border-radius:50% na prostokącie robi elipsę, nie koło, więc wskazówka
       (elipsa) wystawała poza okrągły pierścień z SVG (który zostaje kołem,
       bo <svg> ma domyślne preserveAspectRatio i sam się listwuje). Kwadrat
       na samym .p404-radar-scope usuwa rozjazd u źródła - oba są teraz tym
       samym kołem. */
    .p404-radar-scope {
        position: relative;
        aspect-ratio: 1 / 1;
        margin: 1rem 0;
        border-radius: 50%;
    }
    .p404-radar-scope svg { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
    /* inset:5% (nie 0) - promień pierścienia w SVG to r=90 na viewBox 200,
       czyli 10/200 = 5% marginesu od krawędzi. Bez tego okrąg wskazówki był
       WIĘKSZY niż narysowany pierścień i wystawał poza jego obręcz.
       z-index niższy niż <svg> (i pointer-events:none), żeby pasmo leciało
       ZA siatką i kropkami, nie nad nimi - inaczej zasłaniało kontakty i
       przechwytywało kliknięcia, zanim dotarły do celu. */
    .p404-radar-sweep {
        position: absolute;
        inset: 5%;
        z-index: 0;
        border-radius: 50%;
        overflow: hidden;
        pointer-events: none;
        animation: p404Rotate 5s linear infinite;
    }
    /* Gradient odwrócony względem obrotu (rotate 0deg->360deg, czyli zgodnie
       z ruchem wskazówek): jasna krawędź stoi na 100% (= ten sam kąt co 0%,
       zawija się bez szwu), a poświata dochodzi do niej z 74%. 74-100% to
       kąty TUŻ PRZED bieżącym kątem wskazówki w kierunku obrotu, czyli
       fizycznie ZA nią (teren już zmieciony) - tam ma dogasać. Odwrotnie
       (jasność na 0%, zanik do 26%) świeciło w kątach PO bieżącym, czyli
       przed wskazówką, w teren jeszcze nieprzeczesany - stąd poprawka. */
    .p404-radar-sweep::before {
        content: '';
        position: absolute;
        inset: 0;
        background: conic-gradient(from 0deg, transparent 0%, transparent 74%, rgba(139, 92, 246, 0.5) 100%);
    }
    @keyframes p404Rotate {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .p404-radar-center {
        position: absolute;
        z-index: 2;
        top: 50%;
        left: 50%;
        width: 6px;
        height: 6px;
        margin: -3px;
        border-radius: 50%;
        background: #c4b5fd;
        box-shadow: 0 0 8px rgba(196, 181, 253, 0.9);
    }
    @media (prefers-reduced-motion: reduce) {
        .p404-radar-sweep { animation: none; }
        .p404-radar-sweep::before { background: conic-gradient(from 0deg, transparent 0%, transparent 60%, rgba(139, 92, 246, 0.22) 100%); }
    }

    /* Kontakty na skanerze - klikalne/focusowalne, ale "wykrycie" jest
       CHWILOWE (jak prawdziwe echo radaru): wskazówka mija kontakt, kropka
       zapala się na ~1s i sama gaśnie - patrz JS (funkcja detect, setTimeout).
       Kolko r=9 wokol kropki to wiekszy, niewidoczny cel kliku - prawdziwa
       kropka ma 2.5px promienia, za malo żeby trafić kursorem czy palcem. */
    .p404-contact { cursor: pointer; }
    .p404-contact:focus { outline: none; }
    .p404-contact:focus circle:last-child,
    .p404-contact:hover circle:last-child {
        fill: rgba(196, 181, 253, 0.85);
    }
    .p404-contact circle:last-child { transition: fill 0.6s ease; }
    .p404-contact.is-detected circle:last-child { fill: #4ade80; }

    /* Echo: pierścień wystartowuje z promienia kropki i rozchodzi się na
       zewnątrz, gasnąc im dalej ucieka od środka - sam "puls" radaru, osobno
       od koloru kropki. Kółko bez wypełnienia (fill:none), tylko obrys. */
    .p404-ping {
        fill: none;
        stroke: #4ade80;
        stroke-width: 1.5;
        opacity: 0;
    }
    .p404-contact.is-detected .p404-ping {
        animation: p404Ping 1s ease-out;
    }
    @keyframes p404Ping {
        0%   { r: 2.5; opacity: 0.75; stroke-width: 1.5; }
        100% { r: 16;  opacity: 0;    stroke-width: 0.5; }
    }
    @media (prefers-reduced-motion: reduce) {
        .p404-contact.is-detected .p404-ping { animation: none; }
    }

    /* ---------- Numeral outline-mix (4-0-4) ---------- */
    .p404-numeral {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0.15em;
        font-size: clamp(4.5rem, 11vw, 6.5rem);
        font-weight: 900;
        line-height: 1;
        margin-bottom: 1.25rem;
    }
    .p404-numeral .solid {
        background: linear-gradient(90deg, #8b5cf6, #3b82f6, #8b5cf6);
        background-size: 200% auto;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: gradientFlow 4s ease infinite;
    }
    .p404-numeral .outline {
        color: transparent;
        -webkit-text-stroke: 3px #8b5cf6;
        text-stroke: 3px #8b5cf6;
    }
    @media (prefers-reduced-motion: reduce) {
        .p404-numeral .solid { animation: none; }
    }

    .p404-signal {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.85rem;
        font-size: 0.68rem;
        font-weight: 900;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #f87171;
    }
    .p404-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #ef4444;
        box-shadow: 0 0 10px #ef4444;
        animation: p404Pulse 2.4s ease-in-out infinite;
    }
    @keyframes p404Pulse {
        0%, 100% { opacity: 0.5; transform: scale(0.85); }
        50%      { opacity: 1;   transform: scale(1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .p404-dot { animation: none; }
    }

    /* ---------- Wyszukiwarka (realna) ---------- */
    .p404-search-wrap {
        position: relative;
        max-width: 26rem;
        margin-bottom: 2rem;
    }
    .p404-search {
        position: relative;
    }
    .p404-search input {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        padding: 0.9rem 3.2rem 0.9rem 1.3rem;
        color: #f8fafc;
        font-size: 0.95rem;
    }
    .p404-search input::placeholder { color: #64748b; }
    .p404-search input:focus {
        outline: none;
        border-color: rgba(139, 92, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.35);
    }
    .p404-search-submit {
        position: absolute;
        right: 0.4rem;
        top: 50%;
        transform: translateY(-50%);
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 50%;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
        color: #fff;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .p404-search-submit:hover { transform: translateY(-50%) scale(1.06); }
    .p404-search-submit svg { width: 1rem; height: 1rem; }

    /* Lista pojawia się dopiero pod kursorem/focusem (position:absolute,
       nie w normalnym przepływie) - żeby domyślnie zostawić czysty widok
       z samym polem wyszukiwania, tak jak poproszono. Widoczna też, gdy w
       środku jest już wpisany tekst (:has), żeby wynik nie znikał w trakcie
       przenoszenia kursora z pola na listę. */
    .p404-results {
        position: absolute;
        top: calc(100% + 0.6rem);
        left: 0;
        right: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        padding: 0.5rem;
        border-radius: 16px;
        background: rgba(15, 15, 20, 0.92);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(16px);
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-4px);
        transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
    }
    /* Pole wyszukiwarki siedzi nisko na ekranie telefonu - lista w dół (9
       pozycji) uciekała pod dolną krawędź ekranu. Na wąskich ekranach otwiera
       się więc W GÓRĘ (nad polem), gdzie jest więcej miejsca, i dostaje
       własny scroll na wypadek małych ekranów / dłuższej listy w przyszłości. */
    @media (max-width: 640px) {
        .p404-results {
            top: auto;
            bottom: calc(100% + 0.6rem);
            max-height: min(60vh, 22rem);
            overflow-y: auto;
            transform: translateY(4px);
        }
    }
    .p404-search-wrap:hover .p404-results,
    .p404-search-wrap:focus-within .p404-results,
    .p404-search-wrap:has(input:not(:placeholder-shown)) .p404-results {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .p404-results a {
        display: flex;
        align-items: baseline;
        gap: 0.6rem;
        padding: 0.55rem 0.9rem;
        border-radius: 10px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.86rem;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .p404-results a:hover,
    .p404-results a:focus-visible {
        background: rgba(139, 92, 246, 0.12);
        color: #f8fafc;
        outline: none;
    }
    .p404-results a span { color: #64748b; font-size: 0.76rem; }
    .p404-results-empty { padding: 0.55rem 0.9rem; color: #64748b; font-size: 0.85rem; }

    /* ---------- Akcje ---------- */
    .p404-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; }
    .p404-home-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.85rem 1.6rem;
        border-radius: 16px;
        font-weight: 900;
        font-size: 0.72rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #fff;
        background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
        box-shadow: 0 10px 26px rgba(139, 92, 246, 0.3);
        transition: filter 0.2s ease, transform 0.2s ease;
    }
    .p404-home-btn:hover { filter: brightness(1.12); transform: translateY(-2px); }
    .p404-home-btn svg { width: 1.1rem; height: 1.1rem; }
    .p404-ghost-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.85rem 1.6rem;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #cbd5e1;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.85rem;
        transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease, transform 0.2s ease;
    }
    .p404-ghost-btn:hover {
        border-color: rgba(88, 101, 242, 0.5);
        color: #fff;
        background: rgba(88, 101, 242, 0.08);
        transform: translateY(-2px);
    }
    .p404-ghost-btn svg { width: 1.1rem; height: 1.1rem; color: #5865f2; }
    @media (prefers-reduced-motion: reduce) {
        .p404-ghost-btn:hover { transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .p404-home-btn:hover { transform: none; }
    }
</style>

<div class="p404-card glass">
<div class="glass-content">
<div class="p404-wrap">
    <div class="p404-radar-panel" id="p404-radar-panel">
        <div class="p404-radar-inner">
            <div class="p404-radar-meta">
                <span id="p404-sweep-label">SWEEP 5.0S</span>
                <span>SEKTOR 404</span>
            </div>
            <div class="p404-radar-scope">
                <svg viewBox="0 0 200 200" fill="none">
                    <circle cx="100" cy="100" r="90" stroke="rgba(255,255,255,0.08)"/>
                    <circle cx="100" cy="100" r="60" stroke="rgba(255,255,255,0.08)"/>
                    <circle cx="100" cy="100" r="30" stroke="rgba(255,255,255,0.08)"/>
                    <line x1="10" y1="100" x2="190" y2="100" stroke="rgba(255,255,255,0.06)"/>
                    <line x1="100" y1="10" x2="100" y2="190" stroke="rgba(255,255,255,0.06)"/>
                    <g stroke="rgba(255,255,255,0.14)" stroke-width="2">
                        <line x1="100" y1="6" x2="100" y2="14"/>
                        <line x1="100" y1="186" x2="100" y2="194"/>
                        <line x1="6" y1="100" x2="14" y2="100"/>
                        <line x1="186" y1="100" x2="194" y2="100"/>
                    </g>
                    <g class="p404-contacts">
                        <g class="p404-contact" data-cx="147" data-cy="65" tabindex="0" role="button" aria-label="Nieznany kontakt">
                            <circle cx="147" cy="65" r="9" fill="transparent"/>
                            <circle class="p404-ping" cx="147" cy="65" r="2.5"/>
                            <circle cx="147" cy="65" r="2.5" fill="rgba(196,181,253,0.55)"/>
                        </g>
                        <g class="p404-contact" data-cx="61" data-cy="140" tabindex="0" role="button" aria-label="Nieznany kontakt">
                            <circle cx="61" cy="140" r="9" fill="transparent"/>
                            <circle class="p404-ping" cx="61" cy="140" r="2.5"/>
                            <circle cx="61" cy="140" r="2.5" fill="rgba(196,181,253,0.4)"/>
                        </g>
                        <g class="p404-contact" data-cx="129" data-cy="152" tabindex="0" role="button" aria-label="Nieznany kontakt">
                            <circle cx="129" cy="152" r="9" fill="transparent"/>
                            <circle class="p404-ping" cx="129" cy="152" r="2.5"/>
                            <circle cx="129" cy="152" r="2.5" fill="rgba(196,181,253,0.3)"/>
                        </g>
                    </g>
                </svg>
                <div class="p404-radar-sweep"></div>
                <div class="p404-radar-center"></div>
            </div>
            <div class="p404-radar-meta">
                <span id="p404-contacts-label">KONTAKTY: 00</span>
                <span id="p404-signal-label">SYGNAŁ: ZGUBIONY</span>
            </div>
        </div>
    </div>

    <div>
        <p class="p404-signal">
            <span class="p404-dot"></span>
            Sygnał zgubiony
        </p>
        <p class="p404-numeral"><span class="solid">4</span><span class="outline">0</span><span class="solid">4</span></p>
        <h1 class="text-3xl font-bold mb-3">Nic na skanerze.</h1>
        <p class="text-gray-400 mb-8 max-w-md">
            Przeszukaliśmy każdą trasę dwa razy - ta strona nic już nie nadaje. Poszukaj czego potrzebujesz albo wracaj do bazy.
        </p>

        <div class="p404-search-wrap">
            <form class="p404-search" id="p404-form">
                <input type="text" id="p404-input" placeholder="Szukaj strony (cennik, panel, regulamin...)" autocomplete="off">
                <button type="submit" class="p404-search-submit" aria-label="Szukaj">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>
            <div class="p404-results" id="p404-results"></div>
        </div>

        <div class="p404-actions">
            <a href="/" class="p404-home-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                Strona główna
            </a>
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="p404-ghost-btn">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.369a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.058a.082.082 0 0 0 .031.056 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.246.198.373.292a.077.077 0 0 1-.006.128 12.3 12.3 0 0 1-1.873.891.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.029 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.06.06 0 0 0-.031-.028Zm-12.298 10.97c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.955 2.419-2.157 2.419Zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.946 2.419-2.157 2.419Z"/></svg>
                Napisz do wsparcia
            </a>
        </div>
    </div>
</div>
</div>
</div>

<script>
(function () {
    const routes = [
        { label: 'Strona główna',        href: '/',                     kw: 'start home' },
        { label: 'Cennik',                href: '/cennik',               kw: 'ceny plany minecraft zomboid vintage story kup' },
        { label: 'Twoje serwery',         href: '/twoje-serwery',        kw: 'panel moje konto zarzadzaj' },
        { label: 'Wypróbuj demo panelu',  href: '/demo',                 kw: 'demo test panel' },
        { label: 'Zaloguj się',           href: '/login',                kw: 'logowanie konto' },
        { label: 'Zarejestruj się',       href: '/register',             kw: 'rejestracja nowe konto zalozenie' },
        { label: 'Historia opłat',        href: '/oplaty',               kw: 'platnosci faktury zamowienia' },
        { label: 'Regulamin',             href: '/regulamin',            kw: 'zasady prawny umowa' },
        { label: 'Polityka prywatności',  href: '/polityka-prywatnosci', kw: 'rodo dane gdpr' },
    ];

    const form    = document.getElementById('p404-form');
    const input   = document.getElementById('p404-input');
    const results = document.getElementById('p404-results');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function render(list) {
        if (!list.length) {
            results.innerHTML = '<div class="p404-results-empty">Nic nie pasuje - spróbuj Discorda, tam odpowiemy.</div>';
            return;
        }
        results.innerHTML = list.map(r =>
            `<a href="${escapeHtml(r.href)}">${escapeHtml(r.label)} <span>${escapeHtml(r.href)}</span></a>`
        ).join('');
    }

    function filter(query) {
        const q = query.trim().toLowerCase();
        if (!q) return routes;
        return routes.filter(r => r.label.toLowerCase().includes(q) || r.kw.includes(q));
    }

    render(routes);

    input.addEventListener('input', () => render(filter(input.value)));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const first = filter(input.value)[0];
        if (first) window.location.href = first.href;
    });

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // "Wykrycie" kontaktu jest CHWILOWE, jak echo prawdziwego radaru: kropka
    // zapala się (kolor + rozchodzący się i gasnący pierścień .p404-ping),
    // po ~1s sama gaśnie i czeka na kolejny przejazd wskazówki - stąd zdjęcie
    // klasy w setTimeout, nie tylko jej dodanie. "SYGNAŁ: ZGUBIONY" zostaje
    // stały (strona 404 z zasady niczego nie "odzyskuje"). Kółko r=9 wokół
    // kropki w markupie to większy, niewidoczny cel kliku - klik zostaje jako
    // zapasowa ścieżka (i jedyna, gdy reduced-motion wyłącza obrót), ale
    // normalnie namierza to sama wskazówka, patrz niżej.
    const contacts = document.querySelectorAll('.p404-contact');
    const contactsLabel = document.getElementById('p404-contacts-label');
    let activeCount = 0;

    function detect(el) {
        if (el.classList.contains('is-detected')) return;
        el.classList.add('is-detected');
        activeCount++;
        contactsLabel.textContent = 'KONTAKTY: ' + String(activeCount).padStart(2, '0');
        setTimeout(() => {
            el.classList.remove('is-detected');
            activeCount--;
            contactsLabel.textContent = 'KONTAKTY: ' + String(activeCount).padStart(2, '0');
        }, 1000);
    }

    contacts.forEach((el) => {
        el.addEventListener('click', () => detect(el));
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); detect(el); }
        });
    });

    // Etykieta "SWEEP" w rogu radaru odlicza w rytmie animacji obrotu (5s) -
    // zsynchronizowana z prawdziwym cyklem CSS, nie zmyślona. Ten sam tik
    // liczy też kąt wskazówki (busola: 0° = góra, rośnie zgodnie z ruchem
    // wskazówek zegara - dokładnie układ, w jakim obraca się .p404-radar-sweep)
    // i namierza kontakt automatycznie, gdy pasmo nad nim przejeżdża - realny
    // radar wykrywa sam, bez czekania na klik.
    const sweepLabel = document.getElementById('p404-sweep-label');
    if (!reducedMotion) {
        const contactAngles = Array.from(contacts).map((el) => {
            const dx = parseFloat(el.dataset.cx) - 100;
            const dy = parseFloat(el.dataset.cy) - 100;
            return (Math.atan2(dx, -dy) * 180 / Math.PI + 360) % 360;
        });
        // Namierzenie po PRZEJŚCIU wskazówki przez łuk [poprzedni kąt, nowy kąt],
        // nie po trafieniu w wąskie okno co tik - odstęp tików (100ms = 7.2°)
        // i drobny jitter setInterval potrafiły przeskoczyć wąskie okno i
        // zgubić kropkę na cały obrót. Łuk zawsze łapie każde przejście,
        // niezależnie od jittera, i obsługuje też zawinięcie 360°→0° na
        // starcie nowego cyklu.
        function crossed(angle, from, to) {
            return from <= to ? (angle >= from && angle <= to) : (angle >= from || angle <= to);
        }
        const start = Date.now();
        let prevRot = 0;
        setInterval(() => {
            const t = ((Date.now() - start) / 1000) % 5;
            sweepLabel.textContent = 'SWEEP ' + t.toFixed(1) + 'S';
            const rot = (t / 5) * 360;
            contacts.forEach((el, i) => {
                if (!el.classList.contains('is-detected') && crossed(contactAngles[i], prevRot, rot)) detect(el);
            });
            prevRot = rot;
        }, 100);
    }

    // Przechył 3D + poświata pod kursorem - ten sam wzorzec co karty planów
    // (assets/js/cennik.js attachChromaTilt), przepisany tu lokalnie zamiast
    // dociągać cały cennik.js dla jednej funkcji na stronie błędu.
    const radarPanel = document.getElementById('p404-radar-panel');
    if (!reducedMotion) {
        radarPanel.addEventListener('mousemove', (e) => {
            const r = radarPanel.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top) / r.height;
            const maxTilt = 6;
            radarPanel.style.setProperty('--mx', (px * 100) + '%');
            radarPanel.style.setProperty('--my', (py * 100) + '%');
            radarPanel.style.setProperty('--ry', ((px - 0.5) * 2 * maxTilt).toFixed(2) + 'deg');
            radarPanel.style.setProperty('--rx', ((0.5 - py) * 2 * maxTilt).toFixed(2) + 'deg');
        });
        radarPanel.addEventListener('mouseleave', () => {
            radarPanel.style.setProperty('--rx', '0deg');
            radarPanel.style.setProperty('--ry', '0deg');
        });
    }
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../assets/clear/clear.php';
