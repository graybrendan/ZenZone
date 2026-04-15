<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

const COACH_HOME_PREVIEW_LIMIT = 5;

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$coachStorageReady = isCoachStorageReady($db);
$flash = getFlashMessage();

$formData = [
    'situation_text' => '',
    'situation_type' => 'other',
    'time_available' => '3',
    'stress_level' => '3',
    'upcoming_event' => '',
];

$oldInputForm = (string) getOldInput('coach_form', '');
if ($oldInputForm === 'new_situation') {
    $formData['situation_text'] = (string) getOldInput('situation_text', '');
    $formData['situation_type'] = (string) getOldInput('situation_type', 'other');
    $formData['time_available'] = (string) getOldInput('time_available', '3');
    $formData['stress_level'] = (string) getOldInput('stress_level', '3');
    $formData['upcoming_event'] = (string) getOldInput('upcoming_event', '');
    clearOldInput();
}

if (!in_array($formData['situation_type'], getCoachSituationTypes(), true)) {
    $formData['situation_type'] = 'other';
}
if (!in_array((int) $formData['time_available'], getCoachTimeOptions(), true)) {
    $formData['time_available'] = '3';
}
if (!isValidScaleRating((int) $formData['stress_level'], 1, 5)) {
    $formData['stress_level'] = '3';
}

$recentSituations = [];
$totalSituations = 0;

if ($coachStorageReady) {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM coach_threads
        WHERE user_id = :user_id
          AND archived = 0
    ");
    $countStmt->execute(['user_id' => $userId]);
    $totalSituations = (int) $countStmt->fetchColumn();

    $listStmt = $db->prepare("
        SELECT
            id,
            COALESCE(NULLIF(summary, ''), thread_title) AS summary,
            created_at,
            updated_at,
            last_message_at
        FROM coach_threads
        WHERE user_id = :user_id
          AND archived = 0
        ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC
        LIMIT " . COACH_HOME_PREVIEW_LIMIT . "
    ");
    $listStmt->execute(['user_id' => $userId]);
    $recentSituations = $listStmt->fetchAll();
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatCoachDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('M j, Y g:i A', $timestamp);
}

function truncateSummary(string $summary, int $maxLength = 140): string
{
    return createCoachSituationSummary($summary, $maxLength);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 980px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .button-link,
        button {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #999;
            border-radius: 6px;
            text-decoration: none;
            color: #000;
            background: #f7f7f7;
            cursor: pointer;
            font-size: 14px;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 14px;
            margin-top: 14px;
        }

        .notice {
            margin: 12px 0;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background: #f5f5f5;
        }

        .notice.success {
            border-color: #9bc29b;
            background: #eef9ee;
        }

        .notice.error {
            border-color: #d6a3a3;
            background: #fff0f0;
        }

        .muted {
            color: #666;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        textarea,
        select,
        input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #bbb;
            border-radius: 6px;
            padding: 8px;
            font-family: inherit;
            font-size: 14px;
        }

        .situation-list {
            display: grid;
            gap: 10px;
        }

        .situation-item {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .actions form {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <h1 style="margin: 0;">ZenZone Coach</h1>
            <p class="muted" style="margin: 4px 0 0 0;">Mindfulness and sports psychology support for athletes. Situation -> best action -> done.</p>
        </div>
        <a class="button-link" href="../dashboard.php">Back to Dashboard</a>
    </div>

    <?php if ($flash): ?>
        <div class="notice <?= h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$coachStorageReady): ?>
        <div class="card">
            <h2 style="margin-top: 0;">Coach Setup Required</h2>
            <p>The Coach database tables are missing required columns.</p>
            <p>
                Run:
                <code>sql/migrations/2026-04-15_001_coach_tables.sql</code>
                and
                <code>sql/migrations/2026-04-15_002_coach_thread_situation_fields.sql</code>
                in phpMyAdmin.
            </p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2 style="margin-top: 0;">Start New Situation</h2>
            <form method="POST" action="../../api/coach/submit.php">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

                <label for="situation_text"><strong>Describe what is going on</strong></label>
                <textarea id="situation_text" name="situation_text" rows="5" maxlength="1200" required><?= h($formData['situation_text']) ?></textarea>

                <div class="form-grid" style="margin-top: 10px;">
                    <div>
                        <label for="situation_type">Situation type</label>
                        <select id="situation_type" name="situation_type" required>
                            <?php foreach (getCoachSituationTypes() as $type): ?>
                                <option value="<?= h($type) ?>" <?= $formData['situation_type'] === $type ? 'selected' : '' ?>>
                                    <?= h(ucfirst($type)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="time_available">Time available</label>
                        <select id="time_available" name="time_available" required>
                            <?php foreach (getCoachTimeOptions() as $minutes): ?>
                                <option value="<?= (int) $minutes ?>" <?= ((int) $formData['time_available'] === (int) $minutes) ? 'selected' : '' ?>>
                                    <?= (int) $minutes ?> min
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="stress_level">Stress level (1-5)</label>
                        <select id="stress_level" name="stress_level" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= ((int) $formData['stress_level'] === $i) ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <label for="upcoming_event">Upcoming event (optional)</label>
                    <input id="upcoming_event" name="upcoming_event" type="text" maxlength="120" value="<?= h($formData['upcoming_event']) ?>">
                </div>

                <div style="margin-top: 12px;">
                    <button type="submit">Get Coach Recommendation</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top: 0;">Recent Coach Situations</h2>

            <?php if (empty($recentSituations)): ?>
                <p class="muted">No coach situations yet. Start one above.</p>
            <?php else: ?>
                <div class="situation-list">
                    <?php foreach ($recentSituations as $row): ?>
                        <?php
                        $situationId = (int) ($row['id'] ?? 0);
                        $createdTime = (string) ($row['created_at'] ?? '');
                        $updatedTime = (string) ($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '');
                        $summary = truncateSummary((string) ($row['summary'] ?? ''));
                        ?>
                        <div class="situation-item">
                            <p style="margin: 0 0 6px 0;"><strong><?= h($summary) ?></strong></p>
                            <p class="muted" style="margin: 0;">
                                Created: <?= h(formatCoachDate($createdTime)) ?> |
                                Updated: <?= h(formatCoachDate($updatedTime)) ?>
                            </p>

                            <div class="actions">
                                <a class="button-link" href="view.php?id=<?= $situationId ?>">View</a>
                                <a class="button-link" href="edit.php?id=<?= $situationId ?>">Edit</a>
                                <form method="POST" action="../../api/coach/delete.php" onsubmit="return confirm('Delete this coach situation? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                                    <input type="hidden" name="thread_id" value="<?= $situationId ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalSituations > COACH_HOME_PREVIEW_LIMIT): ?>
                    <p style="margin-top: 12px;">
                        <a class="button-link" href="history.php">View all</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
