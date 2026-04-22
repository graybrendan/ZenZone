CREATE DATABASE IF NOT EXISTS zenzone
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE zenzone;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(60) NOT NULL DEFAULT '',
    sport VARCHAR(80) NOT NULL DEFAULT '',
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    baseline_complete TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS baseline_assessments (
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
    CONSTRAINT chk_baseline_score_ranges CHECK (
        mindfulness BETWEEN 1 AND 7 AND
        energy BETWEEN 1 AND 7 AND
        connectedness BETWEEN 1 AND 7 AND
        motivation BETWEEN 1 AND 7 AND
        confidence BETWEEN 1 AND 7 AND
        emotional_balance BETWEEN 1 AND 7 AND
        recovery BETWEEN 1 AND 7 AND
        readiness BETWEEN 1 AND 7 AND
        baseline_score BETWEEN 1.00 AND 7.00
    ),
    UNIQUE KEY uq_baseline_user (user_id),
    CONSTRAINT fk_baseline_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS check_ins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    is_daily TINYINT(1) NOT NULL DEFAULT 0,
    daily_anchor_user_id INT UNSIGNED AS (IF(is_daily = 1, user_id, NULL)) STORED,
    daily_anchor_date DATE AS (IF(is_daily = 1, checkin_date, NULL)) STORED,
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
    CONSTRAINT chk_checkins_score_ranges CHECK (
        mindfulness BETWEEN 1 AND 7 AND
        energy BETWEEN 1 AND 7 AND
        connectedness BETWEEN 1 AND 7 AND
        motivation BETWEEN 1 AND 7 AND
        confidence BETWEEN 1 AND 7 AND
        emotional_balance BETWEEN 1 AND 7 AND
        recovery BETWEEN 1 AND 7 AND
        readiness BETWEEN 1 AND 7 AND
        entry_score BETWEEN 0.00 AND 100.00
    ),
    UNIQUE KEY uq_checkins_daily_anchor (daily_anchor_user_id, daily_anchor_date),
    KEY idx_checkins_user_date (user_id, checkin_date),
    KEY idx_checkins_user_date_daily (user_id, checkin_date, is_daily),
    CONSTRAINT fk_checkins_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS daily_zenscore_summary (
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


CREATE TABLE IF NOT EXISTS goals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NULL,
    cadence_number INT UNSIGNED NOT NULL DEFAULT 1,
    cadence_unit ENUM('day', 'week', 'month') NOT NULL DEFAULT 'day',
    cadence_type ENUM('daily', 'weekly', 'monthly', 'custom') NOT NULL,
    status ENUM('active', 'paused', 'completed') NOT NULL DEFAULT 'active',
    is_priority TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_goals_id_user (id, user_id),
    KEY idx_goals_user_status (user_id, status),
    KEY idx_goals_user_cadence_priority (user_id, cadence_type, is_priority),
    CONSTRAINT chk_goals_cadence_consistency CHECK (
        (cadence_type = 'daily'   AND cadence_number = 1 AND cadence_unit = 'day') OR
        (cadence_type = 'weekly'  AND cadence_number = 1 AND cadence_unit = 'week') OR
        (cadence_type = 'monthly' AND cadence_number = 1 AND cadence_unit = 'month') OR
        (
            cadence_type = 'custom' AND NOT (
                (cadence_number = 1 AND cadence_unit = 'day') OR
                (cadence_number = 1 AND cadence_unit = 'week') OR
                (cadence_number = 1 AND cadence_unit = 'month')
            )
        )
    ),
    CONSTRAINT fk_goals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS goal_checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goal_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    checkin_date DATE NOT NULL,
    is_complete TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_goal_checkins_goal_user_date (goal_id, user_id, checkin_date),
    KEY idx_goal_checkins_user_date (user_id, checkin_date),
    CONSTRAINT fk_goal_checkins_goal_user
        FOREIGN KEY (goal_id, user_id) REFERENCES goals(id, user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DELIMITER $$

CREATE TRIGGER IF NOT EXISTS bi_goal_checkins_enforce_window_cap
BEFORE INSERT ON goal_checkins
FOR EACH ROW
BEGIN
    DECLARE v_cadence_number INT UNSIGNED DEFAULT NULL;
    DECLARE v_cadence_unit VARCHAR(10) DEFAULT NULL;
    DECLARE v_window_start DATE;
    DECLARE v_window_end DATE;
    DECLARE v_existing_count INT UNSIGNED DEFAULT 0;

    SELECT g.cadence_number, g.cadence_unit
      INTO v_cadence_number, v_cadence_unit
      FROM goals g
     WHERE g.id = NEW.goal_id
       AND g.user_id = NEW.user_id
     LIMIT 1
     FOR UPDATE;

    IF v_cadence_number IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Goal not found for check-in.';
    END IF;

    IF v_cadence_number < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Goal cadence_number must be at least 1.';
    END IF;

    IF v_cadence_unit = 'week' THEN
        SET v_window_start = DATE_SUB(NEW.checkin_date, INTERVAL WEEKDAY(NEW.checkin_date) DAY);
        SET v_window_end = DATE_ADD(v_window_start, INTERVAL 6 DAY);
    ELSEIF v_cadence_unit = 'month' THEN
        SET v_window_start = DATE_SUB(NEW.checkin_date, INTERVAL (DAYOFMONTH(NEW.checkin_date) - 1) DAY);
        SET v_window_end = LAST_DAY(NEW.checkin_date);
    ELSE
        SET v_window_start = NEW.checkin_date;
        SET v_window_end = NEW.checkin_date;
    END IF;

    SELECT COUNT(*)
      INTO v_existing_count
      FROM goal_checkins gc
     WHERE gc.goal_id = NEW.goal_id
       AND gc.user_id = NEW.user_id
       AND gc.checkin_date BETWEEN v_window_start AND v_window_end;

    IF v_existing_count >= v_cadence_number THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Check-in limit reached for this cadence window.';
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS bu_goal_checkins_enforce_window_cap
BEFORE UPDATE ON goal_checkins
FOR EACH ROW
BEGIN
    DECLARE v_cadence_number INT UNSIGNED DEFAULT NULL;
    DECLARE v_cadence_unit VARCHAR(10) DEFAULT NULL;
    DECLARE v_window_start DATE;
    DECLARE v_window_end DATE;
    DECLARE v_existing_count INT UNSIGNED DEFAULT 0;

    SELECT g.cadence_number, g.cadence_unit
      INTO v_cadence_number, v_cadence_unit
      FROM goals g
     WHERE g.id = NEW.goal_id
       AND g.user_id = NEW.user_id
     LIMIT 1
     FOR UPDATE;

    IF v_cadence_number IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Goal not found for check-in.';
    END IF;

    IF v_cadence_number < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Goal cadence_number must be at least 1.';
    END IF;

    IF v_cadence_unit = 'week' THEN
        SET v_window_start = DATE_SUB(NEW.checkin_date, INTERVAL WEEKDAY(NEW.checkin_date) DAY);
        SET v_window_end = DATE_ADD(v_window_start, INTERVAL 6 DAY);
    ELSEIF v_cadence_unit = 'month' THEN
        SET v_window_start = DATE_SUB(NEW.checkin_date, INTERVAL (DAYOFMONTH(NEW.checkin_date) - 1) DAY);
        SET v_window_end = LAST_DAY(NEW.checkin_date);
    ELSE
        SET v_window_start = NEW.checkin_date;
        SET v_window_end = NEW.checkin_date;
    END IF;

    SELECT COUNT(*)
      INTO v_existing_count
      FROM goal_checkins gc
     WHERE gc.goal_id = NEW.goal_id
       AND gc.user_id = NEW.user_id
       AND gc.checkin_date BETWEEN v_window_start AND v_window_end
       AND gc.id <> OLD.id;

    IF v_existing_count >= v_cadence_number THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Check-in limit reached for this cadence window.';
    END IF;
END$$

DELIMITER ;
