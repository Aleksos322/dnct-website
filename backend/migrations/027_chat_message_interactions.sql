-- Akcje dostępne dla prywatnych rozmów na beta i w panelu administratora.
ALTER TABLE chat_messages
  ADD COLUMN IF NOT EXISTS edited_at DATETIME NULL AFTER body,
  ADD COLUMN IF NOT EXISTS withdrawn_at DATETIME NULL AFTER edited_at,
  ADD COLUMN IF NOT EXISTS reply_to_message_id BIGINT UNSIGNED NULL AFTER withdrawn_at,
  ADD KEY IF NOT EXISTS reply_target (reply_to_message_id);

CREATE TABLE IF NOT EXISTS chat_message_reactions (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  emoji VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id, emoji),
  KEY reactions_by_message (message_id, emoji)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_message_hides (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id),
  KEY hides_by_user (user_id, message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
