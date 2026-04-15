<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$threadId = (int) ($_GET['id'] ?? 0);
if ($threadId <= 0) {
    setFlashMessage('error', 'Invalid coach situation selected.');
    authRedirect('coach/index.php');
}

$threadStmt = $db->prepare("
    SELECT
        id,
        summary,
        situation_text,
        situation_type,
        time_available,
        stress_level,
        upcoming_event,
        updated_at
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
    setFlashMessage('error', 'Coach situation not found.');
    authRedirect('coach/index.php');
}

$flash = getFlashMessage();

$formData = [
    'situation_text' => (string) ($thread['situation_text'] ?? ''),
    'situation_type' => (string) ($thread['situation_type'] ?? 'other'),
    'time_available' => (string) ((int) ($thread['time_available'] ?? 3)),
    'stress_level' => (string) ((int) ($thread['stress_level'] ?? 3)),
    'upcoming_event' => (string) ($thread['upcoming_event'] ?? ''),
];

$oldInputForm = (string) getOldInput('coach_form', '');
$oldInputThreadId = (int) getOldInput('coach_edit_thread_id', '0');
if ($oldInputForm === 'edit_situation' && $oldInputThreadId === $threadId) {
    $formData['situation_text'] = (string) getOldInput('situation_text', $formData['situation_text']);
    $formData['situation_type'] = (string) getOldInput('situation_type', $formData['situation_type']);
    $formData['time_available'] = (string) getOldInput('time_available', $formData['time_available']);
    $formData['stress_level'] = (string) getOldInput('stress_level', $formData['stress_level']);
    $formData['upcoming_event'] = (string) getOldInput('upcoming_event', $formData['upcoming_event']);
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

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatCoachDateTime(string $value): string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coach Situation - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
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

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <h1>Edit Coach Situation</h1>
    <p>Last updated: <?= h(formatCoachDateTime((string) ($thread['updated_at'] ?? ''))) ?></p>

    <?php if ($flash): ?>
        <div class="notice <?= h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-top: 0;">Update details and refresh recommendation</h2>
        <form method="POST" action="../../api/coach/update.php">
            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
            <input type="hidden" name="thread_id" value="<?= $threadId ?>">

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

            <div class="actions">
                <button type="submit">Save changes</button>
                <a class="button-link" href="view.php?id=<?= $threadId ?>">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
