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

function getCoachConfigValue(string $constantName, string $envName, string $default = ''): string
{
    if (defined($constantName)) {
        $value = trim((string) constant($constantName));
        if ($value !== '') {
            return $value;
        }
    }

    $value = getenv($envName);
    if ($value === false) {
        return $default;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return $default;
    }

    return $value;
}

function getCoachConfigBool(string $constantName, string $envName, bool $default = false): bool
{
    $fallback = $default ? '1' : '0';
    $value = strtolower(getCoachConfigValue($constantName, $envName, $fallback));

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function getCoachConfigInt(string $constantName, string $envName, int $default): int
{
    $value = getCoachConfigValue($constantName, $envName, (string) $default);
    if (!preg_match('/^-?\d+$/', $value)) {
        return $default;
    }

    return (int) $value;
}

function getCoachConfigFloat(string $constantName, string $envName, float $default): float
{
    $value = getCoachConfigValue($constantName, $envName, (string) $default);
    if (!is_numeric($value)) {
        return $default;
    }

    return (float) $value;
}

function parseCoachCsvList(string $csv): array
{
    if ($csv === '') {
        return [];
    }

    $items = array_map('trim', explode(',', $csv));
    $items = array_values(array_filter($items, static function ($item): bool {
        return is_string($item) && $item !== '';
    }));

    return array_values(array_unique($items));
}

function getCoachReflectionIntentKeywords(): array
{
    return [
        'hermetic',
        'hermetism',
        'kybalion',
        'as above so below',
        'mentalism',
        'correspondence',
        'vibration',
        'polarity',
        'rhythm',
        'cause and effect',
        'alchemical',
        'alchemy',
    ];
}

function resolveCoachKnowledgeMode(array $input): string
{
    $requestedMode = strtolower(trim((string) ($input['knowledge_mode'] ?? '')));
    if (in_array($requestedMode, ['evidence', 'reflection'], true)) {
        return $requestedMode;
    }

    $configuredMode = strtolower(getCoachConfigValue('COACH_KNOWLEDGE_MODE', 'ZENZONE_COACH_KNOWLEDGE_MODE', 'auto'));
    if (in_array($configuredMode, ['evidence', 'reflection'], true)) {
        return $configuredMode;
    }

    $combinedText = strtolower(
        trim((string) ($input['situation_text'] ?? '') . ' ' . (string) ($input['upcoming_event'] ?? ''))
    );

    foreach (getCoachReflectionIntentKeywords() as $keyword) {
        if ($keyword !== '' && strpos($combinedText, $keyword) !== false) {
            return 'reflection';
        }
    }

    return 'evidence';
}

function shouldCoachRequireCitations(string $knowledgeMode): bool
{
    if ($knowledgeMode === 'reflection') {
        return getCoachConfigBool(
            'COACH_REQUIRE_REFLECTION_CITATIONS',
            'ZENZONE_COACH_REQUIRE_REFLECTION_CITATIONS',
            false
        );
    }

    return getCoachConfigBool('COACH_REQUIRE_CITATIONS', 'ZENZONE_COACH_REQUIRE_CITATIONS', true);
}

function buildCoachRetrievalHints(string $knowledgeMode): array
{
    $provider = getCoachConfigValue('COACH_RETRIEVAL_PROVIDER', 'ZENZONE_COACH_RETRIEVAL_PROVIDER', 'openai_file_search');
    $maxResults = getCoachConfigInt('COACH_RETRIEVAL_MAX_RESULTS', 'ZENZONE_COACH_RETRIEVAL_MAX_RESULTS', 6);
    if ($maxResults < 1) {
        $maxResults = 1;
    }
    if ($maxResults > 50) {
        $maxResults = 50;
    }

    $minScore = getCoachConfigFloat('COACH_RETRIEVAL_MIN_SCORE', 'ZENZONE_COACH_RETRIEVAL_MIN_SCORE', 0.0);
    if ($minScore < 0) {
        $minScore = 0.0;
    }
    if ($minScore > 1) {
        $minScore = 1.0;
    }

    $sharedStores = parseCoachCsvList(
        getCoachConfigValue('COACH_VECTOR_STORE_IDS', 'ZENZONE_COACH_VECTOR_STORE_IDS', '')
    );
    $evidenceStores = parseCoachCsvList(
        getCoachConfigValue('COACH_VECTOR_STORE_IDS_EVIDENCE', 'ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE', '')
    );
    $reflectionStores = parseCoachCsvList(
        getCoachConfigValue('COACH_VECTOR_STORE_IDS_REFLECTION', 'ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION', '')
    );

    $modeStores = $knowledgeMode === 'reflection' ? $reflectionStores : $evidenceStores;
    $vectorStoreIds = !empty($modeStores) ? $modeStores : $sharedStores;

    return [
        'provider' => $provider,
        'knowledge_mode' => $knowledgeMode,
        'vector_store_ids' => $vectorStoreIds,
        'max_num_results' => $maxResults,
        'min_score' => $minScore,
        'include_search_results' => true,
    ];
}

function generateCoachResponse(array $input): array
{
    $normalizedInput = normalizeCoachInput($input);
    $knowledgeMode = resolveCoachKnowledgeMode($normalizedInput);
    $combinedText = trim($normalizedInput['situation_text'] . ' ' . $normalizedInput['upcoming_event']);
    $crisisScan = detectCoachCrisisLanguage($combinedText);

    if (!empty($crisisScan['crisis_detected'])) {
        return buildCoachCrisisResponse((string) ($crisisScan['crisis_message'] ?? ''), 'rule_based', $knowledgeMode);
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

    $endpoint = getCoachConfigValue('COACH_AI_ENDPOINT', 'ZENZONE_COACH_AI_ENDPOINT', '');
    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return null;
    }

    if (!function_exists('curl_init')) {
        return null;
    }

    $lessonCatalog = getLessonCatalog();
    $knowledgeMode = resolveCoachKnowledgeMode($input);
    $requireCitations = shouldCoachRequireCitations($knowledgeMode);
    $retrievalHints = buildCoachRetrievalHints($knowledgeMode);

    $payload = [
        'system_prompt' => getCoachSystemPrompt($lessonCatalog),
        'response_format' => 'strict_json',
        'response_schema' => 'coach_normalized_response_v1',
        'assistant_role' => 'zenzone_coach',
        'input' => $input,
        'lesson_catalog' => getCoachPromptCatalogPayload($lessonCatalog),
        'knowledge_mode' => $knowledgeMode,
        'knowledge_contract' => [
            'require_citations' => $requireCitations,
            'citation_minimum' => $requireCitations ? 1 : 0,
            'retrieval' => $retrievalHints,
            'disallow_fabricated_sources' => true,
        ],
    ];

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        return null;
    }

    $headers = ['Content-Type: application/json'];
    $apiToken = getCoachConfigValue('COACH_AI_TOKEN', 'ZENZONE_COACH_AI_TOKEN', '');
    if ($apiToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiToken;
        $headers[] = 'X-ZenZone-Adapter-Token: ' . $apiToken;
    }

    $timeoutSeconds = getCoachConfigInt('COACH_AI_TIMEOUT_SECONDS', 'ZENZONE_COACH_AI_TIMEOUT_SECONDS', 30);
    if ($timeoutSeconds < 10) {
        $timeoutSeconds = 10;
    }
    if ($timeoutSeconds > 120) {
        $timeoutSeconds = 120;
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeoutSeconds,
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

    return normalizeExternalCoachResponse(
        $candidate,
        $input,
        $lessonCatalog,
        $knowledgeMode,
        $requireCitations,
        $retrievalHints
    );
}

function isCoachExternalAiEnabled(): bool
{
    return getCoachConfigBool('COACH_AI_ENABLED', 'ZENZONE_COACH_AI_ENABLED', false);
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
                return mergeCoachAdapterEnvelopeMetadata($containerValue, $decoded);
            }
            if (is_string($containerValue)) {
                $parsed = decodeCoachJsonFromText($containerValue);
                if ($parsed !== null) {
                    return mergeCoachAdapterEnvelopeMetadata($parsed, $decoded);
                }
            }
        }

        return $decoded;
    }

    return decodeCoachJsonFromText($trimmed);
}

