<?php
/**
 * Logowanie - przekierowanie do Authentika (OIDC).
 *
 * Formularza login/hasło już tu nie ma i nie powinno być: gdyby hasło
 * przechodziło przez naszą stronę, przeglądarka nie dostałaby sesji Authentika,
 * a bez niej panel Pterodactyla i tak wymagałby osobnego logowania. Ekran
 * logowania serwuje Authentik - jego wygląd ustawia się w Authentiku
 * (Brands -> stylesheet), żeby wyglądał jak reszta DNCT.
 *
 * Ta strona istnieje dalej, bo kieruje na nią .htaccess (/login) i wiele
 * odnośników w serwisie.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

// Zalogowany nie ma tu czego szukać.
if (isset($_SESSION['authentik_username'])) {
    header('Location: /');
    exit();
}

// Ochrona przed zasypywaniem Authentika próbami z jednego adresu.
$limit = rate_limit_hit('login', RATE_LIMIT_LOGIN);
if (!$limit['allowed']) {
    $minutes = max(1, (int)ceil($limit['retry_after'] / 60));
    $error = 'Zbyt wiele prób logowania. Spróbuj ponownie za ' . $minutes . ' min.';
} else {
    try {
        // ?return_to=/sciezka pozwala wrócić tam, skąd użytkownik przyszedł.
        // Wartość jest przycinana do ścieżki lokalnej w oidc_safe_return_to().
        header('Location: ' . oidc_authorize_url($_GET['return_to'] ?? null));
        exit();
    } catch (RuntimeException $e) {
        error_log('Login: nie udalo sie zbudowac adresu OIDC: ' . $e->getMessage());
        $error = 'Logowanie jest chwilowo niedostępne. Spróbuj za chwilę.';
    }
}

// Poniżej lądujemy tylko wtedy, gdy przekierowanie się nie udało.
$pageTitle = 'Logowanie | DNCT Hosting';
$metaDescription = 'Zaloguj się do panelu zarządzania DNCT Hosting.';

ob_start();
?>
<div class="bg-gray-900/70 border border-gray-800 rounded-2xl p-8 max-w-md w-full backdrop-blur-md">
    <h1 class="text-2xl font-bold mb-3">Logowanie</h1>
    <p class="text-red-400 text-sm mb-6"><?php echo htmlspecialchars($error ?? 'Wystąpił nieoczekiwany błąd.'); ?></p>
    <a href="/login" class="inline-block px-5 py-2.5 bg-gradient-main hover:scale-105 rounded-full font-bold text-sm text-white shadow-lg shadow-purple-500/20 transition-transform">
        Spróbuj ponownie
    </a>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/assets/clear/clear.php';
