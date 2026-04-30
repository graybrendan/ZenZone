<?php

declare(strict_types=1);

const DEFAULT_INPUT_PATH = 'docs/knowledge-sources.json';
const DEFAULT_OUTPUT_DIR = 'tmp/knowledge-manifests';

const ALLOWED_MODES = ['evidence', 'reflection'];
const ALLOWED_DOMAINS = [
    'psychology',
    'sports_psychology',
    'mindfulness',
    'positive_psychology',
    'hermetic_philosophy',
    'general_wellbeing',
];
const ALLOWED_EVIDENCE_TIERS = [
    'guideline',
    'consensus_statement',
    'position_stand',
    'systematic_review',
    'meta_analysis',
    'rct',
    'review',
    'scholarly_reference',
    'other',
];
const ALLOWED_POPULATIONS = [
    'general',
    'adolescents',
    'college_students',
    'adults',
    'athletes',
    'elite_athletes',
    'mixed',
];
const ALLOWED_RISK_LEVELS = ['low', 'medium', 'high'];
const ALLOWED_SOURCE_TYPES = ['url', 'file'];
const ALLOWED_STATUS = ['active', 'draft', 'archived'];

function usage(): void
{
    $lines = [
        'Usage:',
        '  php scripts/prepare_knowledge_manifest.php [options]',
        '',
        'Options:',
        '  --input=PATH        Source registry JSON file (default: docs/knowledge-sources.json)',
        '  --output-dir=PATH   Output directory for manifests (default: tmp/knowledge-manifests)',
        '  --check-only        Validate only; do not write output files',
        '  --include-draft     Include draft entries in output manifests',
        '  --help              Show this help',
        '',
        'Examples:',
        '  php scripts/prepare_knowledge_manifest.php --check-only',
        '  php scripts/prepare_knowledge_manifest.php --output-dir=tmp/knowledge-manifests',
    ];

    echo implode(PHP_EOL, $lines) . PHP_EOL;
}

function parseArgs(array $argv): array
{
    $options = [
        'input' => DEFAULT_INPUT_PATH,
        'output_dir' => DEFAULT_OUTPUT_DIR,
        'check_only' => false,
        'include_draft' => false,
        'help' => false,
    ];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if ($arg === '--check-only') {
            $options['check_only'] = true;
            continue;
        }

        if ($arg === '--include-draft') {
            $options['include_draft'] = true;
            continue;
        }

        if (strpos($arg, '--input=') === 0) {
            $options['input'] = trim(substr($arg, 8));
            continue;
        }

        if (strpos($arg, '--output-dir=') === 0) {
            $options['output_dir'] = trim(substr($arg, 13));
            continue;
        }

        fwrite(STDERR, 'Unknown option: ' . $arg . PHP_EOL);
        $options['help'] = true;
        $options['has_error'] = true;
    }

    return $options;
}

function normalizePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    // Absolute path (Windows drive or UNC).
    if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || strpos($path, '\\\\') === 0) {
        return $path;
    }

    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') {
        return $path;
    }

    return $cwd . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function isValidDateYmd(string $date): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $date;
}

function normalizeTags($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $tags = [];
    foreach ($value as $tag) {
        if (!is_string($tag)) {
            continue;
        }

        $clean = strtolower(trim($tag));
        if ($clean === '' || strlen($clean) > 60) {
            continue;
        }

        $tags[$clean] = $clean;
    }

    return array_values($tags);
}