function mergeCoachAdapterEnvelopeMetadata(array $candidate, array $envelope): array
{
    $mergeKeys = [
        'knowledge_mode',
        'citations',
        'source_citations',
        'retrieval_metadata',
        'retrieval',
        'retrieval_results',
        'file_search_results',
    ];

    foreach ($mergeKeys as $key) {
        if (array_key_exists($key, $candidate)) {
            continue;
        }
        if (!array_key_exists($key, $envelope)) {
            continue;
        }

        $candidate[$key] = $envelope[$key];
    }

    return $candidate;
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

function normalizeCoachCitationText(string $text, int $maxLength = 240): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $text));

    if ($clean === '') {
        return '';
    }

    if ($maxLength < 40) {
        $maxLength = 40;
    }

    if (strlen($clean) > $maxLength) {
        $clean = rtrim(substr($clean, 0, $maxLength - 3)) . '...';
    }

    return $clean;
}

function normalizeCoachCitationItem($candidate): ?array
{
    if (!is_array($candidate)) {
        return null;
    }

    $title = normalizeCoachCitationText(
        (string) (
            $candidate['title']
            ?? $candidate['source_title']
            ?? $candidate['label']
            ?? $candidate['name']
            ?? $candidate['filename']
            ?? ''
        ),
        180
    );

    $urlCandidate = trim((string) ($candidate['url'] ?? $candidate['source_url'] ?? $candidate['link'] ?? ''));
    $url = '';
    if ($urlCandidate !== '' && filter_var($urlCandidate, FILTER_VALIDATE_URL)) {
        $url = $urlCandidate;
    }

    $fileId = normalizeCoachCitationText((string) ($candidate['file_id'] ?? ''), 80);
    $filename = normalizeCoachCitationText((string) ($candidate['filename'] ?? ''), 180);
    $evidenceTier = normalizeCoachCitationText(
        (string) ($candidate['evidence_tier'] ?? $candidate['tier'] ?? ''),
        80
    );

    $score = null;
    $rawScore = $candidate['score'] ?? $candidate['relevance_score'] ?? null;
    if (is_numeric($rawScore)) {
        $score = (float) $rawScore;
        if ($score < 0) {
            $score = 0.0;
        }
        if ($score > 1) {
            $score = 1.0;
        }
    }

    $excerpt = normalizeCoachCitationText(
        (string) ($candidate['excerpt'] ?? $candidate['snippet'] ?? $candidate['text'] ?? $candidate['quote'] ?? ''),
        260
    );

    if ($title === '' && $url === '' && $fileId === '' && $filename === '' && $excerpt === '') {
        return null;
    }

    return [
        'title' => $title,
        'url' => $url,
        'file_id' => $fileId,
        'filename' => $filename,
        'score' => $score,
        'evidence_tier' => $evidenceTier,
        'excerpt' => $excerpt,
    ];
}

