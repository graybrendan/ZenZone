<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();

$goalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($goalId <= 0) {
    redirectWithFlash('goals/index.php', 'Invalid goal selected.');
}

$stmt = $db->prepare("
    SELECT id, title, category, cadence_number, cadence_unit, cadence_type, status, start_date, end_date, notes
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
    redirectWithFlash('goals/index.php', 'Goal not found.');
}
$flash = getFlashMessage();

$selectedCategories = [];
if (!empty($goal['category'])) {
    $selectedCategories = array_filter(array_map('trim', explode(',', (string) $goal['category'])));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Goal - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 760px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input,
        select,
        textarea,
        button {
            width: 100%;
            max-width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .actions a,
        .actions button {
            width: auto;
            text-decoration: none;
            padding: 10px 14px;
            border: 1px solid #999;
            border-radius: 6px;
            background: #f7f7f7;
            color: #000;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Edit Goal</h1>

    <?php if ($flash): ?>
        <p style="padding: 10px; border: 1px solid <?php echo (($flash['type'] ?? '') === 'error') ? '#d6a3a3' : '#9bc29b'; ?>; background: <?php echo (($flash['type'] ?? '') === 'error') ? '#fff0f0' : '#eef9ee'; ?>; border-radius: 6px;">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="../../api/goals/update.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="goal_id" value="<?php echo (int) $goal['id']; ?>">
        <input type="hidden" name="action" value="edit">

        <label for="title">Goal Title</label>
        <input
            type="text"
            id="title"
            name="title"
            required
            value="<?php echo htmlspecialchars($goal['title']); ?>"
        >

        <label>Categories</label>
        <label><input type="checkbox" id="category_body" name="categories[]" value="body" <?php echo in_array('body', $selectedCategories, true) ? 'checked' : ''; ?>> Body</label>
        <label><input type="checkbox" id="category_mind" name="categories[]" value="mind" <?php echo in_array('mind', $selectedCategories, true) ? 'checked' : ''; ?>> Mind</label>
        <label><input type="checkbox" id="category_soul" name="categories[]" value="soul" <?php echo in_array('soul', $selectedCategories, true) ? 'checked' : ''; ?>> Soul</label>

        <label for="cadence_number">Cadence</label>
        <div class="actions">
            <input
                type="number"
                id="cadence_number"
                name="cadence_number"
                min="1"
                value="<?php echo (int) ($goal['cadence_number'] ?? 1); ?>"
                required
            >
            <select id="cadence_unit" name="cadence_unit" required>
                <option value="day" <?php echo (($goal['cadence_unit'] ?? 'day') === 'day') ? 'selected' : ''; ?>>Day</option>
                <option value="week" <?php echo (($goal['cadence_unit'] ?? '') === 'week') ? 'selected' : ''; ?>>Week</option>
                <option value="month" <?php echo (($goal['cadence_unit'] ?? '') === 'month') ? 'selected' : ''; ?>>Month</option>
            </select>
        </div>

        <label for="start_date">Start Date</label>
        <input
            type="date"
            id="start_date"
            name="start_date"
            value="<?php echo htmlspecialchars($goal['start_date'] ?? ''); ?>"
        >

        <label for="end_date">End Date</label>
        <input
            type="date"
            id="end_date"
            name="end_date"
            value="<?php echo htmlspecialchars($goal['end_date'] ?? ''); ?>"
        >

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($goal['notes'] ?? ''); ?></textarea>

        <div class="actions">
            <button type="submit">Save Changes</button>
            <a href="details.php?id=<?php echo (int) $goal['id']; ?>">Back to Goal Details</a>
            <a href="index.php">Back to Goals</a>
        </div>
    </form>
</body>
</html>
