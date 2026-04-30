<?php
require_once __DIR__ . '/config.php';

const ZENZONE_SESSION_COOKIE_NAME = 'ZENZONESESSID_V2';
const ZENZONE_SESSION_COOKIE_LIFETIME = 60 * 60 * 24 * 7;

function getSessionCookiePath(): string
{
    // Use root path so session + CSRF cookies survive /ZenZone vs /zenzone URL casing.
    return '/';
}

function setCsrfCookie(string $token): void
{
    if ($token === '') {
        return;
    }

    setcookie('zz_csrf_token', $token, [
        'expires' => 0,
        'path' => getSessionCookiePath(),
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionCookiePath = getSessionCookiePath();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name(ZENZONE_SESSION_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => ZENZONE_SESSION_COOKIE_LIFETIME,
        'path' => $sessionCookiePath,
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/remember_me.php';

    if (!function_exists('getDB')) {
        require_once __DIR__ . '/db.php';
    }

    try {
        zz_remember_restore_session(getDB());
    } catch (Throwable $e) {
        error_log('Remember-me restore bootstrap failed: ' . $e->getMessage());
    }
}

const LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_WINDOW_SECONDS = 300;
const LOGIN_RATE_LIMIT_LOCK_SECONDS = 600;
const LOGIN_RATE_LIMIT_TABLE = 'login_rate_limits';
const AUTH_CSRF_TTL_SECONDS = 7200;

function authRedirect(string $path, array $query = []): void
{
    $url = BASE_URL . '/' . ltrim($path, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $statusCode = in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? 303 : 302;

    header('Location: ' . $url, true, $statusCode);
    exit;
}

function getAuthPageMessage(string $page, array $queryParams): string
{
    $errorCode = (string) ($queryParams['error'] ?? '');
    $statusCode = (string) ($queryParams['status'] ?? '');

    if ($page === 'login') {
        if ($errorCode === 'invalid_session') {
            return 'Your session is no longer valid. Please log in again.';
        }

        if ($errorCode === 'invalid_credentials') {
            return 'Invalid email or password.';
        }

        if ($errorCode === 'invalid_request') {
            return 'Your request could not be verified. Please try again.';
        }

        if ($errorCode === 'too_many_attempts') {
            $retryAfter = max(0, (int) ($queryParams['retry_after'] ?? 0));
            if ($retryAfter > 0) {
                return 'Too many login attempts. Try again in ' . $retryAfter . ' seconds.';
            }

            return 'Too many login attempts. Please try again later.';
        }

        if ($errorCode === 'login_failed') {
            return 'We could not sign you in right now. Please try again.';
        }

        if ($statusCode === 'logged_out') {
            return 'You have been logged out.';
        }
    }

    if ($page === 'signup') {
        if ($errorCode === 'invalid_input') {
            return 'Please enter a valid first name, last name, activity, email, and password (8+ characters).';
        }

        if ($errorCode === 'email_exists') {
            return 'An account with this email already exists.';
        }

        if ($errorCode === 'invalid_request') {
            return 'Your request could not be verified. Please try again.';
        }

        if ($errorCode === 'registration_failed') {
            return 'We could not create your account right now. Please try again.';
        }
    }

    return '';
}

function clearAuthIdentitySession(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['first_name'],
        $_SESSION['user_sport']
    );
}

function isLoggedIn(): bool
{
    static $authChecked = false;
    static $authIsValid = false;

    if ($authChecked) {
        return $authIsValid;
    }

    $authChecked = true;

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        $authIsValid = false;
        return false;
    }

    if (!function_exists('getDB')) {
        require_once __DIR__ . '/db.php';
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, full_name, email
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('Session user lookup failed: ' . $e->getMessage());
        $authIsValid = false;
        return false;
    }

    if (!$user) {
        clearAuthIdentitySession();
        $_SESSION['auth_invalid_session'] = 1;
        $authIsValid = false;
        return false;
    }

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    unset($_SESSION['auth_invalid_session']);

    if (!isset($_SESSION['first_name']) || trim((string) $_SESSION['first_name']) === '') {
        $fullName = trim((string) ($user['full_name'] ?? ''));
        $parts = preg_split('/\s+/u', $fullName);
        $_SESSION['first_name'] = is_array($parts) && isset($parts[0]) ? trim((string) $parts[0]) : '';
    }

    $authIsValid = true;
    return true;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $invalidSession = !empty($_SESSION['auth_invalid_session']);
        unset($_SESSION['auth_invalid_session']);

        if ($invalidSession) {
            authRedirect('login.php', ['error' => 'invalid_session']);
        }

        authRedirect('login.php');
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        authRedirect('dashboard.php');
    }
}

function getCsrfToken(): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return getSignedAuthCsrfToken($userId);
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = hash('sha256', uniqid((string) mt_rand(), true));
        }
    }

    setCsrfCookie($_SESSION['csrf_token']);

    return $_SESSION['csrf_token'];
}

