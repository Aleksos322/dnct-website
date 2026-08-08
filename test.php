<?php
/**
 * 
 * index.php — strona główna, korzysta ze wspólnego layoutu assets/clear/clear.php
 */

// Sesja musi być odpalona przed jakimkolwiek dostępem do $_SESSION,
// ale clear.php i tak to obsłuży — tu potrzebujemy $isLoggedIn PRZED
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
<!-- <h1 class="text-4xl font-bold mb-6">Panel Testowy DNCT</h1>

<div class="bg-gray-900/80 p-6 rounded-xl border border-gray-800 backdrop-blur-md max-w-md w-full shadow-2xl">
    <?php if ($isLoggedIn): ?>
        <p class="mb-2">Zalogowano jako: <strong class="text-blue-400"><?php echo htmlspecialchars($username); ?></strong>
            <?php if ($email): ?> <br><span class="text-gray-400 text-sm">(<?php echo htmlspecialchars($email); ?>)</span><?php endif; ?>
        </p>

        <p class="my-4">
            <a href="/panel" class="btn-panel">
                Przejdź do panelu
            </a>
        </p>
    <?php else: ?>
        <p class="mb-4 text-gray-300">Zarządzaj swoimi serwerami Minecraft.</p>
        <div class="flex justify-center gap-4">
            <a href="/login" class="px-4 py-2 bg-blue-600 rounded-lg font-semibold hover:bg-blue-700 text-white transition">Zaloguj się</a>
            <a href="/register" class="px-4 py-2 bg-gray-700 rounded-lg font-semibold hover:bg-gray-600 text-white transition">Zarejestruj się</a>
        </div>
    <?php endif; ?>
</div> -->




<?php
$content = ob_get_clean();
require __DIR__ . '/assets/clear/clear.php';
