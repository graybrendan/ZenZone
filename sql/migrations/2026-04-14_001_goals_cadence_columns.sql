-- ZenZone migration
-- Purpose: add cadence columns to existing goals table without dropping data.

USE zenzone;

ALTER TABLE goals
    ADD COLUMN IF NOT EXISTS cadence_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER category,
    ADD COLUMN IF NOT EXISTS cadence_unit ENUM('day', 'week', 'month') NOT NULL DEFAULT 'day' AFTER cadence_number;

ALTER TABLE goals
    MODIFY COLUMN cadence_number INT UNSIGNED NOT NULL DEFAULT 1,
    MODIFY COLUMN cadence_unit ENUM('day', 'week', 'month') NOT NULL DEFAULT 'day';
