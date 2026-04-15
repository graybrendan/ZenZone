<?php

require_once __DIR__ . '/helpers.php';

function getCoachSituationTypes(): array
{
    return [
        'pre-performance nerves',
        'after mistake',
        'low focus',
        'frustration / anger',
        'confidence dip',
        'post-practice reset',
        'other',
    ];
}

function getCoachTimeOptions(): array
{
    return [1, 3, 5, 10];
}

function getCoachOutcomeOptions(): array
{
    return ['better', 'same', 'worse'];
}

function getCoachStorageRequiredThreadColumns(): array
{
    return [
        'summary',
        'situation_text',
        'situation_type',
        'time_available',
        'stress_level',
        'upcoming_event',
    ];
}

function isCoachStorageReady(PDO $db): bool
{
    $tableStmt = $db->query("
        SELECT COUNT(*) AS total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name IN ('coach_threads', 'coach_messages', 'coach_outcomes')
    ");

    $tableCount = (int) $tableStmt->fetchColumn();
    if ($tableCount !== 3) {
        return false;
    }

    $requiredColumns = getCoachStorageRequiredThreadColumns();
    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $columnStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'coach_threads'
          AND column_name IN ($placeholders)
    ");
    $columnStmt->execute($requiredColumns);
    $columnCount = (int) $columnStmt->fetchColumn();

    return $columnCount === count($requiredColumns);
}

function createCoachSituationSummary(string $situationText, int $maxLength = 180): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $situationText));

    if ($clean === '') {
        return 'Situation summary unavailable.';
    }

    if ($maxLength < 20) {
        $maxLength = 20;
    }

    if (strlen($clean) <= $maxLength) {
        return $clean;
    }

    return substr($clean, 0, $maxLength - 3) . '...';
}

function generateCoachResponse(array $input): array
{
    $normalizedInput = normalizeCoachInput($input);
    $combinedText = $normalizedInput['situation_text'] . ' ' . $normalizedInput['upcoming_event'];

    if (detectCoachCrisisLanguage($combinedText)) {
        return [
            'crisis_detected' => true,
            'crisis_message' => "I'm really glad you shared this. You deserve immediate, in-person support right now. If you might hurt yourself or are in immediate danger, call or text 988 now (US) or call 911.",
            'summary' => 'This sounds like a high-distress moment that needs immediate human support.',
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => 'Reach out to a trusted adult, teammate, coach, or family member right now and stay with them while you get support.',
            'source_mode' => 'rule_based',
        ];
    }

    $adapterResponse = generateCoachResponseFromAdapter($normalizedInput);
    if ($adapterResponse !== null) {
        return $adapterResponse;
    }

    return generateRuleBasedCoachResponse($normalizedInput);
}

function normalizeCoachInput(array $input): array
{
    $situationType = trim((string) ($input['situation_type'] ?? 'other'));
    if (!in_array($situationType, getCoachSituationTypes(), true)) {
        $situationType = 'other';
    }

    $timeAvailable = (int) ($input['time_available'] ?? 3);
    if (!in_array($timeAvailable, getCoachTimeOptions(), true)) {
        $timeAvailable = 3;
    }

    $stressLevel = (int) ($input['stress_level'] ?? 3);
    if ($stressLevel < 1 || $stressLevel > 5) {
        $stressLevel = 3;
    }

    $situationText = trim((string) ($input['situation_text'] ?? ''));
    if (strlen($situationText) > 1200) {
        $situationText = substr($situationText, 0, 1200);
    }

    $upcomingEvent = trim((string) ($input['upcoming_event'] ?? ''));
    if (strlen($upcomingEvent) > 120) {
        $upcomingEvent = substr($upcomingEvent, 0, 120);
    }

    return [
        'situation_type' => $situationType,
        'time_available' => $timeAvailable,
        'stress_level' => $stressLevel,
        'situation_text' => $situationText,
        'upcoming_event' => $upcomingEvent,
    ];
}

