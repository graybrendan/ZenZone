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
    SELECT id
    FROM goals
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$goalStmt->execute([
    'id' => $goalId,
    'user_id' => $_SESSION['user_id'],
]);

$goal = $goalStmt->fetch();

if (!$goal) {
    die('Goal not found.');
}

try {
    $db->beginTransaction();

    $deleteCheckinsStmt = $db->prepare("
        DELETE FROM goal_checkins
        WHERE goal_id = :goal_id
          AND user_id = :user_id
    ");
    $deleteCheckinsStmt->execute([
        'goal_id' => $goalId,
        'user_id' => $_SESSION['user_id'],
    ]);

    $deleteGoalStmt = $db->prepare("
        DELETE FROM goals
        WHERE id = :id
          AND user_id = :user_id
    ");
    $deleteGoalStmt->execute([
        'id' => $goalId,
        'user_id' => $_SESSION['user_id'],
    ]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    die('Failed to delete goal.');
}

header('Location: ' . BASE_URL . '/goals/index.php');
exit;