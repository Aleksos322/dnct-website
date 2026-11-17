<?php
/**
 * POST /api/internal/ads-ingest.php
 *
 * Wywoływane PRZEZ Google Ads Script (UrlFetchApp) w koncie Ads, co godzinę.
 * Panel admina jest tylko w LAN-ie, więc dane wpadają tutaj, a admin.lan.dnct.pl
 * czyta je z bazy. Bez CSRF (serwer-do-serwera) - uwierzytelnienie sekretem
 * ADS_INGEST_SECRET w nagłówku X-Ads-Secret, porównywanym stałoczasowo.
 * Idempotentne: ten sam dzień nadpisuje się (REPLACE).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';
require_once dirname(__DIR__, 2) . '/backend/lib/ads.php';

require_method('POST');

$secret = (string)env('ADS_INGEST_SECRET', '');
if ($secret === '') {
    error_log('ads-ingest: brak ADS_INGEST_SECRET w konfiguracji - endpoint zablokowany.');
    json_error('Endpoint nieskonfigurowany.', 500);
}
$given = (string)($_SERVER['HTTP_X_ADS_SECRET'] ?? '');
if ($given === '' || !hash_equals($secret, $given)) {
    json_error('Brak dostępu.', 403);
}

$p = request_payload();
$campaigns = is_array($p['campaigns'] ?? null) ? array_slice($p['campaigns'], 0, 5000) : [];
$terms     = is_array($p['terms'] ?? null) ? array_slice($p['terms'], 0, 20000) : [];

try {
    json_ok(ads_ingest(dnct_db(), $campaigns, $terms));
} catch (Throwable $e) {
    error_log('ads-ingest: ' . $e->getMessage());
    json_error('Błąd zapisu.', 500);
}
