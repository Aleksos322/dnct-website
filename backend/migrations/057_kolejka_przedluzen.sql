-- Kolejka przedluzen: oplacone przedluzenie zawieszonego serwera, na ktorego wezle
-- nie ma juz miejsca w RAM. NULL = nie czeka. Uzupelnione = oplacone, czeka na start.
-- queue_started_at: kiedy kolejka uruchomila serwer (do mail/raportu).
ALTER TABLE orders
  ADD COLUMN queued_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN queue_started_at DATETIME NULL DEFAULT NULL,
  ADD INDEX idx_orders_queued (queued_at);
