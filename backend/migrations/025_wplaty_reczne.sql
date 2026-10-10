-- Wpłaty spoza Stripe'a wpisywane ręcznie w adminie (Rentowność), np. za
-- Jellyfina. Liczą się do przychodu obok salda Stripe'a. Kwota w groszach.
CREATE TABLE IF NOT EXISTS wplaty_reczne (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kwota_gr   INT          NOT NULL,
    data       DATE         NOT NULL,
    zrodlo     VARCHAR(60)  NOT NULL,
    opis       VARCHAR(255) NULL,
    dodal      VARCHAR(120) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wplaty_reczne_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