function collectCoachCitationCandidates(array $candidate): array
{
    $items = [];
    $listKeys = ['citations', 'source_citations', 'retrieval_results', 'file_search_results', 'sources'];

    foreach ($listKeys as $key) {
        if (!isset($candidate[$key]) || !is_array($candidate[$key])) {
            continue;
        }

        foreach ($candidate[$key] as $item) {
            $items[] = $item;
        }
    }

    $nestedContainers = ['retrieval', 'retrieval_metadata'];
    foreach ($nestedContainers as $containerKey) {
        if (!isset($candidate[$containerKey]) || !is_array($candidate[$containerKey])) {
            continue;
        }

        foreach (['citations', 'results', 'sources', 'chunks'] as $nestedKey) {
            if (!isset($candidate[$containerKey][$nestedKey]) || !is_array($candidate[$containerKey][$nestedKey])) {
                continue;
            }

            foreach ($candidate[$containerKey][$nestedKey] as $item) {
                $items[] = $item;
            }
        }
    }

    return $items;
}

function normalizeCoachCitations(array $candidate): array
{
    $rawCandidates = collectCoachCitationCandidates($candidate);
    $normalized = [];
    $seen = [];

    foreach ($rawCandidates as $rawItem) {
        $item = normalizeCoachCitationItem($rawItem);
        if ($item === null) {
            continue;
        }

        $dedupeKey = strtolower(
            trim(
                ($item['file_id'] ?? '') . '|' .
                ($item['filename'] ?? '') . '|' .
                ($item['url'] ?? '') . '|' .
                ($item['title'] ?? '')
            )
        );
        if ($dedupeKey === '') {
            $dedupeKey = md5(json_encode($item) ?: serialize($item));
        }

        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;

        $normalized[] = $item;
        if (count($normalized) >= 10) {
            break;
        }
    }

    return $normalized;
}

