<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/date_helpers.php';
require_once __DIR__ . '/../../includes/coach_view_helpers.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if (!isCoachStorageReady($db)) {
    setFlashMessage('error', 'Coach setup is incomplete. Run the latest Coach migrations first.');
    authRedirect('coach/index.php');
}

$threadId = (int) ($_GET['id'] ?? 0);
if ($threadId <= 0) {
    setFlashMessage('error', 'Invalid coach situation selected.');
    authRedirect('coach/index.php');
}

$threadStmt = $db->prepare("
    SELECT
        id,
        summary,
        situation_text,
        situation_type,
        time_available,
        stress_level,
        upcoming_event,
        updated_at
    FROM coach_threads
    WHERE id = :thread_id
      AND user_id = :user_id
      AND archived = 0
    LIMIT 1
");
$threadStmt->execute([
    'thread_id' => $threadId,
    'user_id' => $userId,
]);
$thread = $threadStmt->fetch();

if (!$thread) {
    setFlashMessage('error', 'Coach situation not found.');
    authRedirect('coach/index.php');
}

$formData = [
    'situation_text' => (string) ($thread['situation_text'] ?? ''),
    'situation_type' => (string) ($thread['situation_type'] ?? 'other'),
    'time_available' => (string) ((int) ($thread['time_available'] ?? 3)),
    'stress_level' => (string) ((int) ($thread['stress_level'] ?? 3)),
    'upcoming_event' => (string) ($thread['upcoming_event'] ?? ''),
];

$oldInputForm = (string) getOldInput('coach_form', '');
$oldInputThreadId = (int) getOldInput('coach_edit_thread_id', '0');
if ($oldInputForm === 'edit_situation' && $oldInputThreadId === $threadId) {
    $formData['situation_text'] = (string) getOldInput('situation_text', $formData['situation_text']);
    $formData['situation_type'] = (string) getOldInput('situation_type', $formData['situation_type']);
    $formData['time_available'] = (string) getOldInput('time_available', $formData['time_available']);
    $formData['stress_level'] = (string) getOldInput('stress_level', $formData['stress_level']);
    $formData['upcoming_event'] = (string) getOldInput('upcoming_event', $formData['upcoming_event']);
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

$pageTitle = 'Edit Situation';
$pageEyebrow = 'Coach';
$pageHelper = 'Update details and refresh your recommendation.';
$activeNav = 'coach';
$showBackButton = true;
$backHref = BASE_URL . '/coach/view.php?id=' . $threadId;
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-coach-page zz-coach-edit" aria-labelledby="zz-coach-edit-title">
    <h2 id="zz-coach-edit-title" class="zz-visually-hidden">Edit coach situation</h2>

    <article class="zz-card zz-coach-thread">
        <h3 class="zz-coach-card-title">Edit Situation</h3>
        <p class="zz-help">Last updated <?= h(zz_format_datetime((string) ($thread['updated_at'] ?? ''))) ?></p>
    </article>

    <article class="zz-card zz-coach-start">
        <form method="POST" action="../../api/coach/update.php" class="zz-coach-form">
            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
            <input type="hidden" name="thread_id" value="<?= h((string) $threadId) ?>">

            <div class="zz-field">
                <label for="situation_text" class="zz-label">What's happening right now?</label>
                <p class="zz-help">A sentence or two is enough. The Coach works with whatever you give it.</p>
                <div class="zz-chip-group zz-chips" data-chip-target="#situation_text">
                    <button type="button" class="zz-chip" data-value="Before a big game" aria-pressed="false">Before a big game</button>
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
                    placeholder="I have a big match tomorrow and I can't stop overthinking my last performance..."
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
                            <span class="zz-help">Anxiety before a game, event, or big moment</span>
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
                            <strong>Post-practice reset</strong>
                            <span class="zz-help">Winding down after training or competition</span>
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

            <div class="zz-field zz-float" data-zz-float>
                <input type="text" id="upcoming_event" name="upcoming_event" class="zz-float__control" placeholder=" " maxlength="120" value="<?= h($formData['upcoming_event']) ?>">
                <label class="zz-float__label" for="upcoming_event">Upcoming event <span class="zz-optional-tag">Optional</span></label>
                <p class="zz-help">If something specific is coming up, name it - helps the Coach tailor the recommendation.</p>
            </div>

            <div class="zz-coach-form__actions">
                <button type="submit" class="zz-btn zz-btn--primary zz-btn--lg zz-btn--block">Update &amp; Refresh Recommendation</button>
            </div>
        </form>
    </article>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
