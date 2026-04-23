<?php

const ZZ_REMEMBER_COOKIE = 'zz_remember';
const ZZ_REMEMBER_LIFETIME = 60 * 60 * 24 * 90;
const ZZ_SELECTOR_BYTES = 8;
const ZZ_VALIDATOR_BYTES = 32;

function zz_remember_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS'])
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function zz_remember_clear_cookie(): void
{
    setcookie(ZZ_REMEMBER_COOKIE, '', zz_remember_cookie_options(time() - 3600));
    unset($_COOKIE[ZZ_REMEMBER_COOKIE]);
}

function zz_remember_is_valid_hex(string $value, int $expectedLength): bool
{
    if (strlen($value) !== $expectedLength) {
        return false;
    }

    return preg_match('/^[a-f0-9]+$/i', $value) === 1;
}

function zz_remember_truncate_user_agent(?string $userAgent): ?string
{
    if ($userAgent === null) {
        return null;
    }

    $userAgent = trim($userAgent);
    if ($userAgent === '') {
        return null;
    }

    return substr($userAgent, 0, 255);
}

function zz_remember_derive_first_name(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '';
    }

    $parts = preg_split('/\s+/u', $fullName);
    if (!is_array($parts) || empty($parts[0])) {
        return $fullName;
    }

    return trim((string) $parts[0]);
}

function zz_remember_users_table_has_column(PDO $db, string $column): bool
{
    try {
        $stmt = $db->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute(['column_name' => $column]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Remember-me users column check failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate a fresh token pair, store it in the database, and return the
 * cookie payload to be sent to the browser.
 */
function zz_remember_issue(PDO $db, int $userId, ?string $userAgent = null): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $userAgent = zz_remember_truncate_user_agent($userAgent);
    $expiresAt = date('Y-m-d H:i:s', time() + ZZ_REMEMBER_LIFETIME);
    $cookieExpires = time() + ZZ_REMEMBER_LIFETIME;

    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $selector = bin2hex(random_bytes(ZZ_SELECTOR_BYTES));
            $validator = bin2hex(random_bytes(ZZ_VALIDATOR_BYTES));
        } catch (Throwable $e) {
            error_log('Remember-me token generation failed: ' . $e->getMessage());
            return null;
        }

        $validatorHash = hash('sha256', $validator);

        try {
            $stmt = $db->prepare("
                INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at, user_agent)
                VALUES (:user_id, :selector, :validator_hash, :expires_at, :user_agent)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'selector' => $selector,
                'validator_hash' => $validatorHash,
                'expires_at' => $expiresAt,
                'user_agent' => $userAgent,
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                continue;
            }

            error_log('Remember-me issue failed: ' . $e->getMessage());
            return null;
        } catch (Throwable $e) {
            error_log('Remember-me issue failed: ' . $e->getMessage());
            return null;
        }

        $cookieValue = $selector . '.' . $validator;
        setcookie(ZZ_REMEMBER_COOKIE, $cookieValue, zz_remember_cookie_options($cookieExpires));
        $_COOKIE[ZZ_REMEMBER_COOKIE] = $cookieValue;

        return $cookieValue;
    }

    return null;
}

/**
 * Read the current remember cookie (if any), verify token, restore session,
 * rotate token and cookie, and update last-used timestamp.
 */
