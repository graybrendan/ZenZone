<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

$displayTimezoneName = trim((string) getEnvOrDefault('APP_TIMEZONE', 'America/New_York'));
try {
    $displayTimezone = new DateTimeZone($displayTimezoneName);
} catch (Throwable $e) {
    $displayTimezone = new DateTimeZone('America/New_York');
}

$nowLocal = new DateTimeImmutable('now', $displayTimezone);
$today = $nowLocal->format('Y-m-d');
$weekStart = $nowLocal->modify('monday this week')->format('Y-m-d');
$monthStart = $nowLocal->format('Y-m-01');

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
$hasBaselineAssessmentToday = false;

$baselineStmt = $db->prepare('
    SELECT
        baseline_score,
        DATE(updated_at) AS baseline_updated_date
    FROM baseline_assessments
    WHERE user_id = :user_id
    ORDER BY updated_at DESC
    LIMIT 1
');
$baselineStmt->execute([
    'user_id' => $userId,
]);
$baseline = $baselineStmt->fetch();

if ($baseline && isset($baseline['baseline_score'])) {
    $baselineScore = (float) $baseline['baseline_score'];
    $hasBaselineAssessmentToday = (string) ($baseline['baseline_updated_date'] ?? '') === $today;
}

$checkinTodayStmt = $db->prepare('
    SELECT
        COUNT(*) AS checkin_count,
        COALESCE(SUM(entry_score), 0) AS total_score
    FROM check_ins
    WHERE user_id = :user_id
      AND checkin_date = :checkin_date
');
$checkinTodayStmt->execute([
    'user_id' => $userId,
    'checkin_date' => $today,
]);
$checkinTodayAggregate = $checkinTodayStmt->fetch() ?: [];
$checkinCountToday = (int) ($checkinTodayAggregate['checkin_count'] ?? 0);
$todayCheckinTotalScore = round((float) ($checkinTodayAggregate['total_score'] ?? 0), 2);
$hasCheckedInToday = $checkinCountToday > 0;

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

$currentGoalsStmt = $db->prepare("
    SELECT
        id,
        title,
        cadence_type,
        cadence_number,
        cadence_unit,
        status,
        is_priority
    FROM goals
    WHERE user_id = :user_id
      AND status IN ('active', 'paused')
    ORDER BY
        CASE
            WHEN status = 'active' THEN 1
            ELSE 2
        END,
        is_priority DESC,
        updated_at DESC,
        created_at DESC
    LIMIT 4
");
$currentGoalsStmt->execute([
    'user_id' => $userId,
]);
$currentGoals = $currentGoalsStmt->fetchAll();

$firstName = trim((string) ($_SESSION['first_name'] ?? ''));
if ($firstName === '') {
    $fullName = trim((string) ($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? ''));
    $firstName = $fullName !== '' ? explode(' ', $fullName)[0] : 'there';
}

$hour = (int) $nowLocal->format('G');
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

$baselineScoreForDisplay = null;
if ($baselineScore !== null) {
    $baselineScoreForDisplay = $baselineScore <= 7
        ? zenzone_convert_to_zenscore((float) $baselineScore)
        : (float) $baselineScore;
}

$todayBaselineInfluenceScore = $hasBaselineAssessmentToday ? $baselineScoreForDisplay : null;
$dailyInfluenceCount = $checkinCountToday + ($todayBaselineInfluenceScore !== null ? 1 : 0);
$dailyCumulativeScore = null;
if ($dailyInfluenceCount > 0) {
    $dailyCumulativeScore = round(
        ($todayCheckinTotalScore + (float) ($todayBaselineInfluenceScore ?? 0)) / $dailyInfluenceCount,
        2
    );
}

$deltaFromBaseline = null;
if ($dailyCumulativeScore !== null && $baselineScoreForDisplay !== null) {
    $deltaFromBaseline = round((float) $dailyCumulativeScore - (float) $baselineScoreForDisplay, 2);
}

$pageTitle = null;
$pageEyebrow = null;
$pageHelper = null;
$activeNav = 'home';
$showBackButton = false;
$dashboardTopLogoPath = is_file(__DIR__ . '/assets/img/log.png')
    ? BASE_URL . '/assets/img/log.png'
    : BASE_URL . '/assets/img/logo.png';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboardScoreRingData(?float $score): ?array
{
    if ($score === null) {
        return null;
    }

    $scoreInt = (int) round($score);
    $scoreInt = max(0, min(100, $scoreInt));

    $circumference = 263.89;
    $progress = round(($scoreInt / 100) * $circumference, 2);
    $remaining = max(0, round($circumference - $progress, 2));

    $toneClass = 'zz-score-ring--low';
    if ($scoreInt >= 70) {
        $toneClass = 'zz-score-ring--high';
    } elseif ($scoreInt >= 45) {
        $toneClass = 'zz-score-ring--mid';
    }

    return [
        'display' => $scoreInt,
        'progress' => $progress,
        'remaining' => $remaining,
        'tone' => $toneClass,
    ];
}

function dashboardGoalCadenceLabel(array $goal): string
{
    $cadenceType = strtolower((string) ($goal['cadence_type'] ?? ''));
    if (in_array($cadenceType, ['daily', 'weekly', 'monthly'], true)) {
        return ucfirst($cadenceType);
    }

    $cadenceNumber = max(1, (int) ($goal['cadence_number'] ?? 1));
    $cadenceUnit = strtolower((string) ($goal['cadence_unit'] ?? 'day'));
    if (!in_array($cadenceUnit, ['day', 'week', 'month'], true)) {
        $cadenceUnit = 'day';
    }

    return $cadenceNumber . ' per ' . $cadenceUnit;
}

function dashboardGoalStatusLabel(array $goal): string
{
    $status = strtolower((string) ($goal['status'] ?? 'active'));
    if ($status === 'paused') {
        return 'Paused';
    }

    return 'Active';
}
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-dashboard-screen zz-dashboard-stack">
    <section class="zz-hero" aria-labelledby="zz-dashboard-greeting">
        <div class="zz-hero__brand">
            <img src="<?= h($dashboardTopLogoPath) ?>" alt="ZenZone">
        </div>
        <p class="zz-hero__eyebrow"><?= h($nowLocal->format('l, F j')) ?></p>
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
                <p class="zz-score-card__label" id="zz-dashboard-score-title">Your Daily ZenScore</p>

                <?php if ($dailyCumulativeScore !== null): ?>
                    <?php $dailyRing = dashboardScoreRingData((float) $dailyCumulativeScore); ?>
                    <?php if ($dailyRing !== null): ?>
                        <div class="zz-score-ring-wrap">
                            <div class="zz-score-ring <?= h((string) $dailyRing['tone']) ?>" role="img" aria-label="<?= h((string) $dailyRing['display']) ?> out of 100">
                                <svg class="zz-score-ring__svg" viewBox="0 0 100 100" aria-hidden="true">
                                    <circle class="zz-score-ring__track" cx="50" cy="50" r="42"></circle>
                                    <circle
                                        class="zz-score-ring__progress"
                                        cx="50"
                                        cy="50"
                                        r="42"
                                        stroke-dasharray="<?= h(number_format((float) $dailyRing['progress'], 2, '.', '')) ?> <?= h(number_format((float) $dailyRing['remaining'], 2, '.', '')) ?>"
                                    ></circle>
                                </svg>
                                <span class="zz-score-ring__value"><?= h((string) $dailyRing['display']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

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

                    <?php if ($hasCheckedInToday && $hasBaselineAssessmentToday): ?>
                        <p class="zz-score-card__context">Includes every check-in plus your baseline assessment updated today.</p>
                    <?php elseif ($hasCheckedInToday): ?>
                        <p class="zz-score-card__context">Includes every check-in logged today.</p>
                    <?php else: ?>
                        <p class="zz-score-card__context">Based on your baseline assessment updated today.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="zz-score-card__context">No daily score yet. Log a check-in or update your baseline today to build your daily ZenScore.</p>
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

            <div class="zz-goals-snapshot__current">
                <h3 class="zz-goals-snapshot__subheading">Current goals</h3>

                <?php if (empty($currentGoals)): ?>
                    <p class="zz-goals-snapshot__empty">No current goals yet.</p>
                    <a class="zz-btn zz-btn--secondary zz-btn--sm" href="goals/create.php">Create Your First Goal</a>
                <?php else: ?>
                    <ul class="zz-goals-snapshot__list">
                        <?php foreach ($currentGoals as $goal): ?>
                            <li class="zz-goals-snapshot__goal">
                                <a class="zz-goals-snapshot__goal-link" href="goals/details.php?id=<?= h((string) ((int) ($goal['id'] ?? 0))) ?>">
                                    <?= h((string) ($goal['title'] ?? 'Goal')) ?>
                                </a>
                                <div class="zz-goals-snapshot__goal-meta">
                                    <span class="zz-badge zz-badge--sage zz-badge--sm"><?= h(dashboardGoalCadenceLabel($goal)) ?></span>
                                    <span class="zz-badge zz-badge--neutral zz-badge--sm"><?= h(dashboardGoalStatusLabel($goal)) ?></span>
                                    <?php if ((int) ($goal['is_priority'] ?? 0) === 1): ?>
                                        <span class="zz-badge zz-badge--gold zz-badge--sm">Priority</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
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
</section>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
