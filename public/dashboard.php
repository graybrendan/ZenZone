<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$flash = getFlashMessage();
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

$userStmt = $db->prepare("
    SELECT baseline_complete
    FROM users
    WHERE id = :user_id
    LIMIT 1
");
$userStmt->execute([
    'user_id' => $userId
]);
$user = $userStmt->fetch();

$baselineComplete = $user ? (int) $user['baseline_complete'] : 0;
$baselineScore = null;
$currentDailyScore = null;

$baselineStmt = $db->prepare("
    SELECT baseline_score
    FROM baseline_assessments
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 1
");
$baselineStmt->execute([
    'user_id' => $userId
]);
$baseline = $baselineStmt->fetch();

if ($baseline) {
    $baselineScore = $baseline['baseline_score'];
}

$needsBaseline = $baselineComplete !== 1 || $baselineScore === null;

$dailyStmt = $db->prepare("
    SELECT daily_score
    FROM daily_zenscore_summary
    WHERE user_id = :user_id
      AND summary_date = :summary_date
    LIMIT 1
");
$dailyStmt->execute([
    'user_id' => $userId,
    'summary_date' => $today
]);
$daily = $dailyStmt->fetch();

if ($daily) {
    $currentDailyScore = $daily['daily_score'];
}

$dailyDoneStmt = $db->prepare("
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date = :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = 'active'
      AND g.cadence_type = 'daily'
      AND g.is_priority = 1
");
$dailyDoneStmt->execute([
    'today' => $today,
    'user_id' => $userId
]);
$dailyDone = (int) $dailyDoneStmt->fetchColumn();

$weeklyDoneStmt = $db->prepare("
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date BETWEEN :week_start AND :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = 'active'
      AND g.cadence_type = 'weekly'
      AND g.is_priority = 1
");
$weeklyDoneStmt->execute([
    'week_start' => $weekStart,
    'today' => $today,
    'user_id' => $userId
]);
$weeklyDone = (int) $weeklyDoneStmt->fetchColumn();

$monthlyDoneStmt = $db->prepare("
    SELECT COUNT(DISTINCT g.id)
    FROM goals g
    INNER JOIN goal_checkins gc
        ON gc.goal_id = g.id
       AND gc.user_id = g.user_id
       AND gc.checkin_date BETWEEN :month_start AND :today
       AND gc.is_complete = 1
    WHERE g.user_id = :user_id
      AND g.status = 'active'
      AND g.cadence_type = 'monthly'
      AND g.is_priority = 1
");
$monthlyDoneStmt->execute([
    'month_start' => $monthStart,
    'today' => $today,
    'user_id' => $userId
]);
$monthlyDone = (int) $monthlyDoneStmt->fetchColumn();

$completedGoalsStmt = $db->prepare("
    SELECT COUNT(*)
    FROM goals
    WHERE user_id = :user_id
      AND status = 'completed'
");
$completedGoalsStmt->execute([
    'user_id' => $userId
]);
$completedGoalsCount = (int) $completedGoalsStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ZenZone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            line-height: 1.45;
        }

        .section {
            margin-top: 28px;
        }

        .card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .button-link {
            display: inline-block;
            padding: 10px 14px;
            border: 1px solid #999;
            border-radius: 6px;
            text-decoration: none;
            color: #000;
            background: #f7f7f7;
        }

        .snapshot-row {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <h1>Dashboard</h1>

    <?php if ($flash): ?>
        <div class="card" style="margin-bottom: 16px; border-color: <?php echo (($flash['type'] ?? '') === 'error') ? '#d6a3a3' : '#9bc29b'; ?>; background: <?php echo (($flash['type'] ?? '') === 'error') ? '#fff0f0' : '#eef9ee'; ?>;">
            <?php echo htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>.</p>

    <div class="section">
        <h2>ZenScore</h2>

        <div class="card">
            <?php if ($needsBaseline): ?>
                <p>Baseline assessment is not complete yet.</p>
                <p>Complete your baseline to unlock baseline comparison in future check-ins.</p>
                <a class="button-link" href="baseline.php">Complete Baseline</a>
            <?php elseif ($currentDailyScore !== null): ?>
                <p><strong>Today's ZenScore:</strong> <?php echo htmlspecialchars((string) $currentDailyScore, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Baseline Score:</strong> <?php echo htmlspecialchars((string) $baselineScore, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php else: ?>
                <p><strong>Baseline Score:</strong> <?php echo htmlspecialchars((string) $baselineScore, ENT_QUOTES, 'UTF-8'); ?></p>
                <p>No daily ZenScore summary yet for today.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2>Today Snapshot</h2>

        <div class="card">
            <p class="snapshot-row"><strong>Daily Priority Goals Completed Today:</strong> <?php echo $dailyDone; ?> / 3</p>
            <p class="snapshot-row"><strong>Weekly Priority Goals Completed This Week:</strong> <?php echo $weeklyDone; ?> / 2</p>
            <p class="snapshot-row"><strong>Monthly Priority Goals Completed This Month:</strong> <?php echo $monthlyDone; ?> / 1</p>
            <p class="snapshot-row"><strong>Completed Goals:</strong> <?php echo $completedGoalsCount; ?></p>
        </div>
    </div>

    <div class="section">
        <h2>Quick Actions</h2>

        <div class="actions">
            <a class="button-link" href="checkin.php">Check In</a>
            <a class="button-link" href="trends.php">View Trends</a>
            <a class="button-link" href="goals/index.php">Today's Goals</a>
            <a class="button-link" href="goals/create.php">Create Goal</a>
        </div>
    </div>

    <div class="section">
        <h2>Modules</h2>

        <div class="actions">
            <a class="button-link" href="goals/index.php">Goals</a>
            <a class="button-link" href="coach/index.php">Coach</a>
            <a class="button-link" href="content/index.php">Lessons</a>
        </div>
    </div>

    <div class="section">
        <form method="POST" action="../api/auth/logout.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="button-link" type="submit">Log out</button>
        </form>
    </div>
</body>
</html>


