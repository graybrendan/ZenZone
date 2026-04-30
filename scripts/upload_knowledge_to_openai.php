<?php

declare(strict_types=1);

const DEFAULT_MANIFEST_PATH = 'tmp/knowledge-manifests/combined-manifest.json';
const DEFAULT_OUTPUT_PATH = 'tmp/knowledge-manifests/openai-upload-map.json';
const DEFAULT_DOWNLOAD_DIR = 'tmp/knowledge-downloads';
const OPENAI_API_BASE_URL = 'https://api.openai.com/v1';

function usage(): void
{
    $lines = [
        'Usage:',
        '  php scripts/upload_knowledge_to_openai.php [options]',
        '',
        'Options:',
        '  --manifest=PATH              Manifest JSON file (default: tmp/knowledge-manifests/combined-manifest.json)',
        '  --output=PATH                Upload map output file (default: tmp/knowledge-manifests/openai-upload-map.json)',
        '  --download-dir=PATH          Temporary URL download directory (default: tmp/knowledge-downloads)',
        '  --mode=all|evidence|reflection',
        '  --store-id=ID                Shared existing vector store ID',
        '  --store-id-evidence=ID       Existing evidence vector store ID',
        '  --store-id-reflection=ID     Existing reflection vector store ID',
        '  --purpose=assistants|user_data',
        '  --poll-timeout=SECONDS       Per-file vector store processing timeout (default: 180)',
        '  --dry-run                    Validate and print planned work without calling OpenAI',
        '  --force                      Re-upload records even if the output map already has them',
        '  --help                       Show this help',
        '',
        'Environment:',
        '  OPENAI_API_KEY is required unless --dry-run is used.',
        '  Existing store IDs can also come from ZENZONE_COACH_VECTOR_STORE_IDS,',
        '  ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE, and ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION.',
        '',
        'Examples:',
        '  C:\\xampp\\php\\php.exe scripts/upload_knowledge_to_openai.php --dry-run',
        '  C:\\xampp\\php\\php.exe scripts/upload_knowledge_to_openai.php',
        '  C:\\xampp\\php\\php.exe scripts/upload_knowledge_to_openai.php --store-id-evidence=vs_abc123',
    ];

    echo implode(PHP_EOL, $lines) . PHP_EOL;
}

function parseArgs(array $argv): array
{
    $options = [
        'manifest' => DEFAULT_MANIFEST_PATH,
        'output' => DEFAULT_OUTPUT_PATH,
        'download_dir' => DEFAULT_DOWNLOAD_DIR,
        'mode' => 'all',
        'store_id' => '',
        'store_id_evidence' => '',
        'store_id_reflection' => '',
        'purpose' => 'assistants',
        'poll_timeout' => 180,
        'dry_run' => false,
        'force' => false,
        'help' => false,
        'has_error' => false,
    ];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }
        if ($arg === '--force') {
            $options['force'] = true;
            continue;
        }
        if (strpos($arg, '--manifest=') === 0) {
            $options['manifest'] = trim(substr($arg, 11));
            continue;
        }
        if (strpos($arg, '--output=') === 0) {
            $options['output'] = trim(substr($arg, 9));
            continue;
        }
        if (strpos($arg, '--download-dir=') === 0) {
            $options['download_dir'] = trim(substr($arg, 15));
            continue;
        }
        if (strpos($arg, '--mode=') === 0) {
            $options['mode'] = strtolower(trim(substr($arg, 7)));
            continue;
        }
        if (strpos($arg, '--store-id=') === 0) {
            $options['store_id'] = trim(substr($arg, 11));
            continue;
        }
        if (strpos($arg, '--store-id-evidence=') === 0) {
            $options['store_id_evidence'] = trim(substr($arg, 20));
            continue;
        }
        if (strpos($arg, '--store-id-reflection=') === 0) {
            $options['store_id_reflection'] = trim(substr($arg, 22));
            continue;
        }
        if (strpos($arg, '--purpose=') === 0) {
            $options['purpose'] = strtolower(trim(substr($arg, 10)));
            continue;
        }
        if (strpos($arg, '--poll-timeout=') === 0) {
            $options['poll_timeout'] = (int) trim(substr($arg, 15));
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

    if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || strpos($path, '\\\\') === 0) {
        return $path;
    }

    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') {
        return $path;
    }

    return $cwd . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function getenvTrimmed(string $key): string
{
    $value = getenv($key);
    if ($value === false) {
        return '';
    }

    return trim((string) $value);
}

