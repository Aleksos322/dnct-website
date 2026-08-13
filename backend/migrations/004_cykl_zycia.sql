-- ---------------------------------------------------------------------------
-- Cykl życia serwera po wygaśnięciu opłaty.
--
-- Do tej pory stan wynikał wyłącznie z dat (`expires_at`, `grace_until`), a
-- jedyną akcją było zawieszenie. To wystarczało, dopóki nic nie wysyłało
-- ostrzeżeń i nic nie kasowało serwerów.
--
-- Teraz cron przechodzi przez cztery etapy - ostrzeżenie na 7 dni, ostrzeżenie
-- na 24 h, zawieszenie, usunięcie - i KAŻDY z nich musi wiedzieć, czy już się
-- wydarzył. Bez tych znaczników cron chodzący co godzinę wysyłałby to samo
-- ostrzeżenie 168 razy w tygodniu.
--
-- Znaczniki są datami, nie flagami `0/1`: przy reklamacji trzeba umieć
-- odpowiedzieć nie tylko "czy wysłano", ale "kiedy".
-- ---------------------------------------------------------------------------

ALTER TABLE orders
    ADD COLUMN warn_7d_at   DATETIME NULL DEFAULT NULL AFTER grace_until,
    ADD COLUMN warn_24h_at  DATETIME NULL DEFAULT NULL AFTER warn_7d_at,
    ADD COLUMN suspended_at DATETIME NULL DEFAULT NULL AFTER warn_24h_at,
    ADD COLUMN deleted_at   DATETIME NULL DEFAULT NULL AFTER suspended_at,
    ADD KEY idx_lifecycle (status, expires_at),
    ADD KEY idx_suspended (suspended_at, deleted_at);

-- Zamówienia sprzed tej zmiany: serwery zawieszone ręcznie nie mają daty i
-- nie powinny nagle wpaść w automat kasujący. NULL w `suspended_at` znaczy
-- "cron tego nie zawieszał", więc etap usuwania ich nie dotknie.

-- ---------------------------------------------------------------------------
-- Archiwum plików skasowanego serwera.
--
-- Klient dostaje paczkę z serwerem, zanim maszyna zniknie. Paczki mają realnie
-- 300-400 MB, więc NIE idą mailem jako załącznik - mail niesie link, a plik
-- leży u nas przez ustalony czas.
--
-- `token` jest jedynym kluczem do pliku, dlatego ma być losowy i unikalny.
-- Sam plik leży POZA DocumentRootem; ta tabela jest jedynym miejscem, które
-- łączy token ze ścieżką.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS server_archives (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id         INT UNSIGNED NOT NULL,
    ptero_server_id  INT UNSIGNED NOT NULL,

    -- Backup po stronie panelu. Trzymamy go, dopóki plik nie jest u nas -
    -- po pobraniu i skasowaniu serwera zostaje tylko jako ślad w historii.
    backup_uuid      VARCHAR(64)  NULL DEFAULT NULL,

    -- Ścieżka względem katalogu archiwów (ARCHIVE_DIR), nie pełna - inaczej
    -- przeniesienie katalogu unieważniłoby wszystkie wpisy naraz.
    filename         VARCHAR(255) NULL DEFAULT NULL,
    bytes            BIGINT UNSIGNED NOT NULL DEFAULT 0,

    -- 'requested' -> backup zamówiony w panelu
    -- 'ready'     -> plik pobrany na nasz dysk, link można wysłać
    -- 'failed'    -> nie udało się; etap usuwania MUSI się o to zatrzymać
    -- 'purged'    -> plik skasowany po terminie
    status           ENUM('requested','ready','failed','purged') NOT NULL DEFAULT 'requested',
    error            VARCHAR(500) NULL DEFAULT NULL,

    token            CHAR(64) NOT NULL,
    expires_at       DATETIME NOT NULL,
    downloaded_at    DATETIME NULL DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token),
    KEY idx_order (order_id),
    KEY idx_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
