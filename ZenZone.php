<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();

$goalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($goalId <= 0) {
    die('Invalid goal ID.');
}

$stmt = $db->prepare("
    SELECT id, title, category, cadence_type, status, is_priority, start_date, end_date, notes, created_at, updated_at
    FROM goals
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'id' => $goalId,
    'user_id' => $_SESSION['user_id']
]);

$goal = $stmt->fetch();

if (!$goal) {
    die('Goal not found.');
}

$priorityCadences = ['daily', 'weekly', 'monthly'];

$canMakePriority =
    $goal['status'] === 'active' &&
    (int) $goal['is_priority'] !== 1 &&
    in_array($goal['cadence_type'], $priorityCadences, true);

$canRemovePriority =
    (int) $goal['is_priority'] === 1 &&
    in_array($goal['cadence_type'], $priorityCadences, true);

$priorityLabel = ((int) $goal['is_priority'] === 1)
    ? 'Priority Goal'
    : 'Non-Priority Goal';

$today = date('Y-m-d');

$checkinStmt = $db->prepare("
    SELECT id, checkin_date, is_complete, notes, created_at
    FROM goal_checkins
    WHERE goal_id = :goal_id
      AND user_id = :user_id
      AND checkin_date = :checkin_date
    LIMIT 1
");
$checkinStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $_SESSION['user_id'],
    'checkin_date' => $today
]);

$todaysCheckin = $checkinStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Details - ZenZone</title>
</head>
<body>
    <h1>Goal Details</h1>

    <p><strong>Title:</strong> <?php echo htmlspecialchars($goal['title']); ?></p>
    <p><strong>Category:</strong> <?php echo htmlspecialchars($goal['category'] ?? 'None'); ?></p>
    <p><strong>Cadence:</strong> <?php echo htmlspecialchars($goal['cadence_type']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($goal['status']); ?></p>
    <p><strong>Priority:</strong> <?php echo htmlspecialchars($priorityLabel); ?></p>
    <p><strong>Start Date:</strong> <?php echo htmlspecialchars($goal['start_date'] ?? 'None'); ?></p>
    <p><strong>End Date:</strong> <?php echo htmlspecialchars($goal['end_date'] ?? 'None'); ?></p>
    <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($goal['notes'] ?? 'None')); ?></p>

    <?php if ($canMakePriority): ?>
        <form method="POST" action="../../api/goals/make_priority.php">
            <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
            <p>
                <button type="submit">Make Priority</button>
            </p>
        </form>
    <?php endif; ?>

    <?php if ($canRemovePriority): ?>
        <form method="POST" action="../../api/goals/remove_priority.php">
            <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
            <p>
                <button type="submit">Remove Priority</button>
            </p>
        </form>
    <?php endif; ?>

    <hr>

    <h2>Today's Check-In</h2>

    <?php if ($todaysCheckin): ?>
        <p><strong>Status:</strong>
            <?php echo (int) $todaysCheckin['is_complete'] === 1 ? 'Completed for today' : 'Not completed today'; ?>
        </p>
        <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($todaysCheckin['notes'] ?? 'None')); ?></p>
    <?php else: ?>
        <p>No check-in submitted yet for today.</p>
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
            <label for="notes">Notes</label><br>
            <textarea id="notes" name="notes" rows="4"><?php echo $todaysCheckin && $todaysCheckin['notes'] !== null ? htmlspecialchars($todaysCheckin['notes']) : ''; ?></textarea>
        </p>

        <p>
            <button type="submit">Save Today's Check-In</button>
        </p>
    </form>

    <hr>

    <p><a href="edit.php?id=<?php echo (int) $goal['id']; ?>">Edit Goal</a></p>
    <p><a href="index.php">Back to Goals</a></p>
    <p><a href="../dashboard.php">Back to Dashboard</a></p>
</body>
</html>