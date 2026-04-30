<?php

function zenzone_labels(): array
{
    return [
        'mindfulness' => 'Mindfulness',
        'energy' => 'Energy',
        'connectedness' => 'Connectedness',
        'motivation' => 'Motivation',
        'confidence' => 'Confidence',
        'emotional_balance' => 'Emotional Balance',
        'recovery' => 'Recovery',
        'readiness' => 'Readiness',
    ];
}

function zenzone_validate_scores(array $input): array
{
    $scores = [];

    foreach (array_keys(zenzone_labels()) as $field) {
        if (!isset($input[$field])) {
            throw new InvalidArgumentException('Missing required score.');
        }

        $value = (int) $input[$field];

        if ($value < 1 || $value > 7) {
            throw new InvalidArgumentException('Invalid score value.');
        }

        $scores[$field] = $value;
    }

    return $scores;
}

function zenzone_calculate_raw_average(array $scores): float
{
    return round(array_sum($scores) / count($scores), 2);
}

function zenzone_convert_to_zenscore(float $rawAverage): float
{
    return round((($rawAverage - 1) / 6) * 100, 2);
}

function zenzone_determine_checkin_type(PDO $pdo, int $userId, string $checkinDate): string
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
    ");

    $stmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
    ]);

    $count = (int) $stmt->fetchColumn();

    return $count === 0 ? 'daily' : 'voluntary';
}

function zenzone_insert_checkin(PDO $pdo, int $userId, array $scores, ?string $activityContext = null): array
{
    $checkinDate = date('Y-m-d');
    $checkinType = zenzone_determine_checkin_type($pdo, $userId, $checkinDate);
    $isDaily = $checkinType === 'daily' ? 1 : 0;
    $rawAverage = zenzone_calculate_raw_average($scores);
    $zenscore = zenzone_convert_to_zenscore($rawAverage);

    $activityText = trim((string) $activityContext);
    if ($activityText === '') {
        $activityText = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO check_ins (
            user_id,
            checkin_date,
            is_daily,
            mindfulness,
            energy,
            connectedness,
            motivation,
            confidence,
            emotional_balance,
            recovery,
            readiness,
            entry_score,
            activity_text
        ) VALUES (
            :user_id,
            :checkin_date,
            :is_daily,
            :mindfulness,
            :energy,
            :connectedness,
            :motivation,
            :confidence,
            :emotional_balance,
            :recovery,
            :readiness,
            :entry_score,
            :activity_text
        )
    ");

    $stmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
        'is_daily' => $isDaily,
        'mindfulness' => $scores['mindfulness'],
        'energy' => $scores['energy'],
        'connectedness' => $scores['connectedness'],
        'motivation' => $scores['motivation'],
        'confidence' => $scores['confidence'],
        'emotional_balance' => $scores['emotional_balance'],
        'recovery' => $scores['recovery'],
        'readiness' => $scores['readiness'],
        'entry_score' => $zenscore,
        'activity_text' => $activityText,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'checkin_date' => $checkinDate,
        'checkin_type' => $checkinType,
        'zenscore' => $zenscore,
    ];
}

function zenzone_rebuild_daily_summary(PDO $pdo, int $userId, string $checkinDate): void
{
    $aggregateStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS checkin_count,
            AVG(entry_score) AS daily_score,
            AVG(mindfulness) AS mindfulness_avg,
            AVG(energy) AS energy_avg,
            AVG(connectedness) AS connectedness_avg,
            AVG(motivation) AS motivation_avg,
            AVG(confidence) AS confidence_avg,
            AVG(emotional_balance) AS emotional_balance_avg,
            AVG(recovery) AS recovery_avg,
            AVG(readiness) AS readiness_avg
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
    ");

    $aggregateStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
    ]);

    $aggregate = $aggregateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$aggregate || (int) $aggregate['checkin_count'] === 0) {
        return;
    }

    $anchorStmt = $pdo->prepare("
        SELECT entry_score
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
          AND is_daily = 1
        ORDER BY created_at ASC, id ASC
        LIMIT 1
    ");

    $anchorStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
    ]);

    $anchorScore = $anchorStmt->fetchColumn();

    if ($anchorScore === false) {
        // Fallback when older data has no explicit daily anchor.
        $anchorFallbackStmt = $pdo->prepare("
            SELECT entry_score
            FROM check_ins
            WHERE user_id = :user_id
              AND checkin_date = :checkin_date
            ORDER BY created_at ASC, id ASC
            LIMIT 1
        ");
        $anchorFallbackStmt->execute([
            'user_id' => $userId,
            'checkin_date' => $checkinDate,
        ]);

        $anchorScore = $anchorFallbackStmt->fetchColumn();
    }

    $morningAnchorScore = (float) $anchorScore;

    $latestStmt = $pdo->prepare("
        SELECT
            mindfulness,
            energy,
            connectedness,
            motivation,
            confidence,
            emotional_balance,
            recovery,
            readiness
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    $latestStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
    ]);

    $latestScores = $latestStmt->fetch(PDO::FETCH_ASSOC);

    $feedback = zenzone_get_feedback([
        'mindfulness' => (int) $latestScores['mindfulness'],
        'energy' => (int) $latestScores['energy'],
        'connectedness' => (int) $latestScores['connectedness'],
        'motivation' => (int) $latestScores['motivation'],
        'confidence' => (int) $latestScores['confidence'],
        'emotional_balance' => (int) $latestScores['emotional_balance'],
        'recovery' => (int) $latestScores['recovery'],
        'readiness' => (int) $latestScores['readiness'],
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO daily_zenscore_summary (
            user_id,
            summary_date,
            morning_anchor_score,
            daily_score,
            checkin_count,
            mindfulness_avg,
            energy_avg,
            connectedness_avg,
            motivation_avg,
            confidence_avg,
            emotional_balance_avg,
            recovery_avg,
            readiness_avg,
            insight_text,
            recommendation_key,
            recommendation_text
        ) VALUES (
            :user_id,
            :summary_date,
            :morning_anchor_score,
            :daily_score,
            :checkin_count,
            :mindfulness_avg,
            :energy_avg,
            :connectedness_avg,
            :motivation_avg,
            :confidence_avg,
            :emotional_balance_avg,
            :recovery_avg,
            :readiness_avg,
            :insight_text,
            :recommendation_key,
            :recommendation_text
        )
        ON DUPLICATE KEY UPDATE
            morning_anchor_score = VALUES(morning_anchor_score),
            daily_score = VALUES(daily_score),
            checkin_count = VALUES(checkin_count),
            mindfulness_avg = VALUES(mindfulness_avg),
            energy_avg = VALUES(energy_avg),
            connectedness_avg = VALUES(connectedness_avg),
            motivation_avg = VALUES(motivation_avg),
            confidence_avg = VALUES(confidence_avg),
            emotional_balance_avg = VALUES(emotional_balance_avg),
            recovery_avg = VALUES(recovery_avg),
            readiness_avg = VALUES(readiness_avg),
            insight_text = VALUES(insight_text),
            recommendation_key = VALUES(recommendation_key),
            recommendation_text = VALUES(recommendation_text)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'summary_date' => $checkinDate,
        'morning_anchor_score' => round($morningAnchorScore, 2),
        'daily_score' => round((float) $aggregate['daily_score'], 2),
        'checkin_count' => (int) $aggregate['checkin_count'],
        'mindfulness_avg' => round((float) $aggregate['mindfulness_avg'], 2),
        'energy_avg' => round((float) $aggregate['energy_avg'], 2),
        'connectedness_avg' => round((float) $aggregate['connectedness_avg'], 2),
        'motivation_avg' => round((float) $aggregate['motivation_avg'], 2),
        'confidence_avg' => round((float) $aggregate['confidence_avg'], 2),
        'emotional_balance_avg' => round((float) $aggregate['emotional_balance_avg'], 2),
        'recovery_avg' => round((float) $aggregate['recovery_avg'], 2),
        'readiness_avg' => round((float) $aggregate['readiness_avg'], 2),
        'insight_text' => $feedback['insight'],
        'recommendation_key' => $feedback['recommendation_key'],
        'recommendation_text' => $feedback['recommendation'],
    ]);
}

