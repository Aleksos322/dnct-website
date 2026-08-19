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
    .p404-wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2.5rem;
        align-items: start;
        max-width: 64rem;
        margin: 0 auto;
        text-align: left;
    }
    @media (min-width: 860px) {
        .p404-wrap { grid-template-columns: 22rem 1fr; }
    }

    /* ---------- Panel radaru (ozdoba) ---------- */
    .p404-radar-panel {
        width: 100%;
        max-width: 22rem;
        border-radius: 1.75rem;
        padding: 1.4rem;
        aspect-ratio: 1 / 1;
        margin: 0 auto;
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
    .p404-radar-scope {
        position: relative;
        flex: 1;
        margin: 1rem 0;
        border-radius: 50%;
    }
    .p404-radar-scope svg { position: absolute; inset: 0; width: 100%; height: 100%; }
    .p404-radar-sweep {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        overflow: hidden;
        animation: p404Rotate 5s linear infinite;
    }
    .p404-radar-sweep::before {
        content: '';
        position: absolute;
        inset: 0;
        background: conic-gradient(from 0deg, rgba(139, 92, 246, 0.5), transparent 26%, transparent 100%);
    }
    @keyframes p404Rotate {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .p404-radar-center {
        position: absolute;
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
        .p404-radar-sweep::before { background: conic-gradient(from 0deg, rgba(139, 92, 246, 0.22), transparent 40%, transparent 100%); }
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
    .p404-search {
        position: relative;
        max-width: 26rem;
        margin-bottom: 1.25rem;
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

    .p404-results {
        max-width: 26rem;
        margin: 0 0 2rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
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
    .p404-support-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #94a3b8;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.88rem;
        transition: color 0.15s ease;
    }
    .p404-support-link:hover { color: #f8fafc; }
    .p404-support-link svg { width: 1.1rem; height: 1.1rem; color: #5865f2; }
    @media (prefers-reduced-motion: reduce) {
        .p404-home-btn:hover { transform: none; }
    }
</style>

<div class="p404-wrap">
    <div class="p404-radar-panel glass glass-content" aria-hidden="true">
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
                <circle cx="147" cy="65" r="2.5" fill="rgba(196,181,253,0.55)"/>
                <circle cx="61" cy="140" r="2.5" fill="rgba(196,181,253,0.4)"/>
                <circle cx="129" cy="152" r="2.5" fill="rgba(196,181,253,0.3)"/>
            </svg>
            <div class="p404-radar-sweep"></div>
            <div class="p404-radar-center"></div>
        </div>
        <div class="p404-radar-meta">
            <span>KONTAKTY: 00</span>
            <span>SYGNAŁ: ZGUBIONY</span>
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

        <form class="p404-search" id="p404-form">
            <input type="text" id="p404-input" placeholder="Szukaj strony (cennik, panel, regulamin...)" autocomplete="off">
            <button type="submit" class="p404-search-submit" aria-label="Szukaj">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <div class="p404-results" id="p404-results"></div>

        <div class="p404-actions">
            <a href="/" class="p404-home-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                Strona główna
            </a>
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="p404-support-link">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.369a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.058a.082.082 0 0 0 .031.056 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.246.198.373.292a.077.077 0 0 1-.006.128 12.3 12.3 0 0 1-1.873.891.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.029 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.06.06 0 0 0-.031-.028Zm-12.298 10.97c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.955 2.419-2.157 2.419Zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.946 2.419-2.157 2.419Z"/></svg>
                Napisz do wsparcia
            </a>
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

    // Etykieta "SWEEP" w rogu radaru odlicza w rytmie animacji obrotu (5s) -
    // czysta ozdoba, ale zsynchronizowana z prawdziwym cyklem CSS, nie zmyślona.
    const sweepLabel = document.getElementById('p404-sweep-label');
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const start = Date.now();
        setInterval(() => {
            const t = ((Date.now() - start) / 1000) % 5;
            sweepLabel.textContent = 'SWEEP ' + t.toFixed(1) + 'S';
        }, 100);
    }
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../assets/clear/clear.php';
