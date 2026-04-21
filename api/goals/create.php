<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

function redirectGoalCreate(string $message, string $type = 'error'): void
{
    redirectWithFlash('goals/create.php', $message, $type);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('goals/create.php');
}

$title = trim($_POST['title'] ?? '');
$categoriesInput = $_POST['categories'] ?? [];
$cadence_number = isset($_POST['cadence_number']) ? (int) $_POST['cadence_number'] : 0;
$cadence_unit = trim($_POST['cadence_unit'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date = trim($_POST['end_date'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$allowedCategories = ['body', 'mind', 'soul'];
$allowedCadenceUnits = ['day', 'week', 'month'];
$priorityLimits = [
    'daily' => 3,
    'weekly' => 2,
    'monthly' => 1,
];

if ($title === '') {
    redirectGoalCreate('Goal title is required.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalCreate('Your request could not be verified. Please try again.');
}

if ($cadence_number <= 0) {
    redirectGoalCreate('Cadence number must be at least 1.');
}

if (!in_array($cadence_unit, $allowedCadenceUnits, true)) {
    redirectGoalCreate('Please choose a valid cadence unit.');
}

if (!is_array($categoriesInput)) {
    redirectGoalCreate('Invalid category selection.');
}

$selectedCategories = array_values(array_unique(array_filter(
    array_map('trim', $categoriesInput),
    static function ($value): bool {
        return $value !== '';
    }
)));

if (empty($selectedCategories)) {
    redirectGoalCreate('Select at least one category.');
}

if (array_diff($selectedCategories, $allowedCategories)) {
    redirectGoalCreate('Please choose valid categories.');
}

$category = implode(',', $selectedCategories);

if ($cadence_number === 1 && $cadence_unit === 'day') {
    $cadence_type = 'daily';
} elseif ($cadence_number === 1 && $cadence_unit === 'week') {
    $cadence_type = 'weekly';
} elseif ($cadence_number === 1 && $cadence_unit === 'month') {
    $cadence_type = 'monthly';
} else {
    $cadence_type = 'custom';
}

if ($start_date === '') {
    $start_date = null;
}

if ($end_date === '') {
    $end_date = null;
}

if ($notes === '') {
    $notes = null;
}

if ($start_date !== null && !isValidDateYmd($start_date)) {
    redirectGoalCreate('Please enter a valid start date.');
}

if ($end_date !== null && !isValidDateYmd($end_date)) {
    redirectGoalCreate('Please enter a valid end date.');
}

if ($start_date !== null && $end_date !== null && $end_date < $start_date) {
    redirectGoalCreate('End date cannot be before start date.');
}

try {
    $db = getDB();

    $isPriority = 0;

    if (isset($priorityLimits[$cadence_type])) {
        $priorityStmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM goals
            WHERE user_id = :user_id
              AND cadence_type = :cadence_type
              AND status = 'active'
              AND is_priority = 1
        ");
        $priorityStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'cadence_type' => $cadence_type,
        ]);

        $currentPriorityCount = (int)$priorityStmt->fetchColumn();

        if ($currentPriorityCount < $priorityLimits[$cadence_type]) {
            $isPriority = 1;
        }
    }

    $stmt = $db->prepare("
        INSERT INTO goals (
            user_id,
            title,
            category,
            cadence_number,
            cadence_unit,
            cadence_type,
            status,
            is_priority,
            start_date,
            end_date,
            notes
        ) VALUES (
            :user_id,
            :title,
            :category,
            :cadence_number,
            :cadence_unit,
            :cadence_type,
            'active',
            :is_priority,
            :start_date,
            :end_date,
            :notes
        )
    ");

    $stmt->execute([
        'user_id' => $_SESSION['user_id'],
        'title' => $title,
        'category' => $category,
        'cadence_number' => $cadence_number,
        'cadence_unit' => $cadence_unit,
        'cadence_type' => $cadence_type,
        'is_priority' => $isPriority,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'notes' => $notes,
    ]);

    $newGoalId = (int) $db->lastInsertId();
    redirectWithFlash('goals/details.php', 'Goal created.', 'success', ['id' => $newGoalId]);
} catch (Throwable $e) {
    error_log('Goal create failed: ' . $e->getMessage());
    redirectGoalCreate('Unable to create goal right now. Please try again.');
}
