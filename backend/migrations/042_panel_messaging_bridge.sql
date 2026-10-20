CREATE TABLE IF NOT EXISTS panel_messaging_nonces (
 nonce CHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 expires_at DATETIME NOT NULL,
 INDEX idx_panel_messaging_nonce_expiry (expires_at)
) ENGINE=InnoDB;
