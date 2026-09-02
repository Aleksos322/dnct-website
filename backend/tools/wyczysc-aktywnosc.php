<?php
/**
 * Kasuje sesje z `aktywnosc_sesje` nieaktywne dłużej niż AKTYWNOSC_ZATRZYMAJ_DNI.
 *
 *     php backend/tools/wyczysc-aktywnosc.php
 *
 * Do crona, raz dziennie, JAKO www-data (ten sam wzorzec co lifecycle.php):
 *
 *     10 3 * * * www-data php /var/www/dnct.pl/public_html/WEB/-dnct.pl/backend/tools/wyczysc-aktywnosc.php >> /var/log/dnct-lifecycle.log 2>&1
 *     15 3 * * * www-data DNCT_ENV_FILE=/var/www/dnct.pl/config/beta.env php /var/www/dnct.pl/public_html/WEB/beta.dnct.pl/backend/tools/wyczysc-aktywnosc.php >> /var/log/dnct-lifecycle.log 2>&1
 *
 * Bez trybu podglądu - samo `DELETE WHERE ostatni_ping < 90 dni` nie ma
 * nic do zepsucia, w przeciwieństwie do lifecycle.php (kasowanie serwerów).
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/aktywnosc.php';

$ok = aktywnosc_wyczysc();

echo $ok ? "OK\n" : "BLAD - patrz error_log\n";
exit($ok ? 0 : 1);
