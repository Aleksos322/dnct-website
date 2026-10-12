<?php
// Zabezpieczenie zmiennych, jeśli plik jest inkludowany w różnym środowisku
if (!isset($isLoggedIn)) {
    $isLoggedIn = isset($_SESSION['authentik_username']) || isset($_SESSION['user_id']);
}
if (!isset($username)) {
    if (isset($_SESSION['authentik_username'])) {
        $username = htmlspecialchars($_SESSION['authentik_username']);
    } elseif (isset($_SESSION['username'])) {
        $username = htmlspecialchars($_SESSION['username']);
    } else {
        $username = '';
    }
}
if (!isset($isAdmin)) {
    $isAdmin = false;
    if ($isLoggedIn) {
        if (isset($_SESSION['authentik_is_superuser']) && $_SESSION['authentik_is_superuser']) {
            $isAdmin = true;
        } elseif (isset($_SESSION['root_admin']) && $_SESSION['root_admin'] == 1) {
            $isAdmin = true;
        }
    }
}

// Pełny profil pozostaje prywatny niezależnie od avatara. Obraz pobiera
// lokalny proxy endpoint, który odczytuje aktualny avatar z account.dnct.pl.
$profileAvatarUrl = null;
$profileAvatarUsername = (string)($_SESSION['authentik_username'] ?? '');
if ($isLoggedIn && $profileAvatarUsername !== '') {
    $profileAvatarUrl = '/avatar/' . rawurlencode($profileAvatarUsername);
}

// Profil użytkownika jest wspólny dla usług DNCT.
$profileUrl = 'https://account.dnct.pl/';
/* ---------------------------------------------------------------------------
   PODŚWIETLENIE AKTYWNEJ POZYCJI

   Wcześniej "Strona Główna" miała klasę `text-white` wpisaną na sztywno, więc
   navbar twierdził, że jesteś na stronie głównej ZAWSZE - także w cenniku
   i w swoich serwerach. To jedyny element, po którym odwiedzający orientuje
   się, gdzie jest; mylący jest gorszy niż żaden.

   Dopasowanie idzie po ŚCIEŻCE, bez parametrów - /cennik i /cennik?x=1 to ta
   sama pozycja menu. Podstrony liczą się do rodzica (np. /twoje-serwery/cos),
   a `$extra` dokłada adresy, które należą do sekcji, choć mają inny prefiks:
   zamówienie i strona po płatności to nadal ścieżka zakupu, czyli "Cennik".
   --------------------------------------------------------------------------- */
$navbarPath = rtrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
if ($navbarPath === '') {
    $navbarPath = '/';
}

/** @param string[] $extra dodatkowe ścieżki należące do tej samej pozycji */
function navbar_active(string $href, array $extra = []): bool
{
    global $navbarPath;

    foreach (array_merge([$href], $extra) as $candidate) {
        $candidate = rtrim($candidate, '/');
        if ($candidate === '') {
            // Strona główna tylko przy dokładnym trafieniu - inaczej "/"
            // pasowałoby do każdego adresu w serwisie.
            if ($navbarPath === '/') {
                return true;
            }
            continue;
        }
        if ($navbarPath === $candidate || str_starts_with($navbarPath, $candidate . '/')) {
            return true;
        }
    }

    return false;
}

/** Klasy pozycji menu na komputerze. */
function navbar_class(bool $active): string
{
    return $active
        ? 'text-[#a78bfa] font-semibold uppercase tracking-[0.1em] transition-colors'
        : 'uppercase tracking-[0.1em] hover:text-white transition-colors';
}

/** Klasy pozycji w menu mobilnym. */
function navbar_class_mobile(bool $active): string
{
    return ($active ? 'text-[#a78bfa] font-semibold' : 'text-gray-400 hover:text-white')
        . ' uppercase tracking-[0.1em] py-2 block border-b border-white/5 transition-colors';
}

