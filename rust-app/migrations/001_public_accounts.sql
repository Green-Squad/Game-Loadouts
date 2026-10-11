-- Additive Rust tables only. Apply once with a schema owner, never at app startup.
-- Existing users, loadouts and votes remain shared with Laravel.
CREATE TABLE IF NOT EXISTS rust_sessions (
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 user_email VARCHAR(128) NULL,
 credential_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 csrf CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX(user_email), INDEX(expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rust_account_tokens (
 user_email VARCHAR(128) NOT NULL,
 purpose VARCHAR(16) NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 PRIMARY KEY(user_email,purpose), UNIQUE(token_hash), INDEX(expires_at)
) ENGINE=InnoDB;
