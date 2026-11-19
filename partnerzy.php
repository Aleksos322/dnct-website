<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle = 'Program dla twórców - DNCT Hosting';
$metaDescription = 'Nagrywasz albo streamujesz? Mniejsi twórcy dostają rangę na Discordzie, do 30% zniżki dla siebie i do 10% dla widzów. Więksi YouTuberzy mogą dostać serwer po rozmowie.';
$canonicalUrl = 'https://game.dnct.pl/partnerzy';
$centerContent = false;
$csrfToken = csrf_token();

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">
<style>
    .ptn-zalety { display:grid; grid-template-columns:repeat(auto-fit, minmax(14rem, 1fr)); gap:1rem; margin:1.5rem 0 2rem; }
    .ptn-zalety div { padding:1.1rem 1.2rem; border:1px solid rgba(139,92,246,.35); border-radius:1rem; background:rgba(22,17,36,.55); }
    .ptn-zalety strong { display:block; font-size:1.9rem; line-height:1.1; color:#c4b5fd; }
    .ptn-zalety span { color:#cbd5e1; font-size:.92rem; }
    .ptn-poziomy { display:grid; grid-template-columns:repeat(auto-fit, minmax(17rem, 1fr)); gap:1rem; margin:1.5rem 0 2rem; }
    .ptn-poziomy section { padding:1.2rem 1.3rem; border:1px solid rgba(255,255,255,.09); border-radius:1rem; background:rgba(255,255,255,.04); backdrop-filter:blur(14px); }
    .ptn-poziomy h3 { margin:0 0 .2rem; font-size:.72rem; font-weight:900; letter-spacing:.14em; text-transform:uppercase; color:#a78bfa; }
    .ptn-poziomy p { margin:.2rem 0 .8rem; color:#e5e7eb; font-weight:700; }
    .ptn-poziomy ul { margin:0; padding-left:1.1rem; color:#cbd5e1; font-size:.94rem; }
    .ptn-form label { display:block; margin:1rem 0 .4rem; font-weight:700; color:#e5e7eb; }
    .ptn-form input, .ptn-form textarea { width:100%; padding:.8rem; border:1px solid #4c3a6f; border-radius:.6rem; background:#161124; color:#fff; font:inherit; }
    .ptn-form textarea { min-height:7rem; resize:vertical; }
    .ptn-form .field-error { color:#fca5a5; font-size:.9rem; margin-top:.35rem; }
    .ptn-form button { margin-top:1.25rem; padding:.8rem 1.2rem; border:0; border-radius:.6rem; background:#8b5cf6; color:#fff; font-weight:700; cursor:pointer; }
    .ptn-form button:disabled { opacity:.65; cursor:wait; }
    .ptn-form .form-status { margin-top:1rem; padding:.8rem; border-radius:.6rem; }
    .ptn-form .form-status[hidden] { display:none; }
    .ptn-form .form-status.success { background:#153b2a; color:#bbf7d0; }
    .ptn-form .form-status.error { background:#4a1d28; color:#fecdd3; }
</style>

<article class="legal glass glass-content" style="border-radius:22px;padding:clamp(1.25rem,4vw,3rem);max-width:900px;margin:0 auto 4rem;">
    <h1>Program dla twórców</h1>
    <p>Nagrywasz albo streamujesz? Dostajesz własny kod zniżkowy dla siebie i swoich widzów. Program dopiero się rozkręca, więc na razie wygląda tak:</p>

    <div class="ptn-zalety">
        <div><strong>do -30%</strong><span>dla Ciebie na Twoje serwery</span></div>
        <div><strong>do -10%</strong><span>dla Twoich widzów z Twoim kodem</span></div>
        <div><strong>Ranga</strong><span>twórcy na naszym Discordzie</span></div>
    </div>

    <div class="ptn-poziomy">
        <section>
            <h3>Mniejsi twórcy</h3>
            <p>Ranga i zniżki</p>
            <ul>
                <li>ranga twórcy na Discordzie DNCT,</li>
                <li>do 30% zniżki na Twoje serwery,</li>
                <li>do 10% zniżki dla widzów z Twoim kodem.</li>
            </ul>
        </section>
        <section>
            <h3>Więksi YouTuberzy</h3>
            <p>Serwer po rozmowie</p>
            <ul>
                <li>możliwy serwer od nas na czas współpracy,</li>
                <li>warunki ustalamy indywidualnie,</li>
                <li>zgłoś się formularzem, odezwiemy się mailowo.</li>
            </ul>
        </section>
    </div>

    <h2>Jak to działa</h2>
    <ol>
        <li>Wypełniasz formularz niżej. Patrzymy na kanał i odzywamy się mailowo.</li>
        <li>Dostajesz kod (np. <code>TWOJNICK</code>) i link <code>game.dnct.pl/?kod=TWOJNICK</code>. Z linku kod wpisuje się sam.</li>
        <li>Widz wpisuje kod przy pierwszym zamówieniu i płaci mniej (do 10%).</li>
        <li>Ty dostajesz rangę na Discordzie i własną zniżkę (do 30%). Większym kanałom proponujemy rozmowę o serwerze.</li>
    </ol>

    <h2>Zasady</h2>
    <ul>
        <li>Kod działa tylko przy pierwszym zakupie na danym koncie. Klient, który już coś u nas kupił, nie dostanie zniżki i nie zostanie przypisany.</li>
        <li>Klient należy do twórcy, którego kod wpisał jako pierwszy.</li>
        <li>Nie wolno kupować z własnego kodu, reklamować go w płatnych reklamach na hasło „dnct” ani wrzucać do serwisów z kuponami. Za to zamykamy kod.</li>
        <li>Każda strona może zakończyć współpracę w dowolnym momencie.</li>
    </ul>

    <h2>Zgłoś się</h2>
    <form id="ptn-form" class="ptn-form" action="/api/partnerzy/zgloszenie.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <label for="ptn-nazwa">Nazwa kanału albo nick *</label>
        <input id="ptn-nazwa" name="nazwa" type="text" maxlength="80" required>
        <p id="nazwa-error" class="field-error" aria-live="polite"></p>

        <label for="ptn-email">E-mail do kontaktu *</label>
        <input id="ptn-email" name="email" type="email" maxlength="160" required autocomplete="email"
               value="<?php echo htmlspecialchars((string)($_SESSION['authentik_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <p id="email-error" class="field-error" aria-live="polite"></p>

        <label for="ptn-kanal">Link do kanału *</label>
        <input id="ptn-kanal" name="kanal_url" type="url" maxlength="255" required placeholder="https://youtube.com/@... albo https://twitch.tv/...">
        <p id="kanal_url-error" class="field-error" aria-live="polite"></p>

        <label for="ptn-widzowie">Ilu masz widzów albo subskrybentów?</label>
        <input id="ptn-widzowie" name="widzowie" type="text" maxlength="60" placeholder="np. 2 tys. subów, 40 osób na live">
        <p id="widzowie-error" class="field-error" aria-live="polite"></p>

        <label for="ptn-wiadomosc">Coś jeszcze?</label>
        <textarea id="ptn-wiadomosc" name="wiadomosc" maxlength="1500" placeholder="Co nagrywasz, na jakim serwerze grasz, jak chcesz nas pokazać"></textarea>
        <p id="wiadomosc-error" class="field-error" aria-live="polite"></p>

        <button type="submit">Wyślij zgłoszenie</button>
        <div id="ptn-status" class="form-status" role="status" aria-live="polite" hidden></div>
    </form>
</article>
<script>
(() => {
    const form = document.getElementById('ptn-form');
    const status = document.getElementById('ptn-status');
    const button = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        form.querySelectorAll('.field-error').forEach(n => n.textContent = '');
        status.hidden = true;
        button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) {
                Object.entries(data.errors || {}).forEach(([field, message]) => {
                    const target = document.getElementById(field + '-error');
                    if (target) target.textContent = message;
                });
                throw new Error(data.message || 'Nie udało się wysłać zgłoszenia.');
            }
            form.reset();
            status.className = 'form-status success';
            status.textContent = data.message;
            status.hidden = false;
        } catch (error) {
            status.className = 'form-status error';
            status.textContent = error.message || 'Nie udało się wysłać zgłoszenia.';
            status.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
