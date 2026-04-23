<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/zenscore.php';

function zenzone_get_checkin_dimension_fields(): array
{
    return array_keys(zenzone_labels());
}

function zenzone_get_checkin_by_id(PDO $pdo, int $userId, int $checkinId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
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
            activity_text,
            created_at
        FROM check_ins
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $checkinId,
        'user_id' => $userId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $labels = zenzone_labels();
    foreach ($labels as $field => $label) {
        $row[$field] = (int) ($row[$field] ?? 0);
    }

    $row['id'] = (int) ($row['id'] ?? 0);
    $row['user_id'] = (int) ($row['user_id'] ?? 0);
    $row['is_daily'] = (int) ($row['is_daily'] ?? 0);
    $row['entry_score'] = round((float) ($row['entry_score'] ?? 0), 2);
    $row['activity_text'] = trim((string) ($row['activity_text'] ?? ''));
    $row['checkin_type'] = ((int) $row['is_daily'] === 1) ? 'daily' : 'voluntary';

    return $row;
}

function zenzone_user_owns_checkin(PDO $pdo, int $userId, int $checkinId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM check_ins
        WHERE id = :id
          AND user_id = :user_id
    ");

    $stmt->execute([
        'id' => $checkinId,
        'user_id' => $userId,
    ]);

    return ((int) $stmt->fetchColumn()) > 0;
}

function zenzone_get_latest_checkin_id_for_user(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM check_ins
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute(['user_id' => $userId]);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        return null;
    }

    return (int) $id;
}

function zenzone_get_previous_checkin(PDO $pdo, int $userId, string $createdAt, int $currentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            id,
            checkin_date,
            created_at,
            entry_score
        FROM check_ins
        WHERE user_id = ?
          AND (
                created_at < ?
                OR (created_at = ? AND id < ?)
          )
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $userId,
        $createdAt,
        $createdAt,
        $currentId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['id'] = (int) ($row['id'] ?? 0);
    $row['entry_score'] = round((float) ($row['entry_score'] ?? 0), 2);

    return $row;
}

function zenzone_get_checkin_day_position(PDO $pdo, int $userId, array $checkin): array
{
    $checkinDate = (string) ($checkin['checkin_date'] ?? '');
    $createdAt = (string) ($checkin['created_at'] ?? '');
    $checkinId = (int) ($checkin['id'] ?? 0);

    if ($checkinDate === '' || $createdAt === '' || $checkinId <= 0) {
        return [
            'position' => 1,
            'total' => 1,
        ];
    }

    $totalStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
    ");
    $totalStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $checkinDate,
    ]);
    $total = max(1, (int) $totalStmt->fetchColumn());

    $beforeStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM check_ins
        WHERE user_id = ?
          AND checkin_date = ?
          AND (
                created_at < ?
                OR (created_at = ? AND id < ?)
          )
    ");
    $beforeStmt->execute([
        $userId,
        $checkinDate,
        $createdAt,
        $createdAt,
        $checkinId,
    ]);

    $position = ((int) $beforeStmt->fetchColumn()) + 1;

    return [
        'position' => $position,
        'total' => $total,
    ];
}

function zenzone_rank_checkin_dimensions(array $checkin): array
{
    $labels = zenzone_labels();
    $ranked = [];

    foreach ($labels as $field => $label) {
        $ranked[] = [
            'field' => $field,
            'label' => $label,
            'value' => (int) ($checkin[$field] ?? 0),
        ];
    }

    usort($ranked, static function (array $left, array $right): int {
        $valueDiff = ((int) ($left['value'] ?? 0)) <=> ((int) ($right['value'] ?? 0));
        if ($valueDiff !== 0) {
            return $valueDiff;
        }

        return strcmp((string) ($left['field'] ?? ''), (string) ($right['field'] ?? ''));
    });

    return $ranked;
}

