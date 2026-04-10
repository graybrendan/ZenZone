<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/baseline.php');
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];

$fields = [
    'mindfulness',
    'energy',
    'connectedness',
    'motivation',
    'confidence',
    'emotional_balance',
    'recovery',
    'readiness',
];

$scores = [];

foreach ($fields as $field) {
    if (!isset($_POST[$field])) {
        http_response_code(400);
        exit('Missing field.');
    }

    $value = (int) $_POST[$field];

    if ($value < 1 || $value > 7) {
        http_response_code(400);
        exit('Invalid score.');
    }

    $scores[$field] = $value;
}

$baselineScore = round(array_sum($scores) / count($scores), 2);

try {
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

    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    exit('Error saving baseline.');
}