?>
<nav class="fixed top-0 left-0 right-0 w-full z-50 glass glass-bottom-edge">
    <div class="glass-content max-w-7xl mx-auto px-6 py-4">
        <div class="flex justify-between items-center">
            
            <!-- LOGO -->
            <div class="flex text-2xl font-extrabold tracking-tighter items-center gap-2 z-50">
                <img src="/assets/media/icons/logo_s.png" alt="DNCT Hosting - Logo" class="block w-8 h-8 rounded-lg object-cover">

                <div class="flex items-center text-white">
                    <span class="brand-dnct">DNCT</span><span class="brand-hosting ml-1">HOSTING</span>
                </div>
            </div>

            <!-- MENU DESKTOP -->
            <div class="nav-links-desktop space-x-8 font-medium text-gray-400">
                <a href="/" class="<?php echo navbar_class(navbar_active('/', [])); ?>"<?php echo navbar_active('/', []) ? ' aria-current="page"' : ''; ?>>Strona Główna</a>
                <a href="/cennik" class="<?php echo navbar_class(navbar_active('/cennik', ['/zamowienie', '/sukces'])); ?>"<?php echo navbar_active('/cennik', ['/zamowienie', '/sukces']) ? ' aria-current="page"' : ''; ?>>Cennik</a>
                <a href="/twoje-serwery" class="<?php echo navbar_class(navbar_active('/twoje-serwery', ['/oplaty'])); ?>"<?php echo navbar_active('/twoje-serwery', ['/oplaty']) ? ' aria-current="page"' : ''; ?>>Twoje serwery</a>
                <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="uppercase tracking-[0.1em] hover:text-white transition-colors">Pomoc</a>
            </div>

            <!-- PRZYCISKI / PROFIL -->
            <div class="flex items-center gap-4 z-50 ml-auto md:ml-0">
    <?php /* Przełącznik języka (lib/i18n.php). Linki, nie JS: działa bez skryptów,
             a wybór zostaje w ciasteczku dnct_lang. */ ?>
    <?php $navLang = function_exists('i18n_lang') ? i18n_lang() : 'pl'; ?>
    <div class="nav-lang" role="group" aria-label="Język / Language">
        <?php foreach (['pl' => 'PL', 'en' => 'EN', 'de' => 'DE'] as $kod => $etykieta): ?>
            <a href="<?php echo htmlspecialchars(i18n_link($kod)); ?>" hreflang="<?php echo $kod; ?>" lang="<?php echo $kod; ?>"
               class="nav-lang-opt<?php echo $navLang === $kod ? ' is-active' : ''; ?>"<?php echo $navLang === $kod ? ' aria-current="true"' : ''; ?>><?php echo $etykieta; ?></a>
        <?php endforeach; ?>
    </div>
    <?php /* Przelacznik dzwiekow - widoczny niezaleznie od logowania (goscie
             tez dostaja toasty, np. po rejestracji). Obsluga w sound.js. */ ?>
    <button type="button" id="dnct-sound-toggle" class="dnct-bell-btn dnct-sound-toggle-btn w-9 h-9 flex items-center justify-center rounded-full transition" aria-label="Włącz lub wyłącz dźwięki" aria-pressed="true" title="Dźwięki">
        <svg class="dnct-sound-icon-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
            <path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path>
            <path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path>
        </svg>
        <svg class="dnct-sound-icon-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
            <line x1="23" y1="9" x2="17" y2="15"></line>
            <line x1="17" y1="9" x2="23" y2="15"></line>
        </svg>
    </button>
    <?php if($isLoggedIn): ?>
        
        <!-- Główny zewnętrzny kontener (Cała pigułka z obramowaniem) -->
        <div class="profile-magic-wrapper">

            <!-- Interaktywny przycisk - na hover pokazuje "Profil" i otwiera konto DNCT -->
            <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="profile-magic-btn">

                <!-- Spód: nazwa użytkownika, nieruchoma - zakrywa ją wjeżdżająca warstwa -->
                <div class="profile-magic-text-container">
                    <span class="profile-text-username <?php echo $isAdmin ? 'text-red-500' : 'text-white'; ?>">
                        <?php echo $username; ?>
                    </span>
                </div>

                <!-- Warstwa jadąca: tło + napis "Profil" + avatar, wszystko naraz -->
                <div class="profile-magic-slide">
                    <span class="profile-text-profil">Profil</span>
                    <div class="profile-magic-avatar">
                        <?php if ($profileAvatarUrl !== null): ?>
                            <?php /* Bez zdjęcia w account.dnct.pl proxy zwraca 404 - wtedy wraca litera. */ ?>
                            <img src="<?php echo htmlspecialchars($profileAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="profile-magic-avatar__image"
                                 onerror="this.replaceWith(document.createTextNode(<?php echo htmlspecialchars(json_encode(strtoupper(substr((string)$username, 0, 1))), ENT_QUOTES, 'UTF-8'); ?>))">
                        <?php else: ?>
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                </div>

            </a>

            <!-- Przycisk wylogowania (inline SVG - nie zależy od doładowania Lucide) -->
            <a href="/wyloguj" class="profile-logout-btn" title="Wyloguj się" aria-label="Wyloguj się">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>

        </div>

    <?php else: ?>
        <a href="/login" class="bg-gradient-main px-4 md:px-6 py-2 md:py-2.5 rounded-full font-bold text-sm md:text-base text-white hover:scale-105 transition-transform shadow-lg shadow-purple-500/20 no-underline">
            Zaloguj
        </a>
    <?php endif; ?>

    <!-- HAMBURGER (MOBILE)
         Inline SVG, nie <i data-lucide> - pusty <i> jest elementem liniowym o
         zerowym rozmiarze, więc gdyby Lucide się nie wykonał, przycisk byłby
         niewidoczny i nieklikalny (a z nim całe menu mobilne). -->
    <button id="mobile-menu-btn" class="nav-hamburger text-white p-1 focus:outline-none" aria-label="Menu" aria-expanded="false" aria-controls="mobile-menu">
        <svg class="nav-icon nav-icon-menu" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
        <svg class="nav-icon nav-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
