<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $categoriesInput = $_POST['categories'] ?? [];
    $cadenceNumber = isset($_POST['cadence_number']) ? (int) $_POST['cadence_number'] : 0;
    $cadenceUnit = trim($_POST['cadence_unit'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $allowedCategories = ['body', 'mind', 'soul'];
    $allowedCadenceUnits = ['day', 'week', 'month'];
    $selectedCategories = [];

    if (is_array($categoriesInput)) {
        $selectedCategories = array_values(array_unique(array_filter(
            array_map('trim', $categoriesInput),
            static function ($value): bool {
                return $value !== '';
            }
        )));
    }

    if ($title === '') {
        $error = 'Goal title is required.';
    } elseif (empty($selectedCategories)) {
        $error = 'Select at least one category.';
    } elseif (array_diff($selectedCategories, $allowedCategories)) {
        $error = 'Please choose valid categories.';
    } elseif ($cadenceNumber <= 0) {
        $error = 'Cadence number must be at least 1.';
    } elseif (!in_array($cadenceUnit, $allowedCadenceUnits, true)) {
        $error = 'Please choose a valid cadence unit.';
    } elseif ($startDate !== '' && !isValidDateYmd($startDate)) {
        $error = 'Please enter a valid start date.';
    } elseif ($endDate !== '' && !isValidDateYmd($endDate)) {
        $error = 'Please enter a valid end date.';
    } elseif ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
        $error = 'End date cannot be before start date.';
    } else {
        if ($cadenceNumber === 1 && $cadenceUnit === 'day') {
            $cadenceType = 'daily';
        } elseif ($cadenceNumber === 1 && $cadenceUnit === 'week') {
            $cadenceType = 'weekly';
        } elseif ($cadenceNumber === 1 && $cadenceUnit === 'month') {
            $cadenceType = 'monthly';
        } else {
            $cadenceType = 'custom';
        }

        $category = implode(',', $selectedCategories);

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

        try {
            $insertStmt = $db->prepare("
                INSERT INTO goals (
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
                ) VALUES (
                    :user_id,
                    :title,
                    :category,
                    :cadence_number,
                    :cadence_unit,
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
                'cadence_number' => $cadenceNumber,
                'cadence_unit' => $cadenceUnit,
                'cadence_type' => $cadenceType,
                'is_priority' => $isPriority,
                'start_date' => $startDate !== '' ? $startDate : null,
                'end_date' => $endDate !== '' ? $endDate : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $newGoalId = (int) $db->lastInsertId();

            header('Location: ' . BASE_URL . '/goals/details.php?id=' . $newGoalId);
            exit;
        } catch (PDOException $exception) {
            $error = 'Unable to create goal right now. Please try again.';
        }
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

        <label>Categories</label>
        <?php
            $postedCategories = $_POST['categories'] ?? [];
            if (!is_array($postedCategories)) {
                $postedCategories = [];
            }
        ?>
        <label><input type="checkbox" id="category_body" name="categories[]" value="body" <?php echo in_array('body', $postedCategories, true) ? 'checked' : ''; ?>> Body</label>
        <label><input type="checkbox" id="category_mind" name="categories[]" value="mind" <?php echo in_array('mind', $postedCategories, true) ? 'checked' : ''; ?>> Mind</label>
        <label><input type="checkbox" id="category_soul" name="categories[]" value="soul" <?php echo in_array('soul', $postedCategories, true) ? 'checked' : ''; ?>> Soul</label>

        <label for="cadence_number">Cadence</label>
        <div class="actions">
            <input
                type="number"
                id="cadence_number"
                name="cadence_number"
                min="1"
                value="<?php echo htmlspecialchars($_POST['cadence_number'] ?? '1'); ?>"
                required
            >
            <select id="cadence_unit" name="cadence_unit" required>
                <option value="day" <?php echo (($_POST['cadence_unit'] ?? 'day') === 'day') ? 'selected' : ''; ?>>Day</option>
                <option value="week" <?php echo (($_POST['cadence_unit'] ?? '') === 'week') ? 'selected' : ''; ?>>Week</option>
                <option value="month" <?php echo (($_POST['cadence_unit'] ?? '') === 'month') ? 'selected' : ''; ?>>Month</option>
            </select>
        </div>
        <p class="help">Use 1 per day/week/month for priority-eligible cadence. Higher numbers are treated as custom cadence.</p>

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
