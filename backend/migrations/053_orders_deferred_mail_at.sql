-- Kiedy wyslano klientowi przypomnienie, ze serwer ruszy dopiero po 14 dniach
-- (z przyciskiem "uruchom teraz"). NULL = jeszcze nie wyslano.
ALTER TABLE orders ADD COLUMN deferred_mail_at DATETIME NULL DEFAULT NULL AFTER service_start_at;
