<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';
require_once __DIR__ . '/../includes/checkin_functions.php';

requireLogin();

$checkinId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($checkinId <= 0) {
    redirectWithFlash('checkin.php', 'Check-in result not found.');
}

try {
    $pdo = getDB();
    $userId = (int) $_SESSION['user_id'];
    $checkin = zenzone_get_checkin_by_id($pdo, $userId, $checkinId);
} catch (Throwable $e) {
    error_log('Check-in result load failed: ' . $e->getMessage());
    redirectWithFlash('checkin.php', 'Could not load this check-in result right now. Please try again.');
}

if ($checkin === null) {
    redirectWithFlash('checkin.php', 'That check-in result is unavailable.');
}

try {
    $labels = zenzone_labels();
    $recommendationBundle = zenzone_generate_checkin_recommendations($checkin);

    $topRecommendation = is_array($recommendationBundle['top_recommendation'] ?? null)
        ? $recommendationBundle['top_recommendation']
        : null;
    $alternativeRecommendations = is_array($recommendationBundle['alternatives'] ?? null)
        ? $recommendationBundle['alternatives']
        : [];

    $dayPosition = zenzone_get_checkin_day_position($pdo, $userId, $checkin);
    $previousCheckin = zenzone_get_previous_checkin(
        $pdo,
        $userId,
        (string) ($checkin['created_at'] ?? ''),
        (int) ($checkin['id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('Check-in result compute failed: ' . $e->getMessage());
    redirectWithFlash('checkin.php', 'Could not load recommendation details for this check-in.');
}

$deltaFromPrevious = null;
if (is_array($previousCheckin)) {
    $deltaFromPrevious = round((float) $checkin['entry_score'] - (float) $previousCheckin['entry_score'], 2);
}

$deltaClass = 'zz-score-card__delta--flat';
$deltaText = null;

if ($deltaFromPrevious !== null) {
    if ($deltaFromPrevious > 0) {
        $deltaClass = 'zz-score-card__delta--up';
        $deltaText = '+' . number_format($deltaFromPrevious, 2) . ' from last check-in';
    } elseif ($deltaFromPrevious < 0) {
        $deltaClass = 'zz-score-card__delta--down';
        $deltaText = number_format($deltaFromPrevious, 2) . ' from last check-in';
    } else {
        $deltaText = '0.00 from last check-in';
    }
}

$pageTitle = 'Check-In Complete';
$pageEyebrow = 'Result';
$pageHelper = null;
$activeNav = 'checkin';
$showBackButton = false;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function recommendationStartUrl(?array $recommendation): ?string
{
    if (!is_array($recommendation)) {
        return null;
    }

    $slug = trim((string) ($recommendation['slug'] ?? ''));
    if ($slug === '' || getLessonBySlug($slug) === null) {
        return null;
    }

    return 'content/view.php?slug=' . urlencode($slug);
}

function checkinTypeLabel(string $checkinType): string
{
    return $checkinType === 'daily' ? 'Daily' : 'Additional';
}

function buildStrengthSummary(array $checkin, array $labels): string
{
    $ranked = [];
    foreach ($labels as $field => $label) {
        $ranked[] = [
            'field' => $field,
            'label' => (string) $label,
            'value' => (int) ($checkin[$field] ?? 0),
        ];
    }

    usort($ranked, static function (array $left, array $right): int {
        $valueCompare = ((int) ($right['value'] ?? 0)) <=> ((int) ($left['value'] ?? 0));
        if ($valueCompare !== 0) {
            return $valueCompare;
        }

        return strcmp((string) ($left['field'] ?? ''), (string) ($right['field'] ?? ''));
    });

    $topA = $ranked[0] ?? null;
    $topB = $ranked[1] ?? null;
    $lowest = $ranked[count($ranked) - 1] ?? null;

    if (!is_array($topA) || !is_array($topB) || !is_array($lowest)) {
        return 'Check-in saved. Keep building on what is already working for you today.';
    }

    return 'Your strongest areas today were ' .
        (string) ($topA['label'] ?? 'one area') . ' and ' .
        (string) ($topB['label'] ?? 'another area') .
        '. Keep leaning on those strengths and give a little extra attention to ' .
        (string) ($lowest['label'] ?? 'your lowest area') . ' next.';
}

$topStartUrl = recommendationStartUrl($topRecommendation);
$loggedAt = strtotime((string) ($checkin['created_at'] ?? ''));
$strengthSummary = buildStrengthSummary($checkin, $labels);
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-result-layout" aria-labelledby="zz-score-title">
    <article class="zz-card zz-score-card">
        <h2 id="zz-score-title">Today's ZenScore</h2>
        <p class="zz-score-card__value" aria-live="polite" aria-atomic="true"><?= h(number_format((float) $checkin['entry_score'], 2)) ?></p>
        <p class="zz-score-card__context"><?= h($strengthSummary) ?></p>

        <?php if ($deltaText !== null): ?>
            <span class="zz-score-card__delta <?= h($deltaClass) ?>"><?= h($deltaText) ?></span>
        <?php else: ?>
            <p class="zz-help zz-score-card__meta">No previous check-in to compare yet.</p>
        <?php endif; ?>

        <p class="zz-muted zz-score-card__meta">
            Logged on <?= h($loggedAt ? date('M j, Y g:i A', $loggedAt) : (string) ($checkin['created_at'] ?? '')) ?>
            (<?= h(checkinTypeLabel((string) ($checkin['checkin_type'] ?? 'voluntary'))) ?> check-in).
            <?php if ((int) ($dayPosition['position'] ?? 1) === 1): ?>
                This was your first check-in today.
            <?php else: ?>
                This was check-in #<?= h((string) ($dayPosition['position'] ?? 1)) ?> of <?= h((string) ($dayPosition['total'] ?? 1)) ?> today.
            <?php endif; ?>
        </p>
    </article>

    <article class="zz-card" aria-labelledby="zz-ratings-title">
        <h2 id="zz-ratings-title">Your ratings</h2>
        <div class="zz-ratings-grid">
            <?php foreach ($labels as $field => $label): ?>
                <div class="zz-rating-tile">
                    <p class="zz-rating-tile__label"><?= h($label) ?></p>
                    <p class="zz-rating-tile__value"><?= h((string) ($checkin[$field] ?? 0)) ?><span class="zz-rating-tile__denominator">/7</span></p>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="zz-card zz-recommendation-card" aria-labelledby="zz-recommendation-title">
        <p class="zz-recommendation-card__eyebrow">Recommended next step</p>

        <?php if ($topRecommendation !== null): ?>
            <h3 id="zz-recommendation-title"><?= h((string) ($topRecommendation['title'] ?? 'Recommended Reset')) ?></h3>
            <p class="zz-recommendation-card__fit"><strong>Why this fits:</strong> <?= h((string) ($recommendationBundle['fit_reason'] ?? '')) ?></p>
            <p class="zz-help"><?= h((string) ($recommendationBundle['summary'] ?? '')) ?></p>

            <?php if ($topStartUrl !== null): ?>
                <a href="<?= h($topStartUrl) ?>" class="zz-btn zz-btn--primary">Start</a>
            <?php else: ?>
                <a href="content/index.php" class="zz-btn zz-btn--secondary">Open Lessons</a>
            <?php endif; ?>

            <?php if (!empty($alternativeRecommendations)): ?>
                <div class="zz-recommendation-card__alternatives">
                    <p class="zz-help">Alternatives</p>
                    <div class="zz-inline">
                        <?php foreach ($alternativeRecommendations as $alternative): ?>
                            <?php
                            $altStartUrl = recommendationStartUrl($alternative);
                            if ($altStartUrl === null) {
                                continue;
                            }
                            ?>
                            <a href="<?= h($altStartUrl) ?>" class="zz-btn zz-btn--secondary zz-btn--sm">
                                <?= h((string) ($alternative['title'] ?? 'Option')) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h3 id="zz-recommendation-title">Recommendation unavailable</h3>
            <p class="zz-help">Recommendation details were unavailable for this check-in.</p>
            <a href="content/index.php" class="zz-btn zz-btn--secondary">Browse Lessons</a>
        <?php endif; ?>
    </article>

    <div class="zz-action-row" aria-label="Check-in result actions">
        <a href="trends.php" class="zz-btn zz-btn--primary">View Trends</a>
        <a href="checkin.php" class="zz-btn zz-btn--secondary">Check In Again</a>
        <a href="dashboard.php" class="zz-btn zz-btn--ghost">Back to Dashboard</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
