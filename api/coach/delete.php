<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('coach/index.php');
}

$threadId = (int) ($_POST['thread_id'] ?? 0);

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Your request could not be verified. Please try again.');
    authRedirect('coach/index.php');
}

if ($threadId <= 0) {
    setFlashMessage('error', 'Invalid coach situation selected.');
    authRedirect('coach/index.php');
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$threadStmt = $db->prepare("
    SELECT id
    FROM coach_threads
    WHERE id = :thread_id
      AND user_id = :user_id
    LIMIT 1
");
$threadStmt->execute([
    'thread_id' => $threadId,
    'user_id' => $userId,
]);
$thread = $threadStmt->fetch();

if (!$thread) {
    setFlashMessage('error', 'Coach situation not found.');
    authRedirect('coach/index.php');
}

try {
    $deleteStmt = $db->prepare("
        DELETE FROM coach_threads
        WHERE id = :thread_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $deleteStmt->execute([
        'thread_id' => $threadId,
        'user_id' => $userId,
    ]);
} catch (Throwable $e) {
    error_log('Coach delete failed: ' . $e->getMessage());
    setFlashMessage('error', 'Could not delete this coach situation. Please try again.');
    authRedirect('coach/index.php');
}

setFlashMessage('success', 'Coach situation deleted.');
authRedirect('coach/index.php');
