<?php
/**
 * /opinia?t=TOKEN - przycisk "Oceń DNCT w Google" z maila (backend/tools/prosba-o-opinie.php).
 * Zapisuje klik w opinie_klienci i przekierowuje do formularza opinii Google.
 * Nieznany albo pusty token też przekierowuje - klient ma trafić do opinii, nie na błąd.
 */

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

const OPINIA_GOOGLE = 'https://g.page/r/CRwe2fxVy-gHEBM/review';

$t = (string)($_GET['t'] ?? '');
if (preg_match('/^[a-f0-9]{32}$/', $t)) {
    try {
        dnct_db()->prepare('UPDATE opinie_klienci SET clicks = clicks + 1, clicked_at = COALESCE(clicked_at, NOW()) WHERE token = ?')->execute([$t]);
    } catch (Throwable $e) {
        error_log('opinia: ' . $e->getMessage());
    }
}
header('X-Robots-Tag: noindex');
header('Location: ' . OPINIA_GOOGLE, true, 302);
exit;
