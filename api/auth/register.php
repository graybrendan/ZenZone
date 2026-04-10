<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

$full_name = cleanInput($_POST['full_name'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if ($full_name === '' || $email === '' || $password === '') {
    die('All fields are required.');
}

if (!isValidEmail($email)) {
    die('Invalid email address.');
}

if (!isStrongEnoughPassword($password)) {
    die('Password must be at least 6 characters.');
}

try {
    $db = getDB();

    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute(['email' => $email]);

    if ($checkStmt->fetch()) {
        die('An account with this email already exists.');
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $insertStmt = $db->prepare("
        INSERT INTO users (full_name, email, password_hash)
        VALUES (:full_name, :email, :password_hash)
    ");

    $insertStmt->execute([
        'full_name' => $full_name,
        'email' => $email,
        'password_hash' => $password_hash
    ]);

    $user_id = $db->lastInsertId();

    // Start user session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_email'] = $email;

    // Redirect to dashboard
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;

} catch (PDOException $e) {
    die("Registration failed: " . $e->getMessage());
}