function normalizeCoachRetrievalMetadata(array $candidate, string $knowledgeMode, array $retrievalHints, int $citationCount): array
{
    $rawMetadata = [];
    if (!empty($candidate['retrieval_metadata']) && is_array($candidate['retrieval_metadata'])) {
        $rawMetadata = $candidate['retrieval_metadata'];
    } elseif (!empty($candidate['retrieval']) && is_array($candidate['retrieval'])) {
        $rawMetadata = $candidate['retrieval'];
    }

    $provider = normalizeCoachCitationText(
        (string) ($rawMetadata['provider'] ?? $retrievalHints['provider'] ?? 'openai_file_search'),
        80
    );

    $resultCount = (int) ($rawMetadata['result_count'] ?? 0);
    if ($resultCount <= 0) {
        foreach (['results', 'chunks', 'sources'] as $resultKey) {
            if (!empty($rawMetadata[$resultKey]) && is_array($rawMetadata[$resultKey])) {
                $resultCount = count($rawMetadata[$resultKey]);
                break;
            }
        }
    }
    if ($resultCount <= 0) {
        $resultCount = $citationCount;
    }

    $queries = [];
    if (!empty($rawMetadata['queries']) && is_array($rawMetadata['queries'])) {
        foreach ($rawMetadata['queries'] as $query) {
            $queryText = normalizeCoachCitationText((string) $query, 160);
            if ($queryText !== '') {
                $queries[] = $queryText;
            }
        }
    }

    return [
        'provider' => $provider,
        'knowledge_mode' => $knowledgeMode,
        'vector_store_ids' => $retrievalHints['vector_store_ids'] ?? [],
        'max_num_results' => (int) ($retrievalHints['max_num_results'] ?? 0),
        'min_score' => isset($retrievalHints['min_score']) ? (float) $retrievalHints['min_score'] : 0.0,
        'result_count' => max(0, $resultCount),
        'queries' => array_slice($queries, 0, 4),
    ];
}

function normalizeExternalCoachResponse(
    array $candidate,
    array $input,
    array $lessonCatalog,
    string $knowledgeMode,
    bool $requireCitations,
    array $retrievalHints
): ?array
{
    $candidateKnowledgeMode = strtolower(trim((string) ($candidate['knowledge_mode'] ?? '')));
    if (in_array($candidateKnowledgeMode, ['evidence', 'reflection'], true)) {
        $knowledgeMode = $candidateKnowledgeMode;
    }

    $crisisDetected = !empty($candidate['crisis_detected']);
    $citations = normalizeCoachCitations($candidate);
    $retrievalMetadata = normalizeCoachRetrievalMetadata(
        $candidate,
        $knowledgeMode,
        $retrievalHints,
        count($citations)
    );

    if ($crisisDetected) {
        $crisisMessage = trim((string) ($candidate['crisis_message'] ?? ''));
        return buildCoachCrisisResponse($crisisMessage, 'external_ai', $knowledgeMode);
    }

    if ($requireCitations && empty($citations)) {
        return null;
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
        'knowledge_mode' => $knowledgeMode,
        'citations' => $citations,
        'retrieval_metadata' => $retrievalMetadata,
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
    $knowledgeMode = resolveCoachKnowledgeMode($input);
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
            'knowledge_mode' => $knowledgeMode,
            'citations' => [],
            'retrieval_metadata' => [
                'provider' => 'rule_based',
                'knowledge_mode' => $knowledgeMode,
                'result_count' => 0,
            ],
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
        'knowledge_mode' => $knowledgeMode,
        'citations' => [],
        'retrieval_metadata' => [
            'provider' => 'rule_based',
            'knowledge_mode' => $knowledgeMode,
            'result_count' => 0,
        ],
        'input_context' => $input,
    ];

    $response = buildCoachNarrative($response);

    return normalizeCoachResponseShape($response, $lessonCatalog, 'rule_based');
}

