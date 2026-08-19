<?php
/**
 * test.php - wzorcowa pusta strona. Kopiuj ten plik jako punkt startowy dla
 * nowej podstrony, korzysta ze wspólnego layoutu assets/clear/clear.php.
 */

// Sesja musi być odpalona przed jakimkolwiek dostępem do $_SESSION,
// ale clear.php i tak to obsłuży - tu potrzebujemy $isLoggedIn PRZED
// zbudowaniem treści, więc odpalamy sesję lokalnie też.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['authentik_username']);
$username   = $_SESSION['authentik_username'] ?? null;
$email      = $_SESSION['authentik_email'] ?? null;

$pageTitle = 'DNCT Hosting';

ob_start();
?>

<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
