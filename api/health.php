<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$isLocalHealthRequest = APP_ENV === 'local' || isLocalRequestHost();
if (!$isLocalHealthRequest) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Not found',
    ]);
    exit;
}

$response = [
    'status' => 'ok',
    'message' => 'ZenZone API is running',
    'checks' => [
        'app_env' => APP_ENV,
        'db_connection' => false,
        'selected_database' => null,
        'users_table_exists' => false,
        'users_select_ok' => false,
    ],
];

try {
    $db = getDB();
    $response['checks']['db_connection'] = true;

    $selectedDb = $db->query('SELECT DATABASE()')->fetchColumn();
    $response['checks']['selected_database'] = is_string($selectedDb) ? $selectedDb : null;

    $schemaName = (string) ($response['checks']['selected_database'] ?? DB_NAME);

    $tableStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = :schema_name
          AND table_name = 'users'
    ");
    $tableStmt->execute([
        'schema_name' => $schemaName,
    ]);

    $response['checks']['users_table_exists'] = ((int) $tableStmt->fetchColumn()) > 0;

    if ($response['checks']['users_table_exists']) {
        $db->query('SELECT id FROM users LIMIT 1');
        $response['checks']['users_select_ok'] = true;
    }
} catch (PDOException $e) {
    error_log('Health DB check failed: ' . $e->getMessage());
    $response['status'] = 'error';
    $response['message'] = 'Database check failed';
}

if ($response['checks']['db_connection'] === false || $response['checks']['users_table_exists'] === false || $response['checks']['users_select_ok'] === false) {
    if ($response['status'] !== 'error') {
        $response['status'] = 'error';
        $response['message'] = 'Database is not ready for auth';
    }
    http_response_code(500);
}

echo json_encode($response);
