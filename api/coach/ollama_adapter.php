<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';

function zzCoachOllamaSendJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    echo is_string($json) ? $json : '{"error":"json_encode_failed"}';
    exit;
}

function zzCoachOllamaConfigValue(array $constantNames, array $envNames, string $default = ''): string
{
    foreach ($constantNames as $constantName) {
        if (defined($constantName)) {
            $value = trim((string) constant($constantName));
            if ($value !== '') {
                return $value;
            }
        }
    }

    foreach ($envNames as $envName) {
        $value = getenv($envName);
        if ($value === false) {
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function zzCoachOllamaConfigInt(array $constantNames, array $envNames, int $default, int $min, int $max): int
{
    $raw = zzCoachOllamaConfigValue($constantNames, $envNames, (string) $default);
    if (!preg_match('/^-?\d+$/', $raw)) {
        return $default;
    }

    $value = (int) $raw;
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }

    return $value;
}

function zzCoachOllamaConfigBool(array $constantNames, array $envNames, bool $default): bool
{
    $fallback = $default ? '1' : '0';
    $value = strtolower(zzCoachOllamaConfigValue($constantNames, $envNames, $fallback));

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function zzCoachOllamaBearerToken(): string
{
    $localHeader = trim((string) ($_SERVER['HTTP_X_ZENZONE_ADAPTER_TOKEN'] ?? ''));
    if ($localHeader !== '') {
        return $localHeader;
    }

    $header = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $header = trim((string) $value);
                    break;
                }
            }
        }
    }

    if ($header === '' || stripos($header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($header, 7));
}

function zzCoachOllamaRequireToken(): void
{
    $expected = zzCoachOllamaConfigValue(['COACH_AI_TOKEN'], ['ZENZONE_COACH_AI_TOKEN'], '');
    if ($expected === '') {
        zzCoachOllamaSendJson(503, [
            'error' => 'adapter_token_not_configured',
            'message' => 'COACH_AI_TOKEN must be configured.',
        ]);
    }

    $actual = zzCoachOllamaBearerToken();
    if ($actual === '' || !hash_equals($expected, $actual)) {
        zzCoachOllamaSendJson(401, [
            'error' => 'unauthorized',
            'message' => 'Missing or invalid adapter token.',
        ]);
    }
}

function zzCoachOllamaReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        zzCoachOllamaSendJson(400, [
            'error' => 'empty_request_body',
            'message' => 'Request body must be JSON.',
        ]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        zzCoachOllamaSendJson(400, [
            'error' => 'invalid_json',
            'message' => 'Request body was not valid JSON.',
        ]);
    }

    return $decoded;
}

function zzCoachOllamaNormalizePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || strpos($path, '\\\\') === 0) {
        return $path;
    }

    $root = dirname(__DIR__, 2);
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function zzCoachOllamaKnowledgeMode(array $payload): string
{
    $mode = strtolower(trim((string) ($payload['knowledge_mode'] ?? 'evidence')));
    return in_array($mode, ['evidence', 'reflection'], true) ? $mode : 'evidence';
}

function zzCoachOllamaMaxResults(array $payload): int
{
    $contract = is_array($payload['knowledge_contract'] ?? null) ? $payload['knowledge_contract'] : [];
    $retrieval = is_array($contract['retrieval'] ?? null) ? $contract['retrieval'] : [];
    $maxResults = (int) ($retrieval['max_num_results'] ?? 4);

    if ($maxResults < 1) {
        return 1;
    }
    if ($maxResults > 3) {
        return 3;
    }

    return $maxResults;
}

function zzCoachOllamaBuildCompactSystemPrompt(string $knowledgeMode): string
{
    return implode("\n", [
        'You are ZenZone Coach, a calm performance psychology and mindfulness support coach for training, fitness, sport, performing arts, school, work, and competitive settings.',
        'Return STRICT JSON only. No markdown.',
        'Recommend one ZenZone tool using a slug from the provided lesson_catalog, plus up to two alternatives.',
        'Prefer zero or one alternative if time is limited.',
        'Keep summaries, reasons, and steps very short, concrete, and action-oriented.',
        'No diagnosis, no medical claims, no crisis counseling.',
        'If self-harm or immediate danger is present, set crisis_detected=true and provide a brief escalation message.',
        'Use local_knowledge only for grounding. Do not invent citations.',
        'Set source_mode="external_ai" and knowledge_mode="' . $knowledgeMode . '".',
        'Required JSON keys: crisis_detected, crisis_message, summary, top_recommendation, alternatives, coach_message, source_mode, knowledge_mode, citations, retrieval_metadata.',
        'top_recommendation must include: slug, title, why_this_works, when_to_use, steps, duration_minutes, evidence_note.',
    ]);
}

