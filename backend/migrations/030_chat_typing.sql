CREATE TABLE IF NOT EXISTS chat_typing (
 conversation_id BIGINT UNSIGNED NOT NULL,
 user_id VARCHAR(191) NOT NULL,
 expires_at DATETIME NOT NULL,
 PRIMARY KEY (conversation_id,user_id),
 CONSTRAINT chat_typing_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
