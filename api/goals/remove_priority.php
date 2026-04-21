<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

function redirectGoalPriorityRemoval(int $goalId, string $message, string $type = 'error'): void
{
    if ($goalId > 0) {
        redirectWithFlash('goals/details.php', $message, $type, ['id' => $goalId]);
    }

    redirectWithFlash('goals/index.php', $message, $type);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('goals/index.php');
}

$goalId = isset($_POST['goal_id']) ? (int) $_POST['goal_id'] : 0;

if ($goalId <= 0) {
    redirectGoalPriorityRemoval(0, 'Invalid goal selected.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalPriorityRemoval($goalId, 'Your request could not be verified. Please try again.');
}

try {
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
        redirectGoalPriorityRemoval($goalId, 'Goal not found.');
    }

    $priorityCadences = ['daily', 'weekly', 'monthly'];

    if (!in_array($goal['cadence_type'], $priorityCadences, true)) {
        redirectGoalPriorityRemoval($goalId, 'This goal type does not use priority slots.');
    }

    if ((int)$goal['is_priority'] !== 1) {
        redirectGoalPriorityRemoval($goalId, 'Goal is already not using a priority slot.', 'success');
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

    redirectGoalPriorityRemoval($goalId, 'Priority removed from goal.', 'success');
} catch (Throwable $e) {
    error_log('Goal remove-priority failed: ' . $e->getMessage());
    redirectGoalPriorityRemoval($goalId, 'Could not update goal priority right now. Please try again.');
}