function validateSource(array $source, int $index, array &$errors): ?array
{
    $prefix = 'sources[' . $index . ']';

    $id = trim((string) ($source['id'] ?? ''));
    $title = trim((string) ($source['title'] ?? ''));
    $mode = strtolower(trim((string) ($source['mode'] ?? '')));
    $domain = strtolower(trim((string) ($source['domain'] ?? '')));
    $evidenceTier = strtolower(trim((string) ($source['evidence_tier'] ?? '')));
    $population = strtolower(trim((string) ($source['population'] ?? '')));
    $riskLevel = strtolower(trim((string) ($source['risk_level'] ?? '')));
    $lastReviewed = trim((string) ($source['last_reviewed_at'] ?? ''));
    $sourceType = strtolower(trim((string) ($source['source_type'] ?? '')));
    $sourcePath = trim((string) ($source['source_path'] ?? ''));
    $status = strtolower(trim((string) ($source['status'] ?? 'active')));
    $tags = normalizeTags($source['tags'] ?? []);

    if ($id === '' || preg_match('/^[a-z0-9][a-z0-9-_]{2,120}$/', $id) !== 1) {
        $errors[] = $prefix . '.id must be lowercase slug format (3-121 chars).';
    }
    if ($title === '' || strlen($title) > 240) {
        $errors[] = $prefix . '.title is required and must be <= 240 chars.';
    }
    if (!in_array($mode, ALLOWED_MODES, true)) {
        $errors[] = $prefix . '.mode must be one of: ' . implode(', ', ALLOWED_MODES) . '.';
    }
    if (!in_array($domain, ALLOWED_DOMAINS, true)) {
        $errors[] = $prefix . '.domain must be one of: ' . implode(', ', ALLOWED_DOMAINS) . '.';
    }
    if (!in_array($evidenceTier, ALLOWED_EVIDENCE_TIERS, true)) {
        $errors[] = $prefix . '.evidence_tier must be one of: ' . implode(', ', ALLOWED_EVIDENCE_TIERS) . '.';
    }
    if (!in_array($population, ALLOWED_POPULATIONS, true)) {
        $errors[] = $prefix . '.population must be one of: ' . implode(', ', ALLOWED_POPULATIONS) . '.';
    }
    if (!in_array($riskLevel, ALLOWED_RISK_LEVELS, true)) {
        $errors[] = $prefix . '.risk_level must be one of: ' . implode(', ', ALLOWED_RISK_LEVELS) . '.';
    }
    if (!isValidDateYmd($lastReviewed)) {
        $errors[] = $prefix . '.last_reviewed_at must use YYYY-MM-DD.';
    }
    if (!in_array($sourceType, ALLOWED_SOURCE_TYPES, true)) {
        $errors[] = $prefix . '.source_type must be one of: ' . implode(', ', ALLOWED_SOURCE_TYPES) . '.';
    }
    if (!in_array($status, ALLOWED_STATUS, true)) {
        $errors[] = $prefix . '.status must be one of: ' . implode(', ', ALLOWED_STATUS) . '.';
    }
    if ($sourcePath === '') {
        $errors[] = $prefix . '.source_path is required.';
    }
    if ($sourceType === 'url' && $sourcePath !== '' && filter_var($sourcePath, FILTER_VALIDATE_URL) === false) {
        $errors[] = $prefix . '.source_path must be a valid URL when source_type=url.';
    }
    if ($sourceType === 'file' && $sourcePath !== '' && preg_match('/^https?:\/\//i', $sourcePath) === 1) {
        $errors[] = $prefix . '.source_path must be a local file path when source_type=file.';
    }

    if (!empty($errors)) {
        return null;
    }

    return [
        'id' => $id,
        'title' => $title,
        'mode' => $mode,
        'source' => [
            'type' => $sourceType,
            'path' => $sourcePath,
        ],
        'metadata' => [
            'domain' => $domain,
            'evidence_tier' => $evidenceTier,
            'population' => $population,
            'risk_level' => $riskLevel,
            'last_reviewed_at' => $lastReviewed,
            'mode' => $mode,
            'status' => $status,
            'tags' => $tags,
        ],
    ];
}

function writeJsonFile(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Failed to encode JSON for ' . $path . '.');
    }

    $written = file_put_contents($path, $json . PHP_EOL);
    if ($written === false) {
        throw new RuntimeException('Failed to write file: ' . $path);
    }
}

