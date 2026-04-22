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
    $summaryText = zenzone_build_checkin_summary($checkin);
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

$topStartUrl = recommendationStartUrl($topRecommendation);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In Result - ZenZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h1 class="h3 mb-0">Check-In Result</h1>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    <a href="checkin.php" class="btn btn-outline-dark">New Check-In</a>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h5">Your Check-In Summary</h2>
                            <p class="mb-2"><?= h($summaryText) ?></p>
                            <p class="mb-1 text-muted">
                                Logged on <?= h(date('M j, Y g:i A', strtotime((string) $checkin['created_at']))) ?>
                                (<?= h(checkinTypeLabel((string) $checkin['checkin_type'])) ?> check-in)
                            </p>
                            <?php if ((int) ($dayPosition['position'] ?? 1) === 1): ?>
                                <p class="mb-0 text-muted">This was your first check-in today.</p>
                            <?php else: ?>
                                <p class="mb-0 text-muted">
                                    This was check-in #<?= h((string) ($dayPosition['position'] ?? 1)) ?> of
                                    <?= h((string) ($dayPosition['total'] ?? 1)) ?> today.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h2 class="h6 text-muted">Your ZenScore</h2>
                            <div class="display-6 fw-bold"><?= h(number_format((float) $checkin['entry_score'], 2)) ?></div>
                            <?php if ($deltaFromPrevious !== null): ?>
                                <p class="mb-0 mt-2 <?= $deltaFromPrevious >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $deltaFromPrevious >= 0 ? '+' : '' ?><?= h(number_format($deltaFromPrevious, 2)) ?>
                                    vs last check-in
                                </p>
                            <?php else: ?>
                                <p class="mb-0 mt-2 text-muted">No previous check-in to compare yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5">Submitted Ratings</h2>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-2">
                        <?php foreach ($labels as $field => $label): ?>
                            <div class="col">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted"><?= h($label) ?></div>
                                    <div class="fw-semibold"><?= h((string) ($checkin[$field] ?? 0)) ?>/7</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3 border-dark">
                <div class="card-body">
                    <h2 class="h5 mb-1">Recommended Next Step</h2>
                    <p class="text-muted mb-3"><?= h((string) ($recommendationBundle['summary'] ?? '')) ?></p>

                    <?php if ($topRecommendation): ?>
                        <h3 class="h5"><?= h((string) ($topRecommendation['title'] ?? 'Recommended Reset')) ?></h3>
                        <p class="mb-2"><?= h((string) ($recommendationBundle['fit_reason'] ?? '')) ?></p>
                        <p class="mb-2"><strong>Why this works:</strong> <?= h((string) ($topRecommendation['why_this_works'] ?? '')) ?></p>
                        <p class="mb-2"><strong>When to use:</strong> <?= h((string) ($topRecommendation['when_to_use'] ?? '')) ?></p>
                        <p class="mb-2"><strong>Coach cue:</strong> <?= h((string) ($recommendationBundle['coach_message'] ?? '')) ?></p>

                        <?php if (!empty($topRecommendation['steps']) && is_array($topRecommendation['steps'])): ?>
                            <ol>
                                <?php foreach ($topRecommendation['steps'] as $step): ?>
                                    <li><?= h((string) $step) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>

                        <?php if ($topStartUrl !== null): ?>
                            <a href="<?= h($topStartUrl) ?>" class="btn btn-dark btn-lg">Start Recommendation</a>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">
                                This recommendation is available, but its lesson link is missing. Use the lessons page to start manually.
                                <a href="content/index.php" class="alert-link">Open Lessons</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0">
                            Recommendation details were unavailable, so you can choose a lesson directly.
                            <a href="content/index.php" class="alert-link">Browse Lessons</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5">Other Good Options</h2>

                    <?php if (empty($alternativeRecommendations)): ?>
                        <p class="text-muted mb-0">No alternate recommendations were available for this check-in yet.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($alternativeRecommendations as $alternative): ?>
                                <?php $altStartUrl = recommendationStartUrl($alternative); ?>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <h3 class="h6 mb-1"><?= h((string) ($alternative['title'] ?? 'Option')) ?></h3>
                                        <p class="mb-2"><strong>Why:</strong> <?= h((string) ($alternative['why_this_works'] ?? '')) ?></p>
                                        <p class="mb-2"><strong>When:</strong> <?= h((string) ($alternative['when_to_use'] ?? '')) ?></p>
                                        <?php if ($altStartUrl !== null): ?>
                                            <a href="<?= h($altStartUrl) ?>" class="btn btn-outline-dark btn-sm">Start</a>
                                        <?php else: ?>
                                            <span class="text-muted small">Start link unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($topStartUrl !== null): ?>
                    <a href="<?= h($topStartUrl) ?>" class="btn btn-dark">Start Recommendation</a>
                <?php else: ?>
                    <a href="content/index.php" class="btn btn-dark">Browse Lessons</a>
                <?php endif; ?>
                <a href="trends.php?result_id=<?= h((string) $checkin['id']) ?>" class="btn btn-outline-dark">View Trends</a>
                <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                <a href="checkin.php" class="btn btn-outline-secondary">Check-in Again</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