</div>
        </div>
    </div>

    <!-- MENU MOBILE -->
    <div id="mobile-menu" class="nav-mobile-menu border-t border-white/5 absolute w-full left-0 top-full">
        <div class="flex flex-col p-6 space-y-6 text-center font-medium text-lg">
            <a href="/" class="<?php echo navbar_class_mobile(navbar_active('/', [])); ?>"<?php echo navbar_active('/', []) ? ' aria-current="page"' : ''; ?>>Strona Główna</a>
            <a href="/cennik" class="<?php echo navbar_class_mobile(navbar_active('/cennik', ['/zamowienie', '/sukces'])); ?>"<?php echo navbar_active('/cennik', ['/zamowienie', '/sukces']) ? ' aria-current="page"' : ''; ?>>Cennik</a>
            <a href="/twoje-serwery" class="<?php echo navbar_class_mobile(navbar_active('/twoje-serwery', ['/oplaty'])); ?>"<?php echo navbar_active('/twoje-serwery', ['/oplaty']) ? ' aria-current="page"' : ''; ?>>Twoje serwery</a>
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="uppercase tracking-[0.1em] text-gray-400 hover:text-white py-2 block transition-colors flex justify-center items-center gap-2">
                <svg class="nav-icon-inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg> Pomoc
            </a>
            <?php /* Na telefonie przełącznik języka mieszka tutaj - w górnym pasku
                     zgniatał logo (navbar.css chowa tam .nav-lang < 640 px). */ ?>
            <div class="nav-lang nav-lang--mobile" role="group" aria-label="Język / Language">
                <?php foreach (['pl' => 'PL', 'en' => 'EN', 'de' => 'DE'] as $kod => $etykieta): ?>
                    <a href="<?php echo htmlspecialchars(i18n_link($kod)); ?>" hreflang="<?php echo $kod; ?>" lang="<?php echo $kod; ?>"
                       class="nav-lang-opt<?php echo $navLang === $kod ? ' is-active' : ''; ?>"<?php echo $navLang === $kod ? ' aria-current="true"' : ''; ?>><?php echo $etykieta; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Skrypty JS zawsze ze ścieżką od głównego katalogu (/) -->
<?php /* defer: 387 KB nieblokujace. navbar.js i tak czeka na
         DOMContentLoaded zanim wywola lucide.createIcons(), a deferowane
         skrypty wykonuja sie w kolejnosci dokumentu tuz przed tym zdarzeniem
         - kolejnosc (lucide zdefiniowany -> navbar.js podpina listener)
         zostaje zachowana. */ ?>
<script src="/assets/js/lucide.min.js" defer></script>
<script src="/assets/navibar/navbar.js" defer></script>
