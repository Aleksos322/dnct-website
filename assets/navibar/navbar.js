document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    // Navbar rysuje ikonki inline SVG i nie potrzebuje Lucide, ale inne elementy
    // strony mogą - jeśli biblioteka jest dostępna, podmieniamy co trzeba.
    if (window.lucide) {
        lucide.createIcons();
    }

    if (mobileMenuBtn && mobileMenu) {
        const setMenuOpen = (open) => {
            mobileMenu.classList.toggle('open', open);
            mobileMenuBtn.classList.toggle('open', open);
            mobileMenuBtn.setAttribute('aria-expanded', String(open));
        };

        mobileMenuBtn.addEventListener('click', () => {
            setMenuOpen(!mobileMenu.classList.contains('open'));
        });

        document.querySelectorAll('#mobile-menu a:not(.nav-disabled)').forEach(link => {
            link.addEventListener('click', () => setMenuOpen(false));
        });
    }

    // Linki do stron jeszcze niegotowych: nie nawigują, tylko pokazują dymek.
    // Na telefonach nie ma hovera, więc dymek pokazujemy chwilowo po dotknięciu.
    const TOOLTIP_VISIBLE_MS = 2500;
    document.querySelectorAll('a.nav-disabled').forEach(link => {
        let hideTimer = null;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            link.classList.add('nav-disabled-tapped');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => link.classList.remove('nav-disabled-tapped'), TOOLTIP_VISIBLE_MS);
        });
    });
});