function firstCsvItem(string $value): string
{
    $items = array_map('trim', explode(',', $value));
    foreach ($items as $item) {
        if ($item !== '') {
            return $item;
        }
    }

    return '';
}

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('File not found: ' . $path);
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('File is empty or unreadable: ' . $path);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON in file: ' . $path);
    }

    return $decoded;
}

function writeJsonFile(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create output directory: ' . $dir);
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Failed to encode JSON for ' . $path);
    }

    if (file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException('Failed to write file: ' . $path);
    }
}

function loadManifestRecords(array $manifest, string $mode): array
{
    $records = $manifest['records'] ?? null;
    if (!is_array($records)) {
        throw new RuntimeException('Manifest must include a top-level records array.');
    }

    $filtered = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $recordMode = strtolower(trim((string) ($record['mode'] ?? ($record['metadata']['mode'] ?? ''))));
        if (!in_array($recordMode, ['evidence', 'reflection'], true)) {
            continue;
        }
        if ($mode !== 'all' && $recordMode !== $mode) {
            continue;
        }

        $source = $record['source'] ?? [];
        if (!is_array($source)) {
            continue;
        }

        $record['mode'] = $recordMode;
        $filtered[] = $record;
    }

    return $filtered;
}

function sanitizeFilename(string $name): string
{
    $clean = strtolower(trim($name));
    $clean = preg_replace('/[^a-z0-9._-]+/', '-', $clean) ?? $clean;
    $clean = trim($clean, '.-');

    return $clean !== '' ? $clean : 'source';
}

function extensionFromContentType(string $contentType): string
{
    $contentType = strtolower(trim(explode(';', $contentType)[0]));
    $map = [
        'application/pdf' => '.pdf',
        'text/html' => '.html',
        'application/xhtml+xml' => '.html',
        'text/markdown' => '.md',
        'text/plain' => '.txt',
        'application/json' => '.json',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
    ];

    return $map[$contentType] ?? '';
}

function extensionFromUrl(string $url): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'html', 'htm', 'md', 'txt', 'json', 'doc', 'docx', 'pptx'];
    if ($ext !== '' && in_array($ext, $allowed, true)) {
        return '.' . $ext;
    }

    return '';
}

function detectMimeType(string $path, string $fallback = 'application/octet-stream'): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return $fallback;
}

function downloadUrlSource(array $record, string $downloadDir): array
{
    $sourceId = sanitizeFilename((string) ($record['id'] ?? 'source'));
    $url = trim((string) ($record['source']['path'] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Invalid source URL for ' . $sourceId);
    }

    if (!is_dir($downloadDir) && !mkdir($downloadDir, 0777, true) && !is_dir($downloadDir)) {
        throw new RuntimeException('Could not create download directory: ' . $downloadDir);
    }

    $tempPath = $downloadDir . DIRECTORY_SEPARATOR . $sourceId . '.download';
    $handle = fopen($tempPath, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not open temporary download file: ' . $tempPath);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $handle,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'ZenZone Knowledge Uploader/1.0',
    ]);

    $ok = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($handle);

    if ($ok !== true || $httpStatus < 200 || $httpStatus >= 300) {
        @unlink($tempPath);
        throw new RuntimeException('Download failed for ' . $url . ' (HTTP ' . $httpStatus . '): ' . $error);
    }

    $extension = extensionFromUrl($url);
    if ($extension === '') {
        $extension = extensionFromContentType($contentType);
    }
    if ($extension === '') {
        $extension = '.txt';
    }

    $finalPath = $downloadDir . DIRECTORY_SEPARATOR . $sourceId . $extension;
    if (is_file($finalPath)) {
        @unlink($finalPath);
    }
    if (!rename($tempPath, $finalPath)) {
        @unlink($tempPath);
        throw new RuntimeException('Could not move downloaded file to: ' . $finalPath);
    }

    return [
        'path' => $finalPath,
        'filename' => basename($finalPath),
        'mime_type' => detectMimeType($finalPath, $contentType !== '' ? $contentType : 'application/octet-stream'),
    ];
}

