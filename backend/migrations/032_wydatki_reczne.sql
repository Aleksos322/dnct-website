-- Wydatki wpisywane ręcznie w adminie (Rentowność): sprzęt, domeny, łącze...
-- Odejmowane od bilansu miesiąca obok kosztu prądu. Kwota w groszach.
CREATE TABLE IF NOT EXISTS wydatki_reczne (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kwota_gr   INT          NOT NULL,
    data       DATE         NOT NULL,
    kategoria  VARCHAR(60)  NOT NULL,
    opis       VARCHAR(255) NULL,
    dodal      VARCHAR(120) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wydatki_reczne_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
