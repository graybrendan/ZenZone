<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';

requireGuest();

function clampTextLength(string $value, int $maxLength): string
{
    if ($maxLength <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function usersTableHasFirstNameColumn(PDO $db): bool
{
    $stmt = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'first_name'
        LIMIT 1
    ");

    return (bool) $stmt->fetchColumn();
}

function failRegistration(string $errorCode = 'invalid_input', array $oldInput = []): void
{
    $allowed = ['invalid_input', 'email_exists', 'registration_failed'];
    if (!in_array($errorCode, $allowed, true)) {
        $errorCode = 'registration_failed';
    }

    if (!empty($oldInput)) {
        setOldInput($oldInput);
    }

    authRedirect('signup.php', ['error' => $errorCode]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('signup.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $cookieToken = (string) ($_COOKIE['zz_csrf_token'] ?? '');

    error_log(
        'Register CSRF validation failed: sid=' . session_id() .
        ', request_uri=' . (string) ($_SERVER['REQUEST_URI'] ?? '') .
        ', submitted_len=' . strlen($submittedToken) .
        ', session_len=' . strlen($sessionToken) .
        ', cookie_len=' . strlen($cookieToken)
    );

    authRedirect('signup.php', ['error' => 'invalid_request']);
}

$first_name = clampTextLength(trim((string) ($_POST['first_name'] ?? '')), 60);
$full_name = cleanInput($_POST['full_name'] ?? '');
$email = strtolower(cleanInput($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$oldInput = [
    'first_name' => $first_name,
    'full_name' => $full_name,
    'email' => $email,
];

if ($first_name === '' || $full_name === '' || $email === '' || $password === '') {
    failRegistration('invalid_input', $oldInput);
}

if (!isValidEmail($email)) {
    failRegistration('invalid_input', $oldInput);
}

if (!isStrongEnoughPassword($password)) {
    failRegistration('invalid_input', $oldInput);
}

try {
    $db = getDB();

    if (!usersTableHasFirstNameColumn($db)) {
        setOldInput($oldInput);
        setFlashMessage('danger', 'Signup requires a database update before new accounts can be created.');
        authRedirect('signup.php');
    }

    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        failRegistration('email_exists', $oldInput);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($password_hash) || $password_hash === '') {
        throw new RuntimeException('Password hashing failed.');
    }

    $insertStmt = $db->prepare("
        INSERT INTO users (full_name, first_name, email, password_hash)
        VALUES (:full_name, :first_name, :email, :password_hash)
    ");

    $insertStmt->execute([
        'full_name' => $full_name,
        'first_name' => $first_name,
        'email' => $email,
        'password_hash' => $password_hash,
    ]);

    $user = [
        'id' => (int) $db->lastInsertId(),
        'first_name' => $first_name,
        'full_name' => $full_name,
        'email' => $email,
    ];

    clearOldInput();
    loginUser($user);
    $_SESSION['first_name'] = $first_name;
    authRedirect('dashboard.php');
} catch (Throwable $e) {
    error_log('Registration failed: ' . $e->getMessage());

    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        failRegistration('email_exists', $oldInput);
    }

    failRegistration('registration_failed', $oldInput);
}
