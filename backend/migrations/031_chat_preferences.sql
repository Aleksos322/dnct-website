CREATE TABLE IF NOT EXISTS chat_preferences (
 conversation_id BIGINT UNSIGNED NOT NULL,
 user_id VARCHAR(191) NOT NULL,
 muted_until DATETIME NULL,
 mute_forever TINYINT(1) NOT NULL DEFAULT 0,
 archived TINYINT(1) NOT NULL DEFAULT 0,
 hidden TINYINT(1) NOT NULL DEFAULT 0,
 blocked TINYINT(1) NOT NULL DEFAULT 0,
 marked_unread TINYINT(1) NOT NULL DEFAULT 0,
 PRIMARY KEY(conversation_id,user_id),
 CONSTRAINT chat_preferences_conversation FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
