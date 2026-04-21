<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

function redirectGoalDelete(string $message, string $type = 'error'): void
{
    redirectWithFlash('goals/index.php', $message, $type);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('goals/index.php');
}

$goalId = isset($_POST['goal_id']) ? (int) $_POST['goal_id'] : 0;

if ($goalId <= 0) {
    redirectGoalDelete('Invalid goal selected.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalDelete('Your request could not be verified. Please try again.');
}

$db = null;
try {
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
        redirectGoalDelete('Goal not found.');
    }

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
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Goal delete failed: ' . $e->getMessage());
    redirectGoalDelete('Failed to delete goal. Please try again.');
}

redirectGoalDelete('Goal deleted.', 'success');