function zenzone_get_daily_summary(PDO $pdo, int $userId, string $checkinDate): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            d.summary_date,
            d.morning_anchor_score,
            d.daily_score AS daily_zenscore,
            d.checkin_count AS total_checkins,
            d.mindfulness_avg,
            d.energy_avg,
            d.connectedness_avg,
            d.motivation_avg,
            d.confidence_avg,
            d.emotional_balance_avg,
            d.recovery_avg,
            d.readiness_avg,
            d.insight_text,
            d.recommendation_text,
            (
                SELECT c.entry_score
                FROM check_ins c
                WHERE c.user_id = d.user_id
                  AND c.checkin_date = d.summary_date
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT 1
            ) AS latest_zenscore
        FROM daily_zenscore_summary d
        WHERE d.user_id = :user_id
          AND d.summary_date = :summary_date
        LIMIT 1
    ");

    $stmt->execute([
        'user_id' => $userId,
        'summary_date' => $checkinDate,
    ]);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    return $summary ?: null;
}

function zenzone_get_feedback(array $scores): array
{
    $labels = zenzone_labels();
    $rawAverage = zenzone_calculate_raw_average($scores);
    $zenscore = zenzone_convert_to_zenscore($rawAverage);

    $ascending = $scores;
    asort($ascending);
    $lowestField = array_key_first($ascending);
    $lowestValue = (int) $ascending[$lowestField];

    $descending = $scores;
    arsort($descending);
    $highestField = array_key_first($descending);
    $highestValue = (int) $descending[$highestField];

    if ($zenscore >= 80) {
        $headline = 'Strong check-in logged.';
    } elseif ($zenscore >= 60) {
        $headline = 'Solid check-in logged.';
    } else {
        $headline = 'Check-in logged.';
    }

    if (($highestValue - $lowestValue) <= 1) {
        $insight = 'Your check-in looks relatively balanced right now.';
    } else {
        $insight = 'Your strongest area right now is ' . $labels[$highestField] . ' and your lowest area is ' . $labels[$lowestField] . '.';
    }

    $recommendationKey = $lowestField;
    $recommendation = zenzone_recommendation_text($recommendationKey);

    return [
        'headline' => $headline,
        'insight' => $insight,
        'recommendation_key' => $recommendationKey,
        'recommendation' => $recommendation,
    ];
}

function zenzone_recommendation_text(string $field): string
{
    return match ($field) {
        'mindfulness' => 'Slow down and do a short breathing reset before your next activity.',
        'energy' => 'Choose a recovery-focused next step and reduce unnecessary load.',
        'connectedness' => 'Reach out to a teammate, coach, classmate, coworker, or friend before isolating.',
        'motivation' => 'Pick one small action and complete it before thinking bigger.',
        'confidence' => 'Refocus on one successful action and the next controllable action.',
        'emotional_balance' => 'Pause, ground yourself, and avoid reacting too quickly.',
        'recovery' => 'Prioritize recovery input before pushing intensity again.',
        'readiness' => 'Lower the pressure and focus on preparation before performance.',
        default => 'Take one small supportive action before your next task.',
    };
}
