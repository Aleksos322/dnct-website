<?php
/**
 * errors/404.php
 * Podepnij w .htaccess: ErrorDocument 404 /errors/404.php
 */

http_response_code(404);

$pageTitle       = 'Strona nie znaleziona (404) — DNCT Hosting';
$metaDescription = 'Ta strona nie istnieje lub została przeniesiona.';
$centerContent   = true;

ob_start();
?>
<div class="max-w-lg w-full">
    <p class="text-8xl font-black gradient-text mb-4">404</p>
    <h1 class="text-3xl font-bold mb-4">Ups, nic tu nie ma</h1>
    <p class="text-gray-400 mb-8">
        Strona której szukasz nie istnieje, została przeniesiona, albo literówka wkradła się w adres.
    </p>
    <div class="flex flex-col md:flex-row justify-center gap-4">
        <a href="/" class="bg-gradient-main px-8 py-3 rounded-xl font-bold hover:shadow-[0_0_30px_rgba(139,92,246,0.5)] transition-all">
            Strona główna
        </a>
        <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="glass px-8 py-3 rounded-xl font-bold hover:bg-white/10 transition-all">
            Zgłoś problem na Discordzie
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../assets/clear/clear.php';