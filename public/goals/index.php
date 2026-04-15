<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT
        id,
        user_id,
        title,
        category,
        cadence_number,
        cadence_unit,
        cadence_type,
        status,
        is_priority,
        start_date,
        end_date,
        notes,
        created_at,
        updated_at
    FROM goals
    WHERE user_id = :user_id
    ORDER BY
        CASE
            WHEN status = 'active' THEN 1
            WHEN status = 'paused' THEN 2
            WHEN status = 'completed' THEN 3
            ELSE 4
        END,
        CASE
            WHEN cadence_type = 'daily' THEN 1
            WHEN cadence_type = 'weekly' THEN 2
            WHEN cadence_type = 'monthly' THEN 3
            ELSE 4
        END,
        updated_at DESC,
        created_at DESC
");
$stmt->execute([
    'user_id' => $userId
]);

$allGoals = $stmt->fetchAll();

$currentGoals = [];
$completedGoals = [];

foreach ($allGoals as $goal) {
    if (($goal['status'] ?? '') === 'completed') {
        $completedGoals[] = $goal;
    } else {
        $currentGoals[] = $goal;
    }
}

function formatGoalMeta(array $goal): string
{
    $parts = [];

    if (!empty($goal['category'])) {
        $categories = array_filter(array_map('trim', explode(',', (string) $goal['category'])));
        if (!empty($categories)) {
            $parts[] = implode(', ', array_map('ucfirst', $categories));
        }
    }

    $cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
    $cadenceUnit = $goal['cadence_unit'] ?? 'day';
    if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
        $cadenceUnit = 'day';
    }
    $parts[] = $cadenceNumber . ' per ' . $cadenceUnit;

    return !empty($parts) ? implode(' • ', $parts) : 'No details';
}

function formatGoalStatus(array $goal): string
{
    $status = ucfirst($goal['status'] ?? 'unknown');

    if (!empty($goal['is_priority'])) {
        return $status . ' • Priority';
    }

    return $status;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goals - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .section {
            margin-top: 32px;
        }

        .goal-card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .goal-title {
            margin: 0 0 8px 0;
        }

        .goal-meta,
        .goal-status,
        .goal-dates,
        .goal-notes {
            margin: 6px 0;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .actions form,
        .actions a {
            display: inline-block;
            margin: 0;
        }

        button,
        .button-link {
            padding: 8px 12px;
            border: 1px solid #999;
            border-radius: 6px;
            background: #f7f7f7;
            text-decoration: none;
            color: #000;
            cursor: pointer;
        }

        .empty-state {
            color: #666;
            font-style: italic;
        }

        hr {
            margin: 32px 0;
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div>
            <h1 style="margin: 0;">Goals</h1>
            <p style="margin: 6px 0 0 0;">Track current goals and keep completed goals visible.</p>
        </div>

        <div>
            <a class="button-link" href="create.php">Create Goal</a>
            <a class="button-link" href="../dashboard.php">Back to Dashboard</a>
        </div>
    </div>

    <div class="section">
        <h2>Current Goals</h2>

        <?php if (empty($currentGoals)): ?>
            <p class="empty-state">No current goals yet.</p>
        <?php else: ?>
            <?php foreach ($currentGoals as $goal): ?>
                <div class="goal-card">
                    <h3 class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></h3>

                    <p class="goal-meta">
                        <strong>Type:</strong>
                        <?php echo htmlspecialchars(formatGoalMeta($goal)); ?>
                    </p>

                    <p class="goal-status">
                        <strong>Status:</strong>
                        <?php echo htmlspecialchars(formatGoalStatus($goal)); ?>
                    </p>

                    <p class="goal-dates">
                        <strong>Dates:</strong>
                        <?php echo htmlspecialchars($goal['start_date'] ?: 'No start date'); ?>
                        —
                        <?php echo htmlspecialchars($goal['end_date'] ?: 'No end date'); ?>
                    </p>

                    <?php if (!empty($goal['notes'])): ?>
                        <p class="goal-notes">
                            <strong>Notes:</strong>
                            <?php echo nl2br(htmlspecialchars($goal['notes'])); ?>
                        </p>
                    <?php endif; ?>

                    <div class="actions">
                        <a class="button-link" href="details.php?id=<?php echo (int) $goal['id']; ?>">View</a>
                        <a class="button-link" href="edit.php?id=<?php echo (int) $goal['id']; ?>">Edit</a>

                        <?php if (($goal['status'] ?? '') === 'active'): ?>
                            <form method="POST" action="../../api/goals/update.php">
                                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                                <input type="hidden" name="action" value="pause">
                                <button type="submit">Pause</button>
                            </form>

                            <form method="POST" action="../../api/goals/update.php">
                                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit">Complete</button>
                            </form>
                        <?php elseif (($goal['status'] ?? '') === 'paused'): ?>
                            <form method="POST" action="../../api/goals/update.php">
                                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                                <input type="hidden" name="action" value="resume">
                                <button type="submit">Resume</button>
                            </form>

                            <form method="POST" action="../../api/goals/update.php">
                                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit">Complete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr>

    <div class="section">
        <h2>Completed Goals</h2>

        <?php if (empty($completedGoals)): ?>
            <p class="empty-state">No completed goals yet.</p>
        <?php else: ?>
            <?php foreach ($completedGoals as $goal): ?>
                <div class="goal-card">
                    <h3 class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></h3>

                    <p class="goal-meta">
                        <strong>Type:</strong>
                        <?php echo htmlspecialchars(formatGoalMeta($goal)); ?>
                    </p>

                    <p class="goal-status">
                        <strong>Status:</strong>
                        Completed
                    </p>

                    <p class="goal-dates">
                        <strong>Dates:</strong>
                        <?php echo htmlspecialchars($goal['start_date'] ?: 'No start date'); ?>
                        —
                        <?php echo htmlspecialchars($goal['end_date'] ?: 'No end date'); ?>
                    </p>

                    <?php if (!empty($goal['notes'])): ?>
                        <p class="goal-notes">
                            <strong>Notes:</strong>
                            <?php echo nl2br(htmlspecialchars($goal['notes'])); ?>
                        </p>
                    <?php endif; ?>

                    <div class="actions">
                        <a class="button-link" href="details.php?id=<?php echo (int) $goal['id']; ?>">View</a>

                        <form method="POST" action="../../api/goals/delete.php" onsubmit="return confirm('Delete this completed goal? This cannot be undone.');">
                            <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>
