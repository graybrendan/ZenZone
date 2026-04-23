<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

function redirectCoachOutcome(int $threadId, string $flashType, string $message): void
{
    setFlashMessage($flashType, $message);

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

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectCoachOutcome($threadId, 'error', 'Your request could not be verified. Please try again.');
}

if ($threadId <= 0) {
    redirectCoachOutcome(0, 'error', 'Invalid coach situation selected.');
}

if (!in_array($outcome, getCoachOutcomeOptions(), true)) {
    redirectCoachOutcome($threadId, 'error', 'Please choose a valid outcome.');
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
      AND archived = 0
    LIMIT 1
");
$threadStmt->execute([
    'thread_id' => $threadId,
    'user_id' => $userId,
]);
$thread = $threadStmt->fetch();

if (!$thread) {
    redirectCoachOutcome(0, 'error', 'Coach situation not found.');
}

try {
    $existingStmt = $db->prepare("
        SELECT id
        FROM coach_outcomes
        WHERE thread_id = :thread_id
          AND user_id = :user_id
        ORDER BY id DESC
        LIMIT 1
    ");
    $existingStmt->execute([
        'thread_id' => $threadId,
        'user_id' => $userId,
    ]);
    $existingOutcome = $existingStmt->fetch();

    if ($existingOutcome) {
        $updateStmt = $db->prepare("
            UPDATE coach_outcomes
            SET outcome = :outcome
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
        ");
        $updateStmt->execute([
            'outcome' => $outcome,
            'id' => (int) ($existingOutcome['id'] ?? 0),
            'user_id' => $userId,
        ]);
    } else {
        $insertStmt = $db->prepare("
            INSERT INTO coach_outcomes (thread_id, user_id, outcome)
            VALUES (:thread_id, :user_id, :outcome)
        ");
        $insertStmt->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'outcome' => $outcome,
        ]);
    }
} catch (Throwable $e) {
    error_log('Coach outcome save failed: ' . $e->getMessage());
    redirectCoachOutcome($threadId, 'error', 'Could not save your outcome. Please try again.');
}

redirectCoachOutcome($threadId, 'success', 'Outcome saved.');
