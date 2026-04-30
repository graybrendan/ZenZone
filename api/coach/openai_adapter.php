<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';

const ZZ_COACH_OPENAI_RESPONSES_URL = 'https://api.openai.com/v1/responses';

function zzCoachAdapterSendJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json');

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    echo is_string($json) ? $json : '{"error":"json_encode_failed"}';
    exit;
}

function zzCoachAdapterConfigValue(array $constantNames, array $envNames, string $default = ''): string
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

function zzCoachAdapterConfigInt(array $constantNames, array $envNames, int $default, int $min, int $max): int
{
    $raw = zzCoachAdapterConfigValue($constantNames, $envNames, (string) $default);
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

function zzCoachAdapterBearerToken(): string
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

    if ($header === '') {
        return '';
    }

    if (stripos($header, 'Bearer ') !== 0) {
        return '';
    }

    return trim(substr($header, 7));
}

function zzCoachAdapterRequireToken(): void
{
    $expected = zzCoachAdapterConfigValue(
        ['COACH_AI_TOKEN'],
        ['ZENZONE_COACH_AI_TOKEN'],
        ''
    );

    if ($expected === '') {
        zzCoachAdapterSendJson(503, [
            'error' => 'adapter_token_not_configured',
            'message' => 'ZENZONE_COACH_AI_TOKEN or COACH_AI_TOKEN must be configured.',
        ]);
    }

    $actual = zzCoachAdapterBearerToken();
    if ($actual === '' || !hash_equals($expected, $actual)) {
        zzCoachAdapterSendJson(401, [
            'error' => 'unauthorized',
            'message' => 'Missing or invalid adapter bearer token.',
        ]);
    }
}

function zzCoachAdapterReadJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        zzCoachAdapterSendJson(400, [
            'error' => 'empty_request_body',
            'message' => 'Request body must be JSON.',
        ]);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        zzCoachAdapterSendJson(400, [
            'error' => 'invalid_json',
            'message' => 'Request body was not valid JSON.',
        ]);
    }

    return $decoded;
}

function zzCoachAdapterCsvList(string $csv): array
{
    if ($csv === '') {
        return [];
    }

    $items = array_map('trim', explode(',', $csv));
    $items = array_filter($items, static function ($item): bool {
        return is_string($item) && $item !== '';
    });

    return array_values(array_unique($items));
}

function zzCoachAdapterResolveVectorStores(array $payload, string $knowledgeMode): array
{
    $contract = is_array($payload['knowledge_contract'] ?? null) ? $payload['knowledge_contract'] : [];
    $retrieval = is_array($contract['retrieval'] ?? null) ? $contract['retrieval'] : [];
    $fromPayload = $retrieval['vector_store_ids'] ?? [];

    if (is_array($fromPayload)) {
        $ids = [];
        foreach ($fromPayload as $id) {
            $clean = trim((string) $id);
            if ($clean !== '') {
                $ids[] = $clean;
            }
        }
        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }
    }

    $modeEnv = $knowledgeMode === 'reflection'
        ? zzCoachAdapterConfigValue(['COACH_VECTOR_STORE_IDS_REFLECTION'], ['ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION'], '')
        : zzCoachAdapterConfigValue(['COACH_VECTOR_STORE_IDS_EVIDENCE'], ['ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE'], '');

    $ids = zzCoachAdapterCsvList($modeEnv);
    if (!empty($ids)) {
        return $ids;
    }

    return zzCoachAdapterCsvList(
        zzCoachAdapterConfigValue(['COACH_VECTOR_STORE_IDS'], ['ZENZONE_COACH_VECTOR_STORE_IDS'], '')
    );
}

function zzCoachAdapterKnowledgeMode(array $payload): string
{
    $mode = strtolower(trim((string) ($payload['knowledge_mode'] ?? 'evidence')));
    return in_array($mode, ['evidence', 'reflection'], true) ? $mode : 'evidence';
}

function zzCoachAdapterMaxResults(array $payload): int
{
    $contract = is_array($payload['knowledge_contract'] ?? null) ? $payload['knowledge_contract'] : [];
    $retrieval = is_array($contract['retrieval'] ?? null) ? $contract['retrieval'] : [];
    $maxResults = (int) ($retrieval['max_num_results'] ?? 6);

    if ($maxResults < 1) {
        return 1;
    }
    if ($maxResults > 20) {
        return 20;
    }

    return $maxResults;
}