function zz_remember_restore_session(PDO $db): bool
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    $cookieValue = $_COOKIE[ZZ_REMEMBER_COOKIE] ?? '';
    if (!is_string($cookieValue) || $cookieValue === '') {
        return false;
    }

    $parts = explode('.', $cookieValue, 2);
    if (count($parts) !== 2) {
        zz_remember_clear_cookie();
        return false;
    }

    [$selector, $validator] = $parts;
    if (!zz_remember_is_valid_hex($selector, ZZ_SELECTOR_BYTES * 2)
        || !zz_remember_is_valid_hex($validator, ZZ_VALIDATOR_BYTES * 2)) {
        zz_remember_clear_cookie();
        return false;
    }

    $selector = strtolower($selector);
    $validator = strtolower($validator);

    try {
        $tokenStmt = $db->prepare("
            SELECT id, user_id, validator_hash
            FROM auth_tokens
            WHERE selector = :selector
              AND expires_at > NOW()
            LIMIT 1
        ");
        $tokenStmt->execute(['selector' => $selector]);
        $tokenRow = $tokenStmt->fetch();
    } catch (Throwable $e) {
        error_log('Remember-me lookup failed: ' . $e->getMessage());
        return false;
    }

    if (!$tokenRow) {
        zz_remember_clear_cookie();
        return false;
    }

    $storedHash = strtolower(trim((string) ($tokenRow['validator_hash'] ?? '')));
    $providedHash = hash('sha256', $validator);
    if (!zz_remember_is_valid_hex($storedHash, 64) || !hash_equals($storedHash, $providedHash)) {
        try {
            $deleteStmt = $db->prepare("DELETE FROM auth_tokens WHERE id = :id LIMIT 1");
            $deleteStmt->execute(['id' => (int) ($tokenRow['id'] ?? 0)]);
        } catch (Throwable $e) {
            error_log('Remember-me theft cleanup failed: ' . $e->getMessage());
        }

        zz_remember_clear_cookie();
        return false;
    }

    $userId = (int) ($tokenRow['user_id'] ?? 0);
    if ($userId <= 0) {
        try {
            $deleteStmt = $db->prepare("DELETE FROM auth_tokens WHERE id = :id LIMIT 1");
            $deleteStmt->execute(['id' => (int) ($tokenRow['id'] ?? 0)]);
        } catch (Throwable $e) {
            error_log('Remember-me invalid user cleanup failed: ' . $e->getMessage());
        }

        zz_remember_clear_cookie();
        return false;
    }

    $hasFirstNameColumn = zz_remember_users_table_has_column($db, 'first_name');
    $hasSportColumn = zz_remember_users_table_has_column($db, 'sport');
    $selectColumns = 'id, full_name, email';
    if ($hasFirstNameColumn) {
        $selectColumns .= ', first_name';
    }
    if ($hasSportColumn) {
        $selectColumns .= ', sport';
    }

    try {
        $userStmt = $db->prepare("
            SELECT {$selectColumns}
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $userStmt->execute(['user_id' => $userId]);
        $user = $userStmt->fetch();
    } catch (Throwable $e) {
        error_log('Remember-me user lookup failed: ' . $e->getMessage());
        return false;
    }

    if (!$user) {
        try {
            $deleteStmt = $db->prepare("DELETE FROM auth_tokens WHERE id = :id LIMIT 1");
            $deleteStmt->execute(['id' => (int) ($tokenRow['id'] ?? 0)]);
        } catch (Throwable $e) {
            error_log('Remember-me missing user cleanup failed: ' . $e->getMessage());
        }

        zz_remember_clear_cookie();
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    unset($_SESSION['auth_invalid_session']);

    $firstName = '';
    if ($hasFirstNameColumn && isset($user['first_name']) && is_string($user['first_name'])) {
        $firstName = trim($user['first_name']);
    }
    if ($firstName === '') {
        $firstName = zz_remember_derive_first_name((string) ($user['full_name'] ?? ''));
    }
    $_SESSION['first_name'] = $firstName;
    $_SESSION['user_sport'] = $hasSportColumn && isset($user['sport'])
        ? trim((string) $user['sport'])
        : '';

    try {
        $newValidator = bin2hex(random_bytes(ZZ_VALIDATOR_BYTES));
    } catch (Throwable $e) {
        error_log('Remember-me rotation token generation failed: ' . $e->getMessage());
        zz_remember_clear_cookie();
        return true;
    }

    $newValidatorHash = hash('sha256', $newValidator);
    $newExpiresAt = date('Y-m-d H:i:s', time() + ZZ_REMEMBER_LIFETIME);
    $cookieExpires = time() + ZZ_REMEMBER_LIFETIME;
    $userAgent = zz_remember_truncate_user_agent($_SERVER['HTTP_USER_AGENT'] ?? null);

    try {
        $rotateStmt = $db->prepare("
            UPDATE auth_tokens
            SET validator_hash = :validator_hash,
                expires_at = :expires_at,
                last_used_at = NOW(),
                user_agent = :user_agent
            WHERE id = :id
            LIMIT 1
        ");
        $rotateStmt->execute([
            'validator_hash' => $newValidatorHash,
            'expires_at' => $newExpiresAt,
            'user_agent' => $userAgent,
            'id' => (int) ($tokenRow['id'] ?? 0),
        ]);
    } catch (Throwable $e) {
        error_log('Remember-me rotation update failed: ' . $e->getMessage());
        zz_remember_clear_cookie();
        return true;
    }

    $newCookieValue = $selector . '.' . $newValidator;
    setcookie(ZZ_REMEMBER_COOKIE, $newCookieValue, zz_remember_cookie_options($cookieExpires));
    $_COOKIE[ZZ_REMEMBER_COOKIE] = $newCookieValue;

    if (mt_rand(1, 100) === 1) {
        zz_remember_cleanup_expired($db);
    }

    return true;
}

/**
 * Delete the current token from DB and clear cookie.
 */
function zz_remember_revoke(PDO $db): void
{
    $cookieValue = $_COOKIE[ZZ_REMEMBER_COOKIE] ?? '';
    if (is_string($cookieValue) && $cookieValue !== '') {
        $parts = explode('.', $cookieValue, 2);
        $selector = (string) ($parts[0] ?? '');

        if (zz_remember_is_valid_hex($selector, ZZ_SELECTOR_BYTES * 2)) {
            try {
                $stmt = $db->prepare("
                    DELETE FROM auth_tokens
                    WHERE selector = :selector
                    LIMIT 1
                ");
                $stmt->execute(['selector' => strtolower($selector)]);
            } catch (Throwable $e) {
                error_log('Remember-me revoke failed: ' . $e->getMessage());
            }
        }
    }

    zz_remember_clear_cookie();
}

/**
 * Delete expired remember tokens.
 */
function zz_remember_cleanup_expired(PDO $db): void
{
    try {
        $db->exec("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    } catch (Throwable $e) {
        error_log('Remember-me cleanup failed: ' . $e->getMessage());
    }
}
