-- ZenZone migration
-- Purpose: enforce goal_checkins ownership integrity with composite FK (goal_id, user_id) -> goals(id, user_id).
-- Safe to re-run on MariaDB (XAMPP) due IF EXISTS / IF NOT EXISTS.

USE zenzone;

ALTER TABLE goals
    ADD UNIQUE KEY IF NOT EXISTS uq_goals_id_user (id, user_id);

ALTER TABLE goal_checkins
    ADD KEY IF NOT EXISTS idx_goal_checkins_goal_user_date (goal_id, user_id, checkin_date);

-- If a duplicate would be created after normalizing user_id, keep one row and remove the conflicting extra row.
DELETE gc_bad
FROM goal_checkins gc_bad
INNER JOIN goals g
    ON g.id = gc_bad.goal_id
INNER JOIN goal_checkins gc_keep
    ON gc_keep.goal_id = gc_bad.goal_id
   AND gc_keep.checkin_date = gc_bad.checkin_date
   AND gc_keep.user_id = g.user_id
   AND gc_keep.id <> gc_bad.id
WHERE gc_bad.user_id <> g.user_id;

-- Normalize mismatched user_id values to the goal owner.
UPDATE goal_checkins gc
INNER JOIN goals g
    ON g.id = gc.goal_id
SET gc.user_id = g.user_id
WHERE gc.user_id <> g.user_id;

ALTER TABLE goal_checkins
    DROP FOREIGN KEY IF EXISTS fk_goal_checkins_goal_user;

ALTER TABLE goal_checkins
    DROP FOREIGN KEY IF EXISTS fk_goal_checkins_goal;

ALTER TABLE goal_checkins
    DROP FOREIGN KEY IF EXISTS fk_goal_checkins_user;

ALTER TABLE goal_checkins
    ADD CONSTRAINT fk_goal_checkins_goal_user
    FOREIGN KEY (goal_id, user_id) REFERENCES goals(id, user_id)
    ON DELETE CASCADE;