function zzCoachOllamaCompactLessonCatalog($catalog): array
{
    if (!is_array($catalog)) {
        return [];
    }

    $items = [];
    foreach ($catalog as $lesson) {
        if (!is_array($lesson)) {
            continue;
        }

        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $items[] = [
            'slug' => $slug,
        'title' => zzCoachOllamaCleanText((string) ($lesson['title'] ?? ''), 80),
        'duration_minutes' => (int) ($lesson['duration_minutes'] ?? 0),
        'when_to_use' => zzCoachOllamaCleanText((string) ($lesson['when_to_use'] ?? ''), 90),
        'why_this_works' => zzCoachOllamaCleanText((string) ($lesson['why_this_works'] ?? ''), 110),
        ];
    }

    return $items;
}

function zzCoachOllamaLessonLookup($catalog): array
{
    if (!is_array($catalog)) {
        return [];
    }

    $lookup = [];
    foreach ($catalog as $lesson) {
        if (!is_array($lesson)) {
            continue;
        }

        $slug = trim((string) ($lesson['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $lookup[$slug] = $lesson;
    }

    return $lookup;
}

function zzCoachOllamaFallbackSlug(array $input, array $lessonLookup): string
{
    $type = strtolower(trim((string) ($input['situation_type'] ?? '')));
    $text = strtolower(trim((string) ($input['situation_text'] ?? '') . ' ' . (string) ($input['upcoming_event'] ?? '')));

    $candidates = [];
    if ($type === 'after mistake' || strpos($text, 'mistake') !== false || strpos($text, 'miss') !== false) {
        $candidates[] = 'reset-after-a-mistake';
    }
    if ($type === 'pre-performance nerves' || strpos($text, 'nerv') !== false || strpos($text, 'anx') !== false) {
        $candidates[] = 'box-breathing-reset';
        $candidates[] = 'pre-performance-grounding';
    }
    if ($type === 'low focus' || strpos($text, 'focus') !== false || strpos($text, 'distract') !== false) {
        $candidates[] = 'narrow-the-focus';
    }
    if ($type === 'frustration / anger' || strpos($text, 'frustrat') !== false || strpos($text, 'anger') !== false) {
        $candidates[] = 're-center-after-frustration';
    }
    if ($type === 'confidence dip' || strpos($text, 'confidence') !== false || strpos($text, 'doubt') !== false) {
        $candidates[] = 'confidence-cue-routine';
    }
    if ($type === 'post-practice reset') {
        $candidates[] = 'post-practice-reflection';
    }

    $candidates[] = 'box-breathing-reset';
    foreach ($candidates as $slug) {
        if (isset($lessonLookup[$slug])) {
            return $slug;
        }
    }

    foreach ($lessonLookup as $slug => $_lesson) {
        return (string) $slug;
    }

    return '';
}

function zzCoachOllamaFallbackDecision(array $input, array $lessonLookup): array
{
    $slug = zzCoachOllamaFallbackSlug($input, $lessonLookup);
    $type = strtolower(trim((string) ($input['situation_type'] ?? '')));

    $summary = 'Use one short reset action, then return attention to the next controllable step.';
    $message = 'Run the reset now, then mark Better, Same, or Worse so you can learn from the outcome.';

    if ($type === 'after mistake') {
        $summary = 'A quick post-error reset is the best next move. Use the next few minutes to settle emotion and commit to one controllable action.';
        $message = 'Take the reset now, then get back to the next action with one clear cue.';
    } elseif ($type === 'pre-performance nerves') {
        $summary = 'Bring your arousal down just enough to execute. Use a short breathing or grounding reset before the next performance moment.';
        $message = 'Settle your body first, then choose one cue for the opening action.';
    } elseif ($type === 'low focus') {
        $summary = 'Narrow attention to one cue. The goal is not perfect focus, just a clean return to the next action.';
        $message = 'Pick one target cue and use it on the next action.';
    } elseif ($type === 'frustration / anger') {
        $summary = 'Name the frustration, release the extra tension, and return to one external cue.';
        $message = 'Use the reset before the emotion carries into the next action.';
    } elseif ($type === 'confidence dip') {
        $summary = 'Shift confidence back to process. Use one cue that connects posture, breath, and the next action.';
        $message = 'Choose the cue, repeat it once, and act on the next controllable step.';
    }

    return [
        'slug' => $slug,
        'summary' => $summary,
        'coach_message' => $message,
        'why_this_works' => '',
        'when_to_use' => '',
        'evidence_note' => '',
        'steps' => [],
    ];
}

function zzCoachOllamaResolveDecisionSlug(array $decision, array $input, array $lessonLookup): string
{
    $slug = trim((string) ($decision['slug'] ?? $decision['top_slug'] ?? ''));
    if ($slug !== '' && isset($lessonLookup[$slug])) {
        return $slug;
    }

    return zzCoachOllamaFallbackSlug($input, $lessonLookup);
}

function zzCoachOllamaBuildRecommendation(array $lesson, array $decision, array $input): array
{
    $title = zzCoachOllamaCleanText((string) ($lesson['title'] ?? 'Coach Reset'), 90);
    $why = zzCoachOllamaCleanText((string) ($decision['why_this_works'] ?? $decision['why'] ?? ''), 220);
    if ($why === '') {
        $why = zzCoachOllamaCleanText((string) ($lesson['why_this_works'] ?? 'This gives you one controllable reset action.'), 220);
    }

    $when = zzCoachOllamaCleanText((string) ($decision['when_to_use'] ?? ''), 220);
    if ($when === '') {
        $when = zzCoachOllamaCleanText((string) ($lesson['when_to_use'] ?? 'Use it before the next action.'), 220);
    }

    $steps = [];
    if (!empty($decision['steps']) && is_array($decision['steps'])) {
        foreach ($decision['steps'] as $step) {
            $cleanStep = zzCoachOllamaCleanText((string) $step, 140);
            if ($cleanStep !== '') {
                $steps[] = $cleanStep;
            }
        }
    }

    return [
        'slug' => (string) ($lesson['slug'] ?? ''),
        'title' => $title,
        'why_this_works' => $why,
        'when_to_use' => $when,
        'steps' => array_slice($steps, 0, 4),
        'duration_minutes' => max(1, (int) ($lesson['duration_minutes'] ?? ($input['time_available'] ?? 3))),
        'evidence_note' => zzCoachOllamaCleanText(
            (string) ($decision['evidence_note'] ?? ($lesson['evidence_note'] ?? 'Local retrieval source used for grounding.')),
            180
        ),
    ];
}

function zzCoachOllamaBuildCoachCandidate(
    array $decision,
    array $payload,
    string $knowledgeMode,
    array $retrieval,
    string $model
): array {
    $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
    $lessonLookup = zzCoachOllamaLessonLookup($payload['lesson_catalog'] ?? []);
    $slug = zzCoachOllamaResolveDecisionSlug($decision, $input, $lessonLookup);
    $lesson = isset($lessonLookup[$slug]) && is_array($lessonLookup[$slug]) ? $lessonLookup[$slug] : [];

    $summary = zzCoachOllamaCleanText((string) ($decision['summary'] ?? ''), 220);
    if ($summary === '') {
        $summary = 'Use one short reset action, then return attention to the next controllable step.';
    }

    $coachMessage = zzCoachOllamaCleanText((string) ($decision['coach_message'] ?? ''), 220);
    if ($coachMessage === '') {
        $coachMessage = 'Run the reset now, then mark Better, Same, or Worse so you can learn from the outcome.';
    }

    return [
        'crisis_detected' => false,
        'crisis_message' => null,
        'summary' => $summary,
        'top_recommendation' => zzCoachOllamaBuildRecommendation($lesson, $decision, $input),
        'alternatives' => [],
        'coach_message' => $coachMessage,
        'source_mode' => 'external_ai',
        'knowledge_mode' => $knowledgeMode,
        'citations' => $retrieval['citations'],
        'retrieval_metadata' => [
            'provider' => 'ollama_local',
            'model' => $model,
            'knowledge_mode' => $knowledgeMode,
            'result_count' => count($retrieval['citations']),
            'queries' => $retrieval['query_terms'],
            'manifest_path' => $retrieval['manifest_path'],
        ],
    ];
}

function zzCoachOllamaReadManifestRecords(string $manifestPath, string $knowledgeMode): array
{
    $path = zzCoachOllamaNormalizePath($manifestPath);
    if ($path === '' || !is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    $records = is_array($decoded) ? ($decoded['records'] ?? []) : [];
    if (!is_array($records)) {
        return [];
    }

    $filtered = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $mode = strtolower(trim((string) ($record['mode'] ?? ($record['metadata']['mode'] ?? ''))));
        if ($mode !== $knowledgeMode) {
            continue;
        }

        $filtered[] = $record;
    }

    return $filtered;
}

function zzCoachOllamaCandidateDownloadPath(array $record): string
{
    $sourceId = strtolower(trim((string) ($record['id'] ?? '')));
    if ($sourceId === '') {
        return '';
    }

    $downloadDir = zzCoachOllamaNormalizePath(
        zzCoachOllamaConfigValue(['COACH_LOCAL_KNOWLEDGE_DOWNLOAD_DIR'], ['ZENZONE_COACH_LOCAL_KNOWLEDGE_DOWNLOAD_DIR'], 'tmp/knowledge-downloads')
    );
    if (!is_dir($downloadDir)) {
        return '';
    }

    $matches = glob($downloadDir . DIRECTORY_SEPARATOR . $sourceId . '.*');
    if (!is_array($matches) || empty($matches)) {
        return '';
    }

    foreach ($matches as $match) {
        if (is_file($match) && preg_match('/\.(html?|txt|md|json)$/i', $match) === 1) {
            return $match;
        }
    }

    foreach ($matches as $match) {
        if (is_file($match)) {
            return $match;
        }
    }

    return '';
}

function zzCoachOllamaSourcePath(array $record): string
{
    $source = is_array($record['source'] ?? null) ? $record['source'] : [];
    $sourceType = strtolower(trim((string) ($source['type'] ?? '')));
    $sourcePath = trim((string) ($source['path'] ?? ''));

    if ($sourceType === 'file') {
        $path = zzCoachOllamaNormalizePath($sourcePath);
        return is_file($path) ? $path : '';
    }

    return zzCoachOllamaCandidateDownloadPath($record);
}

function zzCoachOllamaCleanText(string $value, int $maxLength = 260): string
{
    $clean = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
    $clean = trim($clean);

    if ($clean === '') {
        return '';
    }
    if (strlen($clean) > $maxLength) {
        return rtrim(substr($clean, 0, $maxLength - 3)) . '...';
    }

    return $clean;
}

function zzCoachOllamaExtractTextFromFile(string $path): string
{
    if ($path === '' || !is_file($path) || filesize($path) > 3000000) {
        return '';
    }

    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['html', 'htm', 'txt', 'md', 'json'], true)) {
        return '';
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return '';
    }

    if (in_array($ext, ['html', 'htm'], true)) {
        $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $raw) ?? $raw;
        $raw = strip_tags($raw);
    }

    return zzCoachOllamaCleanText($raw, 12000);
}

