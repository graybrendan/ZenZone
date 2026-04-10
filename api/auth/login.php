<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

$email = cleanInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    die('Email and password are required.');
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

    if (!$user) {
        die('Invalid email or password.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        die('Invalid email or password.');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];

    header("Location: " . BASE_URL . "/dashboard.php");
    exit;

} catch (PDOException $e) {
    die("Login failed: " . $e->getMessage());
}