function zzCoachAdapterBuildSchema(): array
{
    $recommendationSchema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'why_this_works' => ['type' => 'string'],
            'when_to_use' => ['type' => 'string'],
            'steps' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'duration_minutes' => ['type' => 'integer'],
            'evidence_note' => ['type' => 'string'],
        ],
        'required' => [
            'slug',
            'title',
            'why_this_works',
            'when_to_use',
            'steps',
            'duration_minutes',
            'evidence_note',
        ],
    ];

    $citationSchema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'title' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'file_id' => ['type' => 'string'],
            'filename' => ['type' => 'string'],
            'score' => ['type' => ['number', 'null']],
            'evidence_tier' => ['type' => 'string'],
            'excerpt' => ['type' => 'string'],
        ],
        'required' => [
            'title',
            'url',
            'file_id',
            'filename',
            'score',
            'evidence_tier',
            'excerpt',
        ],
    ];

    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'crisis_detected' => ['type' => 'boolean'],
            'crisis_message' => ['type' => ['string', 'null']],
            'summary' => ['type' => 'string'],
            'top_recommendation' => [
                'anyOf' => [
                    $recommendationSchema,
                    ['type' => 'null'],
                ],
            ],
            'alternatives' => [
                'type' => 'array',
                'items' => $recommendationSchema,
            ],
            'coach_message' => ['type' => 'string'],
            'source_mode' => ['type' => 'string', 'enum' => ['external_ai']],
            'knowledge_mode' => ['type' => 'string', 'enum' => ['evidence', 'reflection']],
            'citations' => [
                'type' => 'array',
                'items' => $citationSchema,
            ],
            'retrieval_metadata' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'provider' => ['type' => 'string'],
                    'result_count' => ['type' => 'integer'],
                    'queries' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['provider', 'result_count', 'queries'],
            ],
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

function zzCoachAdapterBuildPromptPayload(array $payload, string $knowledgeMode, array $vectorStoreIds): array
{
    return [
        'task' => 'Generate one ZenZone Coach response using file_search for grounded support. Return JSON only.',
        'knowledge_mode' => $knowledgeMode,
        'input' => is_array($payload['input'] ?? null) ? $payload['input'] : [],
        'lesson_catalog' => is_array($payload['lesson_catalog'] ?? null) ? $payload['lesson_catalog'] : [],
        'knowledge_contract' => is_array($payload['knowledge_contract'] ?? null) ? $payload['knowledge_contract'] : [],
        'retrieval_context' => [
            'provider' => 'openai_file_search',
            'vector_store_ids' => $vectorStoreIds,
            'citation_instruction' => 'Use retrieved source information only. Do not fabricate titles, URLs, file IDs, filenames, or scores.',
        ],
    ];
}

function zzCoachAdapterOpenAiRequest(array $payload, string $apiKey, int $timeoutSeconds): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        zzCoachAdapterSendJson(500, [
            'error' => 'openai_payload_encode_failed',
        ]);
    }

    $ch = curl_init(ZZ_COACH_OPENAI_RESPONSES_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $raw = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw)) {
        zzCoachAdapterSendJson(502, [
            'error' => 'openai_request_failed',
            'message' => $error,
        ]);
    }

    $decoded = json_decode($raw, true);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $raw) : $raw;
        zzCoachAdapterSendJson(502, [
            'error' => 'openai_api_error',
            'message' => $message,
            'status' => $httpStatus,
        ]);
    }

    if (!is_array($decoded)) {
        zzCoachAdapterSendJson(502, [
            'error' => 'openai_invalid_json',
        ]);
    }

    return $decoded;
}

