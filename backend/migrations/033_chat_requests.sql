CREATE TABLE IF NOT EXISTS chat_requests (
 conversation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 requester_id VARCHAR(191) NOT NULL,
 state ENUM('draft','pending','accepted','rejected') NOT NULL DEFAULT 'draft',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY request_state(state,requester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
