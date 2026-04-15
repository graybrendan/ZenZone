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

$email = cleanInput($_POST['email'] ?? '');
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

    if (!$user || !password_verify($password, $user['password_hash'])) {
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