<?php
/**
 * errors/404.php
 * Podepnij w .htaccess: ErrorDocument 404 /errors/404.php
 *
 * Wyszukiwarka niżej jest realna, nie ozdobna - filtruje na żywo statyczną
 * listę głównych podstron (JS, bez zapytania do serwera). Lista jest celowo
 * krótka i ręcznie utrzymywana - to nie ma zastępować mapy strony, tylko
 * odesłać kogoś z literówką w adresie w dobre miejsce w dwa kliknięcia.
 */

http_response_code(404);

$pageTitle       = 'Strona nie znaleziona (404) - DNCT Hosting';
$metaDescription = 'Ta strona nie istnieje lub została przeniesiona.';
$centerContent   = true;

ob_start();
?>
<style>
    /* Ten sam jezyk co "NA ŻYWO" w cenniku (assets/css/cennik.css .cnk-live-dot),
       tylko w kolorze danger zamiast success - tu sygnal faktycznie zgubiony. */
    .p404-signal {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
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

    .p404-search {
        position: relative;
        max-width: 26rem;
        margin: 0 auto 1.5rem;
    }
    .p404-search input {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 14px;
        padding: 0.85rem 1rem 0.85rem 2.75rem;
        color: #f8fafc;
        font-size: 0.95rem;
    }
    .p404-search input::placeholder { color: #64748b; }
    .p404-search input:focus {
        outline: none;
        border-color: rgba(139, 92, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.35);
    }
    .p404-search svg {
        position: absolute;
        left: 0.95rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1.1rem;
        height: 1.1rem;
        color: #64748b;
        pointer-events: none;
    }
    .p404-results {
        max-width: 26rem;
        margin: 0 auto 2rem;
        text-align: left;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .p404-results a {
        display: flex;
        align-items: baseline;
        gap: 0.6rem;
        padding: 0.6rem 0.9rem;
        border-radius: 10px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.88rem;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .p404-results a:hover,
    .p404-results a:focus-visible {
        background: rgba(139, 92, 246, 0.12);
        color: #f8fafc;
        outline: none;
    }
    .p404-results a span {
        color: #64748b;
        font-size: 0.76rem;
    }
    .p404-results-empty {
        padding: 0.6rem 0.9rem;
        color: #64748b;
        font-size: 0.85rem;
    }

    .p404-discord {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.85rem 1.4rem;
        border-radius: 14px;
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #fff;
        background: #5865f2; /* barwa Discorda - jedyne dozwolone uzycie, patrz DESIGN.md */
        box-shadow: 0 10px 26px rgba(88, 101, 242, 0.3);
        transition: filter 0.2s ease, transform 0.2s ease;
    }
    .p404-discord:hover { filter: brightness(1.12); transform: translateY(-2px); }
    .p404-discord svg { width: 1.15rem; height: 1.15rem; flex-shrink: 0; }
    @media (prefers-reduced-motion: reduce) {
        .p404-discord:hover { transform: none; }
    }
</style>

<div class="max-w-lg w-full mx-auto text-center">
    <p class="p404-signal">
        <span class="p404-dot" aria-hidden="true"></span>
        Sygnał zgubiony
    </p>
    <p class="text-8xl font-black gradient-text mb-4">404</p>
    <h1 class="text-3xl font-bold mb-4">Ups, nic tu nie ma</h1>
    <p class="text-gray-400 mb-8">
        Strona której szukasz nie istnieje, została przeniesiona, albo literówka wkradła się w adres.
    </p>

    <div class="p404-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <input type="text" id="p404-input" placeholder="Szukaj strony (cennik, panel, regulamin...)" autocomplete="off">
    </div>
    <div class="p404-results" id="p404-results"></div>

    <div class="flex flex-col md:flex-row justify-center gap-4">
        <a href="/" class="bg-gradient-main px-8 py-3 rounded-xl font-bold hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all">
            Strona główna
        </a>
        <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="p404-discord">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.317 4.369a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.058a.082.082 0 0 0 .031.056 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128c.126-.094.252-.192.372-.291a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.009c.12.099.246.198.373.292a.077.077 0 0 1-.006.128 12.3 12.3 0 0 1-1.873.891.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.029 19.84 19.84 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.06.06 0 0 0-.031-.028Zm-12.298 10.97c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.955 2.419-2.157 2.419Zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.418 2.157-2.418 1.211 0 2.176 1.094 2.157 2.418 0 1.334-.946 2.419-2.157 2.419Z"/></svg>
            Zgłoś na Discordzie
        </a>
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
        return routes.filter(r =>
            r.label.toLowerCase().includes(q) || r.kw.includes(q)
        );
    }

    render(routes);

    input.addEventListener('input', () => render(filter(input.value)));

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const first = filter(input.value)[0];
            if (first) window.location.href = first.href;
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../assets/clear/clear.php';