function getAuthCsrfSigningKey(): string
{
    static $signingKey = null;

    if (is_string($signingKey) && $signingKey !== '') {
        return $signingKey;
    }

    $parts = [
        (string) APP_ENV,
        (string) DB_HOST,
        (string) DB_NAME,
        (string) DB_USER,
        (string) DB_PASS,
        __DIR__,
    ];

    $signingKey = hash('sha256', implode('|', $parts));
    return $signingKey;
}

function getSignedAuthCsrfToken(int $userId): string
{
    $timestamp = (string) time();
    try {
        $nonce = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $nonce = hash('sha256', uniqid((string) mt_rand(), true));
        $nonce = substr($nonce, 0, 32);
    }

    $payload = $timestamp . '.' . $nonce . '.' . $userId;
    $signature = hash_hmac('sha256', $payload, getAuthCsrfSigningKey());

    return $payload . '.' . $signature;
}

function validateSignedAuthCsrfToken(string $submittedToken): bool
{
    $parts = explode('.', $submittedToken);
    if (count($parts) !== 4) {
        return false;
    }

    [$timestampRaw, $nonce, $userIdRaw, $submittedSignature] = $parts;
    if (!ctype_digit($timestampRaw) || !ctype_digit($userIdRaw)) {
        return false;
    }

    $timestamp = (int) $timestampRaw;
    $now = time();
    if ($timestamp <= 0 || abs($now - $timestamp) > AUTH_CSRF_TTL_SECONDS) {
        return false;
    }

    if (!preg_match('/^[a-f0-9]{32}$/', $nonce)) {
        return false;
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $submittedSignature)) {
        return false;
    }

    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    $tokenUserId = (int) $userIdRaw;
    if ($sessionUserId <= 0 || $tokenUserId <= 0 || $sessionUserId !== $tokenUserId) {
        return false;
    }

    $payload = $timestampRaw . '.' . $nonce . '.' . $userIdRaw;
    $expectedSignature = hash_hmac('sha256', $payload, getAuthCsrfSigningKey());

    return hash_equals($expectedSignature, $submittedSignature);
}

function getGuestCsrfToken(): string
{
    // Guests use the same session-bound CSRF token model as authenticated forms.
    return getCsrfToken();
}

function validateGuestCsrfToken($submittedToken): bool
{
    return validateCsrfToken($submittedToken);
}

function validateCsrfToken($submittedToken): bool
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
    }

    if (validateSignedAuthCsrfToken($submittedToken)) {
        return true;
    }

    $cookieToken = (string) ($_COOKIE['zz_csrf_token'] ?? '');
    if ($cookieToken !== '' && hash_equals($cookieToken, $submittedToken)) {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            // Re-sync session token when cookie token proves request authenticity.
            $_SESSION['csrf_token'] = $cookieToken;
        }

        return true;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}

function setFlashMessage(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type' => trim($type),
        'message' => trim($message),
    ];
}

function redirectWithFlash(string $path, string $message, string $type = 'error', array $query = []): void
{
    setFlashMessage($type, $message);
    authRedirect($path, $query);
}

function getFlashMessage(): ?array
{
    if (!isset($_SESSION['flash_message']) || !is_array($_SESSION['flash_message'])) {
        return null;
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    $type = trim((string) ($flash['type'] ?? 'info'));
    $message = trim((string) ($flash['message'] ?? ''));

    if ($message === '') {
        return null;
    }

    if ($type === '') {
        $type = 'info';
    }

    return [
        'type' => $type,
        'message' => $message,
    ];
}

function setOldInput(array $input): void
{
    $_SESSION['old_input'] = [];

    foreach ($input as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if (is_array($value) || is_object($value)) {
            continue;
        }

        $_SESSION['old_input'][$key] = is_null($value) ? '' : (string) $value;
    }
}

function getOldInput(string $key, $default = '')
{
    if (!isset($_SESSION['old_input']) || !is_array($_SESSION['old_input'])) {
        return $default;
    }

    if (!array_key_exists($key, $_SESSION['old_input'])) {
        return $default;
    }

    return $_SESSION['old_input'][$key];
}

function clearOldInput(): void
{
    unset($_SESSION['old_input']);
}

function getClientIpAddress(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return is_string($ip) && $ip !== '' ? $ip : 'unknown';
}

function getLoginRateLimitStorageKey(): string
{
    $ip = getClientIpAddress();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return sha1($ip . '|' . $email);
    }

    return sha1($ip);
}

function getDefaultLoginRateLimitEntry(): array
{
    return [
        'count' => 0,
        'window_start' => time(),
        'lock_until' => 0,
    ];
}

function normalizeLoginRateLimitEntry(array $entry): array
{
    return [
        'count' => max(0, (int) ($entry['count'] ?? 0)),
        'window_start' => (int) ($entry['window_start'] ?? time()),
        'lock_until' => max(0, (int) ($entry['lock_until'] ?? 0)),
    ];
}

function getLoginRateLimitDb(): ?PDO
{
    if (!function_exists('getDB')) {
        require_once __DIR__ . '/db.php';
    }

    try {
        return getDB();
    } catch (Throwable $e) {
        error_log('Login rate limit DB bootstrap failed: ' . $e->getMessage());
        return null;
    }
}

function loginRateLimitTableAvailable(?PDO $db): bool
{
    if (!$db instanceof PDO) {
        return false;
    }

    static $tableAvailable = null;
    if (is_bool($tableAvailable)) {
        return $tableAvailable;
    }

    try {
        $stmt = $db->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
            LIMIT 1
        ");
        $stmt->execute([
            'table_name' => LOGIN_RATE_LIMIT_TABLE,
        ]);
        $tableAvailable = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Login rate limit table check failed: ' . $e->getMessage());
        $tableAvailable = false;
    }

    return $tableAvailable;
}