function zzCoachOllamaTokenize(string $text): array
{
    $text = strtolower($text);
    $parts = preg_split('/[^a-z0-9]+/', $text);
    if (!is_array($parts)) {
        return [];
    }

    $stop = array_flip([
        'the', 'and', 'for', 'with', 'that', 'this', 'you', 'your', 'are', 'was',
        'were', 'from', 'have', 'has', 'had', 'but', 'not', 'next', 'just', 'into',
        'about', 'after', 'before', 'during', 'practice', 'game',
    ]);

    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (strlen($part) < 3 || isset($stop[$part])) {
            continue;
        }
        $tokens[$part] = $part;
    }

    return array_values($tokens);
}

function zzCoachOllamaScoreRecord(array $record, string $sourceText, array $tokens): int
{
    $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
    $tags = is_array($metadata['tags'] ?? null) ? implode(' ', $metadata['tags']) : '';
    $haystack = strtolower(
        (string) ($record['title'] ?? '') . ' ' .
        (string) ($metadata['domain'] ?? '') . ' ' .
        (string) ($metadata['evidence_tier'] ?? '') . ' ' .
        $tags . ' ' .
        substr($sourceText, 0, 30000)
    );

    $score = 0;
    foreach ($tokens as $token) {
        if ($token !== '' && strpos($haystack, $token) !== false) {
            $score += 2;
        }
    }

    return $score;
}

