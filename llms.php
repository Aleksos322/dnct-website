<?php
/* /llms.txt (reguła w .htaccess) - ściąga dla czatów AI, generowana z bazy
   przy każdym wejściu, więc zawsze ma aktualne pakiety i ceny.
   Treść: backend/lib/llms.php. */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';
require_once __DIR__ . '/backend/lib/llms.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

try {
    echo llms_tekst();
} catch (Throwable $e) {
    // Baza niedostępna: lepiej 503 niż pusta albo urwana ściąga.
    error_log('llms.txt: ' . $e->getMessage());
    http_response_code(503);
    echo "# DNCT Hosting\n\nCennik chwilowo niedostępny: https://game.dnct.pl/cennik\n";
}
