<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

$priorityLimits = [
    'daily' => 3,
    'weekly' => 2,
    'monthly' => 1,
];

$prioritySlots = [
    'daily' => ['used' => 0, 'limit' => $priorityLimits['daily'], 'label' => 'Daily'],
    'weekly' => ['used' => 0, 'limit' => $priorityLimits['weekly'], 'label' => 'Weekly'],
    'monthly' => ['used' => 0, 'limit' => $priorityLimits['monthly'], 'label' => 'Monthly'],
];

$priorityCountStmt = $db->prepare("
    SELECT cadence_type, COUNT(*) AS total
    FROM goals
    WHERE user_id = :user_id
      AND status = 'active'
      AND is_priority = 1
      AND cadence_type IN ('daily', 'weekly', 'monthly')
    GROUP BY cadence_type
");
$priorityCountStmt->execute([
    'user_id' => $userId,
]);

foreach ($priorityCountStmt->fetchAll() as $row) {
    $cadenceType = strtolower((string) ($row['cadence_type'] ?? ''));
    if (!isset($prioritySlots[$cadenceType])) {
        continue;
    }

    $prioritySlots[$cadenceType]['used'] = (int) ($row['total'] ?? 0);
}

$goalsStmt = $db->prepare("
    SELECT
        id,
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
        is_priority DESC,
        updated_at DESC,
        created_at DESC
");
$goalsStmt->execute([
    'user_id' => $userId,
]);

$allGoals = $goalsStmt->fetchAll();
$hasAnyGoals = !empty($allGoals);

$activePriorityGoals = [];
$activeStandardGoals = [];
$archivedGoals = [];

foreach ($allGoals as $goal) {
    $status = strtolower((string) ($goal['status'] ?? ''));
    $isPriority = (int) ($goal['is_priority'] ?? 0) === 1;

    if ($status === 'active') {
        if ($isPriority) {
            $activePriorityGoals[] = $goal;
        } else {
            $activeStandardGoals[] = $goal;
        }
        continue;
    }

    if ($status === 'paused' || $status === 'completed') {
        $archivedGoals[] = $goal;
    }
}

$activeGoals = array_merge($activePriorityGoals, $activeStandardGoals);

$pageTitle = 'Goals';
$pageEyebrow = 'Your Modules';
$pageHelper = 'Track what matters today.';
$activeNav = 'goals';
$showBackButton = false;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function goalIndexCategories(string $rawCategory): array
{
    $categories = array_filter(array_map('trim', explode(',', $rawCategory)));
    $categories = array_map('strtolower', $categories);
    $categories = array_values(array_unique($categories));

    return array_map('ucfirst', $categories);
}

function goalIndexCadenceLabel(array $goal): string
{
    $cadenceType = strtolower((string) ($goal['cadence_type'] ?? ''));
    if (in_array($cadenceType, ['daily', 'weekly', 'monthly'], true)) {
        return ucfirst($cadenceType);
    }

    $cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
    $cadenceUnit = strtolower((string) ($goal['cadence_unit'] ?? 'day'));
    if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
        $cadenceUnit = 'day';
    }

    return $cadenceNumber . ' per ' . $cadenceUnit;
}
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-goals-page zz-goals-index" aria-labelledby="zz-goals-index-title">
    <h2 id="zz-goals-index-title" class="zz-visually-hidden">Goals overview</h2>

    <article class="zz-card zz-priority-summary" aria-labelledby="zz-priority-slots-heading">
        <h3 id="zz-priority-slots-heading" class="zz-section-title">Priority Slots</h3>
        <div class="zz-priority-slots" role="list">
            <?php foreach ($prioritySlots as $slot): ?>
                <?php
                $used = (int) $slot['used'];
                $limit = (int) $slot['limit'];
                $isFull = $used >= $limit;
                ?>
                <div class="zz-priority-slot<?= $isFull ? ' zz-priority-slot--full' : '' ?>" role="listitem">
                    <span class="zz-priority-slot__count"><?= h($used . ' / ' . $limit) ?></span>
                    <span class="zz-priority-slot__label"><?= h($slot['label']) ?></span>
                    <span class="zz-priority-slot__track" aria-hidden="true">
                        <?php for ($i = 0; $i < $limit; $i++): ?>
                            <span class="zz-priority-slot__dot<?= $i < $used ? ' is-used' : '' ?>"></span>
                        <?php endfor; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <?php if (!$hasAnyGoals): ?>
        <article class="zz-card zz-empty-state">
            <svg class="zz-empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="8"></circle>
                <circle cx="12" cy="12" r="4"></circle>
                <circle cx="12" cy="12" r="1"></circle>
            </svg>
            <h2>No goals yet</h2>
            <p>Start with one of these, or create your own.</p>

            <div class="zz-goal-templates">
                <a class="zz-goal-template" href="<?= h(BASE_URL . '/goals/create.php?template=meditate') ?>">
                    <span class="zz-badge zz-badge--sage zz-badge--sm">Mind</span>
                    <strong>Meditate for 5 minutes</strong>
                    <span class="zz-muted">Daily</span>
                </a>
                <a class="zz-goal-template" href="<?= h(BASE_URL . '/goals/create.php?template=stretch') ?>">
                    <span class="zz-badge zz-badge--sage zz-badge--sm">Body</span>
                    <strong>Stretch after practice</strong>
                    <span class="zz-muted">Daily</span>
                </a>
                <a class="zz-goal-template" href="<?= h(BASE_URL . '/goals/create.php?template=journal') ?>">
                    <span class="zz-badge zz-badge--sage zz-badge--sm">Soul</span>
                    <strong>Journal once a week</strong>
                    <span class="zz-muted">Weekly</span>
                </a>
            </div>

            <a class="zz-btn zz-btn--primary zz-btn--lg" href="<?= h(BASE_URL . '/goals/create.php') ?>">Create Your Own Goal</a>
        </article>
    <?php else: ?>
        <div class="zz-goals-toolbar">
            <a class="zz-btn zz-btn--primary" href="<?= h(BASE_URL . '/goals/create.php') ?>">Create Goal</a>
        </div>

        <section aria-labelledby="zz-active-goals-heading">
            <h2 id="zz-active-goals-heading" class="zz-section-title">Active Goals</h2>

            <?php if (empty($activeGoals)): ?>
                <p class="zz-muted">No active goals right now. Resume a paused goal or create a new one.</p>
            <?php else: ?>
                <div class="zz-goal-list">
                    <?php foreach ($activeGoals as $goal): ?>
                        <?php
                        $goalId = (int) ($goal['id'] ?? 0);
                        $categoryLabel = implode(', ', goalIndexCategories((string) ($goal['category'] ?? '')));
                        ?>
                        <article class="zz-card zz-goal-card" aria-labelledby="zz-goal-title-<?= h((string) $goalId) ?>">
                            <div class="zz-goal-card__header">
                                <h3 id="zz-goal-title-<?= h((string) $goalId) ?>" class="zz-goal-card__title"><?= h((string) ($goal['title'] ?? 'Goal')) ?></h3>
                                <div class="zz-goal-card__badges">
                                    <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h(goalIndexCadenceLabel($goal)) ?></span>
                                    <?php if ((int) ($goal['is_priority'] ?? 0) === 1): ?>
                                        <span class="zz-badge zz-badge--gold zz-badge--sm">Priority</span>
                                    <?php endif; ?>
                                    <?php if ($categoryLabel !== ''): ?>
                                        <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h($categoryLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="zz-goal-card__dates zz-date-value"><?= h(zz_format_date_range($goal['start_date'] ?? null, $goal['end_date'] ?? null)) ?></p>

                            <div class="zz-goal-card__actions">
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/goals/details.php?id=' . $goalId) ?>">View</a>
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/goals/edit.php?id=' . $goalId) ?>">Edit</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($archivedGoals)): ?>
            <details class="zz-goal-archive">
                <summary class="zz-section-title">Completed &amp; Paused Goals (<?= h((string) count($archivedGoals)) ?>)</summary>

                <div class="zz-goal-list">
                    <?php foreach ($archivedGoals as $goal): ?>
                        <?php
                        $goalId = (int) ($goal['id'] ?? 0);
                        $status = strtolower((string) ($goal['status'] ?? ''));
                        $categoryLabel = implode(', ', goalIndexCategories((string) ($goal['category'] ?? '')));
                        $statusLabel = $status === 'paused' ? 'Paused' : 'Completed';
                        $statusBadgeClass = $status === 'paused' ? 'zz-badge--gold' : 'zz-badge--neutral';
                        ?>
                        <article class="zz-card zz-goal-card zz-goal-card--archived" aria-labelledby="zz-archive-goal-title-<?= h((string) $goalId) ?>">
                            <div class="zz-goal-card__header">
                                <h3 id="zz-archive-goal-title-<?= h((string) $goalId) ?>" class="zz-goal-card__title"><?= h((string) ($goal['title'] ?? 'Goal')) ?></h3>
                                <div class="zz-goal-card__badges">
                                    <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h(goalIndexCadenceLabel($goal)) ?></span>
                                    <span class="zz-badge <?= h($statusBadgeClass) ?> zz-badge--sm"><?= h($statusLabel) ?></span>
                                    <?php if ($categoryLabel !== ''): ?>
                                        <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h($categoryLabel) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="zz-goal-card__dates zz-date-value"><?= h(zz_format_date_range($goal['start_date'] ?? null, $goal['end_date'] ?? null)) ?></p>

                            <div class="zz-goal-card__actions">
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/goals/details.php?id=' . $goalId) ?>">View</a>
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/goals/edit.php?id=' . $goalId) ?>">Edit</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