function run(array $argv): int
{
    $options = parseArgs($argv);
    if ($options['help'] === true) {
        usage();
        return !empty($options['has_error']) ? 1 : 0;
    }

    $inputPath = normalizePath((string) $options['input']);
    if ($inputPath === '' || !is_file($inputPath)) {
        fwrite(STDERR, 'Input file not found: ' . $options['input'] . PHP_EOL);
        return 1;
    }

    $raw = file_get_contents($inputPath);
    if (!is_string($raw) || trim($raw) === '') {
        fwrite(STDERR, 'Input file is empty or unreadable: ' . $inputPath . PHP_EOL);
        return 1;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, 'Invalid JSON in input file: ' . $inputPath . PHP_EOL);
        return 1;
    }

    $sources = $decoded['sources'] ?? null;
    if (!is_array($sources)) {
        fwrite(STDERR, 'Input JSON must include a top-level "sources" array.' . PHP_EOL);
        return 1;
    }

    $errors = [];
    $normalized = [];
    $seenIds = [];

    foreach ($sources as $index => $source) {
        if (!is_array($source)) {
            $errors[] = 'sources[' . $index . '] must be an object.';
            continue;
        }

        $sourceErrorsBefore = count($errors);
        $entry = validateSource($source, $index, $errors);

        if ($entry === null) {
            continue;
        }

        $id = $entry['id'];
        if (isset($seenIds[$id])) {
            $errors[] = 'Duplicate source id detected: ' . $id;
            continue;
        }
        $seenIds[$id] = true;

        // If no new errors were added for this source, keep it.
        if (count($errors) === $sourceErrorsBefore) {
            $normalized[] = $entry;
        }
    }

    if (!empty($errors)) {
        fwrite(STDERR, 'Validation failed:' . PHP_EOL);
        foreach ($errors as $error) {
            fwrite(STDERR, ' - ' . $error . PHP_EOL);
        }
        return 1;
    }

    $includeDraft = (bool) $options['include_draft'];

    $eligible = array_values(array_filter($normalized, static function (array $entry) use ($includeDraft): bool {
        $status = (string) ($entry['metadata']['status'] ?? 'active');
        if ($status === 'archived') {
            return false;
        }
        if (!$includeDraft && $status !== 'active') {
            return false;
        }
        return true;
    }));

    $evidence = array_values(array_filter($eligible, static function (array $entry): bool {
        return ($entry['mode'] ?? '') === 'evidence';
    }));
    $reflection = array_values(array_filter($eligible, static function (array $entry): bool {
        return ($entry['mode'] ?? '') === 'reflection';
    }));

    $summary = [
        'input_file' => $options['input'],
        'total_sources' => count($sources),
        'validated_sources' => count($normalized),
        'eligible_sources' => count($eligible),
        'evidence_sources' => count($evidence),
        'reflection_sources' => count($reflection),
        'include_draft' => $includeDraft,
    ];

    echo 'Validation passed.' . PHP_EOL;
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    if ((bool) $options['check_only']) {
        return 0;
    }

    $outputDir = normalizePath((string) $options['output_dir']);
    if ($outputDir === '') {
        fwrite(STDERR, 'Invalid output directory.' . PHP_EOL);
        return 1;
    }

    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            fwrite(STDERR, 'Could not create output directory: ' . $outputDir . PHP_EOL);
            return 1;
        }
    }

    $generatedAt = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

    $combinedManifest = [
        'generated_at' => $generatedAt,
        'source_registry_version' => (int) ($decoded['version'] ?? 1),
        'summary' => $summary,
        'records' => $eligible,
    ];

    $evidenceManifest = [
        'generated_at' => $generatedAt,
        'mode' => 'evidence',
        'count' => count($evidence),
        'records' => $evidence,
    ];

    $reflectionManifest = [
        'generated_at' => $generatedAt,
        'mode' => 'reflection',
        'count' => count($reflection),
        'records' => $reflection,
    ];

    try {
        writeJsonFile($outputDir . DIRECTORY_SEPARATOR . 'combined-manifest.json', $combinedManifest);
        writeJsonFile($outputDir . DIRECTORY_SEPARATOR . 'evidence-manifest.json', $evidenceManifest);
        writeJsonFile($outputDir . DIRECTORY_SEPARATOR . 'reflection-manifest.json', $reflectionManifest);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Failed to write manifests: ' . $e->getMessage() . PHP_EOL);
        return 1;
    }

    echo 'Manifest files written to: ' . $outputDir . PHP_EOL;
    return 0;
}

exit(run($argv));
