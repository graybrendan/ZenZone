<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_WINDOW_SECONDS = 300;
const LOGIN_RATE_LIMIT_LOCK_SECONDS = 600;

function authRedirect(string $path, array $query = []): void
{
    $url = BASE_URL . '/' . ltrim($path, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    header('Location: ' . $url);
    exit;
}

function getAuthPageMessage(string $page, array $queryParams): string
{
    $errorCode = (string) ($queryParams['error'] ?? '');
    $statusCode = (string) ($queryParams['status'] ?? '');

    if ($page === 'login') {
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
            return 'Please enter a valid name, email, and password (6+ characters).';
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

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
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
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = hash('sha256', uniqid((string) mt_rand(), true));
        }
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken($submittedToken): bool
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
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
    return sha1(getClientIpAddress());
}

function getLoginRateLimitEntry(): array
{
    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        $_SESSION['login_rate_limits'] = [];
    }

    $key = getLoginRateLimitStorageKey();
    if (!isset($_SESSION['login_rate_limits'][$key]) || !is_array($_SESSION['login_rate_limits'][$key])) {
        $_SESSION['login_rate_limits'][$key] = [
            'count' => 0,
            'window_start' => time(),
            'lock_until' => 0,
        ];
    }

    return $_SESSION['login_rate_limits'][$key];
}

function setLoginRateLimitEntry(array $entry): void
{
    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        $_SESSION['login_rate_limits'] = [];
    }

    $key = getLoginRateLimitStorageKey();
    $_SESSION['login_rate_limits'][$key] = [
        'count' => max(0, (int) ($entry['count'] ?? 0)),
        'window_start' => (int) ($entry['window_start'] ?? time()),
        'lock_until' => max(0, (int) ($entry['lock_until'] ?? 0)),
    ];
}

function clearFailedLoginAttempts(): void
{
    if (!isset($_SESSION['login_rate_limits']) || !is_array($_SESSION['login_rate_limits'])) {
        return;
    }

    $key = getLoginRateLimitStorageKey();
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
    // Prevent session fixation after successful authentication.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
}

function logoutUser(): void
{
    clearFailedLoginAttempts();
    $_SESSION = [];

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
