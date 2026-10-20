CREATE TABLE IF NOT EXISTS notification_attachments (
 id CHAR(32) NOT NULL PRIMARY KEY,
 storage_key CHAR(64) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 mime_type VARCHAR(64) NOT NULL,
 byte_size INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_attachment_links (
 notification_id BIGINT UNSIGNED NOT NULL,
 attachment_id CHAR(32) NOT NULL,
 PRIMARY KEY(notification_id,attachment_id), INDEX(attachment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
