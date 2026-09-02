-- ---------------------------------------------------------------------------
-- Dysk i backupy jako pola PER PRÓG w hosting_slot_tiers, zamiast stałych
-- PRICING_DEFAULT_DISK_MB / PRICING_DEFAULT_BACKUPS z backend/lib/pricing.php.
--
-- BAZA: panel (DB_*), nie dnct - hosting_slot_tiers żyje obok schematu
-- Pterodactyla z wygody wspólnego połączenia (patrz nagłówek
-- admin.dnct.pl/backend/lib/cennik_admin.php), wspólna dla beta i produkcji.
--
-- DOMYŚLNE WARTOŚCI = DZISIEJSZE STAŁE. Samo ALTER TABLE nie zmienia więc
-- zachowania ani ceny żadnego istniejącego progu - dopóki ktoś nie zrobi
-- świadomego UPDATE-u per gra/próg, pricing_from_tier() ma dostać dokładnie
-- to, co dziś wstawiał na sztywno.
-- ---------------------------------------------------------------------------

ALTER TABLE hosting_slot_tiers
    ADD COLUMN ssd_mb INT UNSIGNED NOT NULL DEFAULT 20480 AFTER cpu_cores,
    ADD COLUMN backups_limit TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER ssd_mb;