function zzCoachOllamaBuildExcerpt(string $sourceText, array $tokens, array $record): string
{
    if ($sourceText !== '') {
        $lower = strtolower($sourceText);
        foreach ($tokens as $token) {
            $position = strpos($lower, $token);
            if ($position === false) {
                continue;
            }

            $start = max(0, $position - 120);
            return zzCoachOllamaCleanText(substr($sourceText, $start, 420), 260);
        }

        return zzCoachOllamaCleanText(substr($sourceText, 0, 420), 260);
    }

    $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
    $parts = [
        (string) ($record['title'] ?? ''),
        (string) ($metadata['domain'] ?? ''),
        (string) ($metadata['evidence_tier'] ?? ''),
    ];

    return zzCoachOllamaCleanText(implode(' | ', array_filter($parts)), 260);
}

function zzCoachOllamaBuildLocalRetrieval(array $payload, string $knowledgeMode, int $maxResults): array
{
    $manifestPath = zzCoachOllamaConfigValue(
        ['COACH_LOCAL_KNOWLEDGE_MANIFEST'],
        ['ZENZONE_COACH_LOCAL_KNOWLEDGE_MANIFEST'],
        'tmp/knowledge-manifests/combined-manifest.json'
    );
    $records = zzCoachOllamaReadManifestRecords($manifestPath, $knowledgeMode);
    $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
    $queryText = implode(' ', [
        (string) ($input['situation_text'] ?? ''),
        (string) ($input['situation_type'] ?? ''),
        (string) ($input['upcoming_event'] ?? ''),
        (string) ($input['goal_title'] ?? ''),
    ]);
    $tokens = zzCoachOllamaTokenize($queryText);

    $ranked = [];
    foreach ($records as $record) {
        $path = zzCoachOllamaSourcePath($record);
        $sourceText = zzCoachOllamaExtractTextFromFile($path);
        $score = zzCoachOllamaScoreRecord($record, $sourceText, $tokens);
        $ranked[] = [
            'record' => $record,
            'path' => $path,
            'source_text' => $sourceText,
            'score' => $score,
        ];
    }

    usort($ranked, static function (array $a, array $b): int {
        return (int) ($b['score'] ?? 0) <=> (int) ($a['score'] ?? 0);
    });

    $sources = [];
    $citations = [];
    foreach (array_slice($ranked, 0, $maxResults) as $item) {
        $record = is_array($item['record'] ?? null) ? $item['record'] : [];
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $source = is_array($record['source'] ?? null) ? $record['source'] : [];
        $sourcePath = trim((string) ($source['path'] ?? ''));
        $url = filter_var($sourcePath, FILTER_VALIDATE_URL) !== false ? $sourcePath : '';
        $filename = trim((string) ($item['path'] !== '' ? basename((string) $item['path']) : ''));
        $excerpt = zzCoachOllamaBuildExcerpt((string) ($item['source_text'] ?? ''), $tokens, $record);
        $score = (int) ($item['score'] ?? 0);
        $normalizedScore = $score > 0 ? min(1.0, $score / 10) : 0.1;

        $citation = [
            'title' => zzCoachOllamaCleanText((string) ($record['title'] ?? ''), 180),
            'url' => $url,
            'file_id' => 'local:' . zzCoachOllamaCleanText((string) ($record['id'] ?? ''), 80),
            'filename' => $filename,
            'score' => $normalizedScore,
            'evidence_tier' => zzCoachOllamaCleanText((string) ($metadata['evidence_tier'] ?? ''), 80),
            'excerpt' => zzCoachOllamaCleanText($excerpt, 120),
        ];

        $citations[] = $citation;
        $sources[] = [
            'source_id' => (string) ($record['id'] ?? ''),
            'title' => $citation['title'],
            'domain' => (string) ($metadata['domain'] ?? ''),
            'evidence_tier' => $citation['evidence_tier'],
            'risk_level' => (string) ($metadata['risk_level'] ?? ''),
            'url' => $citation['url'],
            'excerpt' => $citation['excerpt'],
        ];
    }

    return [
        'sources' => $sources,
        'citations' => $citations,
        'query_terms' => array_slice($tokens, 0, 12),
        'manifest_path' => $manifestPath,
    ];
}

