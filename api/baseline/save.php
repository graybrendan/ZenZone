<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/zenscore.php';

requireLogin();

function redirectBaselineError(string $message, array $input = []): void
{
    setFlashMessage('error', $message);
    setOldInput(array_merge(['baseline_form' => 'baseline'], $input));
    authRedirect('baseline.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('baseline.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    redirectBaselineError('Your request could not be verified. Please try again.');
}

$userId = (int) $_SESSION['user_id'];

$fields = array_keys(zenzone_labels());

$scores = [];
$rawInput = [];

foreach ($fields as $field) {
    $value = (int) ($_POST[$field] ?? 0);
    $rawInput[$field] = (string) $value;
    if ($value < 1 || $value > 7) {
        redirectBaselineError('Please submit valid scores between 1 and 7.', $rawInput);
    }
    $scores[$field] = $value;
}

$baselineScore = round(array_sum($scores) / count($scores), 2);

try {
    $db = getDB();
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO baseline_assessments (
            user_id,
            mindfulness,
            energy,
            connectedness,
            motivation,
            confidence,
            emotional_balance,
            recovery,
            readiness,
            baseline_score
        ) VALUES (
            :user_id,
            :mindfulness,
            :energy,
            :connectedness,
            :motivation,
            :confidence,
            :emotional_balance,
            :recovery,
            :readiness,
            :baseline_score
        )
        ON DUPLICATE KEY UPDATE
            mindfulness = VALUES(mindfulness),
            energy = VALUES(energy),
            connectedness = VALUES(connectedness),
            motivation = VALUES(motivation),
            confidence = VALUES(confidence),
            emotional_balance = VALUES(emotional_balance),
            recovery = VALUES(recovery),
            readiness = VALUES(readiness),
            baseline_score = VALUES(baseline_score)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'mindfulness' => $scores['mindfulness'],
        'energy' => $scores['energy'],
        'connectedness' => $scores['connectedness'],
        'motivation' => $scores['motivation'],
        'confidence' => $scores['confidence'],
        'emotional_balance' => $scores['emotional_balance'],
        'recovery' => $scores['recovery'],
        'readiness' => $scores['readiness'],
        'baseline_score' => $baselineScore,
    ]);

    $stmt = $db->prepare("
        UPDATE users
        SET baseline_complete = 1
        WHERE id = :user_id
    ");
    $stmt->execute([
        'user_id' => $userId
    ]);

    $db->commit();

    clearOldInput();
    redirectWithFlash('dashboard.php', 'Baseline saved successfully.', 'success');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Baseline save failed: ' . $e->getMessage());
    redirectBaselineError('Could not save baseline right now. Please try again.', $rawInput);
}
