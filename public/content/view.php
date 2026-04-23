<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$slug = trim((string) ($_GET['slug'] ?? ''));
$lesson = getLessonBySlug($slug);

if (!$lesson) {
    http_response_code(404);
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTopicLabel(string $topic): string
{
    return ucwords($topic);
}

$pageTitle = $lesson ? ($lesson['title'] ?? 'Lesson') : 'Lesson Not Found';
$pageEyebrow = 'Lessons';
$pageHelper = null;
$activeNav = 'lessons';
$showBackButton = true;
$backHref = BASE_URL . '/content/index.php';
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-lessons-page zz-lesson-view-page" aria-labelledby="zz-lesson-view-title">
    <h2 id="zz-lesson-view-title" class="zz-visually-hidden">Lesson details</h2>

    <?php if (!$lesson): ?>
        <div class="zz-empty-state">
            <svg class="zz-empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="m15 9-6 6"></path>
                <path d="m9 9 6 6"></path>
            </svg>
            <h2>Lesson not found</h2>
            <p>This lesson doesn't exist or has been removed.</p>
            <a class="zz-btn zz-btn--primary" href="index.php">Browse Lessons</a>
        </div>
    <?php else: ?>
        <article class="zz-card zz-lesson-header">
            <div class="zz-lesson-header__meta">
                <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h(formatTopicLabel((string) ($lesson['topic'] ?? ''))) ?></span>
                <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= (int) ($lesson['duration_minutes'] ?? 0) ?> min</span>
                <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h(ucfirst((string) ($lesson['format'] ?? ''))) ?></span>
                <?php if (!empty($lesson['is_featured'])): ?>
                    <span class="zz-badge zz-badge--gold zz-badge--sm">Featured</span>
                <?php endif; ?>
            </div>
            <p class="zz-lesson-header__desc"><?= h($lesson['short_description'] ?? '') ?></p>
        </article>

        <article class="zz-card zz-lesson-trynow">
            <h2>Try Now</h2>
            <p class="zz-help">Follow these steps at your own pace. Most take under <?= (int) ($lesson['duration_minutes'] ?? 5) ?> minutes.</p>

            <?php if (!empty($lesson['try_now_steps']) && is_array($lesson['try_now_steps'])): ?>
                <ol class="zz-lesson-steps">
                    <?php foreach ($lesson['try_now_steps'] as $i => $step): ?>
                        <li class="zz-lesson-step" data-step="<?= $i + 1 ?>">
                            <div class="zz-lesson-step__number"><?= $i + 1 ?></div>
                            <div class="zz-lesson-step__text"><?= h($step) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="zz-muted">Steps for this lesson are coming soon.</p>
            <?php endif; ?>
        </article>

        <details class="zz-card zz-lesson-why" open>
            <summary class="zz-coach-card-title">Why This Works</summary>
            <p><?= h($lesson['why_this_works'] ?? '') ?></p>

            <?php if (!empty($lesson['when_to_use'])): ?>
                <div class="zz-lesson-when">
                    <strong class="zz-lesson-detail-label">When to use</strong>
                    <p><?= h($lesson['when_to_use']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($lesson['evidence_note'])): ?>
                <div class="zz-lesson-evidence">
                    <strong class="zz-lesson-detail-label">Evidence</strong>
                    <p><?= h($lesson['evidence_note']) ?></p>
                </div>
            <?php endif; ?>
        </details>

        <div class="zz-lesson-actions">
            <a class="zz-btn zz-btn--secondary" href="index.php">Back to Lessons</a>
            <a class="zz-btn zz-btn--ghost" href="<?= h(BASE_URL . '/dashboard.php') ?>">Dashboard</a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
