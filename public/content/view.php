<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$slug = trim((string) ($_GET['slug'] ?? ''));
$lesson = getLessonBySlug($slug);

if (!$lesson) {
    http_response_code(404);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Try Lesson - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 860px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        .button-link {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #999;
            border-radius: 6px;
            text-decoration: none;
            color: #000;
            background: #f7f7f7;
            font-size: 14px;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 16px;
            margin-top: 14px;
        }
    </style>
</head>
<body>
    <p>
        <a class="button-link" href="index.php">Back to Lessons</a>
        <a class="button-link" href="../dashboard.php">Back to Dashboard</a>
    </p>

    <?php if (!$lesson): ?>
        <h1>Lesson not found</h1>
        <p>The lesson you requested does not exist or was removed.</p>
    <?php else: ?>
        <h1 style="margin-bottom: 6px;"><?= h($lesson['title'] ?? '') ?></h1>
        <p style="margin-top: 0;">
            Topic: <?= h($lesson['topic'] ?? '') ?> |
            Duration: <?= (int) ($lesson['duration_minutes'] ?? 0) ?> min |
            Format: <?= h(ucfirst((string) ($lesson['format'] ?? ''))) ?>
        </p>

        <div class="card">
            <h2 style="margin-top: 0;">Try now</h2>
            <p><?= h($lesson['short_description'] ?? '') ?></p>

            <?php if (!empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])): ?>
                <ol>
                    <?php foreach ($lesson['try_now_steps'] as $step): ?>
                        <li><?= h($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p>Steps will be added soon.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top: 0;">Why this works</h2>
            <p><?= h($lesson['why_this_works'] ?? '') ?></p>
            <p><strong>When to use:</strong> <?= h($lesson['when_to_use'] ?? '') ?></p>
            <p><strong>Evidence note:</strong> <?= h($lesson['evidence_note'] ?? '') ?></p>

            <?php if (!empty($lesson['external_video_url'])): ?>
                <p>
                    <a href="<?= h($lesson['external_video_url']) ?>" target="_blank" rel="noopener noreferrer">Open external video</a>
                </p>
            <?php else: ?>
                <p>No video link yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
