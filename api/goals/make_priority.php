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
    SELECT id, user_id, cadence_type, status, is_priority
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
    die('Only active goals can be marked as priority.');
}

if ((int)$goal['is_priority'] === 1) {
    header('Location: ' . BASE_URL . '/goals/details.php?id=' . $goalId);
    exit;
}

$priorityLimits = [
    'daily' => 3,
    'weekly' => 2,
    'monthly' => 1,
];

$cadenceType = $goal['cadence_type'];

if (!isset($priorityLimits[$cadenceType])) {
    die('This goal type does not use priority slots.');
}

$countStmt = $db->prepare("
    SELECT COUNT(*) AS total
    FROM goals
    WHERE user_id = :user_id
      AND cadence_type = :cadence_type
      AND status = 'active'
      AND is_priority = 1
");
$countStmt->execute([
    'user_id' => $_SESSION['user_id'],
    'cadence_type' => $cadenceType
]);

$currentPriorityCount = (int)$countStmt->fetchColumn();

if ($currentPriorityCount >= $priorityLimits[$cadenceType]) {
    die('No priority slots are available for this goal cadence.');
}

$updateStmt = $db->prepare("
    UPDATE goals
    SET is_priority = 1,
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