function zzCoachAdapterExtractOutputText(array $response): string
{
    $outputText = trim((string) ($response['output_text'] ?? ''));
    if ($outputText !== '') {
        return $outputText;
    }

    $parts = [];
    $output = $response['output'] ?? [];
    if (!is_array($output)) {
        return '';
    }

    foreach ($output as $item) {
        if (!is_array($item)) {
            continue;
        }

        $content = $item['content'] ?? [];
        if (!is_array($content)) {
            continue;
        }

        foreach ($content as $contentItem) {
            if (!is_array($contentItem)) {
                continue;
            }

            $text = trim((string) ($contentItem['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }
    }

    return trim(implode("\n", $parts));
}

function zzCoachAdapterDecodeJsonFromText(string $text): ?array
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

function zzCoachAdapterCleanText(string $value, int $maxLength = 260): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $value));
    if ($clean === '') {
        return '';
    }

    if (strlen($clean) > $maxLength) {
        $clean = rtrim(substr($clean, 0, $maxLength - 3)) . '...';
    }

    return $clean;
}

function zzCoachAdapterCitationFromSearchResult(array $result): ?array
{
    $attributes = is_array($result['attributes'] ?? null) ? $result['attributes'] : [];
    $fileId = zzCoachAdapterCleanText((string) ($result['file_id'] ?? ''), 90);
    $filename = zzCoachAdapterCleanText((string) ($result['filename'] ?? ''), 180);
    $title = zzCoachAdapterCleanText((string) ($attributes['title'] ?? $filename), 180);
    $url = trim((string) ($attributes['source_path'] ?? ''));
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
        $url = '';
    }

    $score = null;
    if (is_numeric($result['score'] ?? null)) {
        $score = (float) $result['score'];
        if ($score < 0) {
            $score = 0.0;
        }
        if ($score > 1) {
            $score = 1.0;
        }
    }

    $text = '';
    if (isset($result['text']) && is_string($result['text'])) {
        $text = $result['text'];
    } elseif (!empty($result['content']) && is_array($result['content'])) {
        foreach ($result['content'] as $contentItem) {
            if (is_array($contentItem) && isset($contentItem['text'])) {
                $text = (string) $contentItem['text'];
                break;
            }
        }
    }

    if ($title === '' && $fileId === '' && $filename === '' && $text === '') {
        return null;
    }

    return [
        'title' => $title,
        'url' => $url,
        'file_id' => $fileId,
        'filename' => $filename,
        'score' => $score,
        'evidence_tier' => zzCoachAdapterCleanText((string) ($attributes['evidence_tier'] ?? ''), 80),
        'excerpt' => zzCoachAdapterCleanText($text, 260),
    ];
}

