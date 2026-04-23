<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$goalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($goalId <= 0) {
    redirectWithFlash('goals/index.php', 'Invalid goal selected.');
}

$stmt = $db->prepare("
    SELECT
        id,
        title,
        category,
        cadence_number,
        cadence_unit,
        cadence_type,
        status,
        start_date,
        end_date,
        notes
    FROM goals
    WHERE id = :id
      AND user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'id' => $goalId,
    'user_id' => $userId,
]);

$goal = $stmt->fetch();
if (!$goal) {
    redirectWithFlash('goals/index.php', 'Goal not found.');
}

$selectedCategories = [];
if (!empty($goal['category'])) {
    $selectedCategories = array_values(array_unique(array_filter(
        array_map('trim', explode(',', (string) $goal['category'])),
        static function ($category): bool {
            return $category !== '';
        }
    )));
}

$formValues = [
    'title' => (string) ($goal['title'] ?? ''),
    'categories' => $selectedCategories,
    'cadence_number' => (string) max(1, (int) ($goal['cadence_number'] ?? 1)),
    'cadence_unit' => (string) ($goal['cadence_unit'] ?? 'day'),
    'start_date' => (string) ($goal['start_date'] ?? ''),
    'end_date' => (string) ($goal['end_date'] ?? ''),
    'notes' => (string) ($goal['notes'] ?? ''),
];

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

function editPriorityNote(array $slots, string $cadenceUnit, int $cadenceNumber): string
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

$cadenceUnitForNote = in_array($formValues['cadence_unit'], ['day', 'week', 'month'], true)
    ? $formValues['cadence_unit']
    : 'day';
$cadenceNumberForNote = max(1, (int) $formValues['cadence_number']);
$priorityNoteText = editPriorityNote($prioritySlots, $cadenceUnitForNote, $cadenceNumberForNote);

$pageTitle = 'Edit Goal';
$pageEyebrow = 'Goals';
$pageHelper = 'Adjust your goal while keeping the same rhythm.';
$activeNav = 'goals';
$showBackButton = true;
$backHref = BASE_URL . '/goals/details.php?id=' . (int) $goal['id'];
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-goal-form-page" aria-labelledby="zz-edit-goal-form-title">
    <h2 id="zz-edit-goal-form-title" class="zz-visually-hidden">Edit goal form</h2>

    <form
        method="POST"
        action="../../api/goals/update.php"
        class="zz-goal-form"
        data-goal-priority
    >
        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
        <input type="hidden" name="goal_id" value="<?= h((string) ((int) ($goal['id'] ?? 0))) ?>">
        <input type="hidden" name="action" value="edit">

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
                    value="<?= h($formValues['title']) ?>"
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
                ><?= h($formValues['notes']) ?></textarea>
            </div>
        </article>

        <article class="zz-card zz-goal-form__section">
            <h3 class="zz-goal-form__section-title">Focus area</h3>

            <div class="zz-category-picker">
                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="body" <?= in_array('body', $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Body</strong>
                        <p class="zz-help">Physical health, movement, recovery, sleep, and nutrition. Goals that support how your body performs and heals.</p>
                    </span>
                </label>

                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="mind" <?= in_array('mind', $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Mind</strong>
                        <p class="zz-help">Focus, mental skills, confidence, preparation, and competitive mindset. Goals that sharpen how you think and prepare.</p>
                    </span>
                </label>

                <label class="zz-category-option" data-goal-category-option>
                    <span class="zz-checkbox">
                        <input type="checkbox" name="categories[]" value="soul" <?= in_array('soul', $formValues['categories'], true) ? 'checked' : '' ?>>
                        <span class="zz-checkbox__box"></span>
                    </span>
                    <span class="zz-category-option__content">
                        <strong>Soul</strong>
                        <p class="zz-help">Purpose, relationships, gratitude, and emotional grounding. Goals that feed who you are beyond your sport.</p>
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
                        value="<?= h($formValues['cadence_number']) ?>"
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
                        value="<?= h($formValues['start_date']) ?>"
                    >
                </div>

                <div class="zz-field">
                    <label for="end_date" class="zz-label">End date</label>
                    <input
                        type="date"
                        id="end_date"
                        name="end_date"
                        class="zz-input"
                        value="<?= h($formValues['end_date']) ?>"
                    >
                </div>
            </div>
        </article>

        <div class="zz-goal-form__actions">
            <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg">Save Changes</button>
            <a href="<?= h(BASE_URL . '/goals/details.php?id=' . (int) $goal['id']) ?>" class="zz-btn zz-btn--ghost zz-btn--lg">Cancel</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
