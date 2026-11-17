-- Przezroczystość tła rozmowy (20-100%, 100 = pełne krycie, jak dotąd). MariaDB: ADD COLUMN IF NOT EXISTS.
ALTER TABLE chat_backgrounds ADD COLUMN IF NOT EXISTS opacity TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER angle;
