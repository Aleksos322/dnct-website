-- Powiadomienia systemowe (na komputerze), wariant "karta otwarta".
-- Preferencja jest per KONTO, nie per domena: zgoda przeglądarki i localStorage
-- są osobne dla game.dnct.pl, panel.dnct.pl i beta.dnct.pl, więc przełącznik
-- trzymany w przeglądarce "wyłączał się" po przejściu do panelu.
CREATE TABLE IF NOT EXISTS notification_desktop_prefs (
  username VARCHAR(64) NOT NULL,
  enabled TINYINT(1) NULL,              -- NULL = nie wybrano (włączone, jeśli przeglądarka zezwala)
  snoozed_until DATETIME NULL,          -- "Nie teraz" w prośbie o zgodę
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Jedna karta pokazuje dane powiadomienie: otwarte naraz strona i panel to dwie
-- domeny, które nie widzą swojego localStorage. Pierwszy INSERT wygrywa.
CREATE TABLE IF NOT EXISTS notification_desktop_claims (
  username VARCHAR(64) NOT NULL,
  item_key CHAR(64) NOT NULL,           -- sha256 klucza ("n:123", "chat:4:567")
  claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (username, item_key),
  KEY desktop_claims_age (claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
