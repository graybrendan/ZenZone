<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/remember_me.php';
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

function deriveFirstNameFromFullName(string $fullName): string
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

function usersTableHasSportColumn(PDO $db): bool
{
    $stmt = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'sport'
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
        'Register CSRF validation failed: session_active=' . (session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no') .
        ', request_uri=' . (string) ($_SERVER['REQUEST_URI'] ?? '') .
        ', submitted_len=' . strlen($submittedToken) .
        ', session_len=' . strlen($sessionToken) .
        ', cookie_len=' . strlen($cookieToken)
    );

    authRedirect('signup.php', ['error' => 'invalid_request']);
}

$first_name = clampTextLength(trim((string) ($_POST['first_name'] ?? '')), 60);
$last_name = clampTextLength(trim((string) ($_POST['last_name'] ?? '')), 60);
$sport = clampTextLength(trim((string) ($_POST['sport'] ?? '')), 80);
$full_name = trim($first_name . ' ' . $last_name);
$email = strtolower(cleanInput($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$oldInput = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'sport' => $sport,
    'email' => $email,
];

if ($first_name === '' || $last_name === '' || $sport === '' || $email === '' || $password === '') {
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
    $hasFirstNameColumn = usersTableHasFirstNameColumn($db);
    $hasSportColumn = usersTableHasSportColumn($db);

    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        failRegistration('email_exists', $oldInput);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($password_hash) || $password_hash === '') {
        throw new RuntimeException('Password hashing failed.');
    }

    if ($hasFirstNameColumn && $hasSportColumn) {
        $insertStmt = $db->prepare("
            INSERT INTO users (full_name, first_name, sport, email, password_hash)
            VALUES (:full_name, :first_name, :sport, :email, :password_hash)
        ");

        $insertStmt->execute([
            'full_name' => $full_name,
            'first_name' => $first_name,
            'sport' => $sport,
            'email' => $email,
            'password_hash' => $password_hash,
        ]);
    } elseif ($hasFirstNameColumn) {
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
    } elseif ($hasSportColumn) {
        $insertStmt = $db->prepare("
            INSERT INTO users (full_name, sport, email, password_hash)
            VALUES (:full_name, :sport, :email, :password_hash)
        ");

        $insertStmt->execute([
            'full_name' => $full_name,
            'sport' => $sport,
            'email' => $email,
            'password_hash' => $password_hash,
        ]);
    } else {
        $insertStmt = $db->prepare("
            INSERT INTO users (full_name, email, password_hash)
            VALUES (:full_name, :email, :password_hash)
        ");

        $insertStmt->execute([
            'full_name' => $full_name,
            'email' => $email,
            'password_hash' => $password_hash,
        ]);
    }

    $sessionFirstName = $hasFirstNameColumn ? $first_name : deriveFirstNameFromFullName($full_name);

    $user = [
        'id' => (int) $db->lastInsertId(),
        'first_name' => $sessionFirstName,
        'full_name' => $full_name,
        'email' => $email,
    ];

    clearOldInput();
    loginUser($user);
    $_SESSION['first_name'] = $sessionFirstName;
    $_SESSION['user_sport'] = $sport;
    zz_remember_issue($db, (int) $_SESSION['user_id'], $_SERVER['HTTP_USER_AGENT'] ?? null);
    authRedirect('dashboard.php');
} catch (Throwable $e) {
    error_log('Registration failed: ' . $e->getMessage());

    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        failRegistration('email_exists', $oldInput);
    }

    failRegistration('registration_failed', $oldInput);
}