function zzCoachOllamaBuildSchema(): array
{
    $recommendationSchema = [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'why_this_works' => ['type' => 'string'],
            'when_to_use' => ['type' => 'string'],
            'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
            'duration_minutes' => ['type' => 'integer'],
            'evidence_note' => ['type' => 'string'],
        ],
        'required' => ['slug', 'title', 'why_this_works', 'when_to_use', 'steps', 'duration_minutes', 'evidence_note'],
    ];

    return [
        'type' => 'object',
        'properties' => [
            'crisis_detected' => ['type' => 'boolean'],
            'crisis_message' => ['type' => ['string', 'null']],
            'summary' => ['type' => 'string'],
            'top_recommendation' => ['type' => ['object', 'null']],
            'alternatives' => ['type' => 'array', 'items' => $recommendationSchema],
            'coach_message' => ['type' => 'string'],
            'source_mode' => ['type' => 'string'],
            'knowledge_mode' => ['type' => 'string'],
            'citations' => ['type' => 'array'],
            'retrieval_metadata' => ['type' => 'object'],
        ],
        'required' => [
            'crisis_detected',
            'crisis_message',
            'summary',
            'top_recommendation',
            'alternatives',
            'coach_message',
            'source_mode',
            'knowledge_mode',
            'citations',
            'retrieval_metadata',
        ],
    ];
}

