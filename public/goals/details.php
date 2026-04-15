<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$goalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$today = date('Y-m-d');

if ($goalId <= 0) {
    die('Invalid goal ID.');
}

$goalStmt = $db->prepare("
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
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$goalStmt->execute([
    'id' => $goalId,
    'user_id' => $userId
]);

$goal = $goalStmt->fetch();

if (!$goal) {
    die('Goal not found.');
}

function getCadenceWindow(string $unit, DateTimeImmutable $today): array
{
    if ($unit === 'week') {
        $start = $today->modify('monday this week');
        $end = $start->modify('+6 days');
    } elseif ($unit === 'month') {
        $start = $today->modify('first day of this month');
        $end = $today->modify('last day of this month');
    } else {
        $start = $today;
        $end = $today;
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

$cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
$cadenceUnit = $goal['cadence_unit'] ?? 'day';
if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
    $cadenceUnit = 'day';
}

[$windowStart, $windowEnd] = getCadenceWindow($cadenceUnit, new DateTimeImmutable($today));

$periodCountStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM goal_checkins
    WHERE goal_id = :goal_id
      AND user_id = :user_id
      AND checkin_date BETWEEN :start_date AND :end_date
");
$periodCountStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $userId,
    'start_date' => $windowStart,
    'end_date' => $windowEnd
]);
$checkinsThisWindow = (int) $periodCountStmt->fetchColumn();

$todaysCheckinStmt = $db->prepare("
    SELECT
        goal_id,
        user_id,
        checkin_date,
        is_complete,
        notes,
        created_at,
        updated_at
    FROM goal_checkins
    WHERE goal_id = :goal_id
      AND user_id = :user_id
      AND checkin_date = :checkin_date
    ORDER BY created_at DESC, id DESC
    LIMIT 1
");
$todaysCheckinStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $userId,
    'checkin_date' => $today
]);

$todaysCheckin = $todaysCheckinStmt->fetch();

$historyStmt = $db->prepare("
    SELECT
        checkin_date,
        is_complete,
        notes,
        created_at,
        updated_at
    FROM goal_checkins
    WHERE goal_id = :goal_id
      AND user_id = :user_id
    ORDER BY checkin_date DESC, updated_at DESC
    LIMIT 10
");
$historyStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $userId
]);

$recentCheckins = $historyStmt->fetchAll();

$priorityLabel = !empty($goal['is_priority']) ? 'Priority Goal' : 'Non-Priority Goal';
$isActive = ($goal['status'] === 'active');
$isPaused = ($goal['status'] === 'paused');
$isCompleted = ($goal['status'] === 'completed');
$remainingCheckins = max(0, $cadenceNumber - $checkinsThisWindow);
$showCheckinForm = $isActive && $remainingCheckins > 0;

function safeValue($value, $fallback = 'None')
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return htmlspecialchars((string) $value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Details - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 16px;
            margin-top: 16px;
        }

        .actions {
            display: flex;
            gap: 10px;
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
            display: inline-block;
            padding: 10px 14px;
            border: 1px solid #999;
            border-radius: 6px;
            background: #f7f7f7;
            text-decoration: none;
            color: #000;
            cursor: pointer;
        }

        textarea {
            width: 100%;
            min-height: 110px;
            box-sizing: border-box;
            padding: 10px;
            resize: vertical;
        }

        .row {
            margin: 8px 0;
        }

        .muted {
            color: #666;
        }

        .history-item {
            border-top: 1px solid #ddd;
            padding-top: 12px;
            margin-top: 12px;
        }

    </style>
