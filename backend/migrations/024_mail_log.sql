-- Dziennik wysłanych maili (admin -> Maile). Zapisuje send_mail() w backend/mail/mail.php:
-- każdą próbę, z wynikiem i treścią do podglądu. Treść kasowana po 90 dniach
-- (backend/tools/mail-log-cleanup.php), wiersz zostaje.
CREATE TABLE IF NOT EXISTS mail_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email   VARCHAR(254) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    profile    VARCHAR(32)  NOT NULL DEFAULT 'default',
    ok         TINYINT(1)   NOT NULL,
    error      VARCHAR(500) NULL,
    html       MEDIUMTEXT   NULL,
    zrodlo     VARCHAR(120) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mail_log_czas (created_at),
    KEY idx_mail_log_to (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
