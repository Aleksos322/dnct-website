-- Poleć znajomemu (2026-10-06).
-- Klient z opłaconym serwerem dostaje własny kod "na start" (-10%, tylko pierwszy
-- zakup znajomego). Gdy znajomy zapłaci, polecający dostaje +7 dni do serwera.
-- Kupon to zwykły wiersz `coupons` z first_order_only = 1 i właścicielem
-- w `polecajacy_email`; nagrody i historia w `polecenia`.

ALTER TABLE coupons
    ADD COLUMN polecajacy_email VARCHAR(190) NULL AFTER partner_id,
    ADD KEY idx_polecajacy (polecajacy_email);

CREATE TABLE IF NOT EXISTS polecenia (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    polecajacy_email VARCHAR(190) NOT NULL,
    kod              VARCHAR(80)  NOT NULL,
    -- Znajomy liczy się raz: drugi zakup tego samego konta nie daje kolejnej nagrody.
    znajomy_email    VARCHAR(190) NOT NULL,
    order_id         INT UNSIGNED NULL,
    nagroda_order_id INT UNSIGNED NULL,
    nagroda_dni      SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    -- oczekuje = polecający nie miał aktywnego serwera, do przyznania ręcznie.
    status           ENUM('przyznana','oczekuje','blad') NOT NULL DEFAULT 'oczekuje',
    opis             VARCHAR(255) NULL,
    utworzono        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    przyznano        DATETIME NULL,
    UNIQUE KEY uniq_znajomy (znajomy_email),
    KEY idx_polecajacy (polecajacy_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