function zenzone_build_checkin_summary(array $checkin): string
{
    $ranked = zenzone_rank_checkin_dimensions($checkin);
    $lowestA = $ranked[0] ?? null;
    $lowestB = $ranked[1] ?? null;

    if (!is_array($lowestA) || !is_array($lowestB)) {
        return 'Check-in saved successfully.';
    }

    $parts = [];
    $parts[] = 'Today\'s lowest areas were ' .
        (string) ($lowestA['label'] ?? 'an area') . ' (' . (int) ($lowestA['value'] ?? 0) . '/7) and ' .
        (string) ($lowestB['label'] ?? 'another area') . ' (' . (int) ($lowestB['value'] ?? 0) . '/7).';

    $activityText = trim((string) ($checkin['activity_text'] ?? ''));
    if ($activityText !== '') {
        $parts[] = 'Context: ' . zenzone_limit_text($activityText, 120) . '.';
    }

    return implode(' ', $parts);
}

function zenzone_build_checkin_recommendation_fit_reason(array $checkin): string
{
    $confidence = (int) ($checkin['confidence'] ?? 0);
    $emotionalBalance = (int) ($checkin['emotional_balance'] ?? 0);
    $recovery = (int) ($checkin['recovery'] ?? 0);
    $readiness = (int) ($checkin['readiness'] ?? 0);
    $mindfulness = (int) ($checkin['mindfulness'] ?? 0);

    if ($emotionalBalance <= 3 && $readiness <= 3) {
        return 'Your emotional balance and readiness were both low, so a fast downshift and refocus tool is the best match right now.';
    }

    if ($confidence <= 3) {
        return 'Your confidence score dipped, so the recommendation prioritizes composure and one clear execution cue.';
    }

    if ($recovery <= 3) {
        return 'Recovery came in low, so the recommendation focuses on a reset you can do without adding extra load.';
    }

    if ($mindfulness <= 3) {
        return 'Mindfulness was lower today, so this recommendation is aimed at settling attention before your next action.';
    }

    $ranked = zenzone_rank_checkin_dimensions($checkin);
    $lowestA = $ranked[0] ?? null;
    $lowestB = $ranked[1] ?? null;

    if (is_array($lowestA) && is_array($lowestB)) {
        return 'This fits your current state because your lowest areas were ' .
            (string) ($lowestA['label'] ?? 'one area') . ' and ' .
            (string) ($lowestB['label'] ?? 'another area') .
            ', and this tool helps stabilize both quickly.';
    }

    return 'This recommendation matches your latest check-in signals and gives you one practical next step.';
}

function zenzone_build_checkin_coach_input(array $checkin): array
{
    $confidence = (int) ($checkin['confidence'] ?? 4);
    $emotionalBalance = (int) ($checkin['emotional_balance'] ?? 4);
    $recovery = (int) ($checkin['recovery'] ?? 4);
    $readiness = (int) ($checkin['readiness'] ?? 4);
    $mindfulness = (int) ($checkin['mindfulness'] ?? 4);
    $energy = (int) ($checkin['energy'] ?? 4);
    $activityText = trim((string) ($checkin['activity_text'] ?? ''));

    $activityLower = strtolower($activityText);
    $performanceKeywords = ['game', 'match', 'race', 'meet', 'performance', 'competition', 'tournament', 'event'];

    $situationType = 'other';
    if (zenzone_text_contains_any($activityLower, $performanceKeywords) && $confidence <= 4) {
        $situationType = 'pre-performance nerves';
    } elseif ($confidence <= 3) {
        $situationType = 'confidence dip';
    } elseif ($emotionalBalance <= 3) {
        $situationType = 'frustration / anger';
    } elseif ($readiness <= 3 || $mindfulness <= 3) {
        $situationType = 'low focus';
    } elseif ($recovery <= 3 || $energy <= 3) {
        $situationType = 'post-practice reset';
    }

    $stressSignal = 8 - $emotionalBalance;
    if ($confidence <= 3) {
        $stressSignal += 1;
    }
    if ($readiness <= 3) {
        $stressSignal += 1;
    }
    $stressSignal = (int) max(1, min(7, $stressSignal));

    if ($stressSignal >= 6) {
        $stressLevel = 5;
    } elseif ($stressSignal >= 5) {
        $stressLevel = 4;
    } elseif ($stressSignal >= 4) {
        $stressLevel = 3;
    } elseif ($stressSignal >= 3) {
        $stressLevel = 2;
    } else {
        $stressLevel = 1;
    }

    $zenscore = (float) ($checkin['entry_score'] ?? 50);

    $timeAvailable = 3;
    if ($stressLevel >= 4) {
        $timeAvailable = 1;
    } elseif ($recovery <= 3 || $energy <= 3 || $zenscore < 45) {
        $timeAvailable = 5;
    }

    $ranked = zenzone_rank_checkin_dimensions($checkin);
    $lowestA = $ranked[0] ?? null;
    $lowestB = $ranked[1] ?? null;

    $situationTextParts = [];
    $situationTextParts[] = 'ZenScore ' . number_format($zenscore, 2) . '/100.';
    if (is_array($lowestA) && is_array($lowestB)) {
        $situationTextParts[] = 'Lowest areas: ' .
            (string) ($lowestA['label'] ?? 'One area') . ' ' . (int) ($lowestA['value'] ?? 0) . '/7 and ' .
            (string) ($lowestB['label'] ?? 'Another area') . ' ' . (int) ($lowestB['value'] ?? 0) . '/7.';
    }
    if ($activityText !== '') {
        $situationTextParts[] = 'Current context: ' . zenzone_limit_text($activityText, 180) . '.';
    }

    $upcomingEvent = '';
    if ($situationType === 'pre-performance nerves' && $activityText !== '') {
        $upcomingEvent = zenzone_limit_text($activityText, 120);
    }

    return [
        'situation_type' => $situationType,
        'time_available' => $timeAvailable,
        'stress_level' => $stressLevel,
        'situation_text' => zenzone_limit_text(implode(' ', $situationTextParts), 1200),
        'upcoming_event' => $upcomingEvent,
    ];
}

