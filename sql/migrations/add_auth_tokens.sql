CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    selector CHAR(16) NOT NULL,            -- public lookup key (hex)
    validator_hash CHAR(64) NOT NULL,      -- SHA-256 hex of the validator secret
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_tokens_selector (selector),
    KEY idx_auth_tokens_user (user_id),
    KEY idx_auth_tokens_expires (expires_at),
    CONSTRAINT fk_auth_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
