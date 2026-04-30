<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/date_helpers.php';
require_once __DIR__ . '/../../includes/coach_view_helpers.php';

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
];

$oldInputForm = (string) getOldInput('coach_form', '');
if ($oldInputForm === 'new_situation') {
    $formData['situation_text'] = (string) getOldInput('situation_text', '');
    $formData['situation_type'] = (string) getOldInput('situation_type', 'other');
    $formData['time_available'] = (string) getOldInput('time_available', '3');
    $formData['stress_level'] = (string) getOldInput('stress_level', '3');
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
$pageEyebrow = 'Performance Support';
$pageHelper = 'Describe the moment and get one clear next action.';
$activeNav = 'coach';
$showBackButton = false;
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
        <article class="zz-card zz-intro-card zz-coach-intro-card">
            <p class="zz-section-title zz-intro-card__eyebrow">How the Coach Works</p>
            <h2>Describe a moment. Get one clear action.</h2>
            <p>A <strong>situation</strong> is anything on your mind right now - pre-event nerves, a rough session, losing focus, a confidence dip, or just needing a reset. Describe what's happening, and the Coach will give you one grounded next step you can use in minutes.</p>
            <div class="zz-intro-card__badges">
                <span class="zz-badge zz-badge--sage zz-badge--sm">Takes 30 seconds</span>
                <span class="zz-badge zz-badge--sage zz-badge--sm">Private to you</span>
                <span class="zz-badge zz-badge--sage zz-badge--sm">One clear action</span>
            </div>
        </article>

        <article class="zz-card zz-coach-start" aria-labelledby="zz-coach-start-title">
            <div class="zz-coach-card-head">
                <h3 id="zz-coach-start-title" class="zz-coach-card-title">Start New Situation</h3>
                <a class="zz-btn zz-btn--accent zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php') ?>">View History</a>
            </div>
            <p class="zz-help">Describe what is happening, add quick context, and get one clear recommendation.</p>

            <form method="POST" action="../../api/coach/submit.php" class="zz-coach-form">
                <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

                <div class="zz-field">
                    <label for="situation_text" class="zz-label">What's happening right now?</label>
                    <p class="zz-help">A sentence or two is enough. The Coach works with whatever you give it.</p>
                    <div class="zz-chip-group zz-chips" data-chip-target="#situation_text">
                        <button type="button" class="zz-chip" data-value="Before a big event" aria-pressed="false">Before a big event</button>
                        <button type="button" class="zz-chip" data-value="After a mistake" aria-pressed="false">After a mistake</button>
                        <button type="button" class="zz-chip" data-value="Feeling unmotivated" aria-pressed="false">Feeling unmotivated</button>
                        <button type="button" class="zz-chip" data-value="Intense pressure" aria-pressed="false">Intense pressure</button>
                        <button type="button" class="zz-chip" data-value="Losing confidence" aria-pressed="false">Losing confidence</button>
                        <button type="button" class="zz-chip" data-value="Team conflict" aria-pressed="false">Team conflict</button>
                    </div>
                    <textarea
                        id="situation_text"
                        name="situation_text"
                        class="zz-textarea zz-textarea--journal"
                        rows="4"
                        placeholder="I have a big event tomorrow and I can't stop overthinking my last session..."
                        minlength="8"
                        maxlength="1200"
                        required
                    ><?= h($formData['situation_text']) ?></textarea>
                </div>

                <div class="zz-field">
                    <p class="zz-label">What kind of moment is this?</p>
                    <p class="zz-help">Pick the closest match - this helps the Coach focus its recommendation.</p>
                    <div class="zz-card-radio-group">
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="pre-performance nerves" <?= $formData['situation_type'] === 'pre-performance nerves' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Pre-performance nerves</strong>
                                <span class="zz-help">Anxiety before an event, performance, workout, or big moment</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="after mistake" <?= $formData['situation_type'] === 'after mistake' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>After a mistake</strong>
                                <span class="zz-help">Replaying an error, struggling to move on</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="low focus" <?= $formData['situation_type'] === 'low focus' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Low focus</strong>
                                <span class="zz-help">Distracted, scattered, can't lock in</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="frustration / anger" <?= $formData['situation_type'] === 'frustration / anger' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Frustration / anger</strong>
                                <span class="zz-help">Heated, reactive, need to cool down</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="confidence dip" <?= $formData['situation_type'] === 'confidence dip' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Confidence dip</strong>
                                <span class="zz-help">Doubting yourself or your ability</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="post-practice reset" <?= $formData['situation_type'] === 'post-practice reset' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Post-session reset</strong>
                                <span class="zz-help">Winding down after training, practice, work, or competition</span>
                            </span>
                        </label>
                        <label class="zz-card-radio">
                            <input type="radio" name="situation_type" value="other" <?= $formData['situation_type'] === 'other' ? 'checked' : '' ?>>
                            <span class="zz-card-radio__body">
                                <strong>Other</strong>
                                <span class="zz-help">Something else that doesn't fit above</span>
                            </span>
                        </label>
                    </div>
                </div>

                <fieldset class="zz-scale" data-scale-name="stress_level" data-scale-min="1" data-scale-max="5">
                    <legend class="zz-label zz-scale__legend">Emotion intensity</legend>
                    <p class="zz-help zz-scale__description">How strong is what you're feeling right now? Not good or bad - just intensity.</p>
                    <div class="zz-scale__track" role="radiogroup" aria-label="Emotion intensity 1 to 5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php $isSelected = ((int) $formData['stress_level'] === $i); ?>
                            <label class="zz-scale__pill<?= $isSelected ? ' is-selected' : '' ?>">
                                <input type="radio" name="stress_level" value="<?= h((string) $i) ?>" <?= $isSelected ? 'checked' : '' ?>>
                                <span class="zz-scale__num"><?= h((string) $i) ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <div class="zz-scale__endpoints">
                        <span class="zz-scale__endpoint-word">Calm</span>
                        <span class="zz-scale__endpoint-word">Overwhelmed</span>
                    </div>
                </fieldset>

                <div class="zz-field">
                    <p class="zz-label">How much time do you have?</p>
                    <div class="zz-time-pills">
                        <label class="zz-time-pill">
                            <input type="radio" name="time_available" value="1" <?= (int) $formData['time_available'] === 1 ? 'checked' : '' ?>>
                            <span>1 min</span>
                        </label>
                        <label class="zz-time-pill">
                            <input type="radio" name="time_available" value="3" <?= (int) $formData['time_available'] === 3 ? 'checked' : '' ?>>
                            <span>3 min</span>
                        </label>
                        <label class="zz-time-pill">
                            <input type="radio" name="time_available" value="5" <?= (int) $formData['time_available'] === 5 ? 'checked' : '' ?>>
                            <span>5 min</span>
                        </label>
                        <label class="zz-time-pill">
                            <input type="radio" name="time_available" value="10" <?= (int) $formData['time_available'] === 10 ? 'checked' : '' ?>>
                            <span>10 min</span>
                        </label>
                    </div>
                </div>

                <div class="zz-coach-form__actions">
                    <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg zz-btn--block">Get My Recommendation</button>
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
