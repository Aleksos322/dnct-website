<?php
/** Dziennik maili: po 90 dniach kasuje treść (html), zostawia adres, temat i wynik. Cron codziennie. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
$n = dnct_db()->exec('UPDATE mail_log SET html = NULL WHERE html IS NOT NULL AND created_at < NOW() - INTERVAL 90 DAY');
echo 'Wyczyszczono treść maili: ' . (int)$n . PHP_EOL;
