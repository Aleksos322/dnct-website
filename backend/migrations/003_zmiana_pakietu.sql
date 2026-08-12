-- ---------------------------------------------------------------------------
-- Rodzaj zamówienia.
--
-- Do tej pory rozróżnialiśmy je po tym, czy `extends_order_id` jest ustawione.
-- Przy trzecim rodzaju (zmiana pakietu) to przestaje wystarczać: przedłużenie
-- i zmiana pakietu OBA wskazują zamówienie nadrzędne, a webhook ma zrobić po
-- nich zupełnie co innego - jedno dokłada dni, drugie przestawia zasoby.
--
-- Jawna kolumna zamiast domyślania się ze zbiegu okoliczności.
-- ---------------------------------------------------------------------------

ALTER TABLE orders
    ADD COLUMN kind ENUM('new','extension','plan_change') NOT NULL DEFAULT 'new' AFTER billing_mode,
    ADD KEY idx_kind (kind);

-- Zamówienia sprzed tej zmiany: wszystko z rodzicem było przedłużeniem.
UPDATE orders SET kind = 'extension' WHERE extends_order_id IS NOT NULL;
