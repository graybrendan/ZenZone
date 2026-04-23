<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/coach_system_prompt.php';
require_once __DIR__ . '/coach_recommendation_logic.php';

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
    $combinedText = trim($normalizedInput['situation_text'] . ' ' . $normalizedInput['upcoming_event']);
    $crisisScan = detectCoachCrisisLanguage($combinedText);

    if (!empty($crisisScan['crisis_detected'])) {
        return buildCoachCrisisResponse((string) ($crisisScan['crisis_message'] ?? ''), 'rule_based');
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

    $goalTitle = trim((string) ($input['goal_title'] ?? ''));
    if (strlen($goalTitle) > 140) {
        $goalTitle = substr($goalTitle, 0, 140);
    }

    $goalStatus = strtolower(trim((string) ($input['goal_status'] ?? '')));
    if (!in_array($goalStatus, ['active', 'paused', 'completed'], true)) {
        $goalStatus = '';
    }

    $goalCadenceNumber = max(1, (int) ($input['goal_cadence_number'] ?? 1));
    if ($goalCadenceNumber > 365) {
        $goalCadenceNumber = 365;
    }

    $goalCadenceUnit = strtolower(trim((string) ($input['goal_cadence_unit'] ?? '')));
    if (!in_array($goalCadenceUnit, ['day', 'week', 'month'], true)) {
        $goalCadenceUnit = '';
    }

    $goalCheckinsUsed = max(0, (int) ($input['goal_checkins_used'] ?? 0));
    if ($goalCheckinsUsed > 365) {
        $goalCheckinsUsed = 365;
    }

    $goalCheckinsTarget = max(1, (int) ($input['goal_checkins_target'] ?? 1));
    if ($goalCheckinsTarget > 365) {
        $goalCheckinsTarget = 365;
    }

    $goalCategories = [];
    $rawGoalCategories = $input['goal_categories'] ?? [];
    if (is_string($rawGoalCategories) && $rawGoalCategories !== '') {
        $rawGoalCategories = explode(',', $rawGoalCategories);
    }
    if (is_array($rawGoalCategories)) {
        foreach ($rawGoalCategories as $rawCategory) {
            $category = strtolower(trim((string) $rawCategory));
            if (!in_array($category, ['body', 'mind', 'soul'], true)) {
                continue;
            }
            if (in_array($category, $goalCategories, true)) {
                continue;
            }
            $goalCategories[] = $category;
        }
    }

    return [
        'situation_type' => $situationType,
        'time_available' => $timeAvailable,
        'stress_level' => $stressLevel,
        'situation_text' => $situationText,
        'upcoming_event' => $upcomingEvent,
        'goal_title' => $goalTitle,
        'goal_categories' => $goalCategories,
        'goal_status' => $goalStatus,
        'goal_cadence_number' => $goalCadenceNumber,
        'goal_cadence_unit' => $goalCadenceUnit,
        'goal_checkins_used' => $goalCheckinsUsed,
        'goal_checkins_target' => $goalCheckinsTarget,
    ];
}

function generateCoachResponseFromAdapter(array $input): ?array
{
    if (!isCoachExternalAiEnabled()) {
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

    $lessonCatalog = getLessonCatalog();
    $payload = [
        'system_prompt' => getCoachSystemPrompt($lessonCatalog),
        'response_format' => 'strict_json',
        'response_schema' => 'coach_normalized_response_v1',
        'assistant_role' => 'zenzone_coach',
        'input' => $input,
        'lesson_catalog' => getCoachPromptCatalogPayload($lessonCatalog),
    ];

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        return null;
    }

    $headers = ['Content-Type: application/json'];
    $apiToken = defined('COACH_AI_TOKEN') ? (string) COACH_AI_TOKEN : (string) getenv('ZENZONE_COACH_AI_TOKEN');
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

    $candidate = decodeCoachAdapterPayload($rawResponse);
    if ($candidate === null) {
        return null;
    }

    return normalizeExternalCoachResponse($candidate, $input, $lessonCatalog);
}

function isCoachExternalAiEnabled(): bool
{
    $enabledRaw = defined('COACH_AI_ENABLED')
        ? (string) COACH_AI_ENABLED
        : (string) getenv('ZENZONE_COACH_AI_ENABLED');

    return in_array(strtolower(trim($enabledRaw)), ['1', 'true', 'yes', 'on'], true);
}