function zzCoachOllamaBuildDecisionSchema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'slug' => ['type' => 'string'],
            'summary' => ['type' => 'string'],
            'coach_message' => ['type' => 'string'],
            'why_this_works' => ['type' => 'string'],
            'when_to_use' => ['type' => 'string'],
            'evidence_note' => ['type' => 'string'],
            'steps' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => [
            'slug',
            'summary',
            'coach_message',
            'why_this_works',
            'when_to_use',
            'evidence_note',
            'steps',
        ],
    ];
}

function zzCoachOllamaDecodeJsonFromText(string $text): ?array
{
    $clean = trim($text);
    if ($clean === '') {
        return null;
    }

    if (substr($clean, 0, 3) === '```') {
        $clean = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/```$/', '', $clean) ?? $clean;
        $clean = trim($clean);
    }

    $decoded = json_decode($clean, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($clean, '{');
    $end = strrpos($clean, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $snippet = substr($clean, $start, $end - $start + 1);
    $decodedSnippet = json_decode($snippet, true);

    return is_array($decodedSnippet) ? $decodedSnippet : null;
}

function zzCoachOllamaHasCitationObjects($citations): bool
{
    if (!is_array($citations) || empty($citations)) {
        return false;
    }

    foreach ($citations as $citation) {
        if (!is_array($citation)) {
            return false;
        }

        $title = trim((string) ($citation['title'] ?? ''));
        $fileId = trim((string) ($citation['file_id'] ?? ''));
        $url = trim((string) ($citation['url'] ?? ''));
        if ($title !== '' || $fileId !== '' || $url !== '') {
            return true;
        }
    }

    return false;
}

function zzCoachOllamaCall(array $request, string $baseUrl, int $timeoutSeconds): array
{
    $json = json_encode($request, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        zzCoachOllamaSendJson(500, ['error' => 'ollama_payload_encode_failed']);
    }

    $ch = curl_init(rtrim($baseUrl, '/') . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw)) {
        zzCoachOllamaSendJson(502, [
            'error' => 'ollama_request_failed',
            'message' => $error !== '' ? $error : 'Could not reach Ollama.',
        ]);
    }

    $decoded = json_decode($raw, true);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        zzCoachOllamaSendJson(502, [
            'error' => 'ollama_api_error',
            'status' => $httpStatus,
            'message' => is_array($decoded) ? (string) ($decoded['error'] ?? $raw) : $raw,
        ]);
    }

    if (!is_array($decoded)) {
        zzCoachOllamaSendJson(502, [
            'error' => 'ollama_invalid_json',
            'message' => 'Ollama returned invalid JSON.',
        ]);
    }

    return $decoded;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    zzCoachOllamaSendJson(405, [
        'error' => 'method_not_allowed',
        'message' => 'Use POST.',
    ]);
}

zzCoachOllamaRequireToken();

if (!function_exists('curl_init')) {
    zzCoachOllamaSendJson(503, [
        'error' => 'curl_missing',
        'message' => 'PHP cURL extension is required.',
    ]);
}

$payload = zzCoachOllamaReadJsonBody();
$knowledgeMode = zzCoachOllamaKnowledgeMode($payload);
$maxResults = zzCoachOllamaMaxResults($payload);
$retrieval = zzCoachOllamaBuildLocalRetrieval($payload, $knowledgeMode, $maxResults);
$model = zzCoachOllamaConfigValue(['COACH_OLLAMA_MODEL'], ['ZENZONE_COACH_OLLAMA_MODEL'], 'qwen3:0.6b');
$fastMode = zzCoachOllamaConfigBool(['COACH_OLLAMA_FAST_MODE'], ['ZENZONE_COACH_OLLAMA_FAST_MODE'], true);

if ($fastMode) {
    $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
    $lessonLookup = zzCoachOllamaLessonLookup($payload['lesson_catalog'] ?? []);
    $decision = zzCoachOllamaFallbackDecision($input, $lessonLookup);
    $candidate = zzCoachOllamaBuildCoachCandidate($decision, $payload, $knowledgeMode, $retrieval, 'local_fast_mode');
    zzCoachOllamaSendJson(200, $candidate);
}

$baseUrl = zzCoachOllamaConfigValue(['COACH_OLLAMA_BASE_URL'], ['ZENZONE_COACH_OLLAMA_BASE_URL'], 'http://localhost:11434');
$timeoutSeconds = zzCoachOllamaConfigInt(['COACH_OLLAMA_TIMEOUT_SECONDS'], ['ZENZONE_COACH_OLLAMA_TIMEOUT_SECONDS'], 90, 15, 300);
$numPredict = zzCoachOllamaConfigInt(['COACH_OLLAMA_NUM_PREDICT'], ['ZENZONE_COACH_OLLAMA_NUM_PREDICT'], 280, 180, 700);

$userPayload = [
    'instruction' => 'Choose the best lesson slug and write short Coach copy. Return JSON only with keys: slug, summary, coach_message, why_this_works, when_to_use, evidence_note, steps.',
    'knowledge_mode' => $knowledgeMode,
    'input' => is_array($payload['input'] ?? null) ? $payload['input'] : [],
    'lesson_catalog' => zzCoachOllamaCompactLessonCatalog($payload['lesson_catalog'] ?? []),
    'local_knowledge' => $retrieval['sources'],
];

$ollamaResponse = zzCoachOllamaCall([
    'model' => $model,
    'stream' => false,
    'think' => false,
    'keep_alive' => '30m',
    'format' => zzCoachOllamaBuildDecisionSchema(),
    'messages' => [
        [
            'role' => 'system',
            'content' => zzCoachOllamaBuildCompactSystemPrompt($knowledgeMode),
        ],
        [
            'role' => 'user',
            'content' => json_encode($userPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ],
    ],
    'options' => [
        'temperature' => 0.2,
        'num_predict' => $numPredict,
    ],
], $baseUrl, $timeoutSeconds);

$content = '';
if (isset($ollamaResponse['message']) && is_array($ollamaResponse['message'])) {
    $content = trim((string) ($ollamaResponse['message']['content'] ?? ''));
}

$decision = zzCoachOllamaDecodeJsonFromText($content);
if ($decision === null) {
    if (($_GET['debug'] ?? '') === '1') {
        zzCoachOllamaSendJson(502, [
            'error' => 'coach_response_parse_failed',
            'message' => 'Ollama did not return valid Coach JSON.',
            'raw_content' => zzCoachOllamaCleanText($content, 2000),
        ]);
    }

    zzCoachOllamaSendJson(502, [
        'error' => 'coach_response_parse_failed',
        'message' => 'Ollama did not return valid Coach JSON.',
    ]);
}

$candidate = zzCoachOllamaBuildCoachCandidate($decision, $payload, $knowledgeMode, $retrieval, $model);

zzCoachOllamaSendJson(200, $candidate);
