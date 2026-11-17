-- Historia edycji i cofnięć wiadomości (widoczna wyłącznie dla administratora DNCT)
-- oraz znacznik cichego usunięcia przez admina (niewidoczne dla pozostałych).
ALTER TABLE chat_messages
  ADD COLUMN IF NOT EXISTS silent_at DATETIME NULL AFTER withdrawn_at;

CREATE TABLE IF NOT EXISTS chat_message_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  message_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('edit','withdraw') NOT NULL,
  body MEDIUMTEXT NOT NULL,
  actor_id VARCHAR(191) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY revisions_by_message (message_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
