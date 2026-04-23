<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

requireLogin();

const COACH_HOME_PREVIEW_LIMIT = 5;

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$coachStorageReady = isCoachStorageReady($db);

$formData = [
    'situation_text' => '',
    'situation_type' => 'other',
    'time_available' => '3',
    'stress_level' => '3',
    'upcoming_event' => '',
];

$oldInputForm = (string) getOldInput('coach_form', '');
if ($oldInputForm === 'new_situation') {
    $formData['situation_text'] = (string) getOldInput('situation_text', '');
    $formData['situation_type'] = (string) getOldInput('situation_type', 'other');
    $formData['time_available'] = (string) getOldInput('time_available', '3');
    $formData['stress_level'] = (string) getOldInput('stress_level', '3');
    $formData['upcoming_event'] = (string) getOldInput('upcoming_event', '');
    clearOldInput();
}

if (!in_array($formData['situation_type'], getCoachSituationTypes(), true)) {
    $formData['situation_type'] = 'other';
}
if (!in_array((int) $formData['time_available'], getCoachTimeOptions(), true)) {
    $formData['time_available'] = '3';
}
if (!isValidScaleRating((int) $formData['stress_level'], 1, 5)) {
    $formData['stress_level'] = '3';
}

$recentSituations = [];
$totalSituations = 0;

