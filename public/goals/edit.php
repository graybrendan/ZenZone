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
    SELECT id, title, category, cadence_type, status, start_date, end_date, notes
    FROM goals
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'id' => $goalId,
    'user_id' => $_SESSION['user_id'],
]);

$goal = $stmt->fetch();

if (!$goal) {
    die('Goal not found.');
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Goal - ZenZone</title>
</head>
<body>
    <h1>Edit Goal</h1>

    <form method="POST" action="../../api/goals/update.php">
        <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
        <input type="hidden" name="action" value="edit">

        <p>
            <label for="title">Goal Title</label><br>
            <input
                type="text"
                id="title"
                name="title"
                required
                value="<?php echo htmlspecialchars($goal['title']); ?>"
            >
        </p>

        <p>
            <label for="category">Category</label><br>
            <input
                type="text"
                id="category"
                name="category"
                value="<?php echo htmlspecialchars($goal['category'] ?? ''); ?>"
            >
        </p>

        <p>
            <label for="cadence_type">Cadence</label><br>
            <select id="cadence_type" name="cadence_type" required>
                <option value="daily" <?php echo $goal['cadence_type'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                <option value="weekly" <?php echo $goal['cadence_type'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                <option value="monthly" <?php echo $goal['cadence_type'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                <option value="custom" <?php echo $goal['cadence_type'] === 'custom' ? 'selected' : ''; ?>>Custom</option>
            </select>
        </p>

        <p>
            <label for="start_date">Start Date</label><br>
            <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?php echo htmlspecialchars($goal['start_date'] ?? ''); ?>"
            >
        </p>

        <p>
            <label for="end_date">End Date</label><br>
            <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?php echo htmlspecialchars($goal['end_date'] ?? ''); ?>"
            >
        </p>

        <p>
            <label for="notes">Notes</label><br>
            <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($goal['notes'] ?? ''); ?></textarea>
        </p>

        <p>
            <button type="submit">Save Changes</button>
        </p>
    </form>

    <p><a href="details.php?id=<?php echo (int) $goal['id']; ?>">Back to Goal Details</a></p>
    <p><a href="index.php">Back to Goals</a></p>
</body>
</html>