-- ---------------------------------------------------------------------------
-- Statystyki odwiedzin stron i panelu gier (panel admina: /statystyki).
--
-- Bez ciasteczek i bez adresów IP: `gosc` to skrót HMAC (IP + przeglądarka +
-- miesiąc) z tajną solą STATS_SALT - pozwala policzyć unikalnych gości w
-- miesiącu, ale nie da się z niego odtworzyć adresu. Kraj z nagłówka
-- Cloudflare (CF-IPCountry). Numer 017, bo 012-016 rezerwują migracje czatu.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS odwiedziny (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    czas         DATETIME        NOT NULL,
    host         VARCHAR(64)     NOT NULL,
    sciezka      VARCHAR(255)    NOT NULL,
    status       SMALLINT        NOT NULL DEFAULT 200,
    gosc         CHAR(16)        NOT NULL,
    kraj         CHAR(2)         NULL,
    przegladarka VARCHAR(24)     NULL,
    system       VARCHAR(16)     NULL,
    urzadzenie   VARCHAR(10)     NULL,
    zrodlo       VARCHAR(100)    NULL,
    robot        TINYINT(1)      NOT NULL DEFAULT 0,
    bajty        INT UNSIGNED    NULL,
    KEY idx_host_czas (host, czas),
    KEY idx_czas (czas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
