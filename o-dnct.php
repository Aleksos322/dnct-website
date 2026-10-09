<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

$pageTitle       = 'O DNCT Hosting – jasne zasady, płatności i wsparcie';
$metaDescription = 'Poznaj DNCT Hosting: co kupujesz, jak działają płatności Stripe, gdzie znaleźć zasady usługi i jak uzyskać pomoc przed oraz po zakupie.';
$canonicalUrl    = 'https://game.dnct.pl/o-dnct';
$centerContent   = false;

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
        'sameAs' => ['https://discord.gg/4kcXP5WmQ8'],
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
                <p>Usługi działają na sprzęcie w Polsce; w każdym planie jest ochrona DDoS. Szczegóły są przy ofercie gry.</p>
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
