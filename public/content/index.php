<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/date_helpers.php';

requireLogin();

$lessons = getLessonCatalog();
$topicOptions = getLessonTopics();
$durationOptions = getLessonDurationOptions();

$searchQuery = trim((string) ($_GET['q'] ?? ''));
if (strlen($searchQuery) > 80) {
    $searchQuery = substr($searchQuery, 0, 80);
}

$selectedTopic = trim((string) ($_GET['topic'] ?? 'all'));
if ($selectedTopic !== 'all' && !in_array($selectedTopic, $topicOptions, true)) {
    $selectedTopic = 'all';
}

$selectedDuration = trim((string) ($_GET['duration'] ?? 'all'));
if ($selectedDuration !== 'all') {
    if (!ctype_digit($selectedDuration) || !in_array((int) $selectedDuration, $durationOptions, true)) {
        $selectedDuration = 'all';
    }
}

$statusNotice = '';
$statusCode = trim((string) ($_GET['status'] ?? ''));
if ($statusCode === 'feature_disabled') {
    $statusNotice = 'Lesson save and progress tracking are currently turned off.';
}

$filteredLessons = array_values(array_filter($lessons, static function (array $lesson) use ($searchQuery, $selectedTopic, $selectedDuration): bool {
    if ($selectedTopic !== 'all' && (string) ($lesson['topic'] ?? '') !== $selectedTopic) {
        return false;
    }

    if ($selectedDuration !== 'all' && (int) ($lesson['duration_minutes'] ?? 0) !== (int) $selectedDuration) {
        return false;
    }

    if ($searchQuery !== '') {
        $haystack = strtolower(
            (string) ($lesson['title'] ?? '') . ' ' .
            (string) ($lesson['short_description'] ?? '') . ' ' .
            (string) ($lesson['topic'] ?? '')
        );

        if (strpos($haystack, strtolower($searchQuery)) === false) {
            return false;
        }
    }

    return true;
}));

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatTopicLabel(string $topic): string
{
    return ucwords($topic);
}

$pageTitle = 'Lessons';
$pageEyebrow = 'Your Library';
$pageHelper = 'Techniques you can learn and apply in under 5 minutes.';
$activeNav = 'lessons';
$showBackButton = false;
?>
<?php require_once __DIR__ . '/../../includes/partials/header.php'; ?>

<section class="zz-lessons-page" aria-labelledby="zz-lessons-library-title">
    <h2 id="zz-lessons-library-title" class="zz-visually-hidden">Lessons library</h2>

    <form class="zz-card zz-lessons-filters" method="GET" action="index.php">
        <div class="zz-lessons-search">
            <div class="zz-field zz-float" data-zz-float>
                <input
                    type="text"
                    id="q"
                    name="q"
                    class="zz-float__control"
                    placeholder=" "
                    value="<?= h($searchQuery) ?>"
                    maxlength="80"
                >
                <label class="zz-float__label" for="q">Search lessons</label>
            </div>
        </div>

        <div class="zz-lessons-filter-row">
            <div class="zz-field">
                <label for="topic" class="zz-label">Topic</label>
                <select id="topic" name="topic" class="zz-select">
                    <option value="all">All topics</option>
                    <?php foreach ($topicOptions as $topic): ?>
                        <option value="<?= h($topic) ?>" <?= $selectedTopic === $topic ? 'selected' : '' ?>><?= h(formatTopicLabel($topic)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="zz-field">
                <label for="duration" class="zz-label">Duration</label>
                <select id="duration" name="duration" class="zz-select">
                    <option value="all">Any duration</option>
                    <?php foreach ($durationOptions as $minutes): ?>
                        <option value="<?= (int) $minutes ?>" <?= ((int) $selectedDuration === (int) $minutes) ? 'selected' : '' ?>><?= (int) $minutes ?> min</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="zz-lessons-filter-actions">
                <button type="submit" class="zz-btn zz-btn--primary zz-btn--sm">Filter</button>
                <?php if ($searchQuery !== '' || $selectedTopic !== 'all' || $selectedDuration !== 'all'): ?>
                    <a class="zz-btn zz-btn--ghost zz-btn--sm" href="index.php">Clear</a>
                <?php endif; ?>
            </div>
        </div>

        <p class="zz-lessons-count zz-muted"><?= count($filteredLessons) ?> lesson<?= count($filteredLessons) !== 1 ? 's' : '' ?> found</p>
    </form>

    <?php if ($statusNotice !== ''): ?>
        <div class="zz-alert zz-alert--info"><?= h($statusNotice) ?></div>
    <?php endif; ?>

    <?php if (empty($filteredLessons)): ?>
        <div class="zz-empty-state">
            <svg class="zz-empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
            </svg>
            <h2>No lessons matched</h2>
            <p>Try a broader topic or clear your filters.</p>
            <a class="zz-btn zz-btn--secondary" href="index.php">Clear Filters</a>
        </div>
    <?php else: ?>
        <div class="zz-lessons-grid">
            <?php foreach ($filteredLessons as $lesson): ?>
                <?php $slug = trim((string) ($lesson['slug'] ?? '')); ?>
                <article class="zz-lesson-card" aria-labelledby="lesson-<?= h($slug) ?>">
                    <div class="zz-lesson-card__header">
                        <h3 id="lesson-<?= h($slug) ?>" class="zz-lesson-card__title"><?= h($lesson['title'] ?? '') ?></h3>
                        <?php if (!empty($lesson['is_featured'])): ?>
                            <span class="zz-badge zz-badge--gold zz-badge--sm">Featured</span>
                        <?php endif; ?>
                    </div>

                    <div class="zz-lesson-card__meta">
                        <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h(formatTopicLabel((string) ($lesson['topic'] ?? ''))) ?></span>
                        <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= (int) ($lesson['duration_minutes'] ?? 0) ?> min</span>
                        <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h(ucfirst((string) ($lesson['format'] ?? ''))) ?></span>
                    </div>

                    <p class="zz-lesson-card__desc"><?= h($lesson['short_description'] ?? '') ?></p>

                    <details class="zz-lesson-card__details">
                        <summary class="zz-lesson-card__expand">More info</summary>
                        <div class="zz-lesson-card__expanded">
                            <?php if (!empty($lesson['when_to_use'])): ?>
                                <p><strong class="zz-lesson-detail-label">When to use:</strong> <?= h($lesson['when_to_use']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($lesson['evidence_note'])): ?>
                                <p><strong class="zz-lesson-detail-label">Evidence:</strong> <?= h($lesson['evidence_note']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($lesson['why_this_works'])): ?>
                                <p><strong class="zz-lesson-detail-label">Why it works:</strong> <?= h($lesson['why_this_works']) ?></p>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="zz-lesson-card__actions">
                        <a class="zz-btn zz-btn--primary zz-btn--sm" href="view.php?slug=<?= h(urlencode($slug)) ?>">Try Now</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/partials/footer.php'; ?>