function zzCoachAdapterCollectSearchCitations(array $response): array
{
    $citations = [];
    $seen = [];
    $output = $response['output'] ?? [];
    if (!is_array($output)) {
        return [];
    }

    foreach ($output as $item) {
        if (!is_array($item)) {
            continue;
        }

        foreach (['search_results', 'results'] as $resultsKey) {
            $results = $item[$resultsKey] ?? [];
            if (!is_array($results)) {
                continue;
            }

            foreach ($results as $result) {
                if (!is_array($result)) {
                    continue;
                }

                $citation = zzCoachAdapterCitationFromSearchResult($result);
                if ($citation === null) {
                    continue;
                }

                $key = strtolower(($citation['file_id'] ?? '') . '|' . ($citation['filename'] ?? '') . '|' . ($citation['title'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $citations[] = $citation;
            }
        }
    }

    return array_slice($citations, 0, 10);
}

function zzCoachAdapterCollectQueries(array $response): array
{
    $queries = [];
    $output = $response['output'] ?? [];
    if (!is_array($output)) {
        return [];
    }

    foreach ($output as $item) {
        if (!is_array($item) || empty($item['queries']) || !is_array($item['queries'])) {
            continue;
        }

        foreach ($item['queries'] as $query) {
            $clean = zzCoachAdapterCleanText((string) $query, 160);
            if ($clean !== '') {
                $queries[] = $clean;
            }
        }
    }

    return array_slice(array_values(array_unique($queries)), 0, 4);
}

function zzCoachAdapterMergeCitations(array $candidate, array $searchCitations): array
{
    $existing = is_array($candidate['citations'] ?? null) ? $candidate['citations'] : [];
    if (!empty($existing)) {
        return $existing;
    }

    return $searchCitations;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    zzCoachAdapterSendJson(405, [
        'error' => 'method_not_allowed',
        'message' => 'Use POST.',
    ]);
}

zzCoachAdapterRequireToken();

if (!function_exists('curl_init')) {
    zzCoachAdapterSendJson(503, [
        'error' => 'curl_missing',
        'message' => 'PHP cURL extension is required.',
    ]);
}

$apiKey = zzCoachAdapterConfigValue(['OPENAI_API_KEY', 'ZENZONE_OPENAI_API_KEY'], ['OPENAI_API_KEY', 'ZENZONE_OPENAI_API_KEY'], '');
if ($apiKey === '') {
    zzCoachAdapterSendJson(503, [
        'error' => 'openai_api_key_missing',
        'message' => 'OPENAI_API_KEY must be configured.',
    ]);
}

$payload = zzCoachAdapterReadJsonBody();
$knowledgeMode = zzCoachAdapterKnowledgeMode($payload);
$vectorStoreIds = zzCoachAdapterResolveVectorStores($payload, $knowledgeMode);
if (empty($vectorStoreIds)) {
    zzCoachAdapterSendJson(503, [
        'error' => 'vector_store_missing',
        'message' => 'No vector store ID configured for knowledge mode: ' . $knowledgeMode,
    ]);
}

$model = zzCoachAdapterConfigValue(['COACH_OPENAI_MODEL'], ['ZENZONE_COACH_OPENAI_MODEL'], 'gpt-5.4-mini');
$timeoutSeconds = zzCoachAdapterConfigInt(['COACH_OPENAI_TIMEOUT_SECONDS'], ['ZENZONE_COACH_OPENAI_TIMEOUT_SECONDS'], 30, 10, 120);
$maxOutputTokens = zzCoachAdapterConfigInt(['COACH_OPENAI_MAX_OUTPUT_TOKENS'], ['ZENZONE_COACH_OPENAI_MAX_OUTPUT_TOKENS'], 1400, 400, 4000);
$systemPrompt = trim((string) ($payload['system_prompt'] ?? ''));
if ($systemPrompt === '') {
    zzCoachAdapterSendJson(400, [
        'error' => 'system_prompt_missing',
        'message' => 'The adapter payload must include system_prompt.',
    ]);
}

$promptPayload = zzCoachAdapterBuildPromptPayload($payload, $knowledgeMode, $vectorStoreIds);
$openAiPayload = [
    'model' => $model,
    'instructions' => $systemPrompt,
    'input' => json_encode($promptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    'tools' => [
        [
            'type' => 'file_search',
            'vector_store_ids' => $vectorStoreIds,
            'max_num_results' => zzCoachAdapterMaxResults($payload),
        ],
    ],
    'include' => ['file_search_call.results'],
    'max_output_tokens' => $maxOutputTokens,
    'store' => false,
    'text' => [
        'format' => [
            'type' => 'json_schema',
            'name' => 'zenzone_coach_response',
            'strict' => true,
            'schema' => zzCoachAdapterBuildSchema(),
        ],
    ],
];

$openAiResponse = zzCoachAdapterOpenAiRequest($openAiPayload, $apiKey, $timeoutSeconds);
$outputText = zzCoachAdapterExtractOutputText($openAiResponse);
$candidate = zzCoachAdapterDecodeJsonFromText($outputText);
if ($candidate === null) {
    zzCoachAdapterSendJson(502, [
        'error' => 'coach_response_parse_failed',
        'message' => 'OpenAI response did not contain valid Coach JSON.',
        'openai_response_id' => (string) ($openAiResponse['id'] ?? ''),
    ]);
}

$searchCitations = zzCoachAdapterCollectSearchCitations($openAiResponse);
$candidate['source_mode'] = 'external_ai';
$candidate['knowledge_mode'] = $knowledgeMode;
$candidate['citations'] = zzCoachAdapterMergeCitations($candidate, $searchCitations);
$candidate['retrieval_metadata'] = [
    'provider' => 'openai_file_search',
    'result_count' => count($searchCitations),
    'queries' => zzCoachAdapterCollectQueries($openAiResponse),
    'response_id' => (string) ($openAiResponse['id'] ?? ''),
    'model' => (string) ($openAiResponse['model'] ?? $model),
    'vector_store_ids' => $vectorStoreIds,
];

zzCoachAdapterSendJson(200, $candidate);
