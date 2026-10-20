CREATE TABLE IF NOT EXISTS messaging_security_sessions (
 session_hash CHAR(64) PRIMARY KEY, subject_key CHAR(64) NOT NULL, username VARCHAR(191) NOT NULL,
 identity_fingerprint CHAR(64) NOT NULL, sid_hash CHAR(64) NOT NULL DEFAULT '', sub_hash CHAR(64) NOT NULL DEFAULT '',
 logged_at BIGINT NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL, revoked TINYINT NOT NULL DEFAULT 0,
 INDEX(subject_key)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_ws_grants (
 token_hash CHAR(64) PRIMARY KEY, session_hash CHAR(64) NOT NULL, scope VARCHAR(16) NOT NULL,
 user_id VARCHAR(191) NOT NULL DEFAULT '', ticket_id BIGINT NOT NULL DEFAULT 0, role VARCHAR(16) NOT NULL,
 participant_key VARCHAR(191) NOT NULL, expires_at DATETIME NOT NULL, consumed TINYINT NOT NULL DEFAULT 0,
 INDEX(session_hash), INDEX(expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_limits (
 bucket CHAR(64) PRIMARY KEY, hits MEDIUMTEXT NOT NULL, blocked_until BIGINT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_security_audit (
 event_key CHAR(64) PRIMARY KEY, event_type VARCHAR(48) NOT NULL, subject_key CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL, resource_id BIGINT NOT NULL DEFAULT 0, hits INT NOT NULL DEFAULT 1,
 occurred_at DATETIME NOT NULL, last_seen DATETIME NOT NULL, INDEX(occurred_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_media_usage (
 subject_key CHAR(64) PRIMARY KEY, stored_bytes BIGINT NOT NULL DEFAULT 0,
 day_key DATE NOT NULL, uploaded_bytes BIGINT NOT NULL DEFAULT 0, downloaded_bytes BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_media_ledger (
 module VARCHAR(16) NOT NULL, storage_key CHAR(64) NOT NULL, subject_key CHAR(64) NOT NULL,
 byte_size BIGINT NOT NULL, PRIMARY KEY(module,storage_key),INDEX(subject_key)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS messaging_transfer_leases (
 lease_id CHAR(32) PRIMARY KEY, subject_key CHAR(64) NOT NULL, kind VARCHAR(16) NOT NULL,
 expires_at DATETIME NOT NULL, INDEX(subject_key,kind,expires_at)
) ENGINE=InnoDB;
