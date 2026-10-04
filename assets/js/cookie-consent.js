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
        const expires = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
        const hostname = window.location.hostname;
        const domains = new Set(['', hostname, `.${hostname}`]);
        const labels = hostname.split('.');

        // GA przy `cookie_domain: auto` może wybrać domenę nadrzędną (np.
        // dnct.pl zamiast beta.dnct.pl). Ciasteczko da się usunąć tylko z
        // takim samym atrybutem Domain, z którym je utworzono.
        for (let i = 1; i < labels.length - 1; i += 1) {
            const domain = labels.slice(i).join('.');
            domains.add(domain);
            domains.add(`.${domain}`);
        }

        domains.forEach(domain => {
            const domainAttribute = domain ? `;domain=${domain}` : '';
            document.cookie = `${name}=;${expires};path=/${domainAttribute};SameSite=Lax;Secure`;
        });
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
        window[`ga-disable-${GA_ID}`] = false;

        if (window.gtag) {
            // GA może nadal być w pamięci po cofnięciu zgody na tej samej
            // stronie. Ponowne wyrażenie zgody nie wymaga wtedy przeładowania.
            window.gtag('consent', 'update', { analytics_storage: 'granted' });
            window.gtag('config', GA_ID, {
                'anonymize_ip': true,
                'cookie_flags': 'SameSite=Lax;Secure'
            });
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

        gtag('consent', 'default', { analytics_storage: 'granted' });
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
     * Zatrzymanie analityki w już otwartej karcie. Skasowanie cookies samo
     * nie wystarcza: załadowane gtag może wysyłać kolejne zdarzenia bez nich.
     */
    function disableGoogleAnalytics() {
        window[`ga-disable-${GA_ID}`] = true;
        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', { analytics_storage: 'denied' });
        }
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
        disableGoogleAnalytics();
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
        disableGoogleAnalytics();
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
