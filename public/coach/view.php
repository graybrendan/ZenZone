<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
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
        thread_title,
        summary,
        situation_text,
        situation_type,
        time_available,
        stress_level,
        upcoming_event,
        created_at,
        updated_at,
        last_message_at
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

$aiMessageStmt = $db->prepare("
    SELECT content, metadata_json, created_at
    FROM coach_messages
    WHERE thread_id = :thread_id
      AND sender = 'ai'
    ORDER BY id DESC
    LIMIT 1
");
$aiMessageStmt->execute(['thread_id' => $threadId]);
$latestAiMessage = $aiMessageStmt->fetch();

$coachResponse = null;
if ($latestAiMessage && !empty($latestAiMessage['metadata_json'])) {
    $decoded = json_decode((string) $latestAiMessage['metadata_json'], true);
    if (is_array($decoded)) {
        $coachResponse = $decoded;
    }
}

if ($coachResponse === null) {
    $coachResponse = generateCoachResponse([
        'situation_text' => (string) ($thread['situation_text'] ?? ''),
        'situation_type' => (string) ($thread['situation_type'] ?? 'other'),
        'time_available' => (int) ($thread['time_available'] ?? 3),
        'stress_level' => (int) ($thread['stress_level'] ?? 3),
        'upcoming_event' => (string) ($thread['upcoming_event'] ?? ''),
    ]);
}

$threadSummary = trim((string) ($thread['summary'] ?? ''));
if ($threadSummary === '') {
    $threadSummary = createCoachSituationSummary((string) ($thread['situation_text'] ?? ''), 180);
}

$topRecommendation = is_array($coachResponse['top_recommendation'] ?? null)
    ? $coachResponse['top_recommendation']
    : null;
$alternatives = is_array($coachResponse['alternatives'] ?? null)
    ? $coachResponse['alternatives']
    : [];

$topSlug = '';
$topLessonExists = false;
if ($topRecommendation !== null) {
    $topSlug = trim((string) ($topRecommendation['slug'] ?? ''));
    if ($topSlug !== '') {
        $topLessonExists = getLessonBySlug($topSlug) !== null;
    }
}

$createdAt = (string) ($thread['created_at'] ?? '');
$updatedAt = (string) ($thread['last_message_at'] ?? $thread['updated_at'] ?? $thread['created_at'] ?? '');
$createdLabel = zz_format_datetime($createdAt !== '' ? $createdAt : null);
$updatedLabel = zz_format_datetime($updatedAt !== '' ? $updatedAt : null);

$pageTitle = 'Coach Situation';
$pageEyebrow = 'Coach';
$pageHelper = 'Review your recommendation and take the next useful action.';
$activeNav = 'coach';
$showBackButton = true;
$backHref = BASE_URL . '/coach/index.php';

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

<section class="zz-coach-page zz-coach-view" aria-labelledby="zz-coach-view-title">
    <h2 id="zz-coach-view-title" class="zz-visually-hidden">Coach situation details</h2>

    <article class="zz-card zz-coach-thread" aria-labelledby="zz-coach-thread-title">
        <h3 id="zz-coach-thread-title" class="zz-coach-thread__title"><?= h($threadSummary) ?></h3>
        <div class="zz-coach-thread__meta">
            <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h(coachTypeLabel((string) ($thread['situation_type'] ?? 'other'))) ?></span>
            <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h((string) ((int) ($thread['time_available'] ?? 0))) ?> min</span>
            <span class="zz-badge zz-badge--gold zz-badge--sm">Stress <?= h((string) ((int) ($thread['stress_level'] ?? 0))) ?>/5</span>
        </div>
        <p class="zz-coach-thread__dates zz-date-time">
            Created <?= h($createdLabel) ?>
            <span aria-hidden="true">&middot;</span>
            Updated <?= h($updatedLabel) ?>
        </p>
    </article>

    <article class="zz-card zz-coach-recommendation" aria-labelledby="zz-coach-recommendation-title">
        <h3 id="zz-coach-recommendation-title" class="zz-coach-card-title">Recommendation</h3>

        <?php if (!empty($coachResponse['crisis_detected'])): ?>
            <div class="zz-alert zz-alert--danger zz-coach-crisis" role="alert">
                <p><strong>Immediate support recommended</strong></p>
                <?php if (trim((string) ($coachResponse['crisis_message'] ?? '')) !== ''): ?>
                    <p><?= h((string) $coachResponse['crisis_message']) ?></p>
                <?php endif; ?>
                <?php if (trim((string) ($coachResponse['coach_message'] ?? '')) !== ''): ?>
                    <p><?= h((string) $coachResponse['coach_message']) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if (!empty($coachResponse['summary'])): ?>
                <p class="zz-coach-recommendation__summary"><?= h((string) $coachResponse['summary']) ?></p>
            <?php endif; ?>

            <?php if ($topRecommendation !== null): ?>
                <article class="zz-coach-rec-primary">
                    <h4><?= h((string) ($topRecommendation['title'] ?? 'Top recommendation')) ?></h4>
                    <p class="zz-help"><strong>Why this works:</strong> <?= h((string) ($topRecommendation['why_this_works'] ?? '')) ?></p>
                    <p class="zz-help"><strong>When to use:</strong> <?= h((string) ($topRecommendation['when_to_use'] ?? '')) ?></p>
                    <p class="zz-help"><strong>Estimated duration:</strong> <?= h((string) ((int) ($topRecommendation['duration_minutes'] ?? 0))) ?> min</p>

                    <?php if (!empty($topRecommendation['steps']) && is_array($topRecommendation['steps'])): ?>
                        <ol class="zz-coach-rec-steps">
                            <?php foreach ($topRecommendation['steps'] as $step): ?>
                                <li><?= h((string) $step) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if ($topSlug !== '' && $topLessonExists): ?>
                        <a class="zz-btn zz-btn--primary zz-btn--sm" href="<?= h(BASE_URL . '/content/view.php?slug=' . urlencode($topSlug)) ?>">Start This Tool</a>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <p class="zz-muted">No top recommendation was available for this situation.</p>
            <?php endif; ?>

            <?php if (!empty($coachResponse['coach_message'])): ?>
                <p class="zz-coach-recommendation__message"><?= h((string) $coachResponse['coach_message']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </article>

    <?php if (!empty($alternatives)): ?>
        <details class="zz-card zz-coach-details zz-coach-alternatives">
            <summary class="zz-section-title">Alternatives (<?= h((string) count($alternatives)) ?>)</summary>
            <div class="zz-coach-alt-list">
                <?php foreach ($alternatives as $alternative): ?>
                    <?php if (!is_array($alternative)): continue; endif; ?>
                    <?php
                    $altSlug = trim((string) ($alternative['slug'] ?? ''));
                    $altLessonExists = ($altSlug !== '' && getLessonBySlug($altSlug) !== null);
                    ?>
                    <article class="zz-coach-alt-item">
                        <h4><?= h((string) ($alternative['title'] ?? 'Alternative tool')) ?></h4>
                        <p class="zz-help"><strong>Why this works:</strong> <?= h((string) ($alternative['why_this_works'] ?? '')) ?></p>
                        <p class="zz-help"><strong>When to use:</strong> <?= h((string) ($alternative['when_to_use'] ?? '')) ?></p>
                        <p class="zz-help"><strong>Estimated duration:</strong> <?= h((string) ((int) ($alternative['duration_minutes'] ?? 0))) ?> min</p>

                        <?php if (!empty($alternative['steps']) && is_array($alternative['steps'])): ?>
                            <ol class="zz-coach-alt-item__steps">
                                <?php foreach ($alternative['steps'] as $step): ?>
                                    <li><?= h((string) $step) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if ($altLessonExists): ?>
                            <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/content/view.php?slug=' . urlencode($altSlug)) ?>">Start This Tool</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>

    <details class="zz-card zz-coach-details zz-coach-situation">
        <summary class="zz-section-title">Situation Details</summary>
        <dl class="zz-detail-list zz-coach-meta-list">
            <dt>Situation type</dt>
            <dd><?= h(coachTypeLabel((string) ($thread['situation_type'] ?? 'other'))) ?></dd>
            <dt>Time available</dt>
            <dd><?= h((string) ((int) ($thread['time_available'] ?? 0))) ?> min</dd>
            <dt>Stress level</dt>
            <dd><?= h((string) ((int) ($thread['stress_level'] ?? 0))) ?> / 5</dd>
            <dt>Upcoming event</dt>
            <dd><?= h((string) (($thread['upcoming_event'] ?? '') !== '' ? $thread['upcoming_event'] : 'None')) ?></dd>
            <dt>What happened</dt>
            <dd><?= nl2br(h((string) ($thread['situation_text'] ?? ''))) ?></dd>
        </dl>
    </details>

    <div class="zz-coach-actions" aria-label="Coach situation actions">
        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/edit.php?id=' . $threadId) ?>">Edit</a>
        <a class="zz-btn zz-btn--secondary zz-btn--sm" href="<?= h(BASE_URL . '/coach/history.php') ?>">View History</a>
        <a class="zz-btn zz-btn--ghost zz-btn--sm" href="<?= h(BASE_URL . '/coach/index.php') ?>">Back to Coach</a>

        <form method="POST" action="../../api/coach/delete.php" class="zz-inline-form" data-coach-delete-form data-confirm-message="Delete this coach situation? This cannot be undone.">
            <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
            <input type="hidden" name="thread_id" value="<?= h((string) $threadId) ?>">
            <button type="submit" class="zz-btn zz-btn--danger zz-btn--sm">Delete</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
