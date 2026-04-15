-- ZenZone migration
-- Purpose: allow multiple check-ins per goal on the same day.
-- Safe to re-run on MariaDB (XAMPP) because IF EXISTS / IF NOT EXISTS is used.

USE zenzone;

ALTER TABLE goal_checkins
    ADD KEY IF NOT EXISTS idx_goal_checkins_goal_user_date (goal_id, user_id, checkin_date);

ALTER TABLE goal_checkins
    DROP INDEX IF EXISTS uq_goal_checkins_goal_user_date;
