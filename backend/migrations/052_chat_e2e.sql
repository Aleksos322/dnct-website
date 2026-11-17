-- Szyfrowanie end-to-end w rozmowach 1:1 (2026-10-28).
-- chat_e2e_keys: publiczny klucz ECDH P-256 użytkownika (surowy punkt, base64) i opcjonalna kopia klucza
--   prywatnego zaszyfrowana hasłem w przeglądarce. Serwer nigdy nie widzi klucza prywatnego ani hasła.
-- chat_e2e_conversations: rozmowy 1:1 z włączonym szyfrowaniem. W takiej rozmowie serwer przyjmuje
--   wyłącznie koperty "e2e1:..." i nie przyjmuje załączników.
-- IF NOT EXISTS - bezpieczne przy ponownym uruchomieniu.

CREATE TABLE IF NOT EXISTS chat_e2e_keys (
  user_id VARCHAR(191) NOT NULL,
  public_key VARCHAR(120) NOT NULL,
  fingerprint CHAR(64) NOT NULL,
  backup TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_e2e_conversations (
  conversation_id BIGINT UNSIGNED NOT NULL,
  enabled_by VARCHAR(191) NOT NULL,
  enabled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
