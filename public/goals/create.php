<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$error = null;

$goalTemplates = [
    'meditate' => [
        'title' => 'Meditate for 5 minutes',
        'categories' => ['mind'],
        'cadence_number' => 1,
        'cadence_unit' => 'day',
    ],
    'stretch' => [
        'title' => 'Stretch after training',
        'categories' => ['body'],
        'cadence_number' => 1,
        'cadence_unit' => 'day',
    ],
    'journal' => [
        'title' => 'Journal once a week',
        'categories' => ['soul'],
        'cadence_number' => 1,
        'cadence_unit' => 'week',
    ],
];

$templateKey = strtolower(trim((string) ($_GET['template'] ?? '')));
$template = $goalTemplates[$templateKey] ?? null;

$formValues = [
    'title' => $template['title'] ?? '',
    'categories' => $template['categories'] ?? [],
    'cadence_number' => (string) ($template['cadence_number'] ?? 1),
    'cadence_unit' => (string) ($template['cadence_unit'] ?? 'day'),
    'start_date' => '',
    'end_date' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['title'] = trim((string) ($_POST['title'] ?? ''));
    $formValues['categories'] = is_array($_POST['categories'] ?? null) ? $_POST['categories'] : [];
    $formValues['cadence_number'] = trim((string) ($_POST['cadence_number'] ?? ''));
    $formValues['cadence_unit'] = trim((string) ($_POST['cadence_unit'] ?? ''));
    $formValues['start_date'] = trim((string) ($_POST['start_date'] ?? ''));
    $formValues['end_date'] = trim((string) ($_POST['end_date'] ?? ''));
    $formValues['notes'] = trim((string) ($_POST['notes'] ?? ''));

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your request could not be verified. Please try again.';
    }

    $title = trim((string) $formValues['title']);
    $categoriesInput = $formValues['categories'];
    $cadenceNumber = (int) $formValues['cadence_number'];
    $cadenceUnit = trim((string) $formValues['cadence_unit']);
    $startDate = trim((string) $formValues['start_date']);
    $endDate = trim((string) $formValues['end_date']);
    $notes = trim((string) $formValues['notes']);

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

    $formValues['categories'] = $selectedCategories;

    if ($error === null && $title === '') {
        $error = 'Goal title is required.';
    } elseif ($error === null && empty($selectedCategories)) {
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

$prioritySlots = [
    'daily' => ['used' => 0, 'limit' => 3, 'label' => 'daily'],
    'weekly' => ['used' => 0, 'limit' => 2, 'label' => 'weekly'],
    'monthly' => ['used' => 0, 'limit' => 1, 'label' => 'monthly'],
];

$priorityStmt = $db->prepare("
    SELECT cadence_type, COUNT(*) AS total
    FROM goals
    WHERE user_id = :user_id
      AND status = 'active'
      AND is_priority = 1
      AND cadence_type IN ('daily', 'weekly', 'monthly')
    GROUP BY cadence_type
");
$priorityStmt->execute([
    'user_id' => $userId,
]);

foreach ($priorityStmt->fetchAll() as $slotRow) {
    $cadenceType = strtolower((string) ($slotRow['cadence_type'] ?? ''));
    if (!isset($prioritySlots[$cadenceType])) {
        continue;
    }

    $prioritySlots[$cadenceType]['used'] = (int) ($slotRow['total'] ?? 0);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function createPriorityNote(array $slots, string $cadenceUnit, int $cadenceNumber): string
{
    if ($cadenceNumber !== 1) {
        return 'Custom cadence is not priority-eligible. Use 1 per day, week, or month for priority slots.';
    }

    $unitMap = [
        'day' => 'daily',
        'week' => 'weekly',
        'month' => 'monthly',
    ];

    $cadenceType = $unitMap[$cadenceUnit] ?? 'daily';
    $used = (int) ($slots[$cadenceType]['used'] ?? 0);
    $limit = (int) ($slots[$cadenceType]['limit'] ?? 0);
    $available = max(0, $limit - $used);

    if ($available === 0) {
        return 'All ' . $limit . ' ' . $cadenceType . ' priority slots are in use.';
    }

    return 'You have ' . $available . ' of ' . $limit . ' ' . $cadenceType . ' priority slots available.';
}

$cadenceUnitForNote = in_array((string) $formValues['cadence_unit'], ['day', 'week', 'month'], true)
    ? (string) $formValues['cadence_unit']
    : 'day';
$cadenceNumberForNote = max(1, (int) $formValues['cadence_number']);
$priorityNoteText = createPriorityNote($prioritySlots, $cadenceUnitForNote, $cadenceNumberForNote);

$pageTitle = 'Create Goal';
$pageEyebrow = 'Goals';
$pageHelper = 'Define a goal and set a rhythm.';
$activeNav = 'goals';
$showBackButton = true;
$backHref = BASE_URL . '/goals/index.php';
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-goal-form-page" aria-labelledby="zz-create-goal-form-title">
    <h2 id="zz-create-goal-form-title" class="zz-visually-hidden">Create goal form</h2>

    <?php if ($error !== null): ?>
        <div class="zz-alert zz-alert--danger" role="alert">
            <p><?= h($error) ?></p>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action=""
        class="zz-goal-form"
        data-goal-priority
    >
        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

        <article class="zz-card zz-goal-form__section">
            <h3 class="zz-goal-form__section-title">What's your goal?</h3>

            <div class="zz-field zz-float" data-zz-float>
                <input
                    type="text"
                    id="title"
                    name="title"
                    class="zz-float__control"
                    placeholder=" "
                    required
                    value="<?= h((string) $formValues['title']) ?>"
                >
                <label for="title" class="zz-float__label">Goal title</label>
            </div>

            <div class="zz-field">
                <div class="zz-field__header">
                    <label for="notes" class="zz-label">Notes</label>
                    <span class="zz-optional-tag">Optional</span>
                </div>
                <textarea
                    id="notes"
                    name="notes"
                    class="zz-textarea zz-textarea--journal"
                    rows="4"
                    placeholder="Why this goal matters to you..."
                ><?= h((string) $formValues['notes']) ?></textarea>
            </div>
        </article>

        <article class="zz-card zz-goal-form__section">
            <h3 class="zz-goal-form__section-title">Focus area</h3>

            <div class="zz-category-picker">
                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="body" <?= in_array('body', (array) $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Body</strong>
                        <p class="zz-help">Physical health, movement, recovery, sleep, and nutrition. Goals that support how your body performs and heals.</p>
                    </span>
                </label>

                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="mind" <?= in_array('mind', (array) $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Mind</strong>
                        <p class="zz-help">Focus, mental skills, confidence, preparation, and performance mindset. Goals that sharpen how you think and prepare.</p>
                    </span>
                </label>

                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="soul" <?= in_array('soul', (array) $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Soul</strong>
                        <p class="zz-help">Purpose, relationships, gratitude, and emotional grounding. Goals that feed who you are beyond your activity or performance setting.</p>
                    </span>
                </label>
            </div>
        </article>

        <article class="zz-card zz-goal-form__section">
            <h3 class="zz-goal-form__section-title">How often?</h3>

            <div class="zz-goal-form__cadence-grid">
                <div class="zz-field">
                    <label for="cadence_number" class="zz-label">Cadence number</label>
                    <input
                        type="number"
                        id="cadence_number"
                        name="cadence_number"
                        min="1"
                        class="zz-input"
                        required
                        value="<?= h((string) $formValues['cadence_number']) ?>"
                    >
                </div>

                <div class="zz-field">
                    <label for="cadence_unit" class="zz-label">Cadence unit</label>
                    <select id="cadence_unit" name="cadence_unit" class="zz-select" required>
                        <option value="day" <?= $cadenceUnitForNote === 'day' ? 'selected' : '' ?>>Day</option>
                        <option value="week" <?= $cadenceUnitForNote === 'week' ? 'selected' : '' ?>>Week</option>
                        <option value="month" <?= $cadenceUnitForNote === 'month' ? 'selected' : '' ?>>Month</option>
                    </select>
                </div>
            </div>

            <p class="zz-help">Use 1 per day, week, or month to make this goal priority-eligible. Higher numbers create a custom cadence.</p>
            <p
                class="zz-help zz-goal-priority-note"
                data-goal-priority-note
                data-daily-used="<?= h((string) $prioritySlots['daily']['used']) ?>"
                data-daily-limit="<?= h((string) $prioritySlots['daily']['limit']) ?>"
                data-weekly-used="<?= h((string) $prioritySlots['weekly']['used']) ?>"
                data-weekly-limit="<?= h((string) $prioritySlots['weekly']['limit']) ?>"
                data-monthly-used="<?= h((string) $prioritySlots['monthly']['used']) ?>"
                data-monthly-limit="<?= h((string) $prioritySlots['monthly']['limit']) ?>"
            ><?= h($priorityNoteText) ?></p>
        </article>

        <article class="zz-card zz-goal-form__section">
            <h3 class="zz-goal-form__section-title">When?</h3>

            <div class="zz-goal-form__date-grid">
                <div class="zz-field">
                    <label for="start_date" class="zz-label">Start date</label>
                    <input
                        type="date"
                        id="start_date"
                        name="start_date"
                        class="zz-input"
                        value="<?= h((string) $formValues['start_date']) ?>"
                    >
                </div>

                <div class="zz-field">
                    <label for="end_date" class="zz-label">End date</label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        class="zz-input"
                        value="<?= h((string) $formValues['end_date']) ?>"
                    >
                </div>
            </div>
        </article>

        <div class="zz-goal-form__actions">
            <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg">Create Goal</button>
            <a href="<?= h(BASE_URL . '/goals/index.php') ?>" class="zz-btn zz-btn--ghost zz-btn--lg">Cancel</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