function buildCoachCrisisResponse(string $crisisMessage, string $sourceMode, string $knowledgeMode = 'evidence'): array
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
        'knowledge_mode' => $knowledgeMode,
        'citations' => [],
        'retrieval_metadata' => [
            'provider' => $sourceMode === 'external_ai' ? 'external_ai' : 'rule_based',
            'knowledge_mode' => $knowledgeMode,
            'result_count' => 0,
        ],
    ];

    $response = buildCoachNarrative($response);

    return $response;
}

function normalizeCoachResponseShape(array $response, array $lessonCatalog, string $sourceMode): array
{
    $lessonLookup = getCoachLessonLookup($lessonCatalog);
    $crisisDetected = !empty($response['crisis_detected']);
    $knowledgeMode = strtolower(trim((string) ($response['knowledge_mode'] ?? 'evidence')));
    if (!in_array($knowledgeMode, ['evidence', 'reflection'], true)) {
        $knowledgeMode = 'evidence';
    }

    $citations = [];
    if (!empty($response['citations']) && is_array($response['citations'])) {
        foreach ($response['citations'] as $citationCandidate) {
            $citation = normalizeCoachCitationItem($citationCandidate);
            if ($citation === null) {
                continue;
            }
            $citations[] = $citation;
            if (count($citations) >= 10) {
                break;
            }
        }
    }

    $retrievalMetadata = [];
    if (!empty($response['retrieval_metadata']) && is_array($response['retrieval_metadata'])) {
        $retrievalMetadata = $response['retrieval_metadata'];
    }

    if (empty($retrievalMetadata)) {
        $retrievalMetadata = [
            'provider' => $sourceMode === 'external_ai' ? 'external_ai' : 'rule_based',
            'knowledge_mode' => $knowledgeMode,
            'result_count' => count($citations),
        ];
    } else {
        $retrievalMetadata['provider'] = normalizeCoachCitationText(
            (string) ($retrievalMetadata['provider'] ?? ($sourceMode === 'external_ai' ? 'external_ai' : 'rule_based')),
            80
        );
        $retrievalMetadata['knowledge_mode'] = $knowledgeMode;
        $retrievalMetadata['result_count'] = max(
            0,
            (int) ($retrievalMetadata['result_count'] ?? count($citations))
        );
    }

    if ($crisisDetected) {
        return [
            'crisis_detected' => true,
            'crisis_message' => sanitizeCoachNarrativeLine((string) ($response['crisis_message'] ?? ''), 280),
            'summary' => sanitizeCoachNarrativeLine((string) ($response['summary'] ?? ''), 260),
            'top_recommendation' => null,
            'alternatives' => [],
            'coach_message' => sanitizeCoachNarrativeLine((string) ($response['coach_message'] ?? ''), 260),
            'source_mode' => $sourceMode,
            'knowledge_mode' => $knowledgeMode,
            'citations' => [],
            'retrieval_metadata' => $retrievalMetadata,
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
            'knowledge_mode' => $knowledgeMode,
            'citations' => $citations,
            'retrieval_metadata' => $retrievalMetadata,
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
        'knowledge_mode' => $knowledgeMode,
        'citations' => $citations,
        'retrieval_metadata' => $retrievalMetadata,
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
