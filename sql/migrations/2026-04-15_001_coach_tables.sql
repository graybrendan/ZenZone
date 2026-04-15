-- ZenZone migration
-- Purpose: add persistence tables for Coach threads, messages, and outcomes.

USE zenzone;

CREATE TABLE IF NOT EXISTS coach_threads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    thread_title VARCHAR(150) NOT NULL,
    summary VARCHAR(220) NOT NULL,
    situation_text TEXT NOT NULL,
    situation_type VARCHAR(50) NOT NULL,
    time_available TINYINT UNSIGNED NOT NULL,
    stress_level TINYINT UNSIGNED NOT NULL,
    upcoming_event VARCHAR(120) NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_coach_threads_user_recent (user_id, archived, last_message_at),
    CONSTRAINT fk_coach_threads_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coach_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    sender ENUM('user', 'ai', 'system') NOT NULL,
    content TEXT NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_coach_messages_thread_created (thread_id, created_at),
    KEY idx_coach_messages_thread_sender (thread_id, sender),
    CONSTRAINT fk_coach_messages_thread
        FOREIGN KEY (thread_id) REFERENCES coach_threads(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coach_outcomes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    outcome ENUM('better', 'same', 'worse') NOT NULL,
    reflection_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_coach_outcomes_thread_user (thread_id, user_id),
    KEY idx_coach_outcomes_user_created (user_id, created_at),
    CONSTRAINT fk_coach_outcomes_thread
        FOREIGN KEY (thread_id) REFERENCES coach_threads(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_coach_outcomes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
