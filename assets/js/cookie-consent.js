/**
 * DNCT Hosting - Cookie Consent Manager (RODO/GDPR Compliant)
 * Zarządza zgodą użytkownika na cookies analityczne i warunkowo ładuje Google Analytics
 */

(function() {
    'use strict';

    const CONSENT_COOKIE_NAME = 'dnct_cookie_consent';
    const CONSENT_COOKIE_EXPIRY = 365; // dni
    const GA_ID = 'G-0EK3K0EMFP';

    /**
     * Odczyt cookie
     */
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Zapis cookie
     */
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = `expires=${date.toUTCString()}`;
        document.cookie = `${name}=${value};${expires};path=/;SameSite=Lax;Secure`;
    }

    /**
     * Usunięcie cookie
     */
    function deleteCookie(name) {
        document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;`;
    }

    /**
     * Sprawdzenie czy użytkownik już wyraził zgodę
     */
    function hasUserConsented() {
        const consent = getCookie(CONSENT_COOKIE_NAME);
        return consent !== null;
    }

    /**
     * Pobranie statusu zgody
     */
    function getConsentStatus() {
        const consent = getCookie(CONSENT_COOKIE_NAME);
        return consent === 'accepted';
    }

    /**
     * Dynamiczne ładowanie Google Analytics
     */
    function loadGoogleAnalytics() {
        if (window.gtag) {
            console.log('[Cookie Consent] Google Analytics już załadowane');
            return;
        }

        // Dodaj skrypt gtag.js
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_ID}`;
        document.head.appendChild(script);

        // Inicjalizacja dataLayer i gtag
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        window.gtag = gtag;
        
        gtag('js', new Date());
        gtag('config', GA_ID, {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=Lax;Secure'
        });

        console.log('[Cookie Consent] Google Analytics załadowane');
    }

    /**
     * Usunięcie Google Analytics cookies
     */
    function removeGoogleAnalyticsCookies() {
        const gaCookies = ['_ga', '_gat', '_gid'];
        gaCookies.forEach(cookie => {
            deleteCookie(cookie);
        });
        // Usuń cookies GA4
        document.cookie.split(';').forEach(cookie => {
            const cookieName = cookie.split('=')[0].trim();
            if (cookieName.startsWith('_ga_')) {
                deleteCookie(cookieName);
            }
        });
        console.log('[Cookie Consent] Google Analytics cookies usunięte');
    }

    /**
     * Akceptacja cookies
     */
    function acceptCookies() {
        setCookie(CONSENT_COOKIE_NAME, 'accepted', CONSENT_COOKIE_EXPIRY);
        loadGoogleAnalytics();
        hideBanner();
    }

    /**
     * Odrzucenie cookies
     */
    function rejectCookies() {
        setCookie(CONSENT_COOKIE_NAME, 'rejected', CONSENT_COOKIE_EXPIRY);
        removeGoogleAnalyticsCookies();
        hideBanner();
    }

    /**
     * Ukrycie bannera
     */
    function hideBanner() {
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(100%)';
            setTimeout(() => {
                banner.style.display = 'none';
            }, 300);
        }
    }

    /**
     * Wyświetlenie bannera
     */
    function showBanner() {
        const banner = document.getElementById('cookie-consent-banner');
        if (banner) {
            banner.style.display = 'block';
            setTimeout(() => {
                banner.style.opacity = '1';
                banner.style.transform = 'translateY(0)';
            }, 10);
        }
    }

    /**
     * Cofnięcie zgody (usunięcie cookie zgody)
     */
    function revokeConsent() {
        deleteCookie(CONSENT_COOKIE_NAME);
        removeGoogleAnalyticsCookies();
        showBanner();
    }

    /**
     * Inicjalizacja
     */
    function init() {
        // Jeśli użytkownik już wyraził zgodę
        if (hasUserConsented()) {
            if (getConsentStatus()) {
                // Zaakceptował - ładuj GA
                loadGoogleAnalytics();
            }
            // Jeśli odrzucił - nie rób nic
        } else {
            // Nie wyraził zgody - pokaż banner
            showBanner();
        }

        // Event listeners dla przycisków
        const acceptBtn = document.getElementById('cookie-accept');
        const rejectBtn = document.getElementById('cookie-reject');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', acceptCookies);
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', rejectCookies);
        }

        // Debug: expose funkcję do konsoli dla łatwego testowania
        window.dnctCookieConsent = {
            accept: acceptCookies,
            reject: rejectCookies,
            revoke: revokeConsent,
            status: getConsentStatus,
            hasConsented: hasUserConsented
        };
    }

    // Uruchom po załadowaniu DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
