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

// Adres przejścia do panelu. Z .env, żeby zmiana domeny panelu nie wymagała
// szukania po plikach widoku. Fallback na wypadek braku wpisu w konfiguracji.
$panelUrl = function_exists('env')
    ? (string)env('PANEL_SSO_URL', 'https://panel.dnct.pl/extensions/vikingsso/auth/auto')
    : 'https://panel.dnct.pl/extensions/vikingsso/auth/auto';
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
                <a href="/" class="text-white transition-colors">Strona Główna</a>
                <a href="/cennik" class="hover:text-white transition-colors">Cennik</a>
                <a href="/twoje-serwery" class="hover:text-white transition-colors">Twoje serwery</a>
                <a href="https://discord.gg/r2SsJp9e" target="_blank" class="hover:text-white transition-colors">Pomoc</a>
            </div>

            <!-- PRZYCISKI / PROFIL -->
            <div class="flex items-center gap-4 z-50 ml-auto md:ml-0">
    <?php if($isLoggedIn): ?>
        
        <!-- Główny zewnętrzny kontener (Cała pigułka z obramowaniem) -->
        <div class="profile-magic-wrapper">

            <!-- Interaktywny przycisk - na hover pokazuje "Panel" i przenosi od razu do panelu (SSO) -->
            <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="profile-magic-btn">

                <!-- Spód: nazwa użytkownika, nieruchoma - zakrywa ją wjeżdżająca warstwa -->
                <div class="profile-magic-text-container">
                    <span class="profile-text-username <?php echo $isAdmin ? 'text-red-500' : 'text-white'; ?>">
                        <?php echo $username; ?>
                    </span>
                </div>

                <!-- Warstwa jadąca: tło + napis "Panel" + avatar, wszystko naraz -->
                <div class="profile-magic-slide">
                    <span class="profile-text-profil">Panel</span>
                    <div class="profile-magic-avatar">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
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
        <a href="/login" class="bg-blue-600 px-4 md:px-6 py-2 md:py-2.5 rounded-full font-bold text-sm md:text-base text-white hover:scale-105 transition-transform shadow-lg shadow-blue-500/20 no-underline">
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
            <a href="/" class="text-white py-2 block border-b border-white/5">Strona Główna</a>
            <a href="/cennik" class="text-gray-400 hover:text-white py-2 block border-b border-white/5 transition-colors">Cennik</a>
            <a href="/twoje-serwery" class="text-gray-400 hover:text-white py-2 block border-b border-white/5 transition-colors">Twoje serwery</a>
            <a href="https://discord.gg/r2SsJp9e" target="_blank" class="text-gray-400 hover:text-blue-400 py-2 block transition-colors flex justify-center items-center gap-2">
                <svg class="nav-icon-inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                </svg> Pomoc
            </a>
        </div>
    </div>
</nav>

<!-- Skrypty JS zawsze ze ścieżką od głównego katalogu (/) -->
<script src="/assets/js/lucide.min.js"></script>
<script src="/assets/navibar/navbar.js"></script>