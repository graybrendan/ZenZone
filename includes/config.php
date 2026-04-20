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

function getFirstEnvOrDefault(array $keys, string $default): string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value === false) {
            continue;
        }

        $value = trim($value);
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function getMysqlUrlParts(): array
{
    $url = getFirstEnvOrDefault(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL'], '');
    if ($url === '') {
        return [];
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return [];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== '' && $scheme !== 'mysql') {
        return [];
    }

    return [
        'host' => (string) ($parts['host'] ?? ''),
        'port' => isset($parts['port']) ? (string) $parts['port'] : '',
        'name' => isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '',
        'user' => isset($parts['user']) ? urldecode((string) $parts['user']) : '',
        'pass' => isset($parts['pass']) ? urldecode((string) $parts['pass']) : '',
    ];
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

$mysqlUrlParts = getMysqlUrlParts();

$dbHost = getFirstEnvOrDefault(['DB_HOST', 'MYSQLHOST'], $mysqlUrlParts['host'] ?? '127.0.0.1');
$dbPortRaw = getFirstEnvOrDefault(['DB_PORT', 'MYSQLPORT'], $mysqlUrlParts['port'] ?? '3306');
$dbName = getFirstEnvOrDefault(['DB_NAME', 'MYSQLDATABASE'], $mysqlUrlParts['name'] ?? 'zenzone');
$dbUser = getFirstEnvOrDefault(['DB_USER', 'MYSQLUSER'], $mysqlUrlParts['user'] ?? 'root');
$dbPass = getFirstEnvOrDefault(['DB_PASS', 'MYSQLPASSWORD'], $mysqlUrlParts['pass'] ?? '');

$dbPort = ctype_digit($dbPortRaw) ? (int) $dbPortRaw : 3306;
if ($dbPort < 1 || $dbPort > 65535) {
    $dbPort = 3306;
}

$defaultBaseUrl = $appEnv === 'production' ? '' : '/ZenZone/public';
$baseUrl = normalizeBaseUrl(getEnvOrDefault('BASE_URL', $defaultBaseUrl));

define('APP_ENV', $appEnv);
define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('BASE_URL', $baseUrl);