function resolveLocalSource(array $record): array
{
    $sourceId = sanitizeFilename((string) ($record['id'] ?? 'source'));
    $rawPath = trim((string) ($record['source']['path'] ?? ''));
    $path = normalizePath($rawPath);

    if ($path === '' || !is_file($path)) {
        throw new RuntimeException('Local source file not found for ' . $sourceId . ': ' . $rawPath);
    }

    return [
        'path' => $path,
        'filename' => basename($path),
        'mime_type' => detectMimeType($path),
    ];
}

function resolveSourceFile(array $record, string $downloadDir): array
{
    $sourceType = strtolower(trim((string) ($record['source']['type'] ?? '')));
    if ($sourceType === 'url') {
        return downloadUrlSource($record, $downloadDir);
    }
    if ($sourceType === 'file') {
        return resolveLocalSource($record);
    }

    throw new RuntimeException('Unsupported source type for ' . (string) ($record['id'] ?? 'unknown') . ': ' . $sourceType);
}

function truncateAttribute(string $value, int $maxLength = 512): string
{
    $clean = trim((string) preg_replace('/\s+/', ' ', $value));
    if (strlen($clean) > $maxLength) {
        return substr($clean, 0, $maxLength);
    }

    return $clean;
}

function buildVectorStoreAttributes(array $record): array
{
    $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
    $tags = $metadata['tags'] ?? [];
    if (!is_array($tags)) {
        $tags = [];
    }

    return [
        'source_id' => truncateAttribute((string) ($record['id'] ?? ''), 64),
        'title' => truncateAttribute((string) ($record['title'] ?? '')),
        'mode' => truncateAttribute((string) ($record['mode'] ?? '')),
        'domain' => truncateAttribute((string) ($metadata['domain'] ?? ''), 64),
        'evidence_tier' => truncateAttribute((string) ($metadata['evidence_tier'] ?? ''), 64),
        'population' => truncateAttribute((string) ($metadata['population'] ?? ''), 64),
        'risk_level' => truncateAttribute((string) ($metadata['risk_level'] ?? ''), 64),
        'last_reviewed_at' => truncateAttribute((string) ($metadata['last_reviewed_at'] ?? ''), 32),
        'source_type' => truncateAttribute((string) ($record['source']['type'] ?? ''), 32),
        'source_path' => truncateAttribute((string) ($record['source']['path'] ?? '')),
        'tags' => truncateAttribute(implode(',', array_map('strval', $tags))),
    ];
}

function openaiJsonRequest(string $method, string $path, string $apiKey, ?array $payload = null): array
{
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'OpenAI-Beta: assistants=v2',
    ];

    $ch = curl_init(OPENAI_API_BASE_URL . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($payload !== null) {
        $json = json_encode($payload);
        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode OpenAI request payload.');
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw)) {
        throw new RuntimeException('OpenAI request failed: ' . $error);
    }

    $decoded = json_decode($raw, true);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $raw) : $raw;
        throw new RuntimeException('OpenAI API error (HTTP ' . $httpStatus . '): ' . $message);
    }

    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI returned invalid JSON.');
    }

    return $decoded;
}

function openaiUploadFile(string $apiKey, string $filePath, string $filename, string $mimeType, string $purpose): array
{
    $curlFile = new CURLFile($filePath, $mimeType, $filename);

    $ch = curl_init(OPENAI_API_BASE_URL . '/files');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => [
            'purpose' => $purpose,
            'file' => $curlFile,
        ],
    ]);

    $raw = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw)) {
        throw new RuntimeException('OpenAI file upload failed: ' . $error);
    }

    $decoded = json_decode($raw, true);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $raw) : $raw;
        throw new RuntimeException('OpenAI file upload error (HTTP ' . $httpStatus . '): ' . $message);
    }

    if (!is_array($decoded) || trim((string) ($decoded['id'] ?? '')) === '') {
        throw new RuntimeException('OpenAI file upload response did not include a file id.');
    }

    return $decoded;
}

