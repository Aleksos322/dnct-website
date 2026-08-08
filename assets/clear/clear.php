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
 *   $centerContent     (opcjonalnie, bool — true wycentrowuje $content pionowo,
 *                        false dla stron wielosekcyjnych jak landing page)
 *
 * Przykład:
 *   <?php
 *   $pageTitle = 'Cennik — DNCT Hosting';
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

$isLoggedIn = isset($_SESSION['authentik_username']);
$username   = $_SESSION['authentik_username'] ?? null;
$email      = $_SESSION['authentik_email'] ?? null;

// Domyślne wartości SEO — nadpisywane przez stronę wywołującą, jeśli ustawione wcześniej
$pageTitle       = $pageTitle       ?? 'DNCT Hosting | Serwery Minecraft, Project Zomboid, Vintage Story';
$metaDescription = $metaDescription ?? 'DNCT Hosting to serwery Minecraft, Project Zomboid, Vintage Story i innych gier z ochroną DDoS. Start od 3.99 PLN, szybkie NVMe i wsparcie 24/7.';
$ogImage         = $ogImage         ?? 'https://dnct.pl/assets/media/icons/LINKS.png';
$canonicalUrl    = $canonicalUrl    ?? ('https://dnct.pl' . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
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

    <link rel="icon" type="image/png" href="/assets/media/icons/favicon.png">
    <link rel="apple-touch-icon" href="/assets/media/icons/favicon.png">

    <link rel="stylesheet" href="/assets/navibar/navbar.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <script src="/assets/js/tailwind.js"></script>

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
</head>
<body class="bg-[#050505] text-[#f8f9fa] relative min-h-screen font-sans">

    <!-- NAVBAR -->
    <header id="main-header" class="relative z-50 w-full">
        <?php require_once __DIR__ . '/../navibar/navibar.php'; ?>
    </header>


    <!-- POWIADOMIENIA -->
    <?php require_once __DIR__ . '/notifications.php'; ?>

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

    <!-- TŁO ANIMOWANE -->
    <div class="header finisher-header fixed inset-0 pointer-events-none z-0"></div>

    <script src="/assets/js/finisher-header.es5.min.js" type="text/javascript"></script>
    <script type="text/javascript">
    new FinisherHeader({
      "count": 6,
      "size": { "min": 522, "max": 1008, "pulse": 0.1 },
      "speed": { "x": { "min": 0.01, "max": 0.05 }, "y": { "min": 0.01, "max": 0.05 } },
      "colors": {
        "background": "#050505",
        "particles": ["#4f7cfa", "#4f7cfa", "#885ef6", "#984ede", "#984ede", "#984ede", "#07ffd1", "#984ede", "#885ef6"]
      },
      "blending": "none",
      "opacity": { "center": 0.15, "edge": 0 },
      "skew": 0,
      "shapes": ["c"]
    });
    </script>
</body>
</html>