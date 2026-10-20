<?php
/**
 * assets/clear/clear.php
 *
 * Wspólny layout dla wszystkich stron DNCT (tło, navbar, powiadomienia, stopka, SEO).
 *
 * Strona wywołująca ten plik ustawia PRZED require:
 *   $pageTitle         (opcjonalnie, domyślnie 'DNCT Hosting | ...')
 *   $metaDescription   (opcjonalnie, zalecane dla SEO)
 *   $ogImage           (opcjonalnie, pełny URL obrazka do udostępniania)
 *   $canonicalUrl      (opcjonalnie, pełny URL bieżącej strony)
 *   $centerContent     (opcjonalnie, bool - true wycentrowuje $content pionowo,
 *                        false dla stron wielosekcyjnych jak landing page)
 *   $extraHead         (opcjonalnie, surowy HTML/JSON-LD doklejany w <head> PO
 *                        sitewide Organization/WebSite - np. Product/FAQPage
 *                        na cenniku. Budować z tych samych danych co widoczna
 *                        treść, nigdy z osobno wymyślonych - Google i AI boty
 *                        porównują schema z tym, co faktycznie widać.)
 *
 * Przykład:
 *   <?php
 *   $pageTitle = 'Cennik - DNCT Hosting';
 *   $metaDescription = 'Zobacz pełną ofertę hostingu Minecraft, Zomboid i Vintage Story.';
 *   ob_start();
 *   ?>
 *   <h1>Treść strony</h1>
 *   <?php
 *   $content = ob_get_clean();
 *   require __DIR__ . '/assets/clear/clear.php';
 */

// Wspólny bootstrap: sesja, konfiguracja z .env i biblioteki backendu.
// Sesję zakłada bootstrap, bo wykrywa HTTPS zamiast zakładać go na sztywno -
// wcześniejsze `secure => true` zjadało ciasteczko sesji przy dostępie po HTTP.
require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

// Wylogowanie: /index.php?logout=1 (zaszłość - odnośniki w serwisie).
// Właściwe wylogowanie robi /wyloguj, bo musi zakończyć także sesję
// SSO w Authentiku, a nie tylko naszą sesję PHP.
if (isset($_GET['logout'])) {
    header('Location: /wyloguj');
    exit();
}