function createVectorStore(string $apiKey, string $mode): array
{
    $label = $mode === 'reflection' ? 'Reflection' : 'Evidence';

    return openaiJsonRequest('POST', '/vector_stores', $apiKey, [
        'name' => 'ZenZone Coach ' . $label . ' Knowledge Base',
        'metadata' => [
            'app' => 'zenzone',
            'feature' => 'coach',
            'mode' => $mode,
        ],
    ]);
}

function attachFileToVectorStore(string $apiKey, string $vectorStoreId, string $fileId, array $attributes): array
{
    return openaiJsonRequest(
        'POST',
        '/vector_stores/' . rawurlencode($vectorStoreId) . '/files',
        $apiKey,
        [
            'file_id' => $fileId,
            'attributes' => $attributes,
        ]
    );
}

function retrieveVectorStoreFile(string $apiKey, string $vectorStoreId, string $fileId): array
{
    return openaiJsonRequest(
        'GET',
        '/vector_stores/' . rawurlencode($vectorStoreId) . '/files/' . rawurlencode($fileId),
        $apiKey
    );
}

function pollVectorStoreFile(string $apiKey, string $vectorStoreId, string $fileId, int $timeoutSeconds): array
{
    $deadline = time() + $timeoutSeconds;
    $last = [];

    do {
        $last = retrieveVectorStoreFile($apiKey, $vectorStoreId, $fileId);
        $status = strtolower(trim((string) ($last['status'] ?? '')));

        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            return $last;
        }

        sleep(2);
    } while (time() < $deadline);

    $last['status'] = (string) ($last['status'] ?? 'timeout');
    $last['poll_timeout'] = true;

    return $last;
}

function existingRecordKey(array $record): string
{
    return implode('|', [
        (string) ($record['id'] ?? ''),
        (string) ($record['mode'] ?? ''),
        (string) ($record['source']['type'] ?? ''),
        (string) ($record['source']['path'] ?? ''),
        (string) ($record['metadata']['last_reviewed_at'] ?? ''),
    ]);
}

function findReusableUpload(array $existingMap, array $record, string $vectorStoreId): ?array
{
    $records = $existingMap['records'] ?? [];
    if (!is_array($records)) {
        return null;
    }

    $expectedKey = existingRecordKey($record);
    foreach ($records as $existing) {
        if (!is_array($existing)) {
            continue;
        }

        if (($existing['source_key'] ?? '') !== $expectedKey) {
            continue;
        }
        if (($existing['vector_store_id'] ?? '') !== $vectorStoreId) {
            continue;
        }
        if (($existing['status'] ?? '') !== 'completed') {
            continue;
        }
        if (trim((string) ($existing['openai_file_id'] ?? '')) === '') {
            continue;
        }

        return $existing;
    }

    return null;
}

function resolveVectorStoreIdForMode(string $mode, array $options, array $existingMap): string
{
    $specificOption = $mode === 'reflection' ? 'store_id_reflection' : 'store_id_evidence';
    if (($options[$specificOption] ?? '') !== '') {
        return (string) $options[$specificOption];
    }
    if (($options['store_id'] ?? '') !== '') {
        return (string) $options['store_id'];
    }

    $envSpecific = $mode === 'reflection'
        ? firstCsvItem(getenvTrimmed('ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION'))
        : firstCsvItem(getenvTrimmed('ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE'));
    if ($envSpecific !== '') {
        return $envSpecific;
    }

    $envShared = firstCsvItem(getenvTrimmed('ZENZONE_COACH_VECTOR_STORE_IDS'));
    if ($envShared !== '') {
        return $envShared;
    }

    $existingStores = $existingMap['vector_stores'] ?? [];
    if (is_array($existingStores) && !empty($existingStores[$mode]['id'])) {
        return (string) $existingStores[$mode]['id'];
    }

    return '';
}

