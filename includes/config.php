<?php

function getEnvOrDefault(string $key, string $default): string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    $value = trim($value);
    return $value === '' ? $default : $value;
}

function normalizeBaseUrl(string $baseUrl): string
{
    $baseUrl = trim($baseUrl);

    if ($baseUrl === '' || $baseUrl === '/') {
        return '';
    }

    $baseUrl = rtrim($baseUrl, '/');

    if (preg_match('#^https?://#i', $baseUrl) === 1) {
        return $baseUrl;
    }

    if (strpos($baseUrl, '/') !== 0) {
        $baseUrl = '/' . $baseUrl;
    }

    return $baseUrl;
}

$appEnv = strtolower(getEnvOrDefault('APP_ENV', 'local'));
if ($appEnv === '') {
    $appEnv = 'local';
}

$dbPortRaw = getEnvOrDefault('DB_PORT', '3306');
$dbPort = ctype_digit($dbPortRaw) ? (int) $dbPortRaw : 3306;
if ($dbPort < 1 || $dbPort > 65535) {
    $dbPort = 3306;
}

$defaultBaseUrl = $appEnv === 'production' ? '' : '/ZenZone/public';
$baseUrl = normalizeBaseUrl(getEnvOrDefault('BASE_URL', $defaultBaseUrl));

define('APP_ENV', $appEnv);
define('DB_HOST', getEnvOrDefault('DB_HOST', '127.0.0.1'));
define('DB_PORT', $dbPort);
define('DB_NAME', getEnvOrDefault('DB_NAME', 'zenzone'));
define('DB_USER', getEnvOrDefault('DB_USER', 'root'));
define('DB_PASS', getEnvOrDefault('DB_PASS', ''));
define('BASE_URL', $baseUrl);
