-- ---------------------------------------------------------------------------
-- Dowód warunków umowy i wyboru terminu rozpoczęcia usługi.
--
-- Nie zapisujemy adresu IP ani odcisku przeglądarki. Do rozliczenia i obrony
-- umowy wystarczają: wersja warunków, czas akceptacji oraz świadomy wybór
-- natychmiastowego albo odroczonego rozpoczęcia. To realizuje minimalizację
-- danych i nie robi z zamówień magazynu danych śledzących.
-- ---------------------------------------------------------------------------

ALTER TABLE orders
    ADD COLUMN terms_version VARCHAR(20) NULL AFTER currency,
    ADD COLUMN terms_accepted_at DATETIME NULL AFTER terms_version,
    ADD COLUMN immediate_service_requested_at DATETIME NULL AFTER terms_accepted_at,
    ADD COLUMN service_start_at DATETIME NULL AFTER immediate_service_requested_at,
    ADD KEY idx_deferred_start (status, service_start_at);

ALTER TABLE hosting_orders
    ADD COLUMN terms_version VARCHAR(20) NULL AFTER currency,
    ADD COLUMN terms_accepted_at DATETIME NULL AFTER terms_version,
    ADD COLUMN immediate_service_requested_at DATETIME NULL AFTER terms_accepted_at,
    ADD COLUMN service_start_at DATETIME NULL AFTER immediate_service_requested_at,
    ADD KEY idx_deferred_start (status, service_start_at);
