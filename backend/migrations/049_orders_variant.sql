-- Wariant gry w zamówieniu (boty Discord: node / java / python).
-- Puste dla pozostałych gier. Uruchomić na KAŻDEJ bazie strony (produkcja: dnct,
-- beta: nazwa_bazy) PRZED włączeniem sprzedaży botów.
ALTER TABLE orders ADD COLUMN variant VARCHAR(16) NULL DEFAULT NULL AFTER game_type;
