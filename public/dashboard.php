<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

$userStmt = $db->prepare('
    SELECT baseline_complete
    FROM users
    WHERE id = :user_id
    LIMIT 1
');
$userStmt->execute([
    'user_id' => $userId,
]);
$user = $userStmt->fetch();

$baselineComplete = $user ? (int) $user['baseline_complete'] : 0;
$baselineScore = null;
$currentDailyScore = null;
$latestTodayEntryScore = null;

$baselineStmt = $db->prepare('
    SELECT baseline_score
    FROM baseline_assessments
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 1
');
$baselineStmt->execute([
    'user_id' => $userId,
]);
$baseline = $baselineStmt->fetch();

if ($baseline && isset($baseline['baseline_score'])) {
    $baselineScore = (float) $baseline['baseline_score'];
}

$dailyStmt = $db->prepare('
    SELECT daily_score
    FROM daily_zenscore_summary
    WHERE user_id = :user_id
      AND summary_date = :summary_date
    LIMIT 1
');
$dailyStmt->execute([
    'user_id' => $userId,
    'summary_date' => $today,
]);
$daily = $dailyStmt->fetch();

if ($daily && isset($daily['daily_score'])) {
    $currentDailyScore = (float) $daily['daily_score'];
}

$checkinTodayStmt = $db->prepare('
    SELECT COUNT(*)
    FROM check_ins
    WHERE user_id = :user_id
      AND checkin_date = :checkin_date
');
$checkinTodayStmt->execute([
    'user_id' => $userId,
    'checkin_date' => $today,
]);
$checkinCountToday = (int) $checkinTodayStmt->fetchColumn();
$hasCheckedInToday = $checkinCountToday > 0;

if ($hasCheckedInToday && $currentDailyScore === null) {
    $latestTodayStmt = $db->prepare('
        SELECT entry_score
        FROM check_ins
        WHERE user_id = :user_id
          AND checkin_date = :checkin_date
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $latestTodayStmt->execute([
        'user_id' => $userId,
        'checkin_date' => $today,
    ]);

    $latestToday = $latestTodayStmt->fetch();
    if ($latestToday && isset($latestToday['entry_score'])) {
        $latestTodayEntryScore = (float) $latestToday['entry_score'];
    }
}

$dailyDoneStmt = $db->prepare('
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date = :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = \'active\'
      AND g.cadence_type = \'daily\'
      AND g.is_priority = 1
');
$dailyDoneStmt->execute([
    'today' => $today,
    'user_id' => $userId,
]);
$dailyDone = (int) $dailyDoneStmt->fetchColumn();

$weeklyDoneStmt = $db->prepare('
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date BETWEEN :week_start AND :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = \'active\'
      AND g.cadence_type = \'weekly\'
      AND g.is_priority = 1
');
$weeklyDoneStmt->execute([
    'week_start' => $weekStart,
    'today' => $today,
    'user_id' => $userId,
]);
$weeklyDone = (int) $weeklyDoneStmt->fetchColumn();

$monthlyDoneStmt = $db->prepare('
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date BETWEEN :month_start AND :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = \'active\'
      AND g.cadence_type = \'monthly\'
      AND g.is_priority = 1
');
$monthlyDoneStmt->execute([
    'month_start' => $monthStart,
    'today' => $today,
    'user_id' => $userId,
]);
$monthlyDone = (int) $monthlyDoneStmt->fetchColumn();

$completedGoalsStmt = $db->prepare('
    SELECT COUNT(*)
    FROM goals
    WHERE user_id = :user_id
      AND status = \'completed\'
');
$completedGoalsStmt->execute([
    'user_id' => $userId,
]);
$completedGoalsCount = (int) $completedGoalsStmt->fetchColumn();

$firstName = trim((string) ($_SESSION['first_name'] ?? ''));
if ($firstName === '') {
    $fullName = trim((string) ($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? ''));
    $firstName = $fullName !== '' ? explode(' ', $fullName)[0] : 'there';
}

$hour = (int) date('G');
if ($hour < 12) {
    $timeOfDay = 'morning';
} elseif ($hour < 17) {
    $timeOfDay = 'afternoon';
} else {
    $timeOfDay = 'evening';
}

if ($baselineComplete !== 1) {
    $contextLine = 'Let\'s start with a quick calibration.';
} elseif ($hasCheckedInToday) {
    $contextLine = 'You\'re checked in for today. Nice work.';
} else {
    $contextLine = 'Haven\'t checked in yet today. Ready when you are.';
}

$todayScoreValue = null;
if ($hasCheckedInToday) {
    $todayScoreValue = $currentDailyScore ?? $latestTodayEntryScore;
}

$deltaFromBaseline = null;
if ($todayScoreValue !== null && $baselineScore !== null) {
    $deltaFromBaseline = round((float) $todayScoreValue - (float) $baselineScore, 2);
}

$pageTitle = null;
$pageEyebrow = null;
$pageHelper = null;
$activeNav = 'home';
$showBackButton = false;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-dashboard-screen zz-dashboard-stack">
    <section class="zz-hero" aria-labelledby="zz-dashboard-greeting">
        <p class="zz-hero__eyebrow"><?= h(date('l, F j')) ?></p>
        <h1 id="zz-dashboard-greeting" class="zz-hero__greeting">Good <?= h($timeOfDay) ?>, <?= h($firstName) ?>.</h1>
        <p class="zz-hero__context"><?= h($contextLine) ?></p>

        <?php if ($baselineComplete !== 1): ?>
            <a class="zz-btn zz-btn--primary zz-btn--lg" href="<?= h(BASE_URL . '/baseline.php') ?>">Start Your Baseline</a>
        <?php elseif (!$hasCheckedInToday): ?>
            <a class="zz-btn zz-btn--primary zz-btn--lg" href="<?= h(BASE_URL . '/checkin.php') ?>">Check In Now</a>
        <?php else: ?>
            <a class="zz-btn zz-btn--secondary zz-btn--lg" href="<?= h(BASE_URL . '/checkin.php') ?>">Log Another Check-In</a>
        <?php endif; ?>
    </section>

    <div class="zz-dashboard-grid">
        <?php if ($baselineComplete === 1): ?>
            <article class="zz-card zz-score-card" aria-labelledby="zz-dashboard-score-title">
                <?php if ($hasCheckedInToday && $todayScoreValue !== null): ?>
                    <p class="zz-score-card__label" id="zz-dashboard-score-title">Today's ZenScore</p>
                    <p class="zz-score-card__value"><?= h(number_format((float) $todayScoreValue, 2)) ?></p>

                    <?php if ($deltaFromBaseline !== null): ?>
                        <?php
                        $deltaClass = 'zz-score-card__delta--flat';
                        $deltaText = number_format((float) $deltaFromBaseline, 2) . ' vs baseline';

                        if ($deltaFromBaseline > 0) {
                            $deltaClass = 'zz-score-card__delta--up';
                            $deltaText = '+' . number_format((float) $deltaFromBaseline, 2) . ' vs baseline';
                        } elseif ($deltaFromBaseline < 0) {
                            $deltaClass = 'zz-score-card__delta--down';
                        }
                        ?>
                        <span class="zz-score-card__delta <?= h($deltaClass) ?>"><?= h($deltaText) ?></span>
                    <?php endif; ?>

                    <?php if ($currentDailyScore === null): ?>
                        <p class="zz-score-card__context">Today's summary is still updating. Showing your latest check-in score.</p>
                    <?php else: ?>
                        <p class="zz-score-card__context">Based on your check-ins so far today.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="zz-score-card__label" id="zz-dashboard-score-title">Your baseline score</p>
                    <p class="zz-score-card__value"><?= h(number_format((float) ($baselineScore ?? 0), 2)) ?></p>
                    <p class="zz-score-card__context">Your first check-in will generate today's score.</p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <article class="zz-card" aria-labelledby="zz-goals-snapshot-title">
            <h2 id="zz-goals-snapshot-title">Goals Snapshot</h2>
            <div class="zz-goals-snapshot">
                <div class="zz-goals-snapshot__item">
                    <p class="zz-goals-snapshot__label">Daily</p>
                    <p class="zz-goals-snapshot__value"><?= h((string) $dailyDone) ?> <span class="zz-goals-snapshot__denominator">/ 3</span></p>
                </div>
                <div class="zz-goals-snapshot__item">
                    <p class="zz-goals-snapshot__label">Weekly</p>
                    <p class="zz-goals-snapshot__value"><?= h((string) $weeklyDone) ?> <span class="zz-goals-snapshot__denominator">/ 2</span></p>
                </div>
                <div class="zz-goals-snapshot__item">
                    <p class="zz-goals-snapshot__label">Monthly</p>
                    <p class="zz-goals-snapshot__value"><?= h((string) $monthlyDone) ?> <span class="zz-goals-snapshot__denominator">/ 1</span></p>
                </div>
            </div>
            <p class="zz-goals-snapshot__summary">Completed goals: <?= h((string) $completedGoalsCount) ?></p>
        </article>
    </div>

    <article class="zz-card" aria-labelledby="zz-quick-actions-title">
        <h2 id="zz-quick-actions-title">Quick Actions</h2>
        <div class="zz-module-tiles">
            <a class="zz-module-tile" href="goals/index.php">
                <svg class="zz-module-tile__icon" aria-hidden="true">
                    <use xlink:href="#icon-goals"></use>
                </svg>
                <span class="zz-module-tile__label">Goals</span>
            </a>
            <a class="zz-module-tile" href="coach/index.php">
                <svg class="zz-module-tile__icon" aria-hidden="true">
                    <use xlink:href="#icon-coach"></use>
                </svg>
                <span class="zz-module-tile__label">Coach</span>
            </a>
            <a class="zz-module-tile" href="content/index.php">
                <svg class="zz-module-tile__icon" aria-hidden="true">
                    <use xlink:href="#icon-lessons"></use>
                </svg>
                <span class="zz-module-tile__label">Lessons</span>
            </a>
            <a class="zz-module-tile" href="trends.php">
                <svg class="zz-module-tile__icon" aria-hidden="true">
                    <use xlink:href="#icon-checkin"></use>
                </svg>
                <span class="zz-module-tile__label">Trends</span>
            </a>
        </div>
    </article>

    <form method="post" action="<?= h(BASE_URL . '/api/auth/logout.php') ?>" class="zz-dashboard-signout">
        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">
        <button type="submit" class="zz-dashboard-signout__button">Sign out</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>