-- Web Push (wariant B): powiadomienia systemowe także przy zamkniętej karcie.
-- Jedna subskrypcja na PRZEGLĄDARKĘ (device_id = ciasteczko dnct_dev na .dnct.pl,
-- wspólne dla game.dnct.pl, panel.dnct.pl i bety) - inaczej strona i panel
-- otwarte w tej samej przeglądarce dawałyby dwa powiadomienia.
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  device_id CHAR(32) NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  subscription TEXT NOT NULL,           -- endpoint + klucze p256dh/auth (JSON)
  site VARCHAR(8) NOT NULL,             -- 'prod' (game.dnct.pl/panel) albo 'beta'
  origin VARCHAR(100) NOT NULL,         -- skąd zapisano (adres do linków)
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY push_subscriptions_endpoint (endpoint_hash),
  UNIQUE KEY push_subscriptions_device (username, device_id),
  KEY push_subscriptions_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Co już poszło: klucz zdarzenia ("n:123", "chat:45", "ticket-prod:67").
CREATE TABLE IF NOT EXISTS push_sent (
  item_key VARCHAR(40) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (item_key),
  KEY push_sent_age (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kursory czatu i ticketów (rosnące id); przy pierwszym starcie ustawiane na bieżące maksimum.
CREATE TABLE IF NOT EXISTS push_cursors (
  name VARCHAR(32) NOT NULL,
  last_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
