<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$goalId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$today = date('Y-m-d');

if ($goalId <= 0) {
    redirectWithFlash('goals/index.php', 'Invalid goal selected.');
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
    'user_id' => $userId,
]);

$goal = $goalStmt->fetch();
if (!$goal) {
    redirectWithFlash('goals/index.php', 'Goal not found.');
}

function goalDetailsCadenceWindow(string $unit, DateTimeImmutable $date): array
{
    if ($unit === 'week') {
        $start = $date->modify('monday this week');
        $end = $start->modify('+6 days');
    } elseif ($unit === 'month') {
        $start = $date->modify('first day of this month');
        $end = $date->modify('last day of this month');
    } else {
        $start = $date;
        $end = $date;
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

$cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
$cadenceUnit = strtolower((string) ($goal['cadence_unit'] ?? 'day'));
if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
    $cadenceUnit = 'day';
}

[$windowStart, $windowEnd] = goalDetailsCadenceWindow($cadenceUnit, new DateTimeImmutable($today));

$periodCountStmt = $db->prepare("
    SELECT COUNT(*)
    FROM goal_checkins
    WHERE goal_id = :goal_id
      AND user_id = :user_id
      AND checkin_date BETWEEN :window_start AND :window_end
");
$periodCountStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $userId,
    'window_start' => $windowStart,
    'window_end' => $windowEnd,
]);
$checkinsThisWindow = (int) $periodCountStmt->fetchColumn();

