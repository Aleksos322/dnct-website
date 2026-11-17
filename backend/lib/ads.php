<?php
/**
 * Google Ads - dane wysyłane przez Google Ads Script (api/internal/ads-ingest.php)
 * i czytane przez admin.lan.dnct.pl/ads. Tabele zakładają się same.
 *
 * ads_daily  - dzień x kampania: koszt, wyświetlenia, kliknięcia, konwersje.
 * ads_terms  - dzień x kampania x wpisana fraza.
 * ads_meta   - klucz/wartość (ostatnia synchronizacja).
 *
 * Koszt przychodzi w złotówkach (Script dzieli micros przez 1e6).
 */

declare(strict_types=1);

function ads_schema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db->exec(
        'CREATE TABLE IF NOT EXISTS ads_daily (
            day           DATE         NOT NULL,
            campaign_id   BIGINT UNSIGNED NOT NULL,
            campaign      VARCHAR(255) NOT NULL,
            status        VARCHAR(24)  NOT NULL DEFAULT "",
            budget        DECIMAL(12,2) NULL,
            cost          DECIMAL(12,2) NOT NULL DEFAULT 0,
            impressions   INT UNSIGNED NOT NULL DEFAULT 0,
            clicks        INT UNSIGNED NOT NULL DEFAULT 0,
            conversions   DECIMAL(12,2) NOT NULL DEFAULT 0,
            conv_value    DECIMAL(12,2) NOT NULL DEFAULT 0,
            imp_share     DECIMAL(6,4) NULL,
            PRIMARY KEY (day, campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS ads_terms (
            day         DATE         NOT NULL,
            campaign_id BIGINT UNSIGNED NOT NULL,
            term_hash   CHAR(32)     NOT NULL,
            term        VARCHAR(255) NOT NULL,
            cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            clicks      INT UNSIGNED NOT NULL DEFAULT 0,
            conversions DECIMAL(12,2) NOT NULL DEFAULT 0,
            PRIMARY KEY (day, campaign_id, term_hash),
            KEY term (term_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS ads_meta (
            k VARCHAR(32) NOT NULL PRIMARY KEY,
            v VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $done = true;
}

/** Zapis paczki z Scripta: nadpisuje dni, które przyszły (Ads koryguje dane wstecz). */
function ads_ingest(PDO $db, array $campaigns, array $terms): array
{
    ads_schema($db);
    $db->beginTransaction();
    try {
        $c = $db->prepare(
            'REPLACE INTO ads_daily (day, campaign_id, campaign, status, budget, cost, impressions, clicks, conversions, conv_value, imp_share)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $n = 0;
        foreach ($campaigns as $r) {
            $day = (string)($r['day'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !isset($r['campaign_id'])) {
                continue;
            }
            $share = isset($r['imp_share']) && is_numeric($r['imp_share']) ? max(0, min(1, (float)$r['imp_share'])) : null;
            $c->execute([
                $day, (int)$r['campaign_id'], mb_substr((string)($r['campaign'] ?? ''), 0, 255),
                mb_substr((string)($r['status'] ?? ''), 0, 24),
                isset($r['budget']) && is_numeric($r['budget']) ? (float)$r['budget'] : null,
                (float)($r['cost'] ?? 0), (int)($r['impressions'] ?? 0), (int)($r['clicks'] ?? 0),
                (float)($r['conversions'] ?? 0), (float)($r['conv_value'] ?? 0), $share,
            ]);
            $n++;
        }
        $t = $db->prepare(
            'REPLACE INTO ads_terms (day, campaign_id, term_hash, term, cost, impressions, clicks, conversions)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $m = 0;
        foreach ($terms as $r) {
            $day  = (string)($r['day'] ?? '');
            $term = mb_substr(trim((string)($r['term'] ?? '')), 0, 255);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $term === '' || !isset($r['campaign_id'])) {
                continue;
            }
            $t->execute([
                $day, (int)$r['campaign_id'], md5(mb_strtolower($term)), $term,
                (float)($r['cost'] ?? 0), (int)($r['impressions'] ?? 0), (int)($r['clicks'] ?? 0),
                (float)($r['conversions'] ?? 0),
            ]);
            $m++;
        }
        $db->prepare('REPLACE INTO ads_meta (k, v, updated_at) VALUES ("sync", ?, NOW())')
           ->execute([$n . ' kampanii, ' . $m . ' fraz']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return ['campaign_rows' => $n, 'term_rows' => $m];
}
