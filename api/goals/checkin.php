<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

function redirectGoalCheckin(int $goalId, string $message, string $type = 'error'): void
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
$isCompleteRaw = $_POST['is_complete'] ?? null;
$isComplete = ($isCompleteRaw === '1') ? 1 : 0;
$notes = trim($_POST['notes'] ?? '');
$today = date('Y-m-d');

if ($goalId <= 0) {
    redirectGoalCheckin(0, 'Invalid goal selected.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectGoalCheckin($goalId, 'Your request could not be verified. Please try again.');
}

if ($isCompleteRaw !== null && $isCompleteRaw !== '1') {
    redirectGoalCheckin($goalId, 'Invalid completion value.');
}

if ($notes === '') {
    $notes = null;
}

try {
    $db = getDB();

    $goalStmt = $db->prepare("
        SELECT id, status, cadence_number, cadence_unit
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
        redirectGoalCheckin($goalId, 'Goal not found.');
    }

    if ($goal['status'] !== 'active') {
        redirectGoalCheckin($goalId, 'Only active goals can be checked in.');
    }

    $cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
    $cadenceUnit = $goal['cadence_unit'] ?? 'day';
    if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
        $cadenceUnit = 'day';
    }

    $todayDate = new DateTimeImmutable($today);
    if ($cadenceUnit === 'week') {
        $periodStart = $todayDate->modify('monday this week')->format('Y-m-d');
        $periodEnd = $todayDate->modify('monday this week')->modify('+6 days')->format('Y-m-d');
    } elseif ($cadenceUnit === 'month') {
        $periodStart = $todayDate->modify('first day of this month')->format('Y-m-d');
        $periodEnd = $todayDate->modify('last day of this month')->format('Y-m-d');
    } else {
        $periodStart = $today;
        $periodEnd = $today;
    }

    $periodCountStmt = $db->prepare("
        SELECT COUNT(*)
        FROM goal_checkins
        WHERE goal_id = :goal_id
          AND user_id = :user_id
          AND checkin_date BETWEEN :start_date AND :end_date
    ");
    $periodCountStmt->execute([
        'goal_id' => $goalId,
        'user_id' => $_SESSION['user_id'],
        'start_date' => $periodStart,
        'end_date' => $periodEnd,
    ]);
    $checkinsThisPeriod = (int) $periodCountStmt->fetchColumn();

    if ($checkinsThisPeriod >= $cadenceNumber) {
        redirectGoalCheckin($goalId, 'Check-in limit reached for this cadence window.');
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
    ");
    $checkinStmt->execute([
        'goal_id' => $goalId,
        'user_id' => $_SESSION['user_id'],
        'checkin_date' => $today,
        'is_complete' => $isComplete,
        'notes' => $notes,
    ]);
} catch (Throwable $e) {
    error_log('Goal check-in save failed: ' . $e->getMessage());
    redirectGoalCheckin($goalId, 'Failed to save check-in. Please try again.');
}

redirectGoalCheckin($goalId, 'Goal check-in saved.', 'success');