function getLoginRateLimitEntryFromDb(PDO $db, string $key): ?array
{
    $stmt = $db->prepare("
        SELECT attempt_count, window_start, lock_until
        FROM login_rate_limits
        WHERE rate_key = :rate_key
        LIMIT 1
    ");
    $stmt->execute(['rate_key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return null;
    }

    return normalizeLoginRateLimitEntry([
        'count' => (int) ($row['attempt_count'] ?? 0),
        'window_start' => (int) ($row['window_start'] ?? 0),
        'lock_until' => (int) ($row['lock_until'] ?? 0),
    ]);
}

function setLoginRateLimitEntryInDb(PDO $db, string $key, array $entry): void
{
    $entry = normalizeLoginRateLimitEntry($entry);

    $stmt = $db->prepare("
        INSERT INTO login_rate_limits (
            rate_key,
            attempt_count,
            window_start,
            lock_until,
            updated_at
        ) VALUES (
            :rate_key,
            :attempt_count,
            :window_start,
            :lock_until,
            CURRENT_TIMESTAMP
        )
        ON DUPLICATE KEY UPDATE
            attempt_count = VALUES(attempt_count),
            window_start = VALUES(window_start),
            lock_until = VALUES(lock_until),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        'rate_key' => $key,
        'attempt_count' => $entry['count'],
        'window_start' => $entry['window_start'],
        'lock_until' => $entry['lock_until'],
    ]);

    // Lightweight cleanup to keep table size bounded.
    if (mt_rand(1, 100) === 1) {
        $cleanupBefore = time() - 86400;
        $cleanupStmt = $db->prepare("
            DELETE FROM login_rate_limits
            WHERE lock_until < :cleanup_before
              AND updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $cleanupStmt->execute([
            'cleanup_before' => $cleanupBefore,
        ]);
    }
}

function clearLoginRateLimitEntryInDb(PDO $db, string $key): void
{
    $stmt = $db->prepare("
        DELETE FROM login_rate_limits
        WHERE rate_key = :rate_key
        LIMIT 1
    ");
    $stmt->execute([
        'rate_key' => $key,
    ]);
}

function getLoginRateLimitEntry(): array
{
    $key = getLoginRateLimitStorageKey();
    $db = getLoginRateLimitDb();
    if (loginRateLimitTableAvailable($db)) {
        try {
            $entry = getLoginRateLimitEntryFromDb($db, $key);
            if ($entry !== null) {
                return $entry;
            }
        } catch (Throwable $e) {
            error_log('Login rate limit DB read failed: ' . $e->getMessage());
        }
    }

    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        $_SESSION['login_rate_limits'] = [];
    }

    if (!isset($_SESSION['login_rate_limits'][$key]) || !is_array($_SESSION['login_rate_limits'][$key])) {
        $_SESSION['login_rate_limits'][$key] = getDefaultLoginRateLimitEntry();
    }

    return normalizeLoginRateLimitEntry($_SESSION['login_rate_limits'][$key]);
}

function setLoginRateLimitEntry(array $entry): void
{
    $entry = normalizeLoginRateLimitEntry($entry);
    $key = getLoginRateLimitStorageKey();

    $db = getLoginRateLimitDb();
    if (loginRateLimitTableAvailable($db)) {
        try {
            setLoginRateLimitEntryInDb($db, $key, $entry);
            return;
        } catch (Throwable $e) {
            error_log('Login rate limit DB write failed: ' . $e->getMessage());
        }
    }

    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        $_SESSION['login_rate_limits'] = [];
    }

    $_SESSION['login_rate_limits'][$key] = $entry;
}

function clearFailedLoginAttempts(): void
{
    $key = getLoginRateLimitStorageKey();
    $db = getLoginRateLimitDb();
    if (loginRateLimitTableAvailable($db)) {
        try {
            clearLoginRateLimitEntryInDb($db, $key);
            return;
        } catch (Throwable $e) {
            error_log('Login rate limit DB clear failed: ' . $e->getMessage());
        }
    }

    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        return;
    }

    unset($_SESSION['login_rate_limits'][$key]);
}

function isLoginRateLimited(): bool
{
    $entry = getLoginRateLimitEntry();
    $now = time();
    $lockUntil = (int) ($entry['lock_until'] ?? 0);

    if ($lockUntil > $now) {
        return true;
    }

    if ($lockUntil > 0 && $lockUntil <= $now) {
        clearFailedLoginAttempts();
    }

    return false;
}

function getLoginRetryAfterSeconds(): int
{
    $entry = getLoginRateLimitEntry();
    $remaining = (int) ($entry['lock_until'] ?? 0) - time();
    return max(0, $remaining);
}

function recordFailedLoginAttempt(): void
{
    $entry = getLoginRateLimitEntry();
    $now = time();
    $windowStart = (int) ($entry['window_start'] ?? 0);
    $lockUntil = (int) ($entry['lock_until'] ?? 0);

    if ($lockUntil > $now) {
        return;
    }

    if ($windowStart === 0 || ($now - $windowStart) > LOGIN_RATE_LIMIT_WINDOW_SECONDS) {
        $entry['count'] = 0;
        $entry['window_start'] = $now;
        $entry['lock_until'] = 0;
    }

    $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
    if ($entry['count'] >= LOGIN_RATE_LIMIT_MAX_ATTEMPTS) {
        $entry['lock_until'] = $now + LOGIN_RATE_LIMIT_LOCK_SECONDS;
        $entry['count'] = 0;
        $entry['window_start'] = $now;
    }

    setLoginRateLimitEntry($entry);
}

function loginUser(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    if (isset($user['sport'])) {
        $_SESSION['user_sport'] = trim((string) $user['sport']);
    }
}

function logoutUser(): void
{
    clearFailedLoginAttempts();
    $_SESSION = [];
    setcookie('zz_csrf_token', '', time() - 42000, getSessionCookiePath());

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}