function buildInitialOutputMap(array $manifest, array $existingMap, string $manifestPath): array
{
    $now = gmdate('Y-m-d\TH:i:s\Z');

    return [
        'generated_at' => $now,
        'manifest_file' => $manifestPath,
        'source_registry_version' => (int) ($manifest['source_registry_version'] ?? 0),
        'summary' => [
            'uploaded' => 0,
            'skipped' => 0,
            'failed' => 0,
        ],
        'vector_stores' => is_array($existingMap['vector_stores'] ?? null) ? $existingMap['vector_stores'] : [],
        'records' => [],
    ];
}

function run(array $argv): int
{
    $options = parseArgs($argv);
    if ($options['help'] === true) {
        usage();
        return !empty($options['has_error']) ? 1 : 0;
    }

    if (!in_array($options['mode'], ['all', 'evidence', 'reflection'], true)) {
        fwrite(STDERR, '--mode must be all, evidence, or reflection.' . PHP_EOL);
        return 1;
    }
    if (!in_array($options['purpose'], ['assistants', 'user_data'], true)) {
        fwrite(STDERR, '--purpose must be assistants or user_data.' . PHP_EOL);
        return 1;
    }
    if ((int) $options['poll_timeout'] < 10) {
        $options['poll_timeout'] = 10;
    }
    if ((int) $options['poll_timeout'] > 900) {
        $options['poll_timeout'] = 900;
    }

    if (!function_exists('curl_init')) {
        fwrite(STDERR, 'The PHP cURL extension is required.' . PHP_EOL);
        return 1;
    }

    $apiKey = getenvTrimmed('OPENAI_API_KEY');
    if (!$options['dry_run'] && $apiKey === '') {
        fwrite(STDERR, 'OPENAI_API_KEY is required unless --dry-run is used.' . PHP_EOL);
        return 1;
    }

    $manifestPath = normalizePath((string) $options['manifest']);
    $outputPath = normalizePath((string) $options['output']);
    $downloadDir = normalizePath((string) $options['download_dir']);

    try {
        $manifest = readJsonFile($manifestPath);
        $existingMap = is_file($outputPath) ? readJsonFile($outputPath) : [];
        $records = loadManifestRecords($manifest, (string) $options['mode']);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        return 1;
    }

    if (empty($records)) {
        echo 'No manifest records matched mode: ' . $options['mode'] . PHP_EOL;
        return 0;
    }

    $output = buildInitialOutputMap($manifest, $existingMap, (string) $options['manifest']);
    $storeIdsByMode = [];

    foreach (['evidence', 'reflection'] as $mode) {
        $hasMode = false;
        foreach ($records as $record) {
            if (($record['mode'] ?? '') === $mode) {
                $hasMode = true;
                break;
            }
        }
        if (!$hasMode) {
            continue;
        }

        $storeId = resolveVectorStoreIdForMode($mode, $options, $existingMap);
        if ($storeId === '') {
            if ($options['dry_run']) {
                $storeId = 'dry-run-' . $mode . '-vector-store';
                echo '[dry-run] Would create vector store for ' . $mode . '.' . PHP_EOL;
            } else {
                try {
                    echo 'Creating vector store for ' . $mode . '...' . PHP_EOL;
                    $store = createVectorStore($apiKey, $mode);
                    $storeId = (string) ($store['id'] ?? '');
                    if ($storeId === '') {
                        throw new RuntimeException('OpenAI did not return a vector store id for ' . $mode . '.');
                    }
                    $output['vector_stores'][$mode] = [
                        'id' => $storeId,
                        'name' => (string) ($store['name'] ?? ''),
                        'created_at' => (int) ($store['created_at'] ?? 0),
                        'created_by_script' => true,
                    ];
                } catch (Throwable $e) {
                    fwrite(STDERR, 'Could not create vector store for ' . $mode . ': ' . $e->getMessage() . PHP_EOL);
                    fwrite(STDERR, 'Check OPENAI_API_KEY, then rerun the uploader.' . PHP_EOL);
                    return 1;
                }
            }
        } else {
            $output['vector_stores'][$mode] = [
                'id' => $storeId,
                'created_by_script' => false,
            ];
        }

        $storeIdsByMode[$mode] = $storeId;
    }

    foreach ($records as $record) {
        $sourceId = (string) ($record['id'] ?? 'unknown');
        $mode = (string) ($record['mode'] ?? 'evidence');
        $vectorStoreId = (string) ($storeIdsByMode[$mode] ?? '');

        if ($vectorStoreId === '') {
            $output['summary']['failed']++;
            $output['records'][] = [
                'source_id' => $sourceId,
                'source_key' => existingRecordKey($record),
                'mode' => $mode,
                'status' => 'failed',
                'error' => 'No vector store id available.',
            ];
            continue;
        }

        if (!$options['force']) {
            $existing = findReusableUpload($existingMap, $record, $vectorStoreId);
            if ($existing !== null) {
                echo 'Skipping already uploaded source: ' . $sourceId . PHP_EOL;
                $existing['skipped'] = true;
                $output['summary']['skipped']++;
                $output['records'][] = $existing;
                continue;
            }
        }

        if ($options['dry_run']) {
            echo '[dry-run] Would upload ' . $sourceId . ' to ' . $vectorStoreId . PHP_EOL;
            $output['summary']['skipped']++;
            $output['records'][] = [
                'source_id' => $sourceId,
                'source_key' => existingRecordKey($record),
                'mode' => $mode,
                'title' => (string) ($record['title'] ?? ''),
                'vector_store_id' => $vectorStoreId,
                'status' => 'dry_run',
                'source_path' => (string) ($record['source']['path'] ?? ''),
            ];
            continue;
        }

        try {
            echo 'Preparing source: ' . $sourceId . PHP_EOL;
            $file = resolveSourceFile($record, $downloadDir);

            echo 'Uploading file to OpenAI: ' . $file['filename'] . PHP_EOL;
            $uploadedFile = openaiUploadFile(
                $apiKey,
                (string) $file['path'],
                (string) $file['filename'],
                (string) $file['mime_type'],
                (string) $options['purpose']
            );

            $fileId = (string) $uploadedFile['id'];
            echo 'Attaching file to vector store: ' . $vectorStoreId . PHP_EOL;
            attachFileToVectorStore($apiKey, $vectorStoreId, $fileId, buildVectorStoreAttributes($record));

            echo 'Waiting for indexing: ' . $sourceId . PHP_EOL;
            $vectorFile = pollVectorStoreFile($apiKey, $vectorStoreId, $fileId, (int) $options['poll_timeout']);
            $status = strtolower(trim((string) ($vectorFile['status'] ?? 'unknown')));
            $lastError = $vectorFile['last_error'] ?? null;

            if ($status === 'completed') {
                $output['summary']['uploaded']++;
            } else {
                $output['summary']['failed']++;
            }

            $output['records'][] = [
                'source_id' => $sourceId,
                'source_key' => existingRecordKey($record),
                'mode' => $mode,
                'title' => (string) ($record['title'] ?? ''),
                'source_path' => (string) ($record['source']['path'] ?? ''),
                'local_upload_path' => (string) $file['path'],
                'openai_file_id' => $fileId,
                'vector_store_id' => $vectorStoreId,
                'status' => $status,
                'last_error' => $lastError,
                'uploaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
        } catch (Throwable $e) {
            $output['summary']['failed']++;
            $output['records'][] = [
                'source_id' => $sourceId,
                'source_key' => existingRecordKey($record),
                'mode' => $mode,
                'title' => (string) ($record['title'] ?? ''),
                'source_path' => (string) ($record['source']['path'] ?? ''),
                'vector_store_id' => $vectorStoreId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'uploaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
            fwrite(STDERR, 'Failed source ' . $sourceId . ': ' . $e->getMessage() . PHP_EOL);
        }
    }

    try {
        writeJsonFile($outputPath, $output);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        return 1;
    }

    echo 'Upload map written to: ' . $outputPath . PHP_EOL;
    echo json_encode($output['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return $output['summary']['failed'] > 0 ? 1 : 0;
}

exit(run($argv));
