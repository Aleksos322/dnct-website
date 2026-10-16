-- Nowa oferta nie nadpisuje dotychczasowych zamówień i subskrypcji.
CREATE TABLE IF NOT EXISTS hosting_trial_claims (
    account_key VARCHAR(190) NOT NULL,
    user_email VARCHAR(190) NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    checkout_expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    ends_at DATETIME NULL,
    reminder_sent_at DATETIME NULL,
    PRIMARY KEY (account_key),
    UNIQUE KEY hosting_trial_email (user_email),
    UNIQUE KEY hosting_trial_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
