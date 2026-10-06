<?php
/**
 * assets/clear/footer.php
 * Wspólna stopka, dołączana przez clear.php. Osobny plik, żeby łatwo
 * podmieniać linki social media (zostawiamy tylko działające - dziś sam Discord) / dane kontaktowe w jednym miejscu.
 */
?>
<footer class="py-10 glass glass-top-edge relative z-10">
    <div class="glass-content max-w-7xl mx-auto px-6">
        <div class="flex justify-center gap-6 mb-8">
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
