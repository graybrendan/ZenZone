-- ZenZone migration
-- Purpose: enforce score ranges at DB level to match app validation.
-- Safe to re-run on MariaDB (XAMPP) due IF EXISTS on dropped constraints.

USE zenzone;

-- Normalize existing data so range checks can be added safely.
UPDATE baseline_assessments
SET
    mindfulness = LEAST(7, GREATEST(1, mindfulness)),
    energy = LEAST(7, GREATEST(1, energy)),
    connectedness = LEAST(7, GREATEST(1, connectedness)),
    motivation = LEAST(7, GREATEST(1, motivation)),
    confidence = LEAST(7, GREATEST(1, confidence)),
    emotional_balance = LEAST(7, GREATEST(1, emotional_balance)),
    recovery = LEAST(7, GREATEST(1, recovery)),
    readiness = LEAST(7, GREATEST(1, readiness)),
    baseline_score = LEAST(7.00, GREATEST(1.00, baseline_score));

UPDATE check_ins
SET
    mindfulness = LEAST(7, GREATEST(1, mindfulness)),
    energy = LEAST(7, GREATEST(1, energy)),
    connectedness = LEAST(7, GREATEST(1, connectedness)),
    motivation = LEAST(7, GREATEST(1, motivation)),
    confidence = LEAST(7, GREATEST(1, confidence)),
    emotional_balance = LEAST(7, GREATEST(1, emotional_balance)),
    recovery = LEAST(7, GREATEST(1, recovery)),
    readiness = LEAST(7, GREATEST(1, readiness)),
    entry_score = LEAST(100.00, GREATEST(0.00, entry_score));

ALTER TABLE baseline_assessments
    DROP CONSTRAINT IF EXISTS chk_baseline_score_ranges;

ALTER TABLE baseline_assessments
    ADD CONSTRAINT chk_baseline_score_ranges CHECK (
        mindfulness BETWEEN 1 AND 7 AND
        energy BETWEEN 1 AND 7 AND
        connectedness BETWEEN 1 AND 7 AND
        motivation BETWEEN 1 AND 7 AND
        confidence BETWEEN 1 AND 7 AND
        emotional_balance BETWEEN 1 AND 7 AND
        recovery BETWEEN 1 AND 7 AND
        readiness BETWEEN 1 AND 7 AND
        baseline_score BETWEEN 1.00 AND 7.00
    );

ALTER TABLE check_ins
    DROP CONSTRAINT IF EXISTS chk_checkins_score_ranges;

ALTER TABLE check_ins
    ADD CONSTRAINT chk_checkins_score_ranges CHECK (
        mindfulness BETWEEN 1 AND 7 AND
        energy BETWEEN 1 AND 7 AND
        connectedness BETWEEN 1 AND 7 AND
        motivation BETWEEN 1 AND 7 AND
        confidence BETWEEN 1 AND 7 AND
        emotional_balance BETWEEN 1 AND 7 AND
        recovery BETWEEN 1 AND 7 AND
        readiness BETWEEN 1 AND 7 AND
        entry_score BETWEEN 0.00 AND 100.00
    );