function decodeCoachAdapterPayload(string $rawResponse): ?array
{
    $trimmed = trim($rawResponse);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        foreach (['response', 'result', 'output', 'data'] as $containerKey) {
            if (!isset($decoded[$containerKey])) {
                continue;
            }

            $containerValue = $decoded[$containerKey];
            if (is_array($containerValue)) {
                return $containerValue;
            }
            if (is_string($containerValue)) {
                $parsed = decodeCoachJsonFromText($containerValue);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return $decoded;
    }

    return decodeCoachJsonFromText($trimmed);
}

function decodeCoachJsonFromText(string $text): ?array
{
    $candidate = trim($text);
    if ($candidate === '') {
        return null;
    }

    if (substr($candidate, 0, 3) === '```') {
        $candidate = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/```$/', '', $candidate) ?? $candidate;
        $candidate = trim($candidate);
    }

    $decoded = json_decode($candidate, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($candidate, '{');
    $end = strrpos($candidate, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $snippet = substr($candidate, $start, $end - $start + 1);
    $decodedSnippet = json_decode($snippet, true);
    if (is_array($decodedSnippet)) {
        return $decodedSnippet;
    }

    return null;
}

function normalizeExternalCoachResponse(array $candidate, array $input, array $lessonCatalog): ?array
{
    $crisisDetected = !empty($candidate['crisis_detected']);

    if ($crisisDetected) {
        $crisisMessage = trim((string) ($candidate['crisis_message'] ?? ''));
        return buildCoachCrisisResponse($crisisMessage, 'external_ai');
    }

    $topRecommendation = normalizeExternalCoachRecommendation($candidate['top_recommendation'] ?? null, $lessonCatalog, $input);
    if ($topRecommendation === null) {
        return null;
    }

    $alternatives = [];
    if (!empty($candidate['alternatives']) && is_array($candidate['alternatives'])) {
        foreach ($candidate['alternatives'] as $item) {
            $normalizedAlternative = normalizeExternalCoachRecommendation($item, $lessonCatalog, $input);
            if ($normalizedAlternative === null) {
                continue;
            }
            if (($normalizedAlternative['slug'] ?? '') === ($topRecommendation['slug'] ?? '')) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($alternatives as $existing) {
                if (($existing['slug'] ?? '') === ($normalizedAlternative['slug'] ?? '')) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if ($alreadyAdded) {
                continue;
            }

            $alternatives[] = $normalizedAlternative;
            if (count($alternatives) >= 2) {
                break;
            }
        }
    }

    if (count($alternatives) < 2) {
        $ranked = rankCoachRecommendations($input, $lessonCatalog);
        foreach ($ranked as $item) {
            $lesson = is_array($item['lesson'] ?? null) ? $item['lesson'] : null;
            if ($lesson === null) {
                continue;
            }

            $fallbackRecommendation = buildCoachRecommendationFromLesson($lesson, $input);
            $fallbackSlug = (string) ($fallbackRecommendation['slug'] ?? '');

            if ($fallbackSlug === '' || $fallbackSlug === ($topRecommendation['slug'] ?? '')) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($alternatives as $existing) {
                if (($existing['slug'] ?? '') === $fallbackSlug) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if ($alreadyAdded) {
                continue;
            }

            $alternatives[] = $fallbackRecommendation;
            if (count($alternatives) >= 2) {
                break;
            }
        }
    }

    $response = [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => trim((string) ($candidate['summary'] ?? '')),
        'top_recommendation' => $topRecommendation,
        'alternatives' => array_slice($alternatives, 0, 2),
        'coach_message' => trim((string) ($candidate['coach_message'] ?? '')),
        'source_mode' => 'external_ai',
        'input_context' => $input,
    ];

    $response = buildCoachNarrative($response);

    return normalizeCoachResponseShape($response, $lessonCatalog, 'external_ai');
}

function normalizeExternalCoachRecommendation($candidate, array $lessonCatalog, array $input): ?array
{
    if (!is_array($candidate)) {
        return null;
    }

    $lessonLookup = getCoachLessonLookup($lessonCatalog);
    if (empty($lessonLookup)) {
        return null;
    }

    $rawSlug = trim((string) ($candidate['slug'] ?? ''));
    $resolvedSlug = resolveCoachLessonSlug($rawSlug, $lessonLookup);

    if ($resolvedSlug === null && trim((string) ($candidate['title'] ?? '')) !== '') {
        $titleNeedle = strtolower(trim((string) ($candidate['title'] ?? '')));
        foreach ($lessonLookup as $slug => $lesson) {
            $lessonTitle = strtolower(trim((string) ($lesson['title'] ?? '')));
            if ($lessonTitle !== '' && $lessonTitle === $titleNeedle) {
                $resolvedSlug = $slug;
                break;
            }
        }
    }

    if ($resolvedSlug === null || !isset($lessonLookup[$resolvedSlug])) {
        return null;
    }

    $lesson = $lessonLookup[$resolvedSlug];
    $steps = [];

    if (!empty($candidate['steps']) && is_array($candidate['steps'])) {
        foreach ($candidate['steps'] as $step) {
            $stepText = sanitizeCoachNarrativeLine((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps) && !empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        foreach ($lesson['try_now_steps'] as $step) {
            $stepText = sanitizeCoachNarrativeLine((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps)) {
        $steps = [
            'Settle your body with one controlled breath.',
            'Pick one focus cue for the next rep.',
            'Execute the next action with that cue.',
        ];
    }

    $duration = (int) ($candidate['duration_minutes'] ?? 0);
    if ($duration <= 0) {
        $duration = max(1, (int) ($lesson['duration_minutes'] ?? 1));
    }

    $whenToUse = sanitizeCoachNarrativeLine((string) ($candidate['when_to_use'] ?? ''), 220);
    if ($whenToUse === '') {
        $whenToUse = sanitizeCoachNarrativeLine((string) ($lesson['when_to_use'] ?? ''), 220);
    }
    $upcomingEvent = trim((string) ($input['upcoming_event'] ?? ''));
    if ($upcomingEvent !== '' && stripos($whenToUse, $upcomingEvent) === false) {
        $whenToUse = sanitizeCoachNarrativeLine('Use this before ' . $upcomingEvent . '. ' . $whenToUse, 220);
    }

    return [
        'slug' => $resolvedSlug,
        'title' => sanitizeCoachNarrativeLine(
            (string) ($candidate['title'] ?? ($lesson['title'] ?? '')),
            90
        ),
        'why_this_works' => sanitizeCoachNarrativeLine(
            (string) ($candidate['why_this_works'] ?? ($lesson['why_this_works'] ?? '')),
            220
        ),
        'when_to_use' => $whenToUse,
        'steps' => array_slice($steps, 0, 5),
        'duration_minutes' => $duration,
        'evidence_note' => sanitizeCoachNarrativeLine(
            (string) ($candidate['evidence_note'] ?? ($lesson['evidence_note'] ?? '')),
            180
        ),
    ];
}

function generateRuleBasedCoachResponse(array $input): array
{
    $lessonCatalog = getLessonCatalog();
    $ranked = rankCoachRecommendations($input, $lessonCatalog);
    $recommendations = [];

    foreach ($ranked as $item) {
        $lesson = is_array($item['lesson'] ?? null) ? $item['lesson'] : null;
        if ($lesson === null) {
            continue;
        }

        $recommendations[] = buildCoachRecommendationFromLesson($lesson, $input);
    }

    $topRecommendation = $recommendations[0] ?? null;
    if ($topRecommendation === null) {
        $fallbackLesson = $lessonCatalog[0] ?? null;
        if (is_array($fallbackLesson)) {
            $topRecommendation = buildCoachRecommendationFromLesson($fallbackLesson, $input);
        }
    }

    if ($topRecommendation === null) {
        return [
            'crisis_detected' => false,
            'crisis_message' => null,
            'summary' => 'A short reset is the best next move right now.',
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => 'Take one reset action now, then mark Better, Same, or Worse.',
            'source_mode' => 'rule_based',
        ];
    }

    $response = [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => '',
        'top_recommendation' => $topRecommendation,
        'alternatives' => array_slice($recommendations, 1, 2),
        'coach_message' => '',
        'source_mode' => 'rule_based',
        'input_context' => $input,
    ];

    $response = buildCoachNarrative($response);

    return normalizeCoachResponseShape($response, $lessonCatalog, 'rule_based');
}

function buildCoachCrisisResponse(string $crisisMessage, string $sourceMode): array
{
    $message = trim($crisisMessage);
    if ($message === '') {
        $message = "I'm really glad you shared this. This needs immediate human support right now. If you might hurt yourself or are in immediate danger, call or text 988 now (US) or call 911, and reach out to a trusted person immediately.";
    }

    $response = [
        'crisis_detected' => true,
        'crisis_message' => sanitizeCoachNarrativeLine($message, 280),
        'summary' => 'This sounds like a high-distress moment that needs immediate human support.',
        'top_recommendation' => null,
        'alternatives' => [],
        'coach_message' => 'Pause performance work and contact emergency support or a trusted person now.',
        'source_mode' => $sourceMode,
    ];

    $response = buildCoachNarrative($response);

    return $response;
}

function normalizeCoachResponseShape(array $response, array $lessonCatalog, string $sourceMode): array
{
    $lessonLookup = getCoachLessonLookup($lessonCatalog);
    $crisisDetected = !empty($response['crisis_detected']);

    if ($crisisDetected) {
        return [
            'crisis_detected' => true,
            'crisis_message' => sanitizeCoachNarrativeLine((string) ($response['crisis_message'] ?? ''), 280),
            'summary' => sanitizeCoachNarrativeLine((string) ($response['summary'] ?? ''), 260),
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => sanitizeCoachNarrativeLine((string) ($response['coach_message'] ?? ''), 260),
            'source_mode' => $sourceMode,
        ];
    }

    $top = normalizeCoachRecommendationShape($response['top_recommendation'] ?? null, $lessonLookup);
    if ($top === null) {
        $fallbackLesson = null;
        foreach ($lessonLookup as $lesson) {
            if (is_array($lesson)) {
                $fallbackLesson = $lesson;
                break;
            }
        }

        if (is_array($fallbackLesson)) {
            $top = buildCoachRecommendationFromLesson($fallbackLesson);
        }
    }

    if ($top === null) {
        return [
            'crisis_detected' => false,
            'crisis_message' => null,
            'summary' => sanitizeCoachNarrativeLine((string) ($response['summary'] ?? 'A short reset is the best next move right now.'), 260),
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => sanitizeCoachNarrativeLine((string) ($response['coach_message'] ?? 'Take one reset action now, then mark Better, Same, or Worse.'), 260),
            'source_mode' => $sourceMode,
        ];
    }

    $alternatives = [];
    if (!empty($response['alternatives']) && is_array($response['alternatives'])) {
        foreach ($response['alternatives'] as $item) {
            $normalized = normalizeCoachRecommendationShape($item, $lessonLookup);
            if ($normalized === null) {
                continue;
            }
            if (($normalized['slug'] ?? '') === ($top['slug'] ?? '')) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($alternatives as $existing) {
                if (($existing['slug'] ?? '') === ($normalized['slug'] ?? '')) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if ($alreadyAdded) {
                continue;
            }

            $alternatives[] = $normalized;
            if (count($alternatives) >= 2) {
                break;
            }
        }
    }

    return [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => sanitizeCoachNarrativeLine((string) ($response['summary'] ?? ''), 260),
        'top_recommendation' => $top,
        'alternatives' => $alternatives,
        'coach_message' => sanitizeCoachNarrativeLine((string) ($response['coach_message'] ?? ''), 260),
        'source_mode' => $sourceMode,
    ];
}

function normalizeCoachRecommendationShape($candidate, array $lessonLookup): ?array
{
    if (!is_array($candidate)) {
        return null;
    }

    $rawSlug = trim((string) ($candidate['slug'] ?? ''));
    $resolvedSlug = resolveCoachLessonSlug($rawSlug, $lessonLookup);
    if ($resolvedSlug === null || !isset($lessonLookup[$resolvedSlug])) {
        return null;
    }

    $lesson = $lessonLookup[$resolvedSlug];
    $steps = [];

    if (!empty($candidate['steps']) && is_array($candidate['steps'])) {
        foreach ($candidate['steps'] as $step) {
            $stepText = sanitizeCoachNarrativeLine((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps) && !empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])) {
        foreach ($lesson['try_now_steps'] as $step) {
            $stepText = sanitizeCoachNarrativeLine((string) $step, 140);
            if ($stepText !== '') {
                $steps[] = $stepText;
            }
        }
    }

    if (empty($steps)) {
        $steps = [
            'Settle your breathing.',
            'Pick one cue for focus.',
            'Execute the next rep.',
        ];
    }

    $duration = (int) ($candidate['duration_minutes'] ?? 0);
    if ($duration <= 0) {
        $duration = max(1, (int) ($lesson['duration_minutes'] ?? 1));
    }

    return [
        'slug' => $resolvedSlug,
        'title' => sanitizeCoachNarrativeLine((string) ($candidate['title'] ?? ($lesson['title'] ?? '')), 90),
        'why_this_works' => sanitizeCoachNarrativeLine((string) ($candidate['why_this_works'] ?? ($lesson['why_this_works'] ?? '')), 220),
        'when_to_use' => sanitizeCoachNarrativeLine((string) ($candidate['when_to_use'] ?? ($lesson['when_to_use'] ?? '')), 220),
        'steps' => array_slice($steps, 0, 5),
        'duration_minutes' => $duration,
        'evidence_note' => sanitizeCoachNarrativeLine((string) ($candidate['evidence_note'] ?? ($lesson['evidence_note'] ?? '')), 180),
    ];
}
