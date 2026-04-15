<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';

requireLogin();

const COACH_HISTORY_PAGE_SIZE = 10;

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM coach_threads
    WHERE user_id = :user_id
      AND archived = 0
");
$countStmt->execute(['user_id' => $userId]);
$totalRows = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalRows / COACH_HISTORY_PAGE_SIZE));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * COACH_HISTORY_PAGE_SIZE;

$rows = [];
if ($totalRows > 0) {
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
        LIMIT " . COACH_HISTORY_PAGE_SIZE . " OFFSET " . (int) $offset . "
    ");
    $listStmt->execute(['user_id' => $userId]);
    $rows = $listStmt->fetchAll();
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
    <title>Coach History - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 980px;
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

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
        }

        .muted {
            color: #666;
            margin: 4px 0 0 0;
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

        .pager {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
            align-items: center;
        }
    </style>
</head>
<body>
    <h1>Coach Situation History</h1>
    <p>
        <a class="button-link" href="index.php">Back to Coach Home</a>
        <a class="button-link" href="../dashboard.php">Back to Dashboard</a>
    </p>

    <?php if ($flash): ?>
        <div class="notice <?= h($flash['type']) ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="card">
            <p class="muted">No coach situations yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <?php
            $situationId = (int) ($row['id'] ?? 0);
            $summary = createCoachSituationSummary((string) ($row['summary'] ?? ''), 170);
            $createdAt = (string) ($row['created_at'] ?? '');
            $updatedAt = (string) ($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '');
            ?>
            <div class="card">
                <p style="margin: 0;"><strong><?= h($summary) ?></strong></p>
                <p class="muted">
                    Created: <?= h(formatCoachDateTime($createdAt)) ?> |
                    Updated: <?= h(formatCoachDateTime($updatedAt)) ?>
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

        <div class="pager">
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page > 1): ?>
                <a class="button-link" href="history.php?page=<?= $page - 1 ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="button-link" href="history.php?page=<?= $page + 1 ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
