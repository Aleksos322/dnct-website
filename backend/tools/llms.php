<?php
/**
 * Podgląd /llms.txt w konsoli: php backend/tools/llms.php
 * Sam plik generuje się przy każdym wejściu (llms.php) - tego narzędzia
 * nie trzeba uruchamiać po zmianie cen. Treść: backend/lib/llms.php.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/llms.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo llms_tekst();
