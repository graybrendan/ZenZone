-- ZenZone migration
-- Purpose: convert coach_threads into situation-centric records for Coach home/view/edit flows.

USE zenzone;

ALTER TABLE coach_threads
    ADD COLUMN IF NOT EXISTS summary VARCHAR(220) NULL AFTER thread_title,
    ADD COLUMN IF NOT EXISTS situation_text TEXT NULL AFTER summary,
    ADD COLUMN IF NOT EXISTS situation_type VARCHAR(50) NULL AFTER situation_text,
    ADD COLUMN IF NOT EXISTS time_available TINYINT UNSIGNED NULL AFTER situation_type,
    ADD COLUMN IF NOT EXISTS stress_level TINYINT UNSIGNED NULL AFTER time_available,
    ADD COLUMN IF NOT EXISTS upcoming_event VARCHAR(120) NULL AFTER stress_level;

UPDATE coach_threads
SET
    summary = COALESCE(NULLIF(summary, ''), thread_title),
    situation_text = COALESCE(NULLIF(situation_text, ''), thread_title),
    situation_type = COALESCE(NULLIF(situation_type, ''), 'other'),
    time_available = COALESCE(time_available, 3),
    stress_level = COALESCE(stress_level, 3)
WHERE 1 = 1;

ALTER TABLE coach_threads
    MODIFY COLUMN summary VARCHAR(220) NOT NULL,
    MODIFY COLUMN situation_text TEXT NOT NULL,
    MODIFY COLUMN situation_type VARCHAR(50) NOT NULL,
    MODIFY COLUMN time_available TINYINT UNSIGNED NOT NULL,
    MODIFY COLUMN stress_level TINYINT UNSIGNED NOT NULL,
    MODIFY COLUMN upcoming_event VARCHAR(120) NULL;
