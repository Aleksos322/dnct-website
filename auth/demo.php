<?php
/**
 * Wejście na konto poglądowe (demo).
 *
 * Demo pokazuje PANEL i tylko panel - nie loguje nikogo na dnct.pl i nie
 * zakłada sesji w Authentiku. Odwiedzający dostaje jednorazowy bilet
 * (backend/lib/panel_sso.php), wymienia go w panelu na sesję konta demo
 * i wraca do nas jako ten sam anonimowy gość, którym był.
 *
 * Dlaczego nie zwykłe logowanie na konto demo:
 *
 *   1. Rozdawanie loginu i hasła odpada. Authentik zapamiętałby demo jako
 *      zalogowanego, więc kliknięcie "Zaloguj" wpuszczałoby z powrotem na
 *      demo - bez pytania o hasło i bez żadnego sygnału, że to nie jest
 *      konto tej osoby. Przeglądarka dorzuciłaby do tego zapamiętane hasło.
 *   2. To samo dotyczy logowania demo przez osobny proces w Authentiku:
 *      sesja SSO jest wspólna dla przeglądarki, więc skutek byłby ten sam,
 *      a dodatkowo dnct.pl uznałoby gościa za zalogowanego i zamówienie
 *      poszłoby na konto demo.
 *
 * BEZPIECZEŃSTWO: konto bierzemy z DEMO_PANEL_EMAIL, nigdy z żądania. Samo
 * konto nie jest administratorem panelu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

/* Zalogowanego nie wpuszczamy na demo. Panel trzyma jedną sesję na
   przeglądarkę, więc bilet demo WYRZUCIŁBY go z jego własnego konta -
   klient kliknąłby "zobacz demo" z ciekawości i wylądował wylogowany
   ze swojego panelu. Prawdziwy panel jest dla niego lepszy niż demo. */
if (isset($_SESSION['authentik_username'])) {
    $_SESSION['flash'][] = [
        'type'    => 'info',
        'message' => 'Jesteś zalogowany - zamiast demo otwieramy Twój własny panel.',
    ];
    header('Location: /panel');
    exit();
}

// Ten sam limit co przy zwykłym logowaniu - przycisk jest publiczny, więc nie
// może służyć do zasypywania panelu żądaniami ani do pompowania tabeli biletów.
$limit = rate_limit_hit('login', RATE_LIMIT_LOGIN);
if (!$limit['allowed']) {
    $_SESSION['flash'][] = [
        'type'    => 'warning',
        'message' => 'Zbyt wiele prób. Spróbuj ponownie za ' . max(1, (int)ceil($limit['retry_after'] / 60)) . ' min.',
    ];
    header('Location: /');
    exit();
}

$demoEmail = panel_demo_email();

if ($demoEmail === '') {
    error_log('Demo: DEMO_PANEL_EMAIL jest puste - konto pogladowe nie jest skonfigurowane.');
    $_SESSION['flash'][] = [
        'type'    => 'error',
        'message' => 'Konto demonstracyjne jest chwilowo niedostępne.',
    ];
    header('Location: /');
    exit();
}

try {
    header('Location: ' . panel_sso_ticket($demoEmail, 'demo'));
    exit();
} catch (Throwable $e) {
    error_log('Demo: nie udalo sie wystawic biletu do panelu: ' . $e->getMessage());
    $_SESSION['flash'][] = [
        'type'    => 'error',
        'message' => 'Konto demonstracyjne jest chwilowo niedostępne.',
    ];
    header('Location: /');
    exit();
}
