-- ZenZone migration
-- Purpose: persist login rate-limit state outside PHP session storage.

USE zenzone;

CREATE TABLE IF NOT EXISTS login_rate_limits (
    rate_key CHAR(40) PRIMARY KEY,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_start INT UNSIGNED NOT NULL DEFAULT 0,
    lock_until INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_login_rate_limits_lock_until (lock_until),
    KEY idx_login_rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