function zenzone_load_coach_engine_if_available(): bool
{
    if (
        function_exists('generateCoachResponse') &&
        function_exists('buildCoachRecommendationFromLesson') &&
        function_exists('resolveCoachLessonSlug') &&
        function_exists('getCoachLessonLookup')
    ) {
        return true;
    }

    $enginePath = __DIR__ . '/coach_engine.php';
    if (!is_file($enginePath)) {
        return false;
    }

    require_once $enginePath;

    return (
        function_exists('generateCoachResponse') &&
        function_exists('buildCoachRecommendationFromLesson') &&
        function_exists('resolveCoachLessonSlug') &&
        function_exists('getCoachLessonLookup')
    );
}

function zenzone_generate_checkin_recommendations(array $checkin): array
{
    $coachInput = zenzone_build_checkin_coach_input($checkin);
    $fitReason = zenzone_build_checkin_recommendation_fit_reason($checkin);

    $response = null;

    if (zenzone_load_coach_engine_if_available()) {
        try {
            $response = generateCoachResponse($coachInput);
        } catch (Throwable $e) {
            error_log('Check-in recommendation adapter failed: ' . $e->getMessage());
        }
    }

    $topRecommendation = null;
    $alternatives = [];
    $summary = '';
    $coachMessage = '';
    $sourceMode = 'local_fallback';

    if (is_array($response)) {
        $summary = trim((string) ($response['summary'] ?? ''));
        $coachMessage = trim((string) ($response['coach_message'] ?? ''));
        $sourceMode = trim((string) ($response['source_mode'] ?? 'rule_based'));

        $candidateTop = $response['top_recommendation'] ?? null;
        if (is_array($candidateTop) && zenzone_recommendation_has_valid_slug($candidateTop)) {
            $topRecommendation = $candidateTop;
        }

        if (!empty($response['alternatives']) && is_array($response['alternatives'])) {
            foreach ($response['alternatives'] as $candidateAlternative) {
                if (!is_array($candidateAlternative) || !zenzone_recommendation_has_valid_slug($candidateAlternative)) {
                    continue;
                }

                if (($candidateAlternative['slug'] ?? '') === ($topRecommendation['slug'] ?? '')) {
                    continue;
                }

                $alreadyAdded = false;
                foreach ($alternatives as $existing) {
                    if (($existing['slug'] ?? '') === ($candidateAlternative['slug'] ?? '')) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                if ($alreadyAdded) {
                    continue;
                }

                $alternatives[] = $candidateAlternative;
                if (count($alternatives) >= 2) {
                    break;
                }
            }
        }
    }

    if ($topRecommendation === null || count($alternatives) < 2) {
        $fallback = zenzone_build_checkin_fallback_recommendations($checkin, $coachInput);

        if ($topRecommendation === null) {
            $topRecommendation = $fallback['top_recommendation'] ?? null;
            $sourceMode = 'local_fallback';
        }

        foreach (($fallback['alternatives'] ?? []) as $fallbackAlternative) {
            if (!is_array($fallbackAlternative) || !zenzone_recommendation_has_valid_slug($fallbackAlternative)) {
                continue;
            }

            if (($fallbackAlternative['slug'] ?? '') === ($topRecommendation['slug'] ?? '')) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($alternatives as $existing) {
                if (($existing['slug'] ?? '') === ($fallbackAlternative['slug'] ?? '')) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if ($alreadyAdded) {
                continue;
            }

            $alternatives[] = $fallbackAlternative;
            if (count($alternatives) >= 2) {
                break;
            }
        }

        if ($summary === '') {
            $summary = (string) ($fallback['summary'] ?? 'A short, practical reset is your best next step right now.');
        }

        if ($coachMessage === '') {
            $coachMessage = (string) ($fallback['coach_message'] ?? 'Start the tool now, then reassess how you feel.');
        }
    }

    if ($summary === '') {
        $summary = 'A short, practical reset is your best next step right now.';
    }

    if ($coachMessage === '') {
        $coachMessage = 'Start the recommendation now, then check whether your state feels better, same, or worse.';
    }

    return [
        'summary' => $summary,
        'fit_reason' => $fitReason,
        'top_recommendation' => $topRecommendation,
        'alternatives' => array_slice($alternatives, 0, 2),
        'coach_message' => $coachMessage,
        'source_mode' => $sourceMode,
    ];
}

function zenzone_checkin_dimension_to_slug_map(): array
{
    return [
        'mindfulness' => 'box-breathing-reset',
        'energy' => 'physiological-sigh-reset',
        'connectedness' => 'post-practice-reflection',
        'motivation' => 'narrow-the-focus',
        'confidence' => 'confidence-cue-routine',
        'emotional_balance' => 're-center-after-frustration',
        'recovery' => 'post-practice-reflection',
        'readiness' => 'pre-performance-grounding',
    ];
}

function zenzone_get_lesson_lookup(): array
{
    $lookup = [];

    foreach (getLessonCatalog() as $lesson) {
        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $lookup[$slug] = $lesson;
    }

    return $lookup;
}

function zenzone_get_lesson_slug_fallback_map(): array
{
    return [
        'box-breathing-reset' => ['physiological-sigh-reset', '60-second-body-scan', 'narrow-the-focus'],
        'pre-performance-grounding' => ['confidence-cue-routine', 'box-breathing-reset', 'visualization-for-the-next-rep'],
        'confidence-cue-routine' => ['pre-performance-grounding', 'visualization-for-the-next-rep', 'narrow-the-focus'],
        'reset-after-a-mistake' => ['physiological-sigh-reset', 'narrow-the-focus', 're-center-after-frustration'],
        'physiological-sigh-reset' => ['box-breathing-reset', '60-second-body-scan', 're-center-after-frustration'],
        'narrow-the-focus' => ['60-second-body-scan', 'box-breathing-reset', 'confidence-cue-routine'],
        '60-second-body-scan' => ['narrow-the-focus', 'box-breathing-reset', 'physiological-sigh-reset'],
        're-center-after-frustration' => ['physiological-sigh-reset', 'box-breathing-reset', 'narrow-the-focus'],
        'visualization-for-the-next-rep' => ['confidence-cue-routine', 'pre-performance-grounding', 'narrow-the-focus'],
        'post-practice-reflection' => ['60-second-body-scan', 'box-breathing-reset', 'narrow-the-focus'],
    ];
}

function zenzone_resolve_lesson_slug(string $slug, array $lessonLookup): ?string
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    if (function_exists('resolveCoachLessonSlug')) {
        $resolved = resolveCoachLessonSlug($slug, $lessonLookup);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    if (isset($lessonLookup[$slug])) {
        return $slug;
    }

    $fallbackMap = zenzone_get_lesson_slug_fallback_map();
    foreach (($fallbackMap[$slug] ?? []) as $fallbackSlug) {
        if (isset($lessonLookup[$fallbackSlug])) {
            return $fallbackSlug;
        }
    }

    return null;
}

function zenzone_build_recommendation_from_lesson(array $lesson, array $coachInput): array
{
    if (function_exists('buildCoachRecommendationFromLesson')) {
        return buildCoachRecommendationFromLesson($lesson, $coachInput);
    }

    $upcomingEvent = trim((string) ($coachInput['upcoming_event'] ?? ''));
    $steps = [];

    if (!empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        foreach ($lesson['try_now_steps'] as $step) {
            $stepText = zenzone_limit_text((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps)) {
        $steps = [
            'Settle your breathing for one full cycle.',
            'Pick one focus cue for the next rep.',
            'Execute the next action at controlled pace.',
        ];
    }

    $whenToUse = zenzone_limit_text((string) ($lesson['when_to_use'] ?? ''), 220);
    if ($upcomingEvent !== '') {
        $whenToUse = zenzone_limit_text('Use this before ' . $upcomingEvent . '. ' . $whenToUse, 220);
    }

    return [
        'slug' => trim((string) ($lesson['slug'] ?? '')),
        'title' => zenzone_limit_text((string) ($lesson['title'] ?? ''), 90),
        'why_this_works' => zenzone_limit_text((string) ($lesson['why_this_works'] ?? ''), 220),
        'when_to_use' => $whenToUse,
        'steps' => array_slice($steps, 0, 5),
        'duration_minutes' => max(1, (int) ($lesson['duration_minutes'] ?? 1)),
        'evidence_note' => zenzone_limit_text((string) ($lesson['evidence_note'] ?? ''), 180),
    ];
}

function zenzone_build_checkin_fallback_recommendations(array $checkin, array $coachInput): array
{
    $lessonCatalog = getLessonCatalog();
    $lessonLookup = zenzone_get_lesson_lookup();
    $dimensionMap = zenzone_checkin_dimension_to_slug_map();

    $selectedSlugs = [];
    foreach (zenzone_rank_checkin_dimensions($checkin) as $dimension) {
        $field = (string) ($dimension['field'] ?? '');
        if (!isset($dimensionMap[$field])) {
            continue;
        }

        $resolvedSlug = zenzone_resolve_lesson_slug((string) $dimensionMap[$field], $lessonLookup);
        if ($resolvedSlug === null || in_array($resolvedSlug, $selectedSlugs, true)) {
            continue;
        }

        $selectedSlugs[] = $resolvedSlug;
        if (count($selectedSlugs) >= 3) {
            break;
        }
    }

    if (count($selectedSlugs) < 3) {
        foreach ($lessonCatalog as $lesson) {
            $slug = trim((string) ($lesson['slug'] ?? ''));
            if ($slug === '' || in_array($slug, $selectedSlugs, true)) {
                continue;
            }

            $selectedSlugs[] = $slug;
            if (count($selectedSlugs) >= 3) {
                break;
            }
        }
    }

    $recommendations = [];
    foreach ($selectedSlugs as $slug) {
        if (!isset($lessonLookup[$slug])) {
            continue;
        }

        $recommendations[] = zenzone_build_recommendation_from_lesson($lessonLookup[$slug], $coachInput);
    }

    return [
        'summary' => 'Your lowest check-in dimensions suggest starting with one short reset focused on regulation and attention.',
        'coach_message' => 'Run the recommended reset now, then notice whether your state is better, same, or worse.',
        'top_recommendation' => $recommendations[0] ?? null,
        'alternatives' => array_slice($recommendations, 1, 2),
    ];
}

function zenzone_recommendation_has_valid_slug(array $recommendation): bool
{
    $slug = trim((string) ($recommendation['slug'] ?? ''));
    if ($slug === '') {
        return false;
    }

    return getLessonBySlug($slug) !== null;
}

function zenzone_normalize_trend_range(string $range): string
{
    $normalized = strtolower(trim($range));

    if (in_array($normalized, ['7d', '30d', 'all'], true)) {
        return $normalized;
    }

    return '30d';
}

function zenzone_get_trend_range_start_date(string $range): ?string
{
    if ($range === '7d') {
        return date('Y-m-d', strtotime('-6 days'));
    }

    if ($range === '30d') {
        return date('Y-m-d', strtotime('-29 days'));
    }

    return null;
}

function zenzone_get_trend_points(PDO $pdo, int $userId, string $range): array
{
    $range = zenzone_normalize_trend_range($range);
    $startDate = zenzone_get_trend_range_start_date($range);

    $query = "
        SELECT
            summary_date,
            daily_score,
            mindfulness_avg,
            confidence_avg,
            recovery_avg,
            readiness_avg,
            emotional_balance_avg,
            checkin_count
        FROM daily_zenscore_summary
        WHERE user_id = :user_id
    ";

    $params = ['user_id' => $userId];

    if ($startDate !== null) {
        $query .= ' AND summary_date >= :start_date';
        $params['start_date'] = $startDate;
    }

    $query .= ' ORDER BY summary_date ASC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $fallbackQuery = "
            SELECT
                checkin_date AS summary_date,
                AVG(entry_score) AS daily_score,
                AVG(mindfulness) AS mindfulness_avg,
                AVG(confidence) AS confidence_avg,
                AVG(recovery) AS recovery_avg,
                AVG(readiness) AS readiness_avg,
                AVG(emotional_balance) AS emotional_balance_avg,
                COUNT(*) AS checkin_count
            FROM check_ins
            WHERE user_id = :user_id
        ";

        if ($startDate !== null) {
            $fallbackQuery .= ' AND checkin_date >= :start_date';
        }

        $fallbackQuery .= ' GROUP BY checkin_date ORDER BY checkin_date ASC';

        $fallbackStmt = $pdo->prepare($fallbackQuery);
        $fallbackStmt->execute($params);
        $rows = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $points = [];

    foreach ($rows as $row) {
        $emotionalBalance = round((float) ($row['emotional_balance_avg'] ?? 0), 2);
        $stressProxy = round(8 - $emotionalBalance, 2);
        $stressProxy = max(1, min(7, $stressProxy));

        $points[] = [
            'date' => (string) ($row['summary_date'] ?? ''),
            'zenscore' => round((float) ($row['daily_score'] ?? 0), 2),
            'confidence' => round((float) ($row['confidence_avg'] ?? 0), 2),
            'recovery' => round((float) ($row['recovery_avg'] ?? 0), 2),
            'focus' => round((float) ($row['readiness_avg'] ?? 0), 2),
            'stress' => $stressProxy,
            'checkin_count' => (int) ($row['checkin_count'] ?? 0),
        ];
    }

    return $points;
}

function zenzone_get_trend_overview_metrics(PDO $pdo, int $userId): array
{
    $aggregateStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_checkins,
            COUNT(DISTINCT checkin_date) AS active_days,
            AVG(entry_score) AS average_zenscore
        FROM check_ins
        WHERE user_id = :user_id
    ");
    $aggregateStmt->execute(['user_id' => $userId]);
    $aggregate = $aggregateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $latestStmt = $pdo->prepare("
        SELECT
            id,
            entry_score,
            checkin_date
        FROM check_ins
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $latestStmt->execute(['user_id' => $userId]);
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    return [
        'total_checkins' => (int) ($aggregate['total_checkins'] ?? 0),
        'active_days' => (int) ($aggregate['active_days'] ?? 0),
        'average_zenscore' => isset($aggregate['average_zenscore']) && $aggregate['average_zenscore'] !== null
            ? round((float) $aggregate['average_zenscore'], 2)
            : null,
        'latest_zenscore' => is_array($latest)
            ? round((float) ($latest['entry_score'] ?? 0), 2)
            : null,
        'latest_checkin_id' => is_array($latest)
            ? (int) ($latest['id'] ?? 0)
            : null,
        'latest_checkin_date' => is_array($latest)
            ? (string) ($latest['checkin_date'] ?? '')
            : null,
    ];
}

function zenzone_limit_text(string $text, int $maxLength): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $text));

    if ($clean === '' || $maxLength < 8 || strlen($clean) <= $maxLength) {
        return $clean;
    }

    return rtrim(substr($clean, 0, $maxLength - 3)) . '...';
}

function zenzone_text_contains_any(string $text, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($text, $needle) !== false) {
            return true;
        }
    }

    return false;
}

