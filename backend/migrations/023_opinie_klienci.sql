-- Śledzenie próśb o opinię w Google (admin -> Opinie).
-- Google nie zdradza, kto wystawił opinię, więc: zapisujemy prośbę, klik
-- w przycisk z maila (/opinia?t=TOKEN przekierowuje do Google) i ręczne
-- potwierdzenie admina, gdy opinia jest już widoczna w wizytówce.
CREATE TABLE IF NOT EXISTS opinie_klienci (
    email          VARCHAR(190) NOT NULL PRIMARY KEY,
    user_name      VARCHAR(64)  NULL,
    token          CHAR(32)     NOT NULL,
    asked_at       DATETIME     NULL,
    clicked_at     DATETIME     NULL,
    clicks         INT UNSIGNED NOT NULL DEFAULT 0,
    potwierdzona_at DATETIME    NULL,
    UNIQUE KEY uq_opinie_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
