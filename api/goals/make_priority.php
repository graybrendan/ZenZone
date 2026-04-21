<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

function redirectGoalPriority(int $goalId, string $message, string $type = 'error'): void
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
    redirectGoalPriority(0, 'Invalid goal selected.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalPriority($goalId, 'Your request could not be verified. Please try again.');
}

try {
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
        redirectGoalPriority($goalId, 'Goal not found.');
    }

    if ($goal['status'] !== 'active') {
        redirectGoalPriority($goalId, 'Only active goals can be marked as priority.');
    }

    if ((int)$goal['is_priority'] === 1) {
        redirectGoalPriority($goalId, 'Goal is already a priority.', 'success');
    }

    $priorityLimits = [
        'daily' => 3,
        'weekly' => 2,
        'monthly' => 1,
    ];

    $cadenceType = $goal['cadence_type'];

    if (!isset($priorityLimits[$cadenceType])) {
        redirectGoalPriority($goalId, 'This goal type does not use priority slots.');
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
        redirectGoalPriority($goalId, 'No priority slots are available for this goal cadence.');
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

    redirectGoalPriority($goalId, 'Goal marked as priority.', 'success');
} catch (Throwable $e) {
    error_log('Goal make-priority failed: ' . $e->getMessage());
    redirectGoalPriority($goalId, 'Could not update goal priority right now. Please try again.');
}
