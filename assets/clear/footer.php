<?php
/**
 * assets/clear/footer.php
 * Wspólna stopka, dołączana przez clear.php. Osobny plik, żeby łatwo
 * podmieniać linki social media (zostawiamy tylko działające - Discord, YouTube, Facebook, Instagram, Google) / dane kontaktowe w jednym miejscu.
 */
?>
<footer class="py-10 glass glass-top-edge relative z-10">
    <div class="glass-content max-w-7xl mx-auto px-6">
        <div class="flex justify-center gap-6 mb-8">
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Discord">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
            </a>
            <a href="https://www.youtube.com/@DNCT-Hosting" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="YouTube" aria-label="YouTube">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://www.facebook.com/profile.php?id=61594999575970" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Facebook" aria-label="Facebook">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            </a>
            <a href="https://www.instagram.com/dnct_hosting/" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Instagram" aria-label="Instagram">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".6" fill="currentColor"/></svg>
            </a>
            <a href="https://share.google/8aqI2UGkfDyLChG0Y" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Google" aria-label="Google">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/></svg>
            </a>
        </div>
        <p class="text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> DNCT Hosting. Wszystkie prawa zastrzeżone.
            | <a href="tel:+48536180706" class="text-purple-500 hover:text-purple-400 transition-colors">+48 536 180 706</a>
            | <a href="mailto:support@dnct.pl" class="text-purple-500 hover:text-purple-400 transition-colors">support@dnct.pl</a>
        <?php /* Przełącznik podpowiedzi admina (2026-11-11) - tylko dla admina.
                 Ciasteczko czyta backend/lib/admin_hints.php; tu chowamy na żywo
                 wszystko z data-admin-hint. */
        require_once dirname(__DIR__, 2) . '/backend/lib/admin_hints.php';
        if (admin_hints_is_admin()): $adminHintsOn = admin_hints_on(); ?>
            <span class="admin-hints-row">
                <button type="button" class="admin-hints-switch" role="switch" aria-checked="<?php echo $adminHintsOn ? 'true' : 'false'; ?>" data-admin-hints-toggle>
                    <span class="admin-hints-track" aria-hidden="true"><span></span></span>
                    Podpowiedzi admina
                </button>
            </span>
            <style>
                .admin-hints-row { display: inline-flex; vertical-align: middle; margin-left: 0.6rem; }
                .admin-hints-switch { display: inline-flex; align-items: center; gap: 0.6rem; padding: 0.2rem 0.6rem; border-radius: 999px; border: 1px solid rgba(255,255,255,.09); background: rgba(255,255,255,.04); color: #94a3b8; font-size: 0.62rem; font-weight: 900; letter-spacing: 0.14em; text-transform: uppercase; cursor: pointer; transition: color .2s ease, border-color .2s ease; }
                .admin-hints-switch:hover { color: #f1f5f9; border-color: rgba(167,139,250,.35); }
                .admin-hints-switch:focus-visible { outline: 2px solid #a78bfa; outline-offset: 3px; }
                .admin-hints-track { position: relative; width: 28px; height: 16px; border-radius: 999px; background: #40394b; transition: background .2s ease; }
                .admin-hints-track span { position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; border-radius: 50%; background: #e2e8f0; transition: transform .2s ease; }
                .admin-hints-switch[aria-checked="true"] .admin-hints-track { background: #8b5cf6; }
                .admin-hints-switch[aria-checked="true"] .admin-hints-track span { transform: translateX(12px); }
                [data-admin-hint][hidden] { display: none !important; }
                @media (prefers-reduced-motion: reduce) { .admin-hints-switch, .admin-hints-track, .admin-hints-track span { transition: none; } }
            </style>
            <script>
            (function () {
                var b = document.querySelector('[data-admin-hints-toggle]');
                if (!b) return;
                b.addEventListener('click', function () {
                    var on = b.getAttribute('aria-checked') !== 'true';
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                    document.cookie = 'dnct_admin_hints=' + (on ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                    document.querySelectorAll('[data-admin-hint]').forEach(function (el) { el.hidden = !on; });
                });
            })();
            </script>
        <?php endif; ?>
        </p>
        <p class="text-center text-gray-600 text-xs mt-3">
            <a href="/regulamin" class="hover:text-gray-400 transition-colors">Regulamin</a>
            &nbsp;·&nbsp;
            <a href="/polityka-prywatnosci" class="hover:text-gray-400 transition-colors">Polityka Prywatności</a>
            &nbsp;·&nbsp;
            <a href="/poradniki/" class="hover:text-gray-400 transition-colors">Poradniki</a>
            &nbsp;·&nbsp;
            <a href="/wybierz-serwer" class="hover:text-gray-400 transition-colors">Dobierz serwer</a>
            &nbsp;·&nbsp;
            <a href="https://status.dnct.pl" class="hover:text-gray-400 transition-colors">Status usług</a>
            &nbsp;·&nbsp;
            <a href="https://g.page/r/CRwe2fxVy-gHEBM/review" target="_blank" rel="noopener" class="hover:text-gray-400 transition-colors">Oceń nas w Google</a>
            &nbsp;·&nbsp;
            <a href="/partnerzy" class="hover:text-gray-400 transition-colors">Dla twórców</a>
            &nbsp;·&nbsp;
            <a href="/zglos-naruszenie" class="hover:text-gray-400 transition-colors">Zgłoś naruszenie</a>
            &nbsp;·&nbsp;
            <?php /* RODO wymaga, żeby wycofanie zgody było równie łatwe jak jej udzielenie -
                     stąd stały odnośnik w stopce, a nie tylko jednorazowy baner.
                     revoke() kasuje ciasteczko zgody, usuwa cookies _ga* i pokazuje baner
                     od nowa; funkcja jest wystawiana przez assets/js/cookie-consent.js. */ ?>
            <button type="button" class="cookie-settings-link hover:text-gray-400 transition-colors"
                    onclick="window.dnctCookieConsent && window.dnctCookieConsent.revoke()">
                Ustawienia cookies
            </button>
        </p>
    </div>
</footer>
