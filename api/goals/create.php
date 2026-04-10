<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/goals/create.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$cadence_type = trim($_POST['cadence_type'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$end_date = trim($_POST['end_date'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$allowedCadences = ['daily', 'weekly', 'monthly', 'custom'];
$priorityLimits = [
    'daily' => 3,
    'weekly' => 2,
    'monthly' => 1,
];

if ($title === '') {
    die('Goal title is required.');
}

if (!in_array($cadence_type, $allowedCadences, true)) {
    die('Invalid cadence type.');
}

if ($category === '') {
    $category = null;
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
    'cadence_type' => $cadence_type,
    'is_priority' => $isPriority,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'notes' => $notes,
]);

header('Location: ' . BASE_URL . '/goals/index.php');
exit;