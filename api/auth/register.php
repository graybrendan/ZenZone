<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';

requireGuest();

function failRegistration(string $errorCode = 'invalid_input'): void
{
    $allowed = ['invalid_input', 'email_exists', 'registration_failed'];
    if (!in_array($errorCode, $allowed, true)) {
        $errorCode = 'registration_failed';
    }

    authRedirect('signup.php', ['error' => $errorCode]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('signup.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    authRedirect('signup.php', ['error' => 'invalid_request']);
}

$full_name = cleanInput($_POST['full_name'] ?? '');
$email = strtolower(cleanInput($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($full_name === '' || $email === '' || $password === '') {
    failRegistration('invalid_input');
}

if (!isValidEmail($email)) {
    failRegistration('invalid_input');
}

if (!isStrongEnoughPassword($password)) {
    failRegistration('invalid_input');
}

try {
    $db = getDB();

    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        failRegistration('email_exists');
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($password_hash) || $password_hash === '') {
        throw new RuntimeException('Password hashing failed.');
    }

    $insertStmt = $db->prepare("
        INSERT INTO users (full_name, email, password_hash)
        VALUES (:full_name, :email, :password_hash)
    ");

    $insertStmt->execute([
        'full_name' => $full_name,
        'email' => $email,
        'password_hash' => $password_hash,
    ]);

    $user = [
        'id' => (int) $db->lastInsertId(),
        'full_name' => $full_name,
        'email' => $email,
    ];

    loginUser($user);
    authRedirect('dashboard.php');
} catch (Throwable $e) {
    error_log('Registration failed: ' . $e->getMessage());

    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        failRegistration('email_exists');
    }

    failRegistration('registration_failed');
}
