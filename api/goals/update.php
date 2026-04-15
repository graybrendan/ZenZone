<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/goals/index.php');
    exit;
}

$goalId = isset($_POST['goal_id']) ? (int) $_POST['goal_id'] : 0;
$action = trim($_POST['action'] ?? 'edit');

$allowedActions = ['edit', 'complete', 'pause', 'resume'];

if ($goalId <= 0) {
    die('Invalid goal ID.');
}

if (!in_array($action, $allowedActions, true)) {
    die('Invalid goal action.');
}

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
    die('Goal not found.');
}

$redirectUrl = BASE_URL . '/goals/details.php?id=' . $goalId;

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
            die('Goal title is required.');
        }

        if (!is_array($categoriesInput)) {
            die('Invalid category selection.');
        }

        $selectedCategories = array_values(array_unique(array_filter(
            array_map('trim', $categoriesInput),
            static function ($value): bool {
                return $value !== '';
            }
        )));

        if (empty($selectedCategories) || array_diff($selectedCategories, $allowedCategories)) {
            die('Invalid category.');
        }

        if ($cadenceNumber <= 0) {
            die('Cadence number must be at least 1.');
        }

        if (!in_array($cadenceUnit, $allowedCadenceUnits, true)) {
            die('Invalid cadence unit.');
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
            die('Invalid start date.');
        }

        if ($endDate !== null && !isValidDateYmd($endDate)) {
            die('Invalid end date.');
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            die('End date cannot be before start date.');
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
        break;

    case 'pause':
        if ($goal['status'] !== 'active') {
            die('Only active goals can be paused.');
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

        break;

    case 'resume':
        if ($goal['status'] !== 'paused') {
            die('Only paused goals can be resumed.');
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

        break;
}

header('Location: ' . $redirectUrl);
exit;
