<?php
/**
 * Baner zgody na cookies analityczne.
 *
 * Dołączany przez clear.php do KAŻDEJ podstrony, nie tylko do rejestracji.
 * To nie jest kwestia wygody: zgoda na analitykę musi być zebrana od każdego
 * odwiedzającego, ZANIM ciasteczko powstanie. Zgoda „przy rejestracji” nie
 * obejmuje osób, które nigdy się nie rejestrują — a to większość ruchu — i nie
 * byłaby dobrowolna w rozumieniu art. 7 ust. 4 RODO, bo analityka nie jest
 * niezbędna do świadczenia hostingu.
 *
 * Logika siedzi w /assets/js/cookie-consent.js: Google Analytics ładuje się
 * dopiero po kliknięciu „Akceptuję”, a odrzucenie kasuje ciasteczka _ga*.
 * Ten plik dostarcza wyłącznie znaczniki, których ten skrypt szuka po id.
 *
 * Baner startuje z display:none — pokazuje go dopiero skrypt i tylko wtedy,
 * gdy wyboru jeszcze nie ma. Dzięki temu wracający użytkownik nie widzi
 * mignięcia banera przed jego ukryciem.
 */
?>
<div id="cookie-consent-banner" class="glass glass-top-edge" style="display: none;" role="dialog" aria-live="polite" aria-label="Zgoda na pliki cookie">
    <div class="cookie-consent-container glass-content">
        <div class="cookie-consent-text">
            <h3>
                <span class="cookie-icon" aria-hidden="true">🍪</span>
                Używamy plików cookie
            </h3>
            <p>
                Do działania strony używamy wyłącznie niezbędnych cookies. Za Twoją zgodą
                dołożymy też analityczne (Google Analytics), żeby wiedzieć, co poprawiać.
                Bez zgody analityka się nie uruchomi.
                <a href="/polityka-prywatnosci">Polityka prywatności</a>
                <span aria-hidden="true">·</span>
                <a href="/regulamin">Regulamin</a>
            </p>
        </div>
        <div class="cookie-consent-actions">
            <button type="button" id="cookie-reject" class="cookie-btn cookie-btn-reject">
                Odrzuć
            </button>
            <button type="button" id="cookie-accept" class="cookie-btn cookie-btn-accept">
                Akceptuję
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/cookie-consent.js" defer></script>
