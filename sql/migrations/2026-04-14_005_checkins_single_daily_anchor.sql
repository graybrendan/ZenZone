-- ZenZone migration
-- Purpose: enforce only one daily anchor check-in per user per date.
-- Safe to re-run on MariaDB (XAMPP).

USE zenzone;

ALTER TABLE check_ins
    ADD COLUMN IF NOT EXISTS daily_anchor_user_id INT UNSIGNED AS (IF(is_daily = 1, user_id, NULL)) STORED,
    ADD COLUMN IF NOT EXISTS daily_anchor_date DATE AS (IF(is_daily = 1, checkin_date, NULL)) STORED;

-- Keep the earliest daily anchor and demote later duplicates to voluntary check-ins.
UPDATE check_ins ci
INNER JOIN (
    SELECT user_id, checkin_date, MIN(id) AS keep_id
    FROM check_ins
    WHERE is_daily = 1
    GROUP BY user_id, checkin_date
    HAVING COUNT(*) > 1
) dup
    ON dup.user_id = ci.user_id
   AND dup.checkin_date = ci.checkin_date
SET ci.is_daily = 0
WHERE ci.is_daily = 1
  AND ci.id <> dup.keep_id;

ALTER TABLE check_ins
    DROP INDEX IF EXISTS uq_checkins_daily_anchor;

ALTER TABLE check_ins
    ADD UNIQUE KEY uq_checkins_daily_anchor (daily_anchor_user_id, daily_anchor_date);
