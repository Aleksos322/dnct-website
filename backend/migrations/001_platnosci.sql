-- ---------------------------------------------------------------------------
-- Płatności Stripe: zamówienia, idempotencja webhooka, kupony.
--
-- BAZA: `dnct` (MY_DB_*), czyli WŁASNA baza strony — nie `panel`.
-- Baza Pterodactyla jest z tej strony tylko do odczytu (patrz CLAUDE.md).
-- Stara wersja pisała zamówienia prosto do `panel.rented_servers`, w dodatku
-- wyłączając na czas zapisu kontrolę kluczy obcych (`SET FOREIGN_KEY_CHECKS=0`).
-- Zamówienie jest zdarzeniem handlowym strony, nie bytem panelu gry.
--
-- KWOTY SĄ W GROSZACH (INT), nie w złotówkach zmiennoprzecinkowych. Stripe
-- liczy tak samo, więc nie ma tu ani jednego zaokrąglenia w tę i z powrotem.
-- `0.1 + 0.2 != 0.3` to nie ciekawostka, tylko reklamacja od klienta.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS orders (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Kto kupuje. Strona nie ma własnej tabeli kont — tożsamość trzyma
    -- Authentik, a wspólnym mianownikiem z Pterodactylem jest ADRES E-MAIL
    -- (tak samo dopasowuje konta rozszerzenie SSO panelu).
    user_email              VARCHAR(190) NOT NULL,
    user_name               VARCHAR(190) NOT NULL,
    ptero_user_id           INT UNSIGNED NULL,

    -- Co kupuje. Parametry są KOPIOWANE do zamówienia, a nie tylko wskazywane
    -- przez plan_id: cennik się zmienia, a zamówienie sprzed pół roku ma
    -- pozostać czytelne razem z tym, co wtedy obiecaliśmy.
    game_type               VARCHAR(50)  NOT NULL,
    plan_id                 INT          NULL,
    slots                   INT UNSIGNED NULL,
    ram_mb                  INT UNSIGNED NOT NULL,
    cpu_cores               DECIMAL(5,2) NOT NULL,
    ssd_mb                  INT UNSIGNED NOT NULL,
    backups_limit           INT UNSIGNED NOT NULL DEFAULT 0,

    period_days             INT UNSIGNED NOT NULL,
    billing_mode            ENUM('payment','subscription') NOT NULL,
    amount_gross            INT UNSIGNED NOT NULL,
    currency                CHAR(3) NOT NULL DEFAULT 'pln',

    status                  ENUM('pending','paid','provisioned','failed','cancelled') NOT NULL DEFAULT 'pending',
    failure_reason          VARCHAR(500) NULL,

    -- UNIQUE na sesji Stripe'a to druga (po tabeli zdarzeń) blokada przed
    -- podwójnym założeniem serwera z jednej płatności.
    stripe_session_id       VARCHAR(255) NULL,
    stripe_customer_id      VARCHAR(255) NULL,
    stripe_subscription_id  VARCHAR(255) NULL,

    ptero_server_id         INT UNSIGNED NULL,
    ptero_server_uuid       VARCHAR(64)  NULL,

    expires_at              DATETIME NULL,
    grace_until             DATETIME NULL,

    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_session (stripe_session_id),
    KEY idx_email (user_email),
    KEY idx_subscription (stripe_subscription_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Idempotencja webhooka.
--
-- Stripe POWTARZA doręczenia — przy każdym błędzie i przy przekroczeniu czasu
-- odpowiedzi, nawet jeśli zdarzenie zostało już obsłużone. Bez tej tabeli
-- jedna płatność potrafi założyć trzy serwery.
--
-- Wiersz zakładamy PRZED obsługą (`processing`), nie po. Dzięki temu drugie
-- doręczenie tego samego zdarzenia odbija się o klucz główny nawet wtedy, gdy
-- pierwsze jeszcze się liczy.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stripe_events (
    event_id    VARCHAR(255) PRIMARY KEY,
    type        VARCHAR(100) NOT NULL,
    status      ENUM('processing','done','error') NOT NULL DEFAULT 'processing',
    error       VARCHAR(500) NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at  DATETIME NULL,
    KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Kupony.
--
-- ŹRÓDŁEM PRAWDY JEST STRIPE, nie ta tabela. Zniżkę nalicza Checkout, a kod
-- sprawdza Stripe — my trzymamy tylko odbicie, żeby dało się zrobić listę
-- w panelu bez odpytywania API przy każdym odświeżeniu strony.
--
-- Własna implementacja rabatów oznaczałaby liczenie kwoty po naszej stronie
-- i podawanie Stripe'owi gotowej ceny — czyli dokładnie ten wariant, w którym
-- błąd w mnożeniu zamienia się w realną stratę.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(80)  NOT NULL,
    stripe_coupon_id  VARCHAR(255) NOT NULL,
    stripe_promo_id   VARCHAR(255) NOT NULL,

    percent_off       DECIMAL(5,2) NULL,
    amount_off        INT UNSIGNED NULL,
    currency          CHAR(3)      NULL,

    duration          ENUM('once','repeating','forever') NOT NULL DEFAULT 'once',
    duration_months   INT UNSIGNED NULL,

    max_redemptions   INT UNSIGNED NULL,
    times_redeemed    INT UNSIGNED NOT NULL DEFAULT 0,
    redeem_by         DATETIME NULL,
    active            TINYINT(1)   NOT NULL DEFAULT 1,

    created_by        VARCHAR(190) NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_code (code),
    KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
