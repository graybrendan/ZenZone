<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/coach_engine.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

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

$pageTitle = 'Edit Coach Situation';
$pageEyebrow = 'Coach';
$pageHelper = 'Update details and refresh your recommendation.';
$activeNav = 'coach';
$showBackButton = true;
$backHref = BASE_URL . '/coach/view.php?id=' . $threadId;

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

<section class="zz-coach-page zz-coach-edit" aria-labelledby="zz-coach-edit-title">
    <h2 id="zz-coach-edit-title" class="zz-visually-hidden">Edit coach situation</h2>

    <article class="zz-card zz-coach-thread">
        <h3 class="zz-coach-card-title">Edit Situation</h3>
        <p class="zz-help">Last updated <?= h(zz_format_datetime((string) ($thread['updated_at'] ?? ''))) ?></p>
    </article>

    <article class="zz-card zz-coach-start">
        <form method="POST" action="../../api/coach/update.php" class="zz-coach-form" data-coach-char-form>
            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
            <input type="hidden" name="thread_id" value="<?= h((string) $threadId) ?>">

            <div class="zz-field">
                <label for="situation_text" class="zz-label">What happened?</label>
                <textarea
                    id="situation_text"
                    name="situation_text"
                    class="zz-textarea zz-textarea--journal"
                    rows="5"
                    maxlength="1200"
                    data-coach-char-source="situation_text"
                    required
                ><?= h($formData['situation_text']) ?></textarea>
                <p class="zz-help zz-coach-charcount" data-coach-char-target="situation_text" aria-live="polite"></p>
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
                    <label for="stress_level" class="zz-label">Stress level (1-5)</label>
                    <select id="stress_level" name="stress_level" class="zz-select" required>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= ((int) $formData['stress_level'] === $i) ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
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
                <button type="submit" class="zz-btn zz-btn--primary">Save Changes</button>
                <a class="zz-btn zz-btn--ghost" href="<?= h(BASE_URL . '/coach/view.php?id=' . $threadId) ?>">Cancel</a>
            </div>
        </form>
    </article>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