</head>
<body>
    <h1>Goal Details</h1>

    <div class="card">
        <div class="row">
            <strong>Title:</strong>
            <?php echo safeValue($goal['title']); ?>
        </div>

        <div class="row">
            <strong>Category:</strong>
            <?php echo safeValue(implode(', ', array_map('ucfirst', array_filter(array_map('trim', explode(',', (string) ($goal['category'] ?? ''))))))); ?>
        </div>

        <div class="row">
            <strong>Cadence:</strong>
            <?php echo htmlspecialchars($cadenceNumber . ' per ' . $cadenceUnit); ?>
        </div>

        <div class="row">
            <strong>Status:</strong>
            <?php echo safeValue(ucfirst($goal['status'])); ?>
        </div>

        <div class="row">
            <strong>Priority:</strong>
            <?php echo htmlspecialchars($priorityLabel); ?>
        </div>

        <div class="row">
            <strong>Start Date:</strong>
            <?php echo safeValue($goal['start_date']); ?>
        </div>

        <div class="row">
            <strong>End Date:</strong>
            <?php echo safeValue($goal['end_date']); ?>
        </div>

        <div class="row">
            <strong>Notes:</strong><br>
            <?php echo nl2br(safeValue($goal['notes'])); ?>
        </div>

        <div class="row muted">
            <strong>Created:</strong> <?php echo safeValue($goal['created_at']); ?><br>
            <strong>Last Updated:</strong> <?php echo safeValue($goal['updated_at']); ?>
        </div>

        <div class="actions">
            <a class="button-link" href="edit.php?id=<?php echo (int) $goal['id']; ?>">Edit Goal</a>
            <a class="button-link" href="index.php">Back to Goals</a>
            <a class="button-link" href="../dashboard.php">Back to Dashboard</a>

            <?php if ($isActive): ?>
                <form method="POST" action="../../api/goals/update.php">
                    <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                    <input type="hidden" name="action" value="pause">
                    <button type="submit">Pause Goal</button>
                </form>

                <form method="POST" action="../../api/goals/update.php">
                    <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit">Mark Completed</button>
                </form>
            <?php elseif ($isPaused): ?>
                <form method="POST" action="../../api/goals/update.php">
                    <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                    <input type="hidden" name="action" value="resume">
                    <button type="submit">Resume Goal</button>
                </form>

                <form method="POST" action="../../api/goals/update.php">
                    <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit">Mark Completed</button>
                </form>
            <?php endif; ?>

            <form method="POST" action="../../api/goals/delete.php" onsubmit="return confirm('Delete this goal? This cannot be undone.');">
                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
                <button type="submit">Delete Goal</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>Today's Check-In</h2>
        <p class="muted">
            <?php echo htmlspecialchars($checkinsThisWindow . ' of ' . $cadenceNumber . ' check-ins used this ' . $cadenceUnit . '.'); ?>
        </p>

        <?php if ($showCheckinForm): ?>
            <?php if ($todaysCheckin): ?>
                <p>
                    <strong>Latest status for today:</strong>
                    <?php echo ((int) $todaysCheckin['is_complete'] === 1) ? 'Completed today' : 'Not completed today'; ?>
                </p>
            <?php else: ?>
                <p class="muted">No check-in saved yet for today.</p>
            <?php endif; ?>

            <form method="POST" action="../../api/goals/checkin.php">
                <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="is_complete"
                            value="1"
                            <?php echo ($todaysCheckin && (int) $todaysCheckin['is_complete'] === 1) ? 'checked' : ''; ?>
                        >
                        I completed this goal today
                    </label>
                </p>

                <p>
                    <label for="notes"><strong>Notes</strong></label><br>
                    <textarea id="notes" name="notes"><?php echo htmlspecialchars($todaysCheckin['notes'] ?? ''); ?></textarea>
                </p>

                <p>
                    <button type="submit">Save Today's Check-In</button>
                </p>
            </form>
        <?php else: ?>
            <?php if (!$isActive): ?>
                <p class="muted">Check-ins are only available while this goal is active.</p>
            <?php else: ?>
                <p class="muted">You have completed your goal for today! <?php echo htmlspecialchars($cadenceUnit); ?>.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent Check-In History</h2>

        <?php if (empty($recentCheckins)): ?>
            <p class="muted">No check-ins yet for this goal.</p>
        <?php else: ?>
            <?php foreach ($recentCheckins as $checkin): ?>
                <div class="history-item">
                    <div class="row">
                        <strong>Date:</strong>
                        <?php echo safeValue($checkin['checkin_date']); ?>
                    </div>

                    <div class="row">
                        <strong>Result:</strong>
                        <?php echo ((int) $checkin['is_complete'] === 1) ? 'Completed' : 'Not completed'; ?>
                    </div>

                    <div class="row">
                        <strong>Notes:</strong><br>
                        <?php echo nl2br(safeValue($checkin['notes'])); ?>
                    </div>

                    <div class="row muted">
                        <strong>Saved:</strong>
                        <?php echo safeValue($checkin['updated_at'] ?? $checkin['created_at']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
