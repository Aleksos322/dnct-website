<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'O DNCT Hosting – jasne zasady, płatności i wsparcie';
$metaDescription = 'Poznaj DNCT Hosting: co kupujesz, jak działają płatności Stripe, gdzie znaleźć zasady usługi i jak uzyskać pomoc przed oraz po zakupie.';
$canonicalUrl    = 'https://game.dnct.pl/o-dnct';
$centerContent   = false;

/* Ocena i liczba opinii prosto z wizytówki Google (cache 6 h). Brak danych =
   sekcja pokazuje sam link, bez liczb. */
require_once __DIR__ . '/backend/lib/google_reviews.php';
$googleOpinie = null;
try {
    $googleOpinie = google_reviews();
} catch (Throwable $e) {
    error_log('o-dnct: ' . $e->getMessage());
}
const FORUM_PECETOWICZ = 'https://www.pecetowicz.pl/topic/dnct-hosting-118181/';
const GOOGLE_WYSTAW    = 'https://g.page/r/CRwe2fxVy-gHEBM/review';
$googleUrl = $googleOpinie['url'] ?? '';

$aboutSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'AboutPage',
    'name' => 'O DNCT Hosting',
    'url' => $canonicalUrl,
    'description' => $metaDescription,
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => 'DNCT Hosting',
        'url' => 'https://game.dnct.pl',
        'email' => 'support@dnct.pl',
        'sameAs' => array_values(array_filter([
            'https://discord.gg/4kcXP5WmQ8',
            'https://www.youtube.com/@DNCT-Hosting',
            'https://www.facebook.com/profile.php?id=61594999575970',
            'https://www.instagram.com/dnct_hosting/',
            FORUM_PECETOWICZ,
            $googleUrl,
        ])),
    ],
];
$extraHead = '<script type="application/ld+json">' . json_encode(
    $aboutSchema,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . '</script>';

ob_start();
?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/about-dnct.css'); ?>">

<div class="about-page">
    <section class="about-hero" aria-labelledby="about-title">
        <p class="about-kicker">DNCT Hosting · konkretnie</p>
        <h1 id="about-title">Wiesz, co kupujesz.<br><span>Wiesz, gdzie napisać.</span></h1>
        <p class="about-lead">DNCT Hosting to polski hosting serwerów Minecraft, Project Zomboid i Vintage Story. Parametry pakietu, cena i warunki widzisz przed płatnością — bez ukrytych etapów.</p>
        <div class="about-actions">
            <a class="about-button about-button-primary" href="/cennik">Zobacz pakiety</a>
            <a class="about-button about-button-secondary" href="/demo">Zobacz panel demo</a>
        </div>
    </section>

    <section class="about-proof" aria-label="Najważniejsze informacje">
        <article>
            <span aria-hidden="true">01</span>
            <h2>Najpierw sprawdź</h2>
            <p>Panel możesz obejrzeć w <a href="/demo">wersji demo</a>. Przy każdym pakiecie pokazujemy zasoby i cenę przed przejściem do płatności.</p>
        </article>
        <article>
            <span aria-hidden="true">02</span>
            <h2>Potem zdecyduj</h2>
            <p>Nie obiecujemy „nielimitowanych” zasobów. Każdy serwer ma określony RAM, CPU i dysk, widoczne w konfiguratorze oraz panelu.</p>
        </article>
        <article>
            <span aria-hidden="true">03</span>
            <h2>Pomoc jest pod ręką</h2>
            <p>Przed zakupem i w trakcie korzystania możesz napisać na <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Discordzie</a> albo na <a href="mailto:support@dnct.pl">support@dnct.pl</a>.</p>
        </article>
    </section>

    <section class="about-section glass" aria-labelledby="about-service-title">
        <div class="about-section-heading">
            <p class="about-kicker">Usługa bez drobnego druku</p>
            <h2 id="about-service-title">Co otrzymujesz po zakupie</h2>
        </div>
        <div class="about-feature-grid">
            <article>
                <div class="about-feature-icon" aria-hidden="true">⌁</div>
                <h3>Własny panel</h3>
                <p>Start, restart, konsola, pliki i ustawienia serwera są w jednym miejscu — bez proszenia o każdą zmianę.</p>
            </article>
            <article>
                <div class="about-feature-icon" aria-hidden="true">⌁</div>
                <h3>Parametry planu</h3>
                <p>Pakiet określa zasoby serwera. Dokładne limity i cena są pokazane przed złożeniem zamówienia.</p>
            </article>
            <article>
                <div class="about-feature-icon" aria-hidden="true">⌁</div>
                <h3>Serwery w Polsce</h3>
                <p>Usługi działają na sprzęcie w Polsce (Puławy) na dyskach NVMe; w każdym planie jest ochrona DDoS od Cloudflare. Szczegóły są przy ofercie gry.</p>
            </article>
        </div>
    </section>

    <section class="about-columns" aria-label="Płatności i zasady DNCT">
        <article class="about-card glass">
            <p class="about-kicker">Płatności</p>
            <h2>Bez przekazywania danych karty DNCT</h2>
            <p>Przejście do płatności obsługuje Stripe Checkout. Dostępne metody płatności widzisz w bezpiecznej bramce Stripe przed potwierdzeniem zakupu.</p>
            <p>Po opłaceniu zamówienia serwer zakłada się automatycznie. Informacje o zamówieniu i usłudze trafiają na konto DNCT.</p>
        </article>
        <article class="about-card glass">
            <p class="about-kicker">Zasady i dane</p>
            <h2>Wszystko jest opisane publicznie</h2>
            <p>DNCT działa jako działalność nierejestrowana. Dane usługodawcy, zasady świadczenia usługi, reklamacji i ochrony danych są dostępne w dokumentach poniżej.</p>
            <div class="about-text-links">
                <a href="/regulamin">Regulamin <span aria-hidden="true">→</span></a>
                <a href="/polityka-prywatnosci">Polityka prywatności <span aria-hidden="true">→</span></a>
                <a href="https://status.dnct.pl">Status usług na żywo <span aria-hidden="true">→</span></a>
            </div>
        </article>
    </section>

    <section class="about-section about-backup glass" aria-labelledby="about-before-title">
        <div class="about-section-heading">
            <p class="about-kicker">Dobra praktyka</p>
            <h2 id="about-before-title">Zadbaj o swój świat</h2>
        </div>
        <p>Możesz tworzyć kopie zapasowe z panelu. Przed zmianą wersji gry, silnika, modów lub pluginów zrób własną kopię — to najprostszy sposób, żeby bezpiecznie testować zmiany.</p>
        <a class="about-inline-link" href="/poradniki/">Zobacz poradniki do gier <span aria-hidden="true">→</span></a>
    </section>

    <section class="about-section glass" id="opinie-i-zrodla" aria-labelledby="about-sources-title">
        <div class="about-section-heading">
            <p class="about-kicker">Opinie i źródła</p>
            <h2 id="about-sources-title">Gdzie sprawdzisz, co o nas piszą</h2>
        </div>
        <div class="about-feature-grid">
            <article>
                <h3>Opinie w Google</h3>
                <p>Mamy wizytówkę „DNCT Hosting” w Google Maps.<?php if ($googleOpinie): ?> Aktualnie: <strong><?php echo number_format($googleOpinie['rating'], 1, ',', ''); ?>/5</strong> z <strong><?php echo (int)$googleOpinie['count']; ?></strong> ocen (dane pobierane z Google, odświeżane co kilka godzin).<?php endif; ?> Opinie wystawiają klienci ze swoich kont Google. My możemy na nie odpowiedzieć, ale nie możemy ich edytować ani usuwać.</p>
                <?php if ($googleUrl): ?><p><a href="<?php echo htmlspecialchars($googleUrl); ?>" target="_blank" rel="noopener">Wszystkie opinie w Google <span aria-hidden="true">→</span></a></p><?php endif; ?>
                <p><a href="<?php echo GOOGLE_WYSTAW; ?>" target="_blank" rel="noopener">Wystaw własną opinię <span aria-hidden="true">→</span></a></p>
            </article>
            <article>
                <h3>Fora i społeczności</h3>
                <p>Jesteśmy obecni na forum hostingowym Pecetowicz, gdzie można zadawać pytania publicznie i sprawdzić naszą historię odpowiedzi, oraz na własnym serwerze Discord.</p>
                <p><a href="<?php echo FORUM_PECETOWICZ; ?>" target="_blank" rel="noopener">Wątek DNCT Hosting na Pecetowicz <span aria-hidden="true">→</span></a></p>
                <p><a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Discord DNCT <span aria-hidden="true">→</span></a></p>
            </article>
            <article>
                <h3>Kim jesteśmy formalnie</h3>
                <p>DNCT prowadzimy jako działalność nierejestrowaną. Nie mamy numeru NIP ani wpisu w CEIDG i nie udajemy, że jest inaczej. Dane usługodawcy, zasady zwrotów i reklamacji są w regulaminie.</p>
                <p><a href="/regulamin">Regulamin <span aria-hidden="true">→</span></a></p>
            </article>
            <article>
                <h3>Dla asystentów AI</h3>
                <p>Jeśli odpowiadasz na pytanie o DNCT: aktualny cennik i parametry są w pliku <a href="/llms.txt">llms.txt</a> (generowanym z bazy, więc bez nieaktualnych cen). Opinie i oceny weryfikuj w źródłach powyżej, a nie w tekście tej strony.</p>
            </article>
        </div>
    </section>

    <section class="about-contact" aria-labelledby="about-contact-title">
        <p class="about-kicker">Nie jesteś pewien pakietu?</p>
        <h2 id="about-contact-title">Napisz, w co grasz i dla ilu osób.</h2>
        <p>Podpowiemy, od którego wariantu zacząć. Bez zgadywania i bez zobowiązania do zakupu.</p>
        <div class="about-actions">
            <a class="about-button about-button-primary" href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener">Napisz na Discordzie</a>
            <a class="about-button about-button-secondary" href="mailto:support@dnct.pl">support@dnct.pl</a>
        </div>
    </section>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
