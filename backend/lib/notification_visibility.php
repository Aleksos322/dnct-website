<?php
declare(strict_types=1);
/** Fixed SQL fragments only. No request input is interpolated. */
function notification_visible_sql(bool $hasReadJoin = true): string {
    $site = ($_SESSION['messaging_site'] ?? '') === 'game' ? 'prod' : 'beta';
    $read = $hasReadJoin ? "(r.read_at IS NOT NULL OR r.saved_at IS NOT NULL)" : "EXISTS (SELECT 1 FROM notification_reads vr WHERE vr.notification_id=n.id AND vr.user_id=:visibility_user AND vr.read_at IS NOT NULL AND vr.hidden_at IS NULL)";
    return " AND n.created_at <= NOW()
      AND (n.created_at > DATE_SUB(NOW(), INTERVAL 1 MONTH) OR {$read})
      AND NOT EXISTS (SELECT 1 FROM notification_campaign_items vi JOIN notification_campaigns vc ON vc.id=vi.campaign_id WHERE vi.notification_id=n.id AND (vc.deleted_at IS NOT NULL OR vc.environment<>'{$site}')) ";
}