function detectCoachCrisisLanguage(string $text): bool
{
    $normalized = strtolower($text);
    $keywords = [
        'suicide',
        'suicidal',
        'kill myself',
        'end my life',
        'self harm',
        'self-harm',
        'hurt myself',
        'want to die',
        'don\'t want to live',
        'overdose',
        'cut myself',
        'can\'t go on',
        'panic attack and can\'t breathe',
    ];

    foreach ($keywords as $keyword) {
        if (strpos($normalized, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function generateCoachResponseFromAdapter(array $input): ?array
{
    $enabledRaw = defined('COACH_AI_ENABLED') ? (string) COACH_AI_ENABLED : (string) getenv('ZENZONE_COACH_AI_ENABLED');
    $isEnabled = in_array(strtolower(trim($enabledRaw)), ['1', 'true', 'yes', 'on'], true);
    if (!$isEnabled) {
        return null;
    }

    $endpoint = defined('COACH_AI_ENDPOINT') ? (string) COACH_AI_ENDPOINT : (string) getenv('ZENZONE_COACH_AI_ENDPOINT');
    $endpoint = trim($endpoint);
    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return null;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $apiToken = defined('COACH_AI_TOKEN') ? (string) COACH_AI_TOKEN : (string) getenv('ZENZONE_COACH_AI_TOKEN');

    $payload = [
        'assistant_role' => 'sports psychology + mindfulness support coach for athletes',
        'response_rules' => [
            'non_diagnostic',
            'non_clinical',
            'no_crisis_counseling',
            'athlete_friendly',
            'one_top_two_alternates',
            'prefer_existing_zenzone_lesson_slugs',
        ],
        'input' => $input,
    ];

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        return null;
    }

    $headers = ['Content-Type: application/json'];
    if ($apiToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiToken;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonPayload,
    ]);

    $rawResponse = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($rawResponse) || $rawResponse === '' || $httpStatus < 200 || $httpStatus >= 300) {
        return null;
    }

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        return null;
    }

    $candidate = $decoded;
    if (isset($decoded['response']) && is_array($decoded['response'])) {
        $candidate = $decoded['response'];
    }

    $normalized = normalizeExternalCoachResponse($candidate);
    if ($normalized === null) {
        return null;
    }

    $normalized['source_mode'] = 'external_ai';
    return $normalized;
}

function normalizeExternalCoachResponse(array $candidate): ?array
{
    $crisisDetected = !empty($candidate['crisis_detected']);
    $summary = trim((string) ($candidate['summary'] ?? ''));
    $coachMessage = trim((string) ($candidate['coach_message'] ?? ''));

    if ($summary === '' || $coachMessage === '') {
        return null;
    }

    if ($crisisDetected) {
        return [
            'crisis_detected' => true,
            'crisis_message' => trim((string) ($candidate['crisis_message'] ?? '')),
            'summary' => $summary,
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => $coachMessage,
            'source_mode' => 'external_ai',
        ];
    }

    $topRecommendation = normalizeExternalCoachRecommendation($candidate['top_recommendation'] ?? null);
    if ($topRecommendation === null) {
        return null;
    }

    $alternatives = [];
    if (!empty($candidate['alternatives']) && is_array($candidate['alternatives'])) {
        foreach ($candidate['alternatives'] as $item) {
            $normalizedAlt = normalizeExternalCoachRecommendation($item);
            if ($normalizedAlt !== null && $normalizedAlt['slug'] !== $topRecommendation['slug']) {
                $alternatives[] = $normalizedAlt;
            }

            if (count($alternatives) >= 2) {
                break;
            }
        }
    }

    return [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => $summary,
        'top_recommendation' => $topRecommendation,
        'alternatives' => $alternatives,
        'coach_message' => $coachMessage,
        'source_mode' => 'external_ai',
    ];
}

