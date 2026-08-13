<?php
/**
 * Pobranie paczki z plikami skasowanego serwera - /pobierz?token=...
 *
 * Plik leży POZA DocumentRootem, więc to jedyna droga do niego. Token (64 znaki
 * losowe) jest zarazem adresem i uprawnieniem - nie pytamy o logowanie, bo
 * człowiek, któremu skasowaliśmy serwer, mógł już stracić dostęp do konta,
 * a wtedy paczka byłaby dla niego nie do odzyskania.
 *
 * Wydajemy plik strumieniowo. Paczki mają setki megabajtów i wczytanie takiej
 * do pamięci PHP położyłoby proces przy pierwszym pobraniu.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/server_archive.php';

/* Zgadywanie tokenu jest nierealne (64 znaki hex), ale limit i tak zakłada
   ktoś, kto by próbował - a przy okazji chroni przed ściąganiem tej samej
   paczki w kółko przez zapętlony skrypt. */
$limit = rate_limit_hit('download', RATE_LIMIT_DOWNLOAD);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . (int)$limit['retry_after']);
    exit("Zbyt wiele prób. Spróbuj za chwilę.\n");
}

$archive = archive_by_token(dnct_db(), (string)($_GET['token'] ?? ''));
$path    = $archive !== null ? archive_path($archive) : null;

/* Jeden komunikat na wszystkie powody odmowy: zły token, wygasły link
   i skasowany plik muszą wyglądać identycznie, inaczej różnica odpowiedzi
   potwierdza, że dany token kiedyś istniał. */
$ok = $archive !== null
    && $archive['status'] === 'ready'
    && strtotime((string)$archive['expires_at']) > time()
    && $path !== null
    && is_file($path)
    && filesize($path) > 0;

if (!$ok) {
    http_response_code(404);

    $pageTitle     = 'Link wygasł - DNCT Hosting';
    $centerContent = true;

    ob_start();
    ?>
    <div class="cennik-info glass" style="max-width:520px">
        <h2>Tego linku już nie ma</h2>
        <p>Paczki trzymamy przez ograniczony czas i po tym terminie kasujemy je z dysków.
           Link mógł też zostać wpisany z błędem.</p>
        <p>Jeśli potrzebujesz tych plików, napisz do nas na Discordzie - sprawdzimy, czy da się coś zrobić.</p>
    </div>
    <?php
    $content = ob_get_clean();
    require __DIR__ . '/assets/clear/clear.php';
    exit();
}

dnct_db()->prepare('UPDATE server_archives SET downloaded_at = NOW() WHERE id = ?')
         ->execute([(int)$archive['id']]);

$size = (int)filesize($path);
$name = 'serwer-dnct-' . (int)$archive['order_id'] . '.tar.gz';

// Bufory precz - inaczej PHP zbierze cały plik w pamięci, zamiast go przepuścić.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . $size);
header('Content-Transfer-Encoding: binary');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

set_time_limit(0);
readfile($path);
exit();
