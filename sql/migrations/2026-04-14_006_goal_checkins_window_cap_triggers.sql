-- ZenZone migration
-- Purpose: enforce per-window goal check-in caps at DB level to prevent race/manual SQL bypass.

USE zenzone;

DROP TRIGGER IF EXISTS bi_goal_checkins_enforce_window_cap;
DROP TRIGGER IF EXISTS bu_goal_checkins_enforce_window_cap;

DELIMITER $$

CREATE TRIGGER bi_goal_checkins_enforce_window_cap
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

CREATE TRIGGER bu_goal_checkins_enforce_window_cap
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