// Link twórcy (?kod=NAZWA na dowolnej stronie) - zapamiętujemy kod na 30 dni,
// formularz zamówienia wpisze go sam. Sprawdzenie ważności dopiero przy zakupie.
if (isset($_GET['kod']) && !headers_sent()) {
    $kodLinku = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$_GET['kod']) ?? '');
    if ($kodLinku !== '' && strlen($kodLinku) <= 64) {
        setcookie('dnct_kod', $kodLinku, ['expires' => time() + 30 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax']);
        $_COOKIE['dnct_kod'] = $kodLinku;
    }
}

$isLoggedIn = isset($_SESSION['authentik_username']);
$username   = $_SESSION['authentik_username'] ?? null;
$email      = $_SESSION['authentik_email'] ?? null;

// Domyślne wartości SEO - nadpisywane przez stronę wywołującą, jeśli ustawione wcześniej
$pageTitle       = $pageTitle       ?? 'DNCT Hosting | Serwery Minecraft, Project Zomboid, Vintage Story';
$metaDescription = $metaDescription ?? 'Polski hosting serwerów Minecraft, Project Zomboid i Vintage Story z ochroną DDoS. Serwer startuje zaraz po płatności, od 3,99 zł za tydzień.';
$ogImage         = $ogImage         ?? 'https://game.dnct.pl/assets/media/icons/LINKS.png';
// Kanoniczny adres na hoście, który naprawdę serwuje stronę (ten layout renderuje
// też rejestracja na account.dnct.pl); nieznany host -> sklep gier.
$canonicalHost   = in_array($_SERVER['HTTP_HOST'] ?? '', ['game.dnct.pl', 'account.dnct.pl', 'web.dnct.pl'], true) ? $_SERVER['HTTP_HOST'] : 'game.dnct.pl';
$canonicalUrl    = $canonicalUrl    ?? ('https://' . $canonicalHost . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
$centerContent   = $centerContent   ?? true;
$content         = $content         ?? '';

// UWAGA: bez klasy min-h-* z Tailwinda. Selektor klasowy (0,1,0) zawsze wygrywa
// z selektorem elementu `main` (0,0,1) niezależnie od kolejności, więc utility
// Tailwinda przykrywało wysokość ustawianą w layout.css. Wysokość trzyma .page-main.
$mainClasses = 'page-main flex-grow flex flex-col relative z-10 pt-32';
if ($centerContent) {
    $mainClasses .= ' items-center justify-center text-center p-6';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <?php /* Eliminuje bialy blysk PRZED wczytaniem jakiegokolwiek CSS - przy
             zwyklym ladowaniu trwa to niezauwazalne kilka ms, ale animowane
             przejscie miedzy stronami (@view-transition nizej w layout.css)
             potrafi zamrozic ten moment w widoczna klatke. To ustawienie
             dziala na poziomie przegladarki (domyslne tlo canvasu), zanim
             jeszcze cokolwiek z CSS/Tailwind CDN zdazy sie wykonac. */ ?>
    <meta name="color-scheme" content="dark">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="author" content="DNCT Hosting">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">

    <?php /* JSON-LD sitewide. Adresy zawsze wskazuja na game.dnct.pl (prod),
             tak samo jak domyslny $canonicalUrl wyzej - spojna tozsamosc
             marki niezaleznie od tego, czy strona jest ogladana na becie. */ ?>
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'DNCT Hosting',
        // Krótka nazwa marki - wizytówka Google nazywa się "DNCT" i na "dnct"
        // (bez słowa "hosting", za które licytują reklamy OVH itd.) ma stać na górze.
        'alternateName' => ['DNCT', 'DNCT.pl'],
        'url'      => 'https://game.dnct.pl',
        'logo'     => 'https://game.dnct.pl/assets/media/icons/LINKS.png',
        'telephone' => '+48 536 180 706',
        'email'    => 'support@dnct.pl',
        'areaServed' => 'PL',
        'sameAs'   => [
            'https://discord.gg/4kcXP5WmQ8',
            // Wizytówka Google (Knowledge Graph /g/11zy3hs6c7) - łączy stronę z profilem firmy.
            'https://www.google.com/search?kgmid=/g/11zy3hs6c7',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => 'DNCT Hosting',
        'url'      => 'https://game.dnct.pl',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php if (!empty($extraHead)): ?>
    <?php echo $extraHead; ?>
    <?php endif; ?>

    <link rel="icon" type="image/png" href="/assets/media/icons/favicon.png">
    <link rel="apple-touch-icon" href="/assets/media/icons/favicon.png">

    <?php /* asset() dokleja czas modyfikacji pliku - bez tego przeglądarki
             trzymają stary arkusz po wgraniu poprawki. Patrz backend/lib/assets.php. */ ?>
    <link rel="stylesheet" href="<?php echo asset('/assets/navibar/navbar.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/notification-bell.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/layout.css'); ?>">
    <?php /* System dzwiekow - syntezowany Web Audio API, zaladowany site-wide
             (nie tylko dla zalogowanych/dzwonka), zeby toasty gosci (np. po
             rejestracji) tez mialy dzwiek. Celowo BEZ defer: notifications.php
             odpala inline skrypt (initToast na flash-toastach) zaraz w ciele
             strony i musi zastac window.DnctSound juz gotowe - z defer
             wykonalby sie pozniej niz ten inline skrypt i pierwszy toast po
             wejsciu na strone zostalby bez dzwieku. */ ?>
    <script src="<?php echo asset('/assets/js/sound.js'); ?>"></script>

    <style>
        body {
            background-color: #050505;
            color: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        a { text-decoration: none; }
    </style>
    <?php /* Tailwind zbudowany na serwerze (backend/tailwind/build.sh), nie skrypt
             Play CDN - ten ważył 400 KB i blokował wyświetlanie na telefonie.
             Link stoi NA KOŃCU <head>, dokładnie tam, gdzie skrypt doklejał swój
             <style>: kolejność kaskady (Tailwind po naszych arkuszach i po
             stylu inline wyżej) zostaje bez zmian. Po dodaniu nowych klas
             Tailwinda w PHP/JS trzeba przebudować plik. */ ?>
    <link rel="stylesheet" href="<?php echo asset('/assets/css/tailwind.css'); ?>">
</head>
<body class="bg-[#050505] text-[#f8f9fa] relative min-h-screen font-sans">

    <!-- NAVBAR -->
    <header id="main-header" class="relative z-50 w-full">
        <?php require_once __DIR__ . '/../navibar/navibar.php'; ?>
    </header>


    <?php $nativeMessaging = require __DIR__ . '/messaging-native.php'; ?>
    <!-- POWIADOMIENIA -->
    <?php if (!$nativeMessaging) require_once __DIR__ . "/notifications.php"; ?>

    <!-- CZAT SUPPORTU -->
    <?php if (!$nativeMessaging) require_once __DIR__ . '/support-chat.php'; ?>
    <?php if (!$nativeMessaging) require_once __DIR__ . "/support-ticket-panel.php"; ?>
    <?php if (!$nativeMessaging) require_once __DIR__ . '/direct-chat.php'; ?>

    <!-- TREŚĆ STRONY (wstrzykiwana przez podstrony) -->
    <main class="<?php echo $mainClasses; ?>">
        <?php echo $content; ?>
    </main>

    <!-- STOPKA -->
    <?php require_once __DIR__ . '/footer.php'; ?>

    <!-- Dopycha stopkę tak, żeby wymagała min. 185px scrolla poniżej pierwszego ekranu -->
    <script type="text/javascript">
    (function () {
        var HIDDEN_SCROLL_PX = 185;

        function adjustFooterOffset() {
            var main = document.querySelector('main');
            var footer = document.querySelector('footer');
            if (!main || !footer) return;

            main.style.paddingBottom = '0px';

            var footerTop = footer.getBoundingClientRect().top + window.scrollY;
            var desiredMinTop = window.innerHeight + HIDDEN_SCROLL_PX;

            if (footerTop < desiredMinTop) {
                main.style.paddingBottom = (desiredMinTop - footerTop) + 'px';
            }
        }

        // Tailwind CDN generuje CSS asynchronicznie (JIT po stronie klienta),
        // więc liczymy nie tylko na 'load', ale też z małym opóźnieniem i
        // podwójną klatką animacji, żeby mieć pewność że style już są nałożone.
        function scheduleAdjust() {
            requestAnimationFrame(function () {
                requestAnimationFrame(adjustFooterOffset);
            });
        }

        window.addEventListener('load', scheduleAdjust);
        window.addEventListener('resize', adjustFooterOffset);
        setTimeout(scheduleAdjust, 300); // fallback, gdyby Tailwind CDN dogrywał style później
    })();
    </script>

    <!-- ZGODA NA COOKIES -->
    <!-- Na KAŻDEJ podstronie, bo zgoda na analitykę musi być zebrana zanim
         powstanie ciasteczko i od każdego odwiedzającego - nie tylko od osób,
         które przechodzą przez rejestrację. -->
    <?php require_once __DIR__ . '/cookie-banner.php'; ?>

    <!-- SPOKOJNY SCROLL -->
    <?php /* Lenis wgrana lokalnie (assets/js/lenis.min.js), tak jak reszta
             skryptów strony - nie z CDN. Szczegoly i uzasadnienie w
             assets/js/smooth-scroll.js. */ ?>
    <script src="<?php echo asset('/assets/js/lenis.min.js'); ?>"></script>
    <script src="<?php echo asset('/assets/js/smooth-scroll.js'); ?>"></script>

    <!-- TŁO ANIMOWANE -->
    <div class="header finisher-header fixed inset-0 pointer-events-none z-0" aria-hidden="true"></div>

    <script src="/assets/js/finisher-header.es5.min.js" type="text/javascript"></script>
    <?php /* Rozsynchronizowanie przebłysku na taflach .glass - bez tego
             wszystkie błyskają w tej samej sekundzie. */ ?>
    <script src="<?php echo asset('/assets/js/glass-sheen.js'); ?>" defer></script>
    <script type="text/javascript">
    /* 20 klatek/s zamiast 60, a prędkość x3 - ruch wygląda tak samo jak w panelu
       (tam 60 kl/s i prędkość 0.01-0.05). Każda klatka tła zmusza przeglądarkę
       do przeliczenia rozmycia wszystkich tafli .glass, więc pełne 60 kl/s
       kosztowało telefon ~1,9 s procesora na każde 8 s (pomiar 2026-10-07);
       przy 20 kl/s o ~40% mniej. */
    /* Na telefonie (wąski ekran albo dotyk) tło rysuje kilka klatek i STOI -
       decyzja 2026-10-07 dla wyniku PageSpeed. Po zmianie rozmiaru okna (np.
       chowający się pasek adresu) biblioteka czyści canvas, więc wtedy
       dorysowujemy kilka klatek od nowa. */
    var dnctTloStoi = window.matchMedia('(max-width: 768px), (pointer: coarse)').matches;
    (function () {
      var P = FinisherHeader.prototype, rysuj = P.an;
      P.an = function () {
        if (dnctTloStoi && (this._dnctN = (this._dnctN || 0) + 1) > 3) { this._dnctStop = true; return; }
        var t = performance.now();
        if (this._dnctT && t - this._dnctT < 48) { requestAnimationFrame(this.an.bind(this)); return; }
        this._dnctT = t;
        rysuj.call(this);
      };
    })();
    var dnctTlo = new FinisherHeader({
      "count": 6,
      "size": { "min": 522, "max": 1008, "pulse": 0.1 },
      "speed": { "x": { "min": 0.03, "max": 0.15 }, "y": { "min": 0.03, "max": 0.15 } },
      "colors": {
        "background": "#050505",
        "particles": ["#4f7cfa", "#4f7cfa", "#885ef6", "#984ede", "#984ede", "#984ede", "#07ffd1", "#984ede", "#885ef6"]
      },
      "blending": "none",
      "opacity": { "center": 0.15, "edge": 0 },
      "skew": 0,
      "shapes": ["c"]
    });
    if (dnctTloStoi) {
      window.addEventListener('resize', function () {
        setTimeout(function () {
          dnctTlo._dnctN = 0;
          if (dnctTlo._dnctStop) { dnctTlo._dnctStop = false; requestAnimationFrame(dnctTlo.an.bind(dnctTlo)); }
        }, 250);
      });
    }
    </script>

    <!-- OBECNOŚĆ (heartbeat) -->
    <?php /* Co ~20s zgłasza "jestem tu" do api/aktywnosc/ping.php - backend/lib/aktywnosc.php.
             Osobny CSRF_TOKEN (nie ten z notifications.php - zamknięty w tamtejszym IIFE). */ ?>
    <script type="text/javascript">
    (function () {
        const PING_MS = 20000;
        const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;

        function ping() {
            fetch('/api/aktywnosc/ping.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({
                    strona: location.pathname,
                    widoczna: document.visibilityState === 'visible',
                }),
                keepalive: true,
            }).catch(function () {});
        }

        ping();
        setInterval(ping, PING_MS);
        document.addEventListener('visibilitychange', ping);

        // sendBeacon zamiast fetch: strona zamyka się TERAZ, fetch (nawet
        // keepalive) nie ma gwarancji dokończenia. sendBeacon nie ustawia
        // nagłówków, więc csrf_token jedzie w body - csrf_check_request()
        // czyta go stamtąd jako fallback. Content-Type: application/json
        // wymaga Bloba (string dałby text/plain, a request_payload() sparsuje
        // JSON tylko przy application/json).
        window.addEventListener('pagehide', function () {
            var blob = new Blob([JSON.stringify({
                strona: location.pathname,
                widoczna: false,
                csrf_token: CSRF_TOKEN,
            })], { type: 'application/json' });
            navigator.sendBeacon('/api/aktywnosc/ping.php', blob);
        });
    })();
    </script>

    <?php /* Synchronizacja sesji między kartami: odświeżenie tylko po zmianie stanu. */ ?>
    <script type="text/javascript">
    (function () {
        var initialAuthenticated = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        var requestInProgress = false;

        function syncSessionState() {
            if (requestInProgress || document.visibilityState === 'hidden') {
                return;
            }

            requestInProgress = true;
            fetch('/check_session?t=' + Date.now(), {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (session) {
                    if (session && Boolean(session.authenticated) !== initialAuthenticated) {
                        window.location.reload();
                    }
                })
                .catch(function () {})
                .then(function () {
                    requestInProgress = false;
                });
        }

        syncSessionState();
        setInterval(syncSessionState, 5000);
        document.addEventListener('visibilitychange', syncSessionState);
        window.addEventListener('focus', syncSessionState);
    })();
    </script>
</body>
</html>
