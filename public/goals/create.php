<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $cadenceType = trim($_POST['cadence_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $allowedCategories = ['mindset', 'performance', 'recovery'];
    $allowedCadences = ['daily', 'weekly', 'monthly', 'custom'];

    if ($title === '') {
        $error = 'Goal title is required.';
    } elseif (!in_array($category, $allowedCategories, true)) {
        $error = 'Please choose a valid category.';
    } elseif (!in_array($cadenceType, $allowedCadences, true)) {
        $error = 'Please choose a valid cadence.';
    } elseif ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
        $error = 'End date cannot be before start date.';
    } else {
        $priorityLimits = [
            'daily' => 3,
            'weekly' => 2,
            'monthly' => 1,
        ];

        $isPriority = 0;

        if (isset($priorityLimits[$cadenceType])) {
            $countStmt = $db->prepare("
                SELECT COUNT(*)
                FROM goals
                WHERE user_id = :user_id
                  AND cadence_type = :cadence_type
                  AND status = 'active'
                  AND is_priority = 1
            ");
            $countStmt->execute([
                'user_id' => $userId,
                'cadence_type' => $cadenceType,
            ]);

            $currentPriorityCount = (int) $countStmt->fetchColumn();

            if ($currentPriorityCount < $priorityLimits[$cadenceType]) {
                $isPriority = 1;
            }
        }

        $insertStmt = $db->prepare("
            INSERT INTO goals (
                user_id,
                title,
                category,
                cadence_type,
                status,
                is_priority,
                start_date,
                end_date,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :title,
                :category,
                :cadence_type,
                'active',
                :is_priority,
                :start_date,
                :end_date,
                :notes,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $insertStmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'category' => $category,
            'cadence_type' => $cadenceType,
            'is_priority' => $isPriority,
            'start_date' => $startDate !== '' ? $startDate : null,
            'end_date' => $endDate !== '' ? $endDate : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $newGoalId = (int) $db->lastInsertId();

        header('Location: ' . BASE_URL . '/goals/details.php?id=' . $newGoalId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Goal - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 760px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        form {
            margin-top: 20px;
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

        .error {
            margin-top: 16px;
            padding: 12px;
            border: 1px solid #c33;
            background: #fff4f4;
            color: #900;
            border-radius: 6px;
        }

        .help {
            color: #555;
            margin-top: 6px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <h1>Create Goal</h1>
    <p>Create a new goal</p>

    <?php if ($error !== null): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="title">Goal Title</label>
        <input
            type="text"
            id="title"
            name="title"
            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
            required
        >

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <option value="">Select a category</option>
            <option value="mindset" <?php echo (($_POST['category'] ?? '') === 'mindset') ? 'selected' : ''; ?>>Mindset</option>
            <option value="performance" <?php echo (($_POST['category'] ?? '') === 'performance') ? 'selected' : ''; ?>>Performance</option>
            <option value="recovery" <?php echo (($_POST['category'] ?? '') === 'recovery') ? 'selected' : ''; ?>>Recovery</option>
        </select>

        <label for="cadence_type">Cadence</label>
        <select id="cadence_type" name="cadence_type" required>
            <option value="">Select a cadence</option>
            <option value="daily" <?php echo (($_POST['cadence_type'] ?? '') === 'daily') ? 'selected' : ''; ?>>Daily</option>
            <option value="weekly" <?php echo (($_POST['cadence_type'] ?? '') === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
            <option value="monthly" <?php echo (($_POST['cadence_type'] ?? '') === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
            <option value="custom" <?php echo (($_POST['cadence_type'] ?? '') === 'custom') ? 'selected' : ''; ?>>Custom</option>
        </select>
        <p class="help">Daily, weekly, and monthly goals can become priority goals if a slot is available.</p>

        <label for="start_date">Start Date</label>
        <input
            type="date"
            id="start_date"
            name="start_date"
            value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
        >

        <label for="end_date">End Date</label>
        <input
            type="date"
            id="end_date"
            name="end_date"
            value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
        >

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

        <div class="actions">
            <button type="submit">Create Goal</button>
            <a href="index.php">Cancel</a>
        </div>
    </form>
</body>
</html>