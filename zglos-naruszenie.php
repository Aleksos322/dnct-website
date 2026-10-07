<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle = 'Zgłoś nielegalną treść - DNCT Hosting';
$metaDescription = 'Elektroniczny formularz zgłoszenia potencjalnie nielegalnych treści w usługach DNCT Hosting.';
$canonicalUrl = 'https://game.dnct.pl/zglos-naruszenie';
$centerContent = false;
$csrfToken = csrf_token();

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">
<style>
    .abuse-form label { display:block; margin:1rem 0 .4rem; font-weight:700; color:#e5e7eb; }
    .abuse-form input, .abuse-form select, .abuse-form textarea { width:100%; padding:.8rem; border:1px solid #4c3a6f; border-radius:.6rem; background:#161124; color:#fff; }
    .abuse-form textarea { min-height:8rem; resize:vertical; }
    .abuse-form .field-error { color:#fca5a5; font-size:.9rem; margin-top:.35rem; }
    .abuse-form .check-row { display:flex; align-items:flex-start; gap:.65rem; margin-top:1.25rem; }
    .abuse-form .check-row input { width:auto; margin-top:.3rem; }
    .abuse-form button { margin-top:1.25rem; padding:.8rem 1.2rem; border:0; border-radius:.6rem; background:#8b5cf6; color:#fff; font-weight:700; cursor:pointer; }
    .abuse-form button:disabled { opacity:.65; cursor:wait; }
    .abuse-form .form-status { margin-top:1rem; padding:.8rem; border-radius:.6rem; }
    .abuse-form .form-status[hidden] { display:none; }
    .abuse-form .form-status.success { background:#153b2a; color:#bbf7d0; }
    .abuse-form .form-status.error { background:#4a1d28; color:#fecdd3; }
</style>

<article class="legal glass glass-content" style="border-radius:22px;padding:clamp(1.25rem,4vw,3rem);max-width:900px;margin:0 auto 4rem;">
    <h1>Zgłoś potencjalnie nielegalną treść</h1>
    <p>Ten formularz służy do zgłaszania treści lub działań dostępnych w usługach DNCT Hosting, które mogą naruszać prawo. Nie wymaga konta DNCT ani Discorda.</p>
    <div class="info-box">
        <strong>W nagłej sytuacji zagrażającej życiu lub bezpieczeństwu zadzwoń pod 112.</strong>
        Nie przesyłaj przez formularz plików ani materiałów przedstawiających przemoc wobec dzieci. Podaj wyłącznie lokalizację i opis; właściwe służby mogą zażądać dalszych informacji.
    </div>

    <h2>Jak rozpatrujemy zgłoszenie</h2>
    <ol>
        <li>Potwierdzamy przyjęcie, gdy podasz adres e-mail.</li>
        <li>Oceniamy zgłoszenie na podstawie podanych informacji i prawa; możemy poprosić o doprecyzowanie.</li>
        <li>W razie potrzeby ograniczamy dostęp do treści lub usługi. Gdy jest to właściwe, przekazujemy uzasadnienie zgłaszającemu i klientowi, którego sprawa dotyczy.</li>
        <li>Od decyzji można złożyć zastrzeżenie, odpowiadając na wiadomość z numerem sprawy.</li>
    </ol>
    <p>DNCT Hosting nie prowadzi ogólnego monitorowania wszystkich treści klientów. Zgłoszenia są oceniane rzetelnie, obiektywnie i bez zbędnej zwłoki. Nie składamy deklaracji o sztywnym czasie reakcji, ponieważ zależy on od rodzaju sprawy i kompletności informacji.</p>

    <h2>Formularz zgłoszenia</h2>
    <p>Pola oznaczone <strong>*</strong> są wymagane, poza danymi kontaktowymi przy kategorii „Bezpieczeństwo dzieci”. Nie dodawaj zbędnych danych osobowych ani załączników.</p>
    <form id="abuse-report-form" class="abuse-form" action="/api/abuse/report.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="abuse-location">Dokładna lokalizacja treści lub usługi *</label>
        <input id="abuse-location" name="location" type="text" maxlength="1000" required aria-describedby="location-error" placeholder="np. URL, domena, adres IP:port albo identyfikator serwera">
        <p id="location-error" class="field-error" aria-live="polite"></p>

        <label for="abuse-category">Kategoria *</label>
        <select id="abuse-category" name="category" required aria-describedby="category-error">
            <option value="">Wybierz kategorię</option>
            <option value="copyright">Prawa autorskie lub znak towarowy</option>
            <option value="fraud">Oszustwo, phishing lub podszywanie się</option>
            <option value="malware">Złośliwe oprogramowanie lub atak</option>
            <option value="child_safety">Bezpieczeństwo dzieci</option>
            <option value="violence_hate">Przemoc, groźby lub mowa nienawiści</option>
            <option value="privacy">Prywatność lub dane osobowe</option>
            <option value="other">Inne potencjalne naruszenie prawa</option>
        </select>
        <p id="category-error" class="field-error" aria-live="polite"></p>

        <label for="abuse-explanation">Opis, dlaczego treść może być nielegalna *</label>
        <textarea id="abuse-explanation" name="explanation" minlength="20" maxlength="8000" required aria-describedby="explanation-error"></textarea>
        <p id="explanation-error" class="field-error" aria-live="polite"></p>

        <label for="abuse-legal-basis">Podstawa prawna lub powód zgłoszenia *</label>
        <textarea id="abuse-legal-basis" name="legal_basis" minlength="5" maxlength="2000" required aria-describedby="legal_basis-error" placeholder="np. naruszenie praw autorskich, phishing, bezprawne ujawnienie danych"></textarea>
        <p id="legal_basis-error" class="field-error" aria-live="polite"></p>

        <div id="contact-fields">
            <label for="abuse-name" data-required-label="Imię i nazwisko albo nazwa podmiotu">Imię i nazwisko albo nazwa podmiotu *</label>
            <input id="abuse-name" name="reporter_name" type="text" maxlength="160" required aria-describedby="reporter_name-error" autocomplete="name">
            <p id="reporter_name-error" class="field-error" aria-live="polite"></p>

            <label for="abuse-email" data-required-label="Adres e-mail do kontaktu">Adres e-mail do kontaktu *</label>
            <input id="abuse-email" name="reporter_email" type="email" maxlength="254" required aria-describedby="reporter_email-error" autocomplete="email">
            <p id="reporter_email-error" class="field-error" aria-live="polite"></p>
        </div>

        <label class="check-row" for="abuse-good-faith">
            <input id="abuse-good-faith" name="good_faith" type="checkbox" value="1" required>
            <span>Oświadczam w dobrej wierze, że informacje i zarzuty w zgłoszeniu są kompletne oraz zgodne z moją wiedzą. *</span>
        </label>
        <p id="good_faith-error" class="field-error" aria-live="polite"></p>
        <button type="submit">Wyślij zgłoszenie</button>
        <div id="abuse-form-status" class="form-status" role="status" aria-live="polite" hidden></div>
    </form>

    <h2>Alternatywny kontakt</h2>
    <p>Jeżeli formularz jest niedostępny, wyślij te same informacje na <a href="mailto:support@dnct.pl?subject=Abuse%20report">support@dnct.pl</a>. Zasady korzystania z usługi znajdują się w <a href="/regulamin#zgloszenia-nielegalnych-tresci-dsa">Regulaminie</a>.</p>
</article>
<script>
(() => {
    const form = document.getElementById('abuse-report-form');
    const category = document.getElementById('abuse-category');
    const contactFields = document.getElementById('contact-fields');
    const name = document.getElementById('abuse-name');
    const email = document.getElementById('abuse-email');
    const status = document.getElementById('abuse-form-status');
    const button = form.querySelector('button[type="submit"]');
    const updateContactRequirement = () => {
        const anonymous = category.value === 'child_safety';
        name.required = !anonymous;
        email.required = !anonymous;
        contactFields.querySelectorAll('label').forEach(label => {
            label.textContent = label.dataset.requiredLabel + (anonymous ? ' (opcjonalne)' : ' *');
        });
    };
    category.addEventListener('change', updateContactRequirement);
    updateContactRequirement();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        form.querySelectorAll('.field-error').forEach(node => node.textContent = '');
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
            updateContactRequirement();
            status.className = 'form-status success';
            status.textContent = data.message + ' Numer sprawy: ' + data.report_id + '.';
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
