<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$lessons = getLessonCatalog();
$topicOptions = getLessonTopics();
$durationOptions = getLessonDurationOptions();

$searchQuery = trim((string) ($_GET['q'] ?? ''));
if (strlen($searchQuery) > 80) {
    $searchQuery = substr($searchQuery, 0, 80);
}

$selectedTopic = trim((string) ($_GET['topic'] ?? 'all'));
if ($selectedTopic !== 'all' && !in_array($selectedTopic, $topicOptions, true)) {
    $selectedTopic = 'all';
}

$selectedDuration = trim((string) ($_GET['duration'] ?? 'all'));
if ($selectedDuration !== 'all') {
    if (!ctype_digit($selectedDuration) || !in_array((int) $selectedDuration, $durationOptions, true)) {
        $selectedDuration = 'all';
    }
}

$statusNotice = '';
$statusCode = trim((string) ($_GET['status'] ?? ''));
if ($statusCode === 'feature_disabled') {
    $statusNotice = 'Lesson save and progress tracking are currently turned off.';
}

$filteredLessons = array_values(array_filter($lessons, static function (array $lesson) use ($searchQuery, $selectedTopic, $selectedDuration): bool {
    if ($selectedTopic !== 'all' && (string) ($lesson['topic'] ?? '') !== $selectedTopic) {
        return false;
    }

    if ($selectedDuration !== 'all' && (int) ($lesson['duration_minutes'] ?? 0) !== (int) $selectedDuration) {
        return false;
    }

    if ($searchQuery !== '') {
        $haystack = strtolower(
            (string) ($lesson['title'] ?? '') . ' ' .
            (string) ($lesson['short_description'] ?? '') . ' ' .
            (string) ($lesson['topic'] ?? '')
        );

        if (strpos($haystack, strtolower($searchQuery)) === false) {
            return false;
        }
    }

    return true;
}));

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTopicLabel(string $topic): string
{
    return ucwords($topic);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - ZenZone</title>
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
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
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

        .filters {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 18px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            align-items: end;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #bbb;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .muted {
            color: #666;
            margin: 6px 0 0 0;
        }

        .notice {
            margin: 12px 0;
            padding: 10px 12px;
            border: 1px solid #d8c48b;
            background: #fff7e0;
            border-radius: 8px;
        }

        .lessons-grid {
            display: grid;
            gap: 14px;
        }

        .lesson-card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 14px;
        }

        .lesson-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            border: 1px solid #999;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 12px;
        }

        .meta {
            margin: 6px 0 10px 0;
            color: #333;
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        details {
            margin-top: 10px;
        }

        .empty-state {
            border: 1px dashed #bbb;
            border-radius: 10px;
            padding: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <h1 style="margin: 0;">Lessons</h1>
            <p class="muted">Learn -> Find -> Apply. Most tools take under 5 minutes.</p>
        </div>
        <a class="button-link" href="../dashboard.php">Back to Dashboard</a>
    </div>

    <form class="filters" method="GET" action="index.php">
        <div class="filters-row">
            <div>
                <label for="q">Search</label>
                <input id="q" name="q" type="text" value="<?= h($searchQuery) ?>" placeholder="Breathing, reset, focus...">
            </div>

            <div>
                <label for="topic">Topic</label>
                <select id="topic" name="topic">
                    <option value="all">All topics</option>
                    <?php foreach ($topicOptions as $topic): ?>
                        <option value="<?= h($topic) ?>" <?= $selectedTopic === $topic ? 'selected' : '' ?>>
                            <?= h(formatTopicLabel($topic)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="duration">Duration</label>
                <select id="duration" name="duration">
                    <option value="all">Any duration</option>
                    <?php foreach ($durationOptions as $minutes): ?>
                        <option value="<?= (int) $minutes ?>" <?= ((int) $selectedDuration === (int) $minutes) ? 'selected' : '' ?>>
                            <?= (int) $minutes ?> min
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit">Apply filters</button>
                <a class="button-link" href="index.php">Clear</a>
            </div>
        </div>
        <p class="muted"><?= count($filteredLessons) ?> lesson(s) found.</p>
    </form>

    <?php if ($statusNotice !== ''): ?>
        <div class="notice"><?= h($statusNotice) ?></div>
    <?php endif; ?>

    <?php if (empty($filteredLessons)): ?>
        <div class="empty-state">
            No lessons matched these filters yet. Try a broader topic or clear the search.
        </div>
    <?php else: ?>
        <div class="lessons-grid">
            <?php foreach ($filteredLessons as $lesson): ?>
                <?php $slug = (string) ($lesson['slug'] ?? ''); ?>
                <article class="lesson-card">
                    <div class="lesson-head">
                        <h2 style="margin: 0; font-size: 20px;"><?= h($lesson['title'] ?? '') ?></h2>
                        <div>
                            <?php if (!empty($lesson['is_featured'])): ?>
                                <span class="badge">Featured</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="meta">
                        Topic: <?= h(formatTopicLabel((string) ($lesson['topic'] ?? ''))) ?> |
                        Duration: <?= (int) ($lesson['duration_minutes'] ?? 0) ?> min |
                        Format: <?= h(ucfirst((string) ($lesson['format'] ?? ''))) ?>
                    </p>

                    <p><?= h($lesson['short_description'] ?? '') ?></p>
                    <p><strong>When to use:</strong> <?= h($lesson['when_to_use'] ?? '') ?></p>
                    <p><strong>Evidence note:</strong> <?= h($lesson['evidence_note'] ?? '') ?></p>

                    <?php if (!empty($lesson['external_video_url'])): ?>
                        <p>
                            <a href="<?= h($lesson['external_video_url']) ?>" target="_blank" rel="noopener noreferrer">Watch external video</a>
                        </p>
                    <?php else: ?>
                        <p class="muted">No video link yet.</p>
                    <?php endif; ?>

                    <div class="actions">
                        <a class="button-link" href="view.php?slug=<?= urlencode($slug) ?>">Try now</a>
                    </div>

                    <details>
                        <summary>Why this works</summary>
                        <p><?= h($lesson['why_this_works'] ?? '') ?></p>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
