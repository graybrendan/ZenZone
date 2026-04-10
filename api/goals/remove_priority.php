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

if ($goalId <= 0) {
    die('Invalid goal ID.');
}

$db = getDB();

$goalStmt = $db->prepare("
    SELECT id, user_id, cadence_type, is_priority
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

$priorityCadences = ['daily', 'weekly', 'monthly'];

if (!in_array($goal['cadence_type'], $priorityCadences, true)) {
    die('This goal type does not use priority slots.');
}

if ((int)$goal['is_priority'] !== 1) {
    header('Location: ' . BASE_URL . '/goals/details.php?id=' . $goalId);
    exit;
}

$updateStmt = $db->prepare("
    UPDATE goals
    SET is_priority = 0,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id
      AND user_id = :user_id
");
$updateStmt->execute([
    'id' => $goalId,
    'user_id' => $_SESSION['user_id']
]);

header('Location: ' . BASE_URL . '/goals/details.php?id=' . $goalId);
exit;