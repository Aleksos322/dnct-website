<?php
/**
 * Wejście na konto poglądowe (demo).
 *
 * Kieruje do osobnego procesu w Authentiku (`demo-login`), który loguje na
 * konto demo bez pytania o hasło, a następnie wraca do naszego zwykłego
 * przepływu OIDC. Dzięki temu demo przechodzi tą samą drogą co normalny
 * użytkownik - dostaje sesję Authentika, więc panel wpuszcza je bez pytania.
 *
 * BEZPIECZEŃSTWO: proces po stronie Authentika ma nazwę konta zapisaną na
 * sztywno w polityce, więc nie da się nim wejść na cudze konto. Samo konto demo
 * nie jest administratorem panelu i nie ma żadnych serwerów.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/bootstrap.php';

// Zalogowanego nie przelogowujemy - poszedłby prosto do panelu na swoim koncie.
if (isset($_SESSION['authentik_username'])) {
    header('Location: /panel');
    exit();
}

// Ten sam limit co przy zwykłym logowaniu - przycisk jest publiczny, więc nie
// może służyć do zasypywania Authentika żądaniami.
$limit = rate_limit_hit('login', RATE_LIMIT_LOGIN);
if (!$limit['allowed']) {
    $_SESSION['flash'][] = [
        'type'    => 'warning',
        'message' => 'Zbyt wiele prób. Spróbuj ponownie za ' . max(1, (int)ceil($limit['retry_after'] / 60)) . ' min.',
    ];
    header('Location: /');
    exit();
}

$flowSlug = (string)env('DEMO_FLOW_SLUG', 'demo-login');

try {
    // Po zalogowaniu wracamy na stronę, która przerzuca prosto do panelu.
    header('Location: ' . oidc_flow_url($flowSlug, '/panel'));
    exit();
} catch (RuntimeException $e) {
    error_log('Demo: nie udalo sie zbudowac adresu: ' . $e->getMessage());
    $_SESSION['flash'][] = [
        'type'    => 'error',
        'message' => 'Konto demonstracyjne jest chwilowo niedostępne.',
    ];
    header('Location: /');
    exit();
}
