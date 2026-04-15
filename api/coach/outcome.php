<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

function redirectCoachOutcomeError(int $threadId, string $message, string $reflectionNote = ''): void
{
    setFlashMessage('error', $message);
    setOldInput([
        'coach_form' => 'outcome',
        'coach_outcome_thread_id' => (string) $threadId,
        'reflection_note' => $reflectionNote,
    ]);

    if ($threadId > 0) {
        authRedirect('coach/view.php', ['id' => $threadId]);
    }

    authRedirect('coach/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('coach/index.php');
}

$threadId = (int) ($_POST['thread_id'] ?? 0);
$outcome = strtolower(trim((string) ($_POST['outcome'] ?? '')));
$reflectionNote = trim((string) ($_POST['reflection_note'] ?? ''));

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectCoachOutcomeError($threadId, 'Your request could not be verified. Please try again.', $reflectionNote);
}

if ($threadId <= 0) {
    redirectCoachOutcomeError(0, 'Invalid coach situation selected.', $reflectionNote);
}

if (!in_array($outcome, getCoachOutcomeOptions(), true)) {
    redirectCoachOutcomeError($threadId, 'Please choose Better, Same, or Worse.', $reflectionNote);
}

if (strlen($reflectionNote) > 500) {
    redirectCoachOutcomeError($threadId, 'Reflection note must be 500 characters or fewer.', $reflectionNote);
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
    redirectCoachOutcomeError(0, 'Coach situation not found.');
}

try {
    $insertStmt = $db->prepare("
        INSERT INTO coach_outcomes (
            thread_id,
            user_id,
            outcome,
            reflection_note
        ) VALUES (
            :thread_id,
            :user_id,
            :outcome,
            :reflection_note
        )
    ");
    $insertStmt->execute([
        'thread_id' => $threadId,
        'user_id' => $userId,
        'outcome' => $outcome,
        'reflection_note' => $reflectionNote !== '' ? $reflectionNote : null,
    ]);
} catch (Throwable $e) {
    error_log('Coach outcome save failed: ' . $e->getMessage());
    redirectCoachOutcomeError($threadId, 'Could not save your outcome. Please try again.', $reflectionNote);
}

clearOldInput();
setFlashMessage('success', 'Outcome saved.');
authRedirect('coach/view.php', ['id' => $threadId]);
