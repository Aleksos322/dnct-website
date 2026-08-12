<?php
/**
 * assets/clear/footer.php
 * Wspólna stopka, dołączana przez clear.php. Osobny plik, żeby łatwo
 * podmieniać linki social media / dane kontaktowe w jednym miejscu.
 */
?>
<footer class="py-10 glass glass-top-edge relative z-10">
    <div class="glass-content max-w-7xl mx-auto px-6">
        <div class="flex justify-center gap-6 mb-8">
            <a href="https://facebook.com/dncthosting" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Facebook">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333H16V2.169c-.585-.089-1.308-.169-2.227-.169-2.753 0-4.773 1.236-4.773 3.53V8z"/></svg>
            </a>
            <a href="https://instagram.com/dncthosting" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Instagram">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.5" fill="currentColor"/></svg>
            </a>
            <a href="https://x.com/dncthosting" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="X (Twitter)">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2s9 5 20 5a9.5 9.5 0 00-9-5.5c4.75-2.63 7-7 7-11.38a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>
            </a>
            <a href="https://youtube.com/@dncthosting" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="YouTube">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" rel="noopener" class="text-gray-500 hover:text-purple-500 transition-colors" title="Discord">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.79 19.79 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.927 1.793 8.18 1.793 12.061 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.3 12.3 0 0 1-1.873.892.076.076 0 0 0-.04.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.055c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.028zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.955 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
            </a>
        </div>
        <p class="text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> DNCT Hosting. Wszystkie prawa zastrzeżone.
            | <a href="tel:+48536180706" class="text-purple-500 hover:text-purple-400 transition-colors">+48 536 180 706</a>
            | <a href="mailto:support@dnct.pl" class="text-purple-500 hover:text-purple-400 transition-colors">support@dnct.pl</a>
        </p>
        <p class="text-center text-gray-600 text-xs mt-3">
            <a href="/regulamin" class="hover:text-gray-400 transition-colors">Regulamin</a>
            &nbsp;·&nbsp;
            <a href="/polityka-prywatnosci" class="hover:text-gray-400 transition-colors">Polityka Prywatności</a>
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