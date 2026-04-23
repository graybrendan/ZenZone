<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/zenscore.php';

requireLogin();

function redirectCheckinError(string $message, array $input): void
{
    setFlashMessage('error', $message);
    setOldInput(array_merge(['checkin_form' => 'checkin'], $input));
    authRedirect('checkin.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('checkin.php');
}

$labels = zenzone_labels();
$activityText = trim((string) ($_POST['activity_text'] ?? $_POST['activity_context'] ?? ''));
$rawInput = [
    'activity_text' => $activityText,
];

foreach (array_keys($labels) as $field) {
    $rawInput[$field] = (string) ((int) ($_POST[$field] ?? 0));
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectCheckinError('Your request could not be verified. Please try again.', $rawInput);
}

$scores = [];
foreach (array_keys($labels) as $field) {
    if (!isValidScaleRating($_POST[$field] ?? null, 1, 7)) {
        redirectCheckinError('Please submit valid scores between 1 and 7.', $rawInput);
    }

    $scores[$field] = (int) $_POST[$field];
}

if (strlen($rawInput['activity_text']) > 1000) {
    $rawInput['activity_text'] = substr($rawInput['activity_text'], 0, 1000);
}

try {
    $pdo = getDB();
    $userId = (int) $_SESSION['user_id'];

    $inserted = zenzone_insert_checkin($pdo, $userId, $scores, $rawInput['activity_text']);
    $insertedId = (int) ($inserted['id'] ?? 0);
    if ($insertedId <= 0) {
        throw new RuntimeException('Check-in insert did not return a valid id.');
    }

    zenzone_rebuild_daily_summary($pdo, $userId, (string) ($inserted['checkin_date'] ?? date('Y-m-d')));
} catch (Throwable $e) {
    error_log('Check-in submit failed: ' . $e->getMessage());
    redirectCheckinError('Could not save your check-in right now. Please try again.', $rawInput);
}

clearOldInput();
authRedirect('checkin_result.php', ['id' => $insertedId]);
