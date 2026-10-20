-- Dodatkowe stany i interakcje dla wspólnego czatu supportu (beta + panel admina).
-- Migrację należy zastosować w obu bazach obsługiwanych przez panel wsparcia.

ALTER TABLE support_tickets
  ADD COLUMN IF NOT EXISTS closed_by_user TINYINT(1) NOT NULL DEFAULT 0 AFTER closed_at,
  ADD COLUMN IF NOT EXISTS close_reason VARCHAR(500) NULL AFTER closed_by_user;

CREATE TABLE IF NOT EXISTS support_message_reactions (
  message_id BIGINT(20) UNSIGNED NOT NULL,
  participant_role ENUM('customer','admin') NOT NULL,
  participant_key CHAR(64) NOT NULL,
  emoji VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, participant_role, participant_key, emoji),
  KEY ix_support_message_reactions_message (message_id, emoji),
  CONSTRAINT fk_support_message_reactions_message
    FOREIGN KEY (message_id) REFERENCES support_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
