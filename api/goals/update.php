<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

function redirectGoalUpdate(int $goalId, string $message, string $type = 'error'): void
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
$action = trim($_POST['action'] ?? 'edit');

$allowedActions = ['edit', 'complete', 'pause', 'resume'];

if ($goalId <= 0) {
    redirectGoalUpdate(0, 'Invalid goal selected.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalUpdate($goalId, 'Your request could not be verified. Please try again.');
}

if (!in_array($action, $allowedActions, true)) {
    redirectGoalUpdate($goalId, 'Invalid goal action.');
}

function calculatePriorityForActiveGoal(PDO $db, int $userId, int $goalId, string $cadenceType): int
{
    $priorityLimits = [
        'daily' => 3,
        'weekly' => 2,
        'monthly' => 1,
    ];

    if (!isset($priorityLimits[$cadenceType])) {
        return 0;
    }

    $countStmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM goals
        WHERE user_id = :user_id
          AND cadence_type = :cadence_type
          AND status = 'active'
          AND is_priority = 1
          AND id != :goal_id
    ");
    $countStmt->execute([
        'user_id' => $userId,
        'cadence_type' => $cadenceType,
        'goal_id' => $goalId,
    ]);

    $currentPriorityCount = (int) $countStmt->fetchColumn();

    return ($currentPriorityCount < $priorityLimits[$cadenceType]) ? 1 : 0;
}

try {
    $db = getDB();

    $goalStmt = $db->prepare("
        SELECT id, user_id, title, category, cadence_number, cadence_unit, cadence_type, status, is_priority, start_date, end_date, notes
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
        redirectGoalUpdate($goalId, 'Goal not found.');
    }

    $successMessage = 'Goal updated.';

    switch ($action) {
        case 'edit':
        $title = trim($_POST['title'] ?? '');
        $categoriesInput = $_POST['categories'] ?? [];
        $cadenceNumber = isset($_POST['cadence_number']) ? (int) $_POST['cadence_number'] : 0;
        $cadenceUnit = trim($_POST['cadence_unit'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $allowedCategories = ['body', 'mind', 'soul'];
        $allowedCadenceUnits = ['day', 'week', 'month'];

        if ($title === '') {
            redirectGoalUpdate($goalId, 'Goal title is required.');
        }

        if (!is_array($categoriesInput)) {
            redirectGoalUpdate($goalId, 'Invalid category selection.');
        }

        $selectedCategories = array_values(array_unique(array_filter(
            array_map('trim', $categoriesInput),
            static function ($value): bool {
                return $value !== '';
            }
        )));

        if (empty($selectedCategories) || array_diff($selectedCategories, $allowedCategories)) {
            redirectGoalUpdate($goalId, 'Please choose valid categories.');
        }

        if ($cadenceNumber <= 0) {
            redirectGoalUpdate($goalId, 'Cadence number must be at least 1.');
        }

        if (!in_array($cadenceUnit, $allowedCadenceUnits, true)) {
            redirectGoalUpdate($goalId, 'Please choose a valid cadence unit.');
        }

        if ($cadenceNumber === 1 && $cadenceUnit === 'day') {
            $cadenceType = 'daily';
        } elseif ($cadenceNumber === 1 && $cadenceUnit === 'week') {
            $cadenceType = 'weekly';
        } elseif ($cadenceNumber === 1 && $cadenceUnit === 'month') {
            $cadenceType = 'monthly';
        } else {
            $cadenceType = 'custom';
        }

        $category = implode(',', $selectedCategories);
        $startDate = ($startDate === '') ? null : $startDate;
        $endDate = ($endDate === '') ? null : $endDate;
        $notes = ($notes === '') ? null : $notes;

        if ($startDate !== null && !isValidDateYmd($startDate)) {
            redirectGoalUpdate($goalId, 'Please enter a valid start date.');
        }

        if ($endDate !== null && !isValidDateYmd($endDate)) {
            redirectGoalUpdate($goalId, 'Please enter a valid end date.');
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            redirectGoalUpdate($goalId, 'End date cannot be before start date.');
        }

        $isPriority = 0;

        if ($goal['status'] === 'active') {
            $isPriority = calculatePriorityForActiveGoal(
                $db,
                (int) $_SESSION['user_id'],
                $goalId,
                $cadenceType
            );
        }

        $updateStmt = $db->prepare("
            UPDATE goals
            SET title = :title,
                category = :category,
                cadence_number = :cadence_number,
                cadence_unit = :cadence_unit,
                cadence_type = :cadence_type,
                is_priority = :is_priority,
                start_date = :start_date,
                end_date = :end_date,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND user_id = :user_id
        ");
        $updateStmt->execute([
            'title' => $title,
            'category' => $category,
            'cadence_number' => $cadenceNumber,
            'cadence_unit' => $cadenceUnit,
            'cadence_type' => $cadenceType,
            'is_priority' => $isPriority,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $notes,
            'id' => $goalId,
            'user_id' => $_SESSION['user_id'],
        ]);

        $successMessage = 'Goal updated.';
            break;

        case 'complete':
        if ($goal['status'] !== 'completed') {
            $completeStmt = $db->prepare("
                UPDATE goals
                SET status = 'completed',
                    is_priority = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND user_id = :user_id
            ");
            $completeStmt->execute([
                'id' => $goalId,
                'user_id' => $_SESSION['user_id'],
            ]);
        }
        $successMessage = 'Goal marked as completed.';
            break;

        case 'pause':
        if ($goal['status'] !== 'active') {
            redirectGoalUpdate($goalId, 'Only active goals can be paused.');
        }

        $pauseStmt = $db->prepare("
            UPDATE goals
            SET status = 'paused',
                is_priority = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND user_id = :user_id
        ");
        $pauseStmt->execute([
            'id' => $goalId,
            'user_id' => $_SESSION['user_id'],
        ]);

        $successMessage = 'Goal paused.';
            break;

        case 'resume':
        if ($goal['status'] !== 'paused') {
            redirectGoalUpdate($goalId, 'Only paused goals can be resumed.');
        }

        $resumePriority = calculatePriorityForActiveGoal(
            $db,
            (int) $_SESSION['user_id'],
            $goalId,
            $goal['cadence_type']
        );

        $resumeStmt = $db->prepare("
            UPDATE goals
            SET status = 'active',
                is_priority = :is_priority,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND user_id = :user_id
        ");
        $resumeStmt->execute([
            'is_priority' => $resumePriority,
            'id' => $goalId,
            'user_id' => $_SESSION['user_id'],
        ]);

        $successMessage = 'Goal resumed.';
            break;
    }

    redirectGoalUpdate($goalId, $successMessage, 'success');
} catch (Throwable $e) {
    error_log('Goal update failed: ' . $e->getMessage());
    redirectGoalUpdate($goalId, 'Unable to update goal right now. Please try again.');
}
