-- Czat supportu (praca Kuby, 2026-10-01..03), zebrany w jedną migrację 2026-10-07.
-- Zastępuje jego 012_support..016_support_message_lifecycle_media (kolizja numeru 012
-- z 012_zgody_konsumenckie i plik 016 bez praw odczytu). Schemat zrzucony 1:1 z bazy
-- bety, gdzie czat działał. IF NOT EXISTS - bezpieczne na becie, gdzie tabele już są.
-- support_message_attachments: tabela jest, funkcja załączników nie jest wdrożona.

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(8) NOT NULL,
  `access_token_hash` char(64) NOT NULL,
  `owner_key` char(64) DEFAULT NULL,
  `active_owner_key` char(64) DEFAULT NULL,
  `request_key` char(64) DEFAULT NULL,
  `customer_username` varchar(64) DEFAULT NULL,
  `customer_name` varchar(60) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `category_key` varchar(80) NOT NULL,
  `category_label` varchar(190) NOT NULL,
  `status` enum('new','open','closed','archived') NOT NULL DEFAULT 'new',
  `assigned_admin` varchar(64) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `delete_after` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `ip_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_tickets_public_id` (`public_id`),
  UNIQUE KEY `uq_support_request_key` (`request_key`),
  UNIQUE KEY `uq_support_active_owner` (`active_owner_key`),
  KEY `ix_support_tickets_open` (`status`,`updated_at`),
  KEY `ix_support_tickets_customer` (`customer_username`,`updated_at`),
  KEY `ix_support_tickets_access` (`access_token_hash`,`updated_at`),
  KEY `ix_support_cleanup` (`status`,`delete_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `author_role` enum('customer','admin','system') NOT NULL,
  `author_name` varchar(60) NOT NULL,
  `body` text NOT NULL,
  `edited_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `withdrawn_by_role` enum('customer','admin') DEFAULT NULL,
  `withdrawn_by_key` char(64) DEFAULT NULL,
  `client_message_key` char(64) DEFAULT NULL,
  `reply_to_message_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_message_idempotency` (`ticket_id`,`client_message_key`),
  KEY `ix_support_messages_ticket` (`ticket_id`,`id`),
  KEY `ix_support_messages_reply` (`reply_to_message_id`),
  KEY `ix_support_messages_lifecycle` (`ticket_id`,`withdrawn_at`,`id`),
  CONSTRAINT `fk_support_messages_reply` FOREIGN KEY (`reply_to_message_id`) REFERENCES `support_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_support_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_ticket_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `actor_username` varchar(64) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_support_ticket_audit` (`ticket_id`,`id`),
  CONSTRAINT `fk_support_ticket_audit_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_message_receipts` (
  `ticket_id` bigint(20) unsigned NOT NULL,
  `participant_role` enum('customer','admin') NOT NULL,
  `participant_key` varchar(64) NOT NULL,
  `last_message_id` bigint(20) unsigned NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ticket_id`,`participant_role`,`participant_key`),
  KEY `ix_support_receipts_message` (`ticket_id`,`last_message_id`),
  KEY `fk_support_receipts_message` (`last_message_id`),
  CONSTRAINT `fk_support_receipts_message` FOREIGN KEY (`last_message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_receipts_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_realtime_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(32) NOT NULL,
  `payload` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_support_realtime_events_cursor` (`id`),
  KEY `ix_support_realtime_events_ticket` (`ticket_id`,`id`),
  CONSTRAINT `fk_support_realtime_events_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_realtime_tokens` (
  `token_hash` char(64) NOT NULL,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `participant_role` enum('customer','admin') NOT NULL,
  `participant_key` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_hash`),
  KEY `ix_support_realtime_tokens_expiry` (`expires_at`),
  KEY `ix_support_realtime_tokens_ticket` (`ticket_id`,`participant_role`),
  CONSTRAINT `fk_support_realtime_tokens_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_message_hides` (
  `message_id` bigint(20) unsigned NOT NULL,
  `participant_role` enum('customer','admin') NOT NULL,
  `participant_key` char(64) NOT NULL,
  `hidden_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`,`participant_role`,`participant_key`),
  KEY `ix_support_message_hides_viewer` (`participant_role`,`participant_key`,`message_id`),
  CONSTRAINT `fk_support_message_hides_message` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_message_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `storage_key` char(64) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(80) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `byte_size` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_message_attachment_storage` (`storage_key`),
  KEY `ix_support_message_attachments_message` (`message_id`,`id`),
  CONSTRAINT `fk_support_message_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

