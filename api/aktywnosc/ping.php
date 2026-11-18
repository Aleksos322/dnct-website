<?php
/**
 * Heartbeat obecności - wołany co ~20s przez JS z assets/clear/clear.php
 * (site-wide, każda strona). Działa dla gości I zalogowanych - nie wymaga
 * sesji Authentika, tylko session_id() PHP jako klucz sesji.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/aktywnosc.php';

require_method('POST');
csrf_guard();
// Limit na SESJĘ, a na IP tylko szeroki bezpiecznik. Dawniej 10/min na IP: każde wejście
// na stronę to 2 zgłoszenia (wejście + wyjście), więc kilka kliknięć albo kilka kart, czy
// domownicy za jednym IP, kończyło się czerwonym 429 w konsoli i dziurą w statystyce.
rate_limit_guard('aktywnosc', [120, 60], 'Zbyt wiele odświeżeń obecności z tego adresu IP. Odczekaj chwilę.');
rate_limit_guard('aktywnosc-' . substr(hash('sha256', session_id()), 0, 16), [20, 60], 'Zbyt wiele odświeżeń obecności. Odczekaj chwilę.');

$strona   = request_string('strona', '/');
$widoczna = request_bool('widoczna');

$ok = aktywnosc_ping(
    session_id(),
    $_SESSION['authentik_username'] ?? null,
    $strona,
    $widoczna
);

if (!$ok) {
    json_error('Nie udało się zapisać obecności.', 500);
}

json_ok();
