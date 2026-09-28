-- ============================================================================
-- 008: zamówienia hostingu WWW (panel nad HestiaCP)
-- ============================================================================
-- Osobna tabela, a nie wiersze w `orders`: wszystko, co czyta `orders`
-- (cykl życia serwerów, twoje-serwery, reconcile, panel admina, zmiana pakietu),
-- zakłada serwer gry w Pterodactylu. Wiersz hostingu bez ptero_server_id
-- wywracałby każde z tych miejsc po kolei.
--
-- Sam przebieg jest kopią `orders`: zamówienie `pending` -> sesja Stripe ->
-- webhook (jedyne miejsce, w którym zmienia się pakiet) -> `provisioned`.
-- Konto hostingu istnieje wcześniej (jedno konto DNCT, pakiet `dnct-konto`) -
-- zakup tylko podnosi mu pakiet.
-- ============================================================================

CREATE TABLE IF NOT EXISTS hosting_orders (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_email              VARCHAR(190) NOT NULL,
    user_name               VARCHAR(190) NOT NULL,
    -- Nazwa użytkownika w Hestii - znana dopiero po webhooku.
    hosting_user            VARCHAR(64)  NULL,
    -- Kopia pakietu i ceny z chwili zakupu (cennik się zmienia).
    package                 VARCHAR(40)  NOT NULL,
    period_days             INT UNSIGNED NOT NULL,
    billing_mode            ENUM('payment','subscription') NOT NULL,
    amount_gross            INT UNSIGNED NOT NULL,
    currency                CHAR(3) NOT NULL DEFAULT 'pln',
    -- Przedłużenie wskazuje zamówienie, któremu dokłada dni.
    extends_order_id        INT UNSIGNED NULL,
    status                  ENUM('pending','paid','provisioned','failed','cancelled') NOT NULL DEFAULT 'pending',
    failure_reason          VARCHAR(500) NULL,
    stripe_session_id       VARCHAR(255) NULL,
    stripe_customer_id      VARCHAR(255) NULL,
    stripe_subscription_id  VARCHAR(255) NULL,
    expires_at              DATETIME NULL,
    grace_until             DATETIME NULL,
    warn_7d_at              DATETIME NULL,
    warn_24h_at             DATETIME NULL,
    suspended_at            DATETIME NULL,
    -- Po długim zawieszeniu admin dostaje sygnał "do decyzji" - automat
    -- NIE kasuje stron klienta (patrz hosting_lifecycle_run).
    stale_notified_at       DATETIME NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session (stripe_session_id),
    KEY idx_email (user_email),
    KEY idx_subscription (stripe_subscription_id),
    KEY idx_lifecycle (status, expires_at),
    KEY idx_extends (extends_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
