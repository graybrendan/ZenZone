<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

function redirectCoachEditError(int $threadId, string $message, array $input): void
{
    setFlashMessage('error', $message);
    setOldInput(array_merge([
        'coach_form' => 'edit_situation',
        'coach_edit_thread_id' => (string) $threadId,
    ], $input));

    if ($threadId > 0) {
        authRedirect('coach/edit.php', ['id' => $threadId]);
    }

    authRedirect('coach/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('coach/index.php');
}

$threadId = (int) ($_POST['thread_id'] ?? 0);
$input = [
    'situation_text' => trim((string) ($_POST['situation_text'] ?? '')),
    'situation_type' => trim((string) ($_POST['situation_type'] ?? 'other')),
    'time_available' => (string) ((int) ($_POST['time_available'] ?? 0)),
    'stress_level' => (string) ((int) ($_POST['stress_level'] ?? 0)),
    'upcoming_event' => trim((string) ($_POST['upcoming_event'] ?? '')),
];

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectCoachEditError($threadId, 'Your request could not be verified. Please try again.', $input);
}

if ($threadId <= 0) {
    redirectCoachEditError(0, 'Invalid coach situation selected.', $input);
}

if (strlen($input['situation_text']) < 8 || strlen($input['situation_text']) > 1200) {
    redirectCoachEditError($threadId, 'Please describe your situation in 8-1200 characters.', $input);
}

if (!in_array($input['situation_type'], getCoachSituationTypes(), true)) {
    redirectCoachEditError($threadId, 'Please choose a valid situation type.', $input);
}

if (!in_array((int) $input['time_available'], getCoachTimeOptions(), true)) {
    redirectCoachEditError($threadId, 'Please choose a valid time available option.', $input);
}

if (!isValidScaleRating((int) $input['stress_level'], 1, 5)) {
    redirectCoachEditError($threadId, 'Stress level must be between 1 and 5.', $input);
}

if (strlen($input['upcoming_event']) > 120) {
    $input['upcoming_event'] = substr($input['upcoming_event'], 0, 120);
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

$engineInput = [
    'situation_text' => $input['situation_text'],
    'situation_type' => $input['situation_type'],
    'time_available' => (int) $input['time_available'],
    'stress_level' => (int) $input['stress_level'],
    'upcoming_event' => $input['upcoming_event'],
];
$coachResponse = generateCoachResponse($engineInput);

$summary = createCoachSituationSummary($input['situation_text'], 180);
$threadTitle = createCoachSituationSummary($input['situation_text'], 90);

try {
    $db->beginTransaction();

    $updateThreadStmt = $db->prepare("
        UPDATE coach_threads
        SET
            thread_title = :thread_title,
            summary = :summary,
            situation_text = :situation_text,
            situation_type = :situation_type,
            time_available = :time_available,
            stress_level = :stress_level,
            upcoming_event = :upcoming_event,
            last_message_at = NOW(),
            updated_at = NOW()
        WHERE id = :thread_id
          AND user_id = :user_id
        LIMIT 1
    ");
    $updateThreadStmt->execute([
        'thread_title' => $threadTitle,
        'summary' => $summary,
        'situation_text' => $input['situation_text'],
        'situation_type' => $input['situation_type'],
        'time_available' => (int) $input['time_available'],
        'stress_level' => (int) $input['stress_level'],
        'upcoming_event' => $input['upcoming_event'] !== '' ? $input['upcoming_event'] : null,
        'thread_id' => $threadId,
        'user_id' => $userId,
    ]);

    $inputMetadata = json_encode([
        'event' => 'situation_updated',
        'situation_type' => $input['situation_type'],
        'time_available' => (int) $input['time_available'],
        'stress_level' => (int) $input['stress_level'],
        'upcoming_event' => $input['upcoming_event'],
    ]);

    $insertUserMessageStmt = $db->prepare("
        INSERT INTO coach_messages (
            thread_id,
            sender,
            content,
            metadata_json
        ) VALUES (
            :thread_id,
            'user',
            :content,
            :metadata_json
        )
    ");
    $insertUserMessageStmt->execute([
        'thread_id' => $threadId,
        'content' => $input['situation_text'],
        'metadata_json' => $inputMetadata !== false ? $inputMetadata : null,
    ]);

    $responseMetadata = json_encode($coachResponse);
    $insertAiMessageStmt = $db->prepare("
        INSERT INTO coach_messages (
            thread_id,
            sender,
            content,
            metadata_json
        ) VALUES (
            :thread_id,
            'ai',
            :content,
            :metadata_json
        )
    ");
    $insertAiMessageStmt->execute([
        'thread_id' => $threadId,
        'content' => (string) ($coachResponse['coach_message'] ?? ''),
        'metadata_json' => $responseMetadata !== false ? $responseMetadata : null,
    ]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Coach update failed: ' . $e->getMessage());
    redirectCoachEditError($threadId, 'Could not update this coach situation. Please try again.', $input);
}

clearOldInput();
setFlashMessage('success', 'Situation updated. Recommendation refreshed.');
authRedirect('coach/view.php', ['id' => $threadId]);