function normalizeExternalCoachRecommendation($candidate): ?array
{
    if (!is_array($candidate)) {
        return null;
    }

    $slug = trim((string) ($candidate['slug'] ?? ''));
    if ($slug === '') {
        return null;
    }

    $lesson = getLessonBySlug($slug);
    if ($lesson === null) {
        return null;
    }

    $steps = [];
    if (!empty($candidate['steps']) && is_array($candidate['steps'])) {
        foreach ($candidate['steps'] as $step) {
            $stepText = trim((string) $step);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps) && !empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        $steps = array_values(array_filter(array_map('strval', $lesson['try_now_steps']), static function (string $step): bool {
            return trim($step) !== '';
        }));
    }

    return [
        'slug' => $slug,
        'title' => trim((string) ($candidate['title'] ?? $lesson['title'] ?? '')),
        'why_this_works' => trim((string) ($candidate['why_this_works'] ?? $lesson['why_this_works'] ?? '')),
        'when_to_use' => trim((string) ($candidate['when_to_use'] ?? $lesson['when_to_use'] ?? '')),
        'steps' => $steps,
        'duration_minutes' => (int) ($candidate['duration_minutes'] ?? $lesson['duration_minutes'] ?? 0),
    ];
}

function generateRuleBasedCoachResponse(array $input): array
{
    $catalog = getLessonCatalog();
    $lessonsBySlug = [];
    $scores = [];

    foreach ($catalog as $lesson) {
        $slug = (string) ($lesson['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $lessonsBySlug[$slug] = $lesson;
        $scores[$slug] = 0;
    }

    $baseMap = getCoachTypeMapping();
    $baseSlugs = $baseMap[$input['situation_type']] ?? $baseMap['other'];

    foreach ($baseSlugs as $index => $slug) {
        if (isset($scores[$slug])) {
            $scores[$slug] += 300 - ($index * 25);
        }
    }

    applyCoachKeywordBoosts($scores, $input['situation_text'], $input['upcoming_event']);
    applyCoachStressBoosts($scores, $input['stress_level']);
    applyCoachTimeBoosts($scores, $catalog, $input['time_available']);
    applyCoachUpcomingEventBoosts($scores, $input['upcoming_event']);

    $rankedSlugs = array_keys($scores);
    usort($rankedSlugs, static function (string $a, string $b) use ($scores, $lessonsBySlug): int {
        $scoreDiff = ($scores[$b] ?? 0) <=> ($scores[$a] ?? 0);
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        $durationDiff = ((int) ($lessonsBySlug[$a]['duration_minutes'] ?? 0)) <=> ((int) ($lessonsBySlug[$b]['duration_minutes'] ?? 0));
        if ($durationDiff !== 0) {
            return $durationDiff;
        }

        return ((int) ($lessonsBySlug[$a]['sort_order'] ?? 0)) <=> ((int) ($lessonsBySlug[$b]['sort_order'] ?? 0));
    });

    $selectedSlugs = [];
    foreach ($rankedSlugs as $slug) {
        if (($scores[$slug] ?? 0) <= 0) {
            continue;
        }

        $selectedSlugs[] = $slug;
        if (count($selectedSlugs) >= 3) {
            break;
        }
    }

    if (count($selectedSlugs) < 3) {
        foreach ($baseSlugs as $slug) {
            if (!in_array($slug, $selectedSlugs, true) && isset($lessonsBySlug[$slug])) {
                $selectedSlugs[] = $slug;
            }
            if (count($selectedSlugs) >= 3) {
                break;
            }
        }
    }

    if (count($selectedSlugs) < 3) {
        foreach ($catalog as $lesson) {
            $slug = (string) ($lesson['slug'] ?? '');
            if ($slug !== '' && !in_array($slug, $selectedSlugs, true)) {
                $selectedSlugs[] = $slug;
            }

            if (count($selectedSlugs) >= 3) {
                break;
            }
        }
    }

    $recommendations = [];
    foreach (array_slice($selectedSlugs, 0, 3) as $slug) {
        if (!isset($lessonsBySlug[$slug])) {
            continue;
        }

        $recommendations[] = buildCoachRecommendationFromLesson(
            $lessonsBySlug[$slug],
            $input['upcoming_event'],
            $input['stress_level']
        );
    }

    $topRecommendation = $recommendations[0] ?? null;
    $alternatives = array_slice($recommendations, 1, 2);

    return [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => buildCoachSummary($input),
        'top_recommendation' => $topRecommendation,
        'alternatives' => $alternatives,
        'coach_message' => buildCoachMessage($topRecommendation, $input),
        'source_mode' => 'rule_based',
    ];
}

function getCoachTypeMapping(): array
{
    return [
        'pre-performance nerves' => [
            'box-breathing-reset',
            'pre-performance-grounding',
            'confidence-cue-routine',
        ],
        'after mistake' => [
            'reset-after-a-mistake',
            'physiological-sigh-reset',
            'narrow-the-focus',
        ],
        'low focus' => [
            'narrow-the-focus',
            '60-second-body-scan',
            'box-breathing-reset',
        ],
        'frustration / anger' => [
            're-center-after-frustration',
            'physiological-sigh-reset',
            'post-practice-reflection',
        ],
        'confidence dip' => [
            'confidence-cue-routine',
            'visualization-for-the-next-rep',
            'pre-performance-grounding',
        ],
        'post-practice reset' => [
            'post-practice-reflection',
            '60-second-body-scan',
            'box-breathing-reset',
        ],
        'other' => [
            'box-breathing-reset',
            'narrow-the-focus',
            'post-practice-reflection',
        ],
    ];
}

function applyCoachKeywordBoosts(array &$scores, string $situationText, string $upcomingEvent): void
{
    $text = strtolower($situationText . ' ' . $upcomingEvent);
    $keywordBoosts = [
        'nerv' => ['box-breathing-reset' => 70, 'pre-performance-grounding' => 60],
        'anxious' => ['box-breathing-reset' => 65, 'pre-performance-grounding' => 55],
        'mistake' => ['reset-after-a-mistake' => 80, 'physiological-sigh-reset' => 50],
        'turnover' => ['reset-after-a-mistake' => 70, 'narrow-the-focus' => 45],
        'miss' => ['reset-after-a-mistake' => 65, 'confidence-cue-routine' => 40],
        'focus' => ['narrow-the-focus' => 75, '60-second-body-scan' => 45],
        'distract' => ['narrow-the-focus' => 75, '60-second-body-scan' => 45],
        'frustrat' => ['re-center-after-frustration' => 80, 'physiological-sigh-reset' => 55],
        'angry' => ['re-center-after-frustration' => 80, 'physiological-sigh-reset' => 55],
        'confidence' => ['confidence-cue-routine' => 75, 'visualization-for-the-next-rep' => 55],
        'doubt' => ['confidence-cue-routine' => 70, 'pre-performance-grounding' => 45],
        'practice' => ['post-practice-reflection' => 60, '60-second-body-scan' => 35],
        'game' => ['pre-performance-grounding' => 60, 'visualization-for-the-next-rep' => 45],
        'match' => ['pre-performance-grounding' => 60, 'visualization-for-the-next-rep' => 45],
    ];

    foreach ($keywordBoosts as $keyword => $boostMap) {
        if (strpos($text, $keyword) === false) {
            continue;
        }

        foreach ($boostMap as $slug => $boost) {
            if (isset($scores[$slug])) {
                $scores[$slug] += $boost;
            }
        }
    }
}

function applyCoachStressBoosts(array &$scores, int $stressLevel): void
{
    if ($stressLevel >= 4) {
        $highStressBoosts = [
            'physiological-sigh-reset' => 70,
            'box-breathing-reset' => 60,
            're-center-after-frustration' => 45,
        ];

        foreach ($highStressBoosts as $slug => $boost) {
            if (isset($scores[$slug])) {
                $scores[$slug] += $boost;
            }
        }
        return;
    }

    if ($stressLevel <= 2) {
        $lowStressBoosts = [
            'narrow-the-focus' => 40,
            'visualization-for-the-next-rep' => 35,
            'confidence-cue-routine' => 30,
        ];

        foreach ($lowStressBoosts as $slug => $boost) {
            if (isset($scores[$slug])) {
                $scores[$slug] += $boost;
            }
        }
    }
}

function applyCoachTimeBoosts(array &$scores, array $catalog, int $timeAvailable): void
{
    foreach ($catalog as $lesson) {
        $slug = (string) ($lesson['slug'] ?? '');
        $duration = (int) ($lesson['duration_minutes'] ?? 0);
        if (!isset($scores[$slug]) || $duration <= 0) {
            continue;
        }

        if ($duration <= $timeAvailable) {
            $scores[$slug] += 40;
        } else {
            $scores[$slug] -= 20;
        }

        if ($timeAvailable <= 1 && $duration <= 2) {
            $scores[$slug] += 25;
        }
    }
}

function applyCoachUpcomingEventBoosts(array &$scores, string $upcomingEvent): void
{
    if (trim($upcomingEvent) === '') {
        return;
    }

    $eventBoosts = [
        'pre-performance-grounding' => 55,
        'confidence-cue-routine' => 45,
        'visualization-for-the-next-rep' => 40,
    ];

    foreach ($eventBoosts as $slug => $boost) {
        if (isset($scores[$slug])) {
            $scores[$slug] += $boost;
        }
    }
}

function buildCoachRecommendationFromLesson(array $lesson, string $upcomingEvent, int $stressLevel): array
{
    $whyThisWorks = trim((string) ($lesson['why_this_works'] ?? ''));
    if ($stressLevel >= 4) {
        $whyThisWorks .= ' This is a strong first step when intensity is high because it gives your body and attention a clear reset pattern.';
    }

    $whenToUse = trim((string) ($lesson['when_to_use'] ?? ''));
    if ($upcomingEvent !== '') {
        $whenToUse = 'Use this before or during ' . $upcomingEvent . '. ' . $whenToUse;
    }

    $steps = [];
    if (!empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        foreach ($lesson['try_now_steps'] as $step) {
            $stepText = trim((string) $step);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    return [
        'slug' => (string) ($lesson['slug'] ?? ''),
        'title' => (string) ($lesson['title'] ?? ''),
        'why_this_works' => $whyThisWorks,
        'when_to_use' => $whenToUse,
        'steps' => $steps,
        'duration_minutes' => (int) ($lesson['duration_minutes'] ?? 0),
    ];
}

function buildCoachSummary(array $input): string
{
    $situationSnippet = preg_replace('/\s+/', ' ', $input['situation_text']);
    $situationSnippet = trim((string) $situationSnippet);

    if (strlen($situationSnippet) > 180) {
        $situationSnippet = substr($situationSnippet, 0, 177) . '...';
    }

    $summary = 'You shared: "' . $situationSnippet . '". ';
    $summary .= 'Current stress is ' . (int) $input['stress_level'] . '/5 with about ' . (int) $input['time_available'] . ' minute(s) available.';

    if ($input['upcoming_event'] !== '') {
        $summary .= ' Upcoming event: ' . $input['upcoming_event'] . '.';
    }

    return $summary;
}

function buildCoachMessage(?array $topRecommendation, array $input): string
{
    if ($topRecommendation === null) {
        return 'Take one small grounding action now, then reassess in a minute.';
    }

    return 'Start with "' . ($topRecommendation['title'] ?? 'this tool') . '" now. Stick with the steps for about ' . (int) ($topRecommendation['duration_minutes'] ?? $input['time_available']) . ' minute(s), then check if your state shifted.';
}
