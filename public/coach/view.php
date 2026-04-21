<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
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
        thread_title,
        summary,
        situation_text,
        situation_type,
        time_available,
        stress_level,
        upcoming_event,
        created_at,
        updated_at,
        last_message_at
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

$aiMessageStmt = $db->prepare("
    SELECT content, metadata_json, created_at
    FROM coach_messages
    WHERE thread_id = :thread_id
      AND sender = 'ai'
    ORDER BY id DESC
    LIMIT 1
");
$aiMessageStmt->execute(['thread_id' => $threadId]);
$latestAiMessage = $aiMessageStmt->fetch();

$coachResponse = null;
if ($latestAiMessage && !empty($latestAiMessage['metadata_json'])) {
    $decoded = json_decode((string) $latestAiMessage['metadata_json'], true);
    if (is_array($decoded)) {
        $coachResponse = $decoded;
    }
}

if ($coachResponse === null) {
    $coachResponse = generateCoachResponse([
        'situation_text' => (string) ($thread['situation_text'] ?? ''),
        'situation_type' => (string) ($thread['situation_type'] ?? 'other'),
        'time_available' => (int) ($thread['time_available'] ?? 3),
        'stress_level' => (int) ($thread['stress_level'] ?? 3),
        'upcoming_event' => (string) ($thread['upcoming_event'] ?? ''),
    ]);
}

$flash = getFlashMessage();

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
    <title>Coach Situation - ZenZone</title>
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

        .recommendation {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }

        textarea {
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
            margin-top: 10px;
        }

        .actions form {
            margin: 0;
        }

        .crisis-box {
            border: 1px solid #d6a3a3;
            background: #fff0f0;
            border-radius: 8px;
            padding: 10px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <h1 style="margin: 0;">Coach Situation</h1>
            <p class="muted" style="margin: 4px 0 0 0;">Created <?= h(formatCoachDateTime((string) ($thread['created_at'] ?? ''))) ?></p>
        </div>
        <a class="button-link" href="index.php">Back to Coach Home</a>
    </div>

    <?php if ($flash): ?>
        <div class="notice <?= h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-top: 0;">Summary</h2>
        <p style="margin-bottom: 0;"><strong><?= h((string) ($thread['summary'] ?? '')) ?></strong></p>
    </div>

    <div class="card">
        <h2 style="margin-top: 0;">Situation Details</h2>
        <p><strong>Situation type:</strong> <?= h((string) ($thread['situation_type'] ?? '')) ?></p>
        <p><strong>Time available:</strong> <?= (int) ($thread['time_available'] ?? 0) ?> min</p>
        <p><strong>Stress level:</strong> <?= (int) ($thread['stress_level'] ?? 0) ?> / 5</p>
        <p><strong>Upcoming event:</strong> <?= h((string) (($thread['upcoming_event'] ?? '') !== '' ? $thread['upcoming_event'] : 'None')) ?></p>
        <p><strong>What happened:</strong><br><?= nl2br(h((string) ($thread['situation_text'] ?? ''))) ?></p>
    </div>

    <div class="card">
        <h2 style="margin-top: 0;">Recommendation</h2>

        <?php if (!empty($coachResponse['crisis_detected'])): ?>
            <div class="crisis-box">
                <p><strong>Immediate support recommended</strong></p>
                <p><?= h((string) ($coachResponse['crisis_message'] ?? '')) ?></p>
                <p><?= h((string) ($coachResponse['coach_message'] ?? '')) ?></p>
            </div>
        <?php else: ?>
            <?php if (!empty($coachResponse['summary'])): ?>
                <p><strong>Coach summary:</strong> <?= h((string) $coachResponse['summary']) ?></p>
            <?php endif; ?>

            <?php $top = is_array($coachResponse['top_recommendation'] ?? null) ? $coachResponse['top_recommendation'] : null; ?>
            <?php if ($top): ?>
                <div class="recommendation">
                    <h3 style="margin-top: 0;">Top recommendation: <?= h((string) ($top['title'] ?? '')) ?></h3>
                    <p><strong>Why this works:</strong> <?= h((string) ($top['why_this_works'] ?? '')) ?></p>
                    <p><strong>When to use:</strong> <?= h((string) ($top['when_to_use'] ?? '')) ?></p>
                    <p><strong>Estimated duration:</strong> <?= (int) ($top['duration_minutes'] ?? 0) ?> min</p>

                    <?php if (!empty($top['steps']) && is_array($top['steps'])): ?>
                        <p><strong>Steps:</strong></p>
                        <ol>
                            <?php foreach ($top['steps'] as $step): ?>
                                <li><?= h((string) $step) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php $topSlug = trim((string) ($top['slug'] ?? '')); ?>
                    <?php if ($topSlug !== '' && getLessonBySlug($topSlug) !== null): ?>
                        <p><a class="button-link" href="../content/view.php?slug=<?= urlencode($topSlug) ?>">Start this tool</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php $alternatives = is_array($coachResponse['alternatives'] ?? null) ? $coachResponse['alternatives'] : []; ?>
            <?php if (!empty($alternatives)): ?>
                <h3 style="margin-top: 16px;">Alternatives</h3>
                <?php foreach ($alternatives as $alternative): ?>
                    <?php if (!is_array($alternative)): continue; endif; ?>
                    <div class="recommendation">
                        <h4 style="margin-top: 0;"><?= h((string) ($alternative['title'] ?? 'Alternate tool')) ?></h4>
                        <p><strong>Why this works:</strong> <?= h((string) ($alternative['why_this_works'] ?? '')) ?></p>
                        <p><strong>When to use:</strong> <?= h((string) ($alternative['when_to_use'] ?? '')) ?></p>
                        <p><strong>Estimated duration:</strong> <?= (int) ($alternative['duration_minutes'] ?? 0) ?> min</p>

                        <?php if (!empty($alternative['steps']) && is_array($alternative['steps'])): ?>
                            <p><strong>Steps:</strong></p>
                            <ol>
                                <?php foreach ($alternative['steps'] as $step): ?>
                                    <li><?= h((string) $step) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php $altSlug = trim((string) ($alternative['slug'] ?? '')); ?>
                        <?php if ($altSlug !== '' && getLessonBySlug($altSlug) !== null): ?>
                            <p><a class="button-link" href="../content/view.php?slug=<?= urlencode($altSlug) ?>">Start this tool</a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin-top: 0;">Actions</h2>
        <div class="actions">
            <a class="button-link" href="edit.php?id=<?= $threadId ?>">Edit situation</a>
            <a class="button-link" href="history.php">View all situations</a>
            <a class="button-link" href="index.php">Back to Coach home</a>

            <form method="POST" action="../../api/coach/delete.php" onsubmit="return confirm('Delete this coach situation? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                <button type="submit">Delete situation</button>
            </form>
        </div>
    </div>
</body>
</html>