$todaysCheckinStmt = $db->prepare("
    SELECT
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
    'checkin_date' => $today,
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
    LIMIT 12
");
$historyStmt->execute([
    'goal_id' => $goalId,
    'user_id' => $userId,
]);
$recentCheckins = $historyStmt->fetchAll();

$priorityLimits = [
    'daily' => 3,
    'weekly' => 2,
    'monthly' => 1,
];
$priorityCadenceType = strtolower((string) ($goal['cadence_type'] ?? ''));
$isPriorityEligible = isset($priorityLimits[$priorityCadenceType]);
$prioritySlotLimit = $isPriorityEligible ? $priorityLimits[$priorityCadenceType] : 0;
$prioritySlotsUsed = 0;

if ($isPriorityEligible) {
    $priorityCountStmt = $db->prepare("
        SELECT COUNT(*)
        FROM goals
        WHERE user_id = :user_id
          AND cadence_type = :cadence_type
          AND status = 'active'
          AND is_priority = 1
    ");
    $priorityCountStmt->execute([
        'user_id' => $userId,
        'cadence_type' => $priorityCadenceType,
    ]);
    $prioritySlotsUsed = (int) $priorityCountStmt->fetchColumn();
}

$isActive = strtolower((string) ($goal['status'] ?? '')) === 'active';
$isPaused = strtolower((string) ($goal['status'] ?? '')) === 'paused';
$isCompleted = strtolower((string) ($goal['status'] ?? '')) === 'completed';
$isPriority = (int) ($goal['is_priority'] ?? 0) === 1;
$remainingCheckins = max(0, $cadenceNumber - $checkinsThisWindow);
$showCheckinForm = $isActive && $remainingCheckins > 0;
$prioritySlotsFull = $isPriorityEligible && !$isPriority && $prioritySlotsUsed >= $prioritySlotLimit;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function goalDetailsCategories(string $rawCategory): array
{
    $categories = array_filter(array_map('trim', explode(',', $rawCategory)));
    $categories = array_map('strtolower', $categories);
    $categories = array_values(array_unique($categories));
    return array_map('ucfirst', $categories);
}

function goalDetailsCadenceLabel(int $cadenceNumber, string $cadenceUnit): string
{
    return $cadenceNumber . 'x per ' . $cadenceUnit;
}

function goalDetailsStatusBadge(string $status): array
{
    if ($status === 'paused') {
        return ['label' => 'Paused', 'class' => 'zz-badge--gold'];
    }

    if ($status === 'completed') {
        return ['label' => 'Completed', 'class' => 'zz-badge--neutral'];
    }

    return ['label' => 'Active', 'class' => 'zz-badge--sage'];
}

$status = strtolower((string) ($goal['status'] ?? 'active'));
$statusBadge = goalDetailsStatusBadge($status);
$categoryItems = goalDetailsCategories((string) ($goal['category'] ?? ''));
$periodLabel = $cadenceUnit === 'week' ? 'week' : ($cadenceUnit === 'month' ? 'month' : 'day');

$priorityToggleLabel = $isPriority ? 'Remove Priority' : 'Make Priority';
$priorityActionPath = $isPriority
    ? BASE_URL . '/api/goals/remove_priority.php'
    : BASE_URL . '/api/goals/make_priority.php';

if ($isPriorityEligible && $isPriority) {
    $priorityContext = 'This goal uses 1 of your ' . $prioritySlotLimit . ' ' . $priorityCadenceType . ' priority slots.';
} elseif ($isPriorityEligible) {
    $priorityContext = 'You are using ' . $prioritySlotsUsed . ' of ' . $prioritySlotLimit . ' ' . $priorityCadenceType . ' priority slots.';
} else {
    $priorityContext = '';
}

$pageTitle = (string) ($goal['title'] ?? 'Goal');
$pageEyebrow = 'Goals';
$pageHelper = null;
$activeNav = 'goals';
$showBackButton = true;
$backHref = BASE_URL . '/goals/index.php';
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-goal-details-page" aria-labelledby="zz-goal-summary-title">
    <article class="zz-card zz-goal-summary" aria-labelledby="zz-goal-summary-title">
        <h2 id="zz-goal-summary-title" class="zz-visually-hidden">Goal status and metadata</h2>
        <div class="zz-goal-summary__badges">
            <span class="zz-badge <?= h($statusBadge['class']) ?> zz-badge--sm"><?= h($statusBadge['label']) ?></span>
            <?php if ($isPriority): ?>
                <span class="zz-badge zz-badge--gold zz-badge--sm">Priority</span>
            <?php endif; ?>
            <?php foreach ($categoryItems as $categoryItem): ?>
                <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h($categoryItem) ?></span>
            <?php endforeach; ?>
        </div>

        <p class="zz-goal-summary__date-range zz-date-value"><?= h(zz_format_date_range($goal['start_date'] ?? null, $goal['end_date'] ?? null)) ?></p>
        <p class="zz-goal-summary__cadence zz-muted">Cadence: <?= h(goalDetailsCadenceLabel($cadenceNumber, $cadenceUnit)) ?></p>
    </article>

    <article class="zz-card zz-checkin-card" aria-labelledby="zz-today-checkin-title">
        <h2 id="zz-today-checkin-title">Today's Check-In</h2>
        <p class="zz-help"><?= h($checkinsThisWindow . ' of ' . $cadenceNumber . ' check-ins used this ' . $periodLabel . '.') ?></p>

        <?php if ($showCheckinForm): ?>
            <form method="POST" action="../../api/goals/checkin.php" class="zz-checkin-card__form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">

                <label class="zz-checkbox zz-checkbox--lg">
                    <input
                        type="checkbox"
                        name="is_complete"
                        value="1"
                        <?= ($todaysCheckin && (int) ($todaysCheckin['is_complete'] ?? 0) === 1) ? 'checked' : '' ?>
                    >
                    <span class="zz-checkbox__box"></span>
                    <span class="zz-checkbox__label">I completed this goal today</span>
                </label>

                <div class="zz-field">
                    <div class="zz-field__header">
                        <label for="notes" class="zz-label">Notes</label>
                        <span class="zz-optional-tag">Optional</span>
                    </div>
                    <textarea
                        id="notes"
                        name="notes"
                        class="zz-textarea zz-textarea--journal"
                        rows="3"
                        placeholder="How did it go?"
                    ><?= h((string) ($todaysCheckin['notes'] ?? '')) ?></textarea>
                </div>

                <button type="submit" class="zz-btn zz-btn--primary zz-btn--block">Save Today's Check-In</button>
            </form>
        <?php else: ?>
            <?php if (!$isActive): ?>
                <p class="zz-muted">Check-ins are available only while this goal is active.</p>
            <?php else: ?>
                <p class="zz-muted">You've completed your check-ins for this <?= h($periodLabel) ?>. Nice work.</p>
            <?php endif; ?>
        <?php endif; ?>
    </article>

    <div class="zz-goal-actions" aria-label="Goal actions">
        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/goals/edit.php?id=' . $goalId) ?>">Edit</a>

        <?php if ($isActive): ?>
            <form method="POST" action="../../api/goals/update.php" class="zz-inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">
                <input type="hidden" name="action" value="pause">
                <button type="submit" class="zz-btn zz-btn--accent zz-btn--sm">Pause</button>
            </form>
        <?php elseif ($isPaused): ?>
            <form method="POST" action="../../api/goals/update.php" class="zz-inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">
                <input type="hidden" name="action" value="resume">
                <button type="submit" class="zz-btn zz-btn--primary zz-btn--sm">Resume</button>
            </form>
        <?php endif; ?>

        <?php if (!$isCompleted): ?>
            <form method="POST" action="../../api/goals/update.php" class="zz-inline-form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="zz-btn zz-btn--success zz-btn--sm">Complete</button>
            </form>
        <?php endif; ?>

        <?php if ($isPriorityEligible): ?>
            <?php if ($isPriority || $isActive): ?>
                <form method="POST" action="<?= h($priorityActionPath) ?>" class="zz-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                    <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">
                    <button
                        type="submit"
                        class="zz-btn zz-btn--ghost zz-btn--sm"
                        <?= (!$isPriority && $prioritySlotsFull) ? 'disabled aria-disabled="true"' : '' ?>
                    ><?= h($priorityToggleLabel) ?></button>
                </form>
            <?php else: ?>
                <button type="button" class="zz-btn zz-btn--ghost zz-btn--sm" disabled aria-disabled="true">Make Priority</button>
            <?php endif; ?>
        <?php else: ?>
            <button type="button" class="zz-btn zz-btn--ghost zz-btn--sm" disabled aria-disabled="true">Priority Unavailable</button>
        <?php endif; ?>

        <form method="POST" action="../../api/goals/delete.php" class="zz-inline-form" data-goal-delete-form data-confirm-message="Delete this goal? This cannot be undone.">
            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
            <input type="hidden" name="goal_id" value="<?= h((string) $goalId) ?>">
            <button type="submit" class="zz-btn zz-btn--danger zz-btn--sm">Delete</button>
        </form>
    </div>

    <?php if ($isPriorityEligible): ?>
        <p class="zz-help zz-goal-priority-context"><?= h($priorityContext) ?></p>
    <?php endif; ?>

    <?php if ($isPriorityEligible && !$isPriority && $prioritySlotsFull): ?>
        <p class="zz-help zz-goal-priority-context">All <?= h((string) $prioritySlotLimit) ?> <?= h($priorityCadenceType) ?> priority slots are in use.</p>
    <?php endif; ?>

    <details class="zz-card zz-checkin-history" open>
        <summary class="zz-section-title">Recent Check-Ins</summary>

        <?php if (empty($recentCheckins)): ?>
            <p class="zz-muted">No check-ins yet for this goal.</p>
        <?php else: ?>
            <ul class="zz-checkin-list">
                <?php foreach ($recentCheckins as $checkin): ?>
                    <?php
                    $completed = (int) ($checkin['is_complete'] ?? 0) === 1;
                    $notes = trim((string) ($checkin['notes'] ?? ''));
                    ?>
                    <li class="zz-checkin-item">
                        <span class="zz-checkin-item__status <?= $completed ? 'is-complete' : 'is-incomplete' ?>" aria-hidden="true">
                            <?php if ($completed): ?>
                                <svg class="zz-checkin-item__icon">
                                    <use xlink:href="#icon-check"></use>
                                </svg>
                            <?php else: ?>
                                <span class="zz-checkin-item__dash">-</span>
                            <?php endif; ?>
                        </span>
                        <span class="zz-checkin-item__date"><?= h(zz_format_date($checkin['checkin_date'] ?? null, 'smart')) ?></span>
                        <span class="zz-checkin-item__notes">
                            <?= $notes !== '' ? nl2br(h($notes)) : '<span class="zz-muted">No notes.</span>' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </details>

    <details class="zz-card zz-goal-meta">
        <summary class="zz-section-title">Goal Details</summary>
        <dl class="zz-detail-list">
            <dt>Created</dt>
            <dd class="zz-date-time"><?= h(zz_format_datetime($goal['created_at'] ?? null)) ?></dd>
            <dt>Last updated</dt>
            <dd class="zz-date-time"><?= h(zz_format_datetime($goal['updated_at'] ?? null)) ?></dd>
            <dt>Notes</dt>
            <dd><?= trim((string) ($goal['notes'] ?? '')) !== '' ? nl2br(h((string) $goal['notes'])) : '<span class="zz-muted">No notes.</span>' ?></dd>
        </dl>
    </details>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
