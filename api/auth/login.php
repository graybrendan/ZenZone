<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';

requireGuest();

function failLogin(string $errorCode = 'invalid_credentials', array $extraParams = []): void
{
    $query = array_merge(['error' => $errorCode], $extraParams);
    authRedirect('login.php', $query);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('login.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    failLogin('invalid_request');
}

if (isLoginRateLimited()) {
    failLogin('too_many_attempts', [
        'retry_after' => getLoginRetryAfterSeconds(),
    ]);
}

$email = strtolower(cleanInput($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '' || !isValidEmail($email)) {
    failLogin();
}

try {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT id, full_name, email, password_hash
        FROM users
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);

    $user = $stmt->fetch();
    $storedHash = '';
    if ($user && isset($user['password_hash']) && is_string($user['password_hash'])) {
        $storedHash = trim($user['password_hash']);
    }

    $isValidLogin = false;
    if ($user && $storedHash !== '') {
        $isValidLogin = password_verify($password, $storedHash);

        if ($isValidLogin) {
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                if (is_string($newHash) && $newHash !== '') {
                    $rehashStmt = $db->prepare("
                        UPDATE users
                        SET password_hash = :password_hash
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $rehashStmt->execute([
                        'password_hash' => $newHash,
                        'id' => (int) $user['id'],
                    ]);
                }
            }
        } else {
            $hashInfo = password_get_info($storedHash);
            $isPasswordHash = ((int) ($hashInfo['algo'] ?? 0)) !== 0;

            // Supports one-time migration for legacy plain-text password values.
            if (!$isPasswordHash && hash_equals($storedHash, $password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                if (is_string($newHash) && $newHash !== '') {
                    $upgradeStmt = $db->prepare("
                        UPDATE users
                        SET password_hash = :password_hash
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $upgradeStmt->execute([
                        'password_hash' => $newHash,
                        'id' => (int) $user['id'],
                    ]);
                }

                $isValidLogin = true;
            }
        }
    }

    if (!$user || !$isValidLogin) {
        recordFailedLoginAttempt();

        if (isLoginRateLimited()) {
            failLogin('too_many_attempts', [
                'retry_after' => getLoginRetryAfterSeconds(),
            ]);
        }

        failLogin('invalid_credentials');
    }

    clearFailedLoginAttempts();
    loginUser($user);

    authRedirect('dashboard.php');
} catch (PDOException $e) {
    error_log('Login failed: ' . $e->getMessage());
    failLogin('login_failed');
}
