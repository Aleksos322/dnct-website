-- ---------------------------------------------------------------------------
-- Przedłużanie serwera.
--
-- Dokupienie kolejnego okresu jest OSOBNYM zamówieniem, a nie zmianą istniejącego.
-- Dzięki temu zostaje pełna historia płatności: kto, kiedy i za ile dołożył dni.
-- Nadpisywanie pierwotnego zamówienia skasowałoby ślad po każdej wcześniejszej
-- transakcji, a to jest dokument księgowy, nie stan aplikacji.
--
-- `extends_order_id` wskazuje zamówienie, do którego dopisujemy czas. Webhook
-- po takiej płatności NIE tworzy serwera — przesuwa datę wygaśnięcia rodzica
-- i zdejmuje ewentualne zawieszenie.
-- ---------------------------------------------------------------------------

ALTER TABLE orders
    ADD COLUMN extends_order_id INT UNSIGNED NULL DEFAULT NULL AFTER plan_id,
    ADD KEY idx_extends (extends_order_id);
