<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/goals/index.php');
    exit;
}

$goalId = isset($_POST['goal_id']) ? (int) $_POST['goal_id'] : 0;
$isComplete = isset($_POST['is_complete']) ? 1 : 0;
$notes = trim($_POST['notes'] ?? '');
$today = date('Y-m-d');

if ($goalId <= 0) {
    die('Invalid goal ID.');
}

if ($notes === '') {
    $notes = null;
}

$db = getDB();

$goalStmt = $db->prepare("
    SELECT id, status
    FROM goals
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$goalStmt->execute([
    'id' => $goalId,
    'user_id' => $_SESSION['user_id']
]);

$goal = $goalStmt->fetch();

if (!$goal) {
    die('Goal not found.');
}

if ($goal['status'] !== 'active') {
    die('Only active goals can be checked in.');
}

$checkinStmt = $db->prepare("
    INSERT INTO goal_checkins (
        goal_id,
        user_id,
        checkin_date,
        is_complete,
        notes
    ) VALUES (
        :goal_id,
        :user_id,
        :checkin_date,
        :is_complete,
        :notes
    )
    ON DUPLICATE KEY UPDATE
        is_complete = VALUES(is_complete),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
");
$checkinStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $_SESSION['user_id'],
    'checkin_date' => $today,
    'is_complete' => $isComplete,
    'notes' => $notes,
]);

header('Location: ' . BASE_URL . '/goals/details.php?id=' . $goalId);
exit;