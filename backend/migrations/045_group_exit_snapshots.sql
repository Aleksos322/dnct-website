CREATE TABLE IF NOT EXISTS chat_group_exit_snapshots (
 conversation_id BIGINT UNSIGNED NOT NULL,
 user_id VARCHAR(191) NOT NULL,
 last_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 exited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 inbox_json MEDIUMTEXT NULL,
 PRIMARY KEY(conversation_id,user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
