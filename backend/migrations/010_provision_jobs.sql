-- ============================================================================
-- 010: kolejka zakładania kont w usługach (panel gier, hosting, Jellyfin)
-- ============================================================================
-- Rejestracja zapisuje tu po jednym zadaniu na usługę i od razu je wykonuje.
-- Jeśli usługa w tej chwili nie działa, zadanie zostaje "pending" i cykl
-- życia (backend/tools/lifecycle.php, etap `konta`, co godzinę) ponawia je
-- z rosnącym odstępem. Wcześniej Jellyfin zakładał się TYLKO przy rejestracji
-- - wyłączony serwer mediów w tej jednej chwili oznaczał konto bez Jellyfina
-- na zawsze, bez żadnego śladu poza logiem.
--
-- Jedno zadanie na (konto, usługa) - UNIQUE, więc powtórka rejestracji albo
-- ręczne ponowienie nie zakłada drugiego konta.
-- ============================================================================

CREATE TABLE IF NOT EXISTS provision_jobs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    authentik_sub   CHAR(64)     NOT NULL,
    username        VARCHAR(190) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    first_name      VARCHAR(100) NOT NULL DEFAULT '',
    last_name       VARCHAR(100) NOT NULL DEFAULT '',
    service         ENUM('pterodactyl','hosting','jellyfin') NOT NULL,
    status          ENUM('pending','done','failed') NOT NULL DEFAULT 'pending',
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    last_error      VARCHAR(500) NULL,
    next_try_at     DATETIME     NULL,
    done_at         DATETIME     NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_konto_usluga (authentik_sub, service),
    KEY idx_kolejka (status, next_try_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
