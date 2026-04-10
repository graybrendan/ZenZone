CREATE DATABASE IF NOT EXISTS zenzone
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE zenzone;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS daily_zenscore_summary;
DROP TABLE IF EXISTS check_ins;
DROP TABLE IF EXISTS baseline_assessments;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    baseline_complete TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE baseline_assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    mindfulness TINYINT UNSIGNED NOT NULL,
    energy TINYINT UNSIGNED NOT NULL,
    connectedness TINYINT UNSIGNED NOT NULL,
    motivation TINYINT UNSIGNED NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL,
    emotional_balance TINYINT UNSIGNED NOT NULL,
    recovery TINYINT UNSIGNED NOT NULL,
    readiness TINYINT UNSIGNED NOT NULL,
    baseline_score DECIMAL(4,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_baseline_user (user_id),
    CONSTRAINT fk_baseline_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE check_ins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    is_daily TINYINT(1) NOT NULL DEFAULT 0,
    mindfulness TINYINT UNSIGNED NOT NULL,
    energy TINYINT UNSIGNED NOT NULL,
    connectedness TINYINT UNSIGNED NOT NULL,
    motivation TINYINT UNSIGNED NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL,
    emotional_balance TINYINT UNSIGNED NOT NULL,
    recovery TINYINT UNSIGNED NOT NULL,
    readiness TINYINT UNSIGNED NOT NULL,
    entry_score DECIMAL(5,2) NOT NULL,
    activity_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_checkins_user_date (user_id, checkin_date),
    KEY idx_checkins_user_date_daily (user_id, checkin_date, is_daily),
    CONSTRAINT fk_checkins_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_zenscore_summary (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    summary_date DATE NOT NULL,
    morning_anchor_score DECIMAL(5,2) NOT NULL,
    daily_score DECIMAL(5,2) NOT NULL,
    checkin_count INT UNSIGNED NOT NULL DEFAULT 0,
    mindfulness_avg DECIMAL(4,2) NOT NULL,
    energy_avg DECIMAL(4,2) NOT NULL,
    connectedness_avg DECIMAL(4,2) NOT NULL,
    motivation_avg DECIMAL(4,2) NOT NULL,
    confidence_avg DECIMAL(4,2) NOT NULL,
    emotional_balance_avg DECIMAL(4,2) NOT NULL,
    recovery_avg DECIMAL(4,2) NOT NULL,
    readiness_avg DECIMAL(4,2) NOT NULL,
    insight_text TEXT NULL,
    recommendation_key VARCHAR(100) NULL,
    recommendation_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_daily_summary_user_date (user_id, summary_date),
    KEY idx_daily_summary_user_date (user_id, summary_date),
    CONSTRAINT fk_daily_summary_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