if ($coachStorageReady) {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM coach_threads
        WHERE user_id = :user_id
          AND archived = 0
    ");
    $countStmt->execute(['user_id' => $userId]);
    $totalSituations = (int) $countStmt->fetchColumn();

    $listStmt = $db->prepare("
        SELECT
            id,
            COALESCE(NULLIF(summary, ''), thread_title) AS summary,
            created_at,
            updated_at,
            last_message_at
        FROM coach_threads
        WHERE user_id = :user_id
          AND archived = 0
        ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC
        LIMIT " . COACH_HOME_PREVIEW_LIMIT . "
    ");
    $listStmt->execute(['user_id' => $userId]);
    $recentSituations = $listStmt->fetchAll();
}

$pageTitle = 'Coach';
$pageEyebrow = 'Mental Performance';
$pageHelper = 'Describe the moment and get one clear next action.';
$activeNav = 'coach';
$showBackButton = false;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function coachTypeLabel(string $type): string
{
    $normalized = strtolower(trim($type));
    $normalized = str_replace(['_', '-', '/'], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = is_string($normalized) ? trim($normalized) : '';

    if ($normalized === '') {
        return 'Other';
    }

    return ucwords($normalized);
}
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-coach-page zz-coach-home" aria-labelledby="zz-coach-home-title">
    <h2 id="zz-coach-home-title" class="zz-visually-hidden">Coach home</h2>

    <?php if (!$coachStorageReady): ?>
        <article class="zz-card zz-alert zz-alert--warning zz-coach-setup" role="alert">
            <h3 class="zz-coach-card-title">Coach Setup Required</h3>
            <p>The Coach database tables are missing required columns.</p>
            <p>
                Run
                <code>sql/migrations/2026-04-15_001_coach_tables.sql</code>
                and
                <code>sql/migrations/2026-04-15_002_coach_thread_situation_fields.sql</code>
                in phpMyAdmin.
            </p>
        </article>
    <?php else: ?>
        <article class="zz-card zz-coach-start" aria-labelledby="zz-coach-start-title">
            <div class="zz-coach-card-head">
                <h3 id="zz-coach-start-title" class="zz-coach-card-title">Start New Situation</h3>
                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php') ?>">View History</a>
            </div>
            <p class="zz-help">Describe what is happening, add quick context, and get one clear recommendation.</p>

            <form method="POST" action="../../api/coach/submit.php" class="zz-coach-form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

                <div class="zz-field">
                    <label for="situation_text" class="zz-label">What happened?</label>
                    <textarea
                        id="situation_text"
                        name="situation_text"
                        class="zz-textarea zz-textarea--journal"
                        rows="5"
                        maxlength="1200"
                        required
                    ><?= h($formData['situation_text']) ?></textarea>
                    <p class="zz-help">Share enough detail so the recommendation fits your moment.</p>
                </div>

                <div class="zz-coach-form__grid">
                    <div class="zz-field">
                        <label for="situation_type" class="zz-label">Situation type</label>
                        <select id="situation_type" name="situation_type" class="zz-select" required>
                            <?php foreach (getCoachSituationTypes() as $type): ?>
                                <option value="<?= h($type) ?>" <?= $formData['situation_type'] === $type ? 'selected' : '' ?>>
                                    <?= h(coachTypeLabel((string) $type)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="zz-field">
                        <label for="time_available" class="zz-label">Time available</label>
                        <select id="time_available" name="time_available" class="zz-select" required>
                            <?php foreach (getCoachTimeOptions() as $minutes): ?>
                                <option value="<?= (int) $minutes ?>" <?= ((int) $formData['time_available'] === (int) $minutes) ? 'selected' : '' ?>>
                                    <?= (int) $minutes ?> min
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="zz-field">
                        <label for="stress_level" class="zz-label">What emotions did you experience?</label>
                        <select id="stress_level" name="stress_level" class="zz-select" required>
                            <option value="1" <?= ((int) $formData['stress_level'] === 1) ? 'selected' : '' ?>>Calm / Grounded</option>
                            <option value="2" <?= ((int) $formData['stress_level'] === 2) ? 'selected' : '' ?>>Slightly tense</option>
                            <option value="3" <?= ((int) $formData['stress_level'] === 3) ? 'selected' : '' ?>>Frustrated / Distracted</option>
                            <option value="4" <?= ((int) $formData['stress_level'] === 4) ? 'selected' : '' ?>>Anxious / Overwhelmed</option>
                            <option value="5" <?= ((int) $formData['stress_level'] === 5) ? 'selected' : '' ?>>Panicked / Angry</option>
                        </select>
                        <p class="zz-help">Naming your emotion in the moment is the first step to awareness. Choose the closest fit.</p>
                    </div>
                </div>

                <div class="zz-field">
                    <div class="zz-field__header">
                        <label for="upcoming_event" class="zz-label">Upcoming event</label>
                        <span class="zz-optional-tag">Optional</span>
                    </div>
                    <input
                        id="upcoming_event"
                        name="upcoming_event"
                        type="text"
                        maxlength="120"
                        class="zz-input"
                        value="<?= h($formData['upcoming_event']) ?>"
                    >
                </div>

                <div class="zz-coach-form__actions">
                    <button type="submit" class="zz-btn zz-btn--primary">Get Coach Recommendation</button>
                </div>
            </form>
        </article>

        <article class="zz-card zz-coach-recent" aria-labelledby="zz-coach-recent-title">
            <div class="zz-coach-card-head">
                <h3 id="zz-coach-recent-title" class="zz-coach-card-title">Recent Coach Situations</h3>
                <?php if ($totalSituations > COACH_HOME_PREVIEW_LIMIT): ?>
                    <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php') ?>">View All</a>
                <?php endif; ?>
            </div>

            <?php if (empty($recentSituations)): ?>
                <div class="zz-coach-empty">
                    <svg class="zz-coach-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H10l-5 4V7a2 2 0 0 1 2-2z"></path>
                        <path d="M9 10h6"></path>
                        <path d="M9 13h4"></path>
                    </svg>
                    <h4>No coach situations yet</h4>
                    <p>Start one above to get your first recommendation.</p>
                </div>
            <?php else: ?>
                <div class="zz-coach-list">
                    <?php foreach ($recentSituations as $row): ?>
                        <?php
                        $threadId = (int) ($row['id'] ?? 0);
                        $summary = createCoachSituationSummary((string) ($row['summary'] ?? ''), 170);
                        $createdAt = (string) ($row['created_at'] ?? '');
                        $updatedAt = (string) ($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '');
                        ?>
                        <article class="zz-coach-item" aria-labelledby="zz-coach-thread-<?= h((string) $threadId) ?>">
                            <h4 id="zz-coach-thread-<?= h((string) $threadId) ?>" class="zz-coach-item__title"><?= h($summary) ?></h4>
                            <p class="zz-coach-item__meta">
                                Created <?= h(zz_format_datetime($createdAt !== '' ? $createdAt : null)) ?>
                                <span aria-hidden="true">&middot;</span>
                                Updated <?= h(zz_format_datetime($updatedAt !== '' ? $updatedAt : null)) ?>
                            </p>

                            <div class="zz-coach-item__actions">
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/view.php?id=' . $threadId) ?>">View</a>
                                <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/edit.php?id=' . $threadId) ?>">Edit</a>
                                <form method="POST" action="../../api/coach/delete.php" class="zz-inline-form" data-coach-delete-form data-confirm-message="Delete this coach situation? This cannot be undone.">
                                    <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
                                    <input type="hidden" name="thread_id" value="<?= h((string) $threadId) ?>">
                                    <button type="submit" class="zz-btn zz-btn--danger zz-btn--sm">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
