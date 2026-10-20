-- Prywatne rozmowy bety i panelu administracyjnego; bez zmian w zgłoszeniach supportu.
CREATE TABLE IF NOT EXISTS chat_conversations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pair_key CHAR(64) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_members (
 conversation_id BIGINT UNSIGNED NOT NULL,
 user_id VARCHAR(191) NOT NULL,
 username VARCHAR(150) NOT NULL,
 display_name VARCHAR(150) NOT NULL,
 is_admin TINYINT(1) NOT NULL DEFAULT 0,
 last_read_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(conversation_id,user_id),
 KEY user_conversations(user_id,conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_messages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 conversation_id BIGINT UNSIGNED NOT NULL,
 author_id VARCHAR(191) NOT NULL,
 body TEXT NOT NULL,
 client_key CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY message_retry(author_id,client_key),
 KEY conversation_messages(conversation_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 recipient_id VARCHAR(191) NOT NULL,
 conversation_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY recipient_events(recipient_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_realtime_tokens (
 token_hash CHAR(64) NOT NULL PRIMARY KEY,
 user_id VARCHAR(191) NOT NULL,
 expires_at DATETIME NOT NULL,
 KEY token_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
