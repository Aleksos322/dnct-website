CREATE TABLE IF NOT EXISTS chat_presence (
 user_id VARCHAR(191) NOT NULL,
 client_id CHAR(64) NOT NULL,
 visible TINYINT(1) NOT NULL DEFAULT 0,
 last_active_at DATETIME NOT NULL,
 seen_at DATETIME NOT NULL,
 PRIMARY KEY (user_id,client_id),
 KEY presence_seen (seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
