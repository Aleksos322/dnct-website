-- ---------------------------------------------------------------------------
-- Kupony "na start" i program partnerski dla twórców (2026-10-06).
--
-- Kupon na start: tylko Coupon w Stripe, BEZ PromotionCode - kodu nie da się
-- wpisać na stronie płatności Stripe'a, przechodzi wyłącznie przez nasze pole
-- w zamówieniu, które sprawdza, czy konto nic wcześniej nie kupiło.
--
-- Partner: klient, który pierwszy raz kupi z jego kodem, zostaje do niego
-- przypisany na `okres_miesiecy`; od każdej płatności tego klienta w tym czasie
-- naliczamy `prowizja_procent` od kwoty zapłaconej (jeden wpis na płatność,
-- unikalny po `stripe_ref`). Zwrot w Stripe cofa prowizję (charge.refunded).
-- ---------------------------------------------------------------------------

ALTER TABLE coupons
    MODIFY stripe_promo_id VARCHAR(255) NULL,
    ADD COLUMN first_order_only TINYINT(1) NOT NULL DEFAULT 0 AFTER active,
    ADD COLUMN partner_id INT UNSIGNED NULL AFTER first_order_only;

ALTER TABLE orders ADD COLUMN promo_code VARCHAR(64) NULL AFTER kind;

CREATE TABLE IF NOT EXISTS partnerzy (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazwa            VARCHAR(80)   NOT NULL,
    kontakt          VARCHAR(160)  NULL,
    kanal_url        VARCHAR(255)  NULL,
    prowizja_procent DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    okres_miesiecy   TINYINT UNSIGNED NOT NULL DEFAULT 6,
    notatka          VARCHAR(500)  NULL,
    aktywny          TINYINT(1)    NOT NULL DEFAULT 1,
    utworzono        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_klienci (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    partner_id    INT UNSIGNED  NOT NULL,
    klient_email  VARCHAR(190)  NOT NULL,
    kod           VARCHAR(64)   NOT NULL,
    przypisano    DATETIME      NOT NULL,
    wygasa        DATETIME      NOT NULL,
    UNIQUE KEY uniq_klient (klient_email),
    KEY idx_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_prowizje (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    partner_id     INT UNSIGNED  NOT NULL,
    order_id       INT UNSIGNED  NULL,
    klient_email   VARCHAR(190)  NOT NULL,
    stripe_ref     VARCHAR(80)   NOT NULL,
    kwota_klienta  INT UNSIGNED  NOT NULL,
    prowizja       INT           NOT NULL,
    status         ENUM('naliczona','wyplacona','cofnieta') NOT NULL DEFAULT 'naliczona',
    utworzono      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    wyplacono      DATETIME      NULL,
    UNIQUE KEY uniq_ref (stripe_ref),
    KEY idx_partner_status (partner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_zgloszenia (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazwa      VARCHAR(80)   NOT NULL,
    email      VARCHAR(190)  NOT NULL,
    kanal_url  VARCHAR(255)  NOT NULL,
    widzowie   VARCHAR(40)   NULL,
    wiadomosc  VARCHAR(1500) NULL,
    konto      VARCHAR(80)   NULL,
    status     ENUM('nowe','przyjete','odrzucone') NOT NULL DEFAULT 'nowe',
    utworzono  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
