<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';

requireLogin();

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];
$today = date('Y-m-d');

$error = null;
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $scores = zenzone_validate_scores($_POST);
        $activityContext = $_POST['activity_context'] ?? null;

        $inserted = zenzone_insert_checkin($pdo, $userId, $scores, $activityContext);
        zenzone_rebuild_daily_summary($pdo, $userId, $inserted['checkin_date']);

        $summary = zenzone_get_daily_summary($pdo, $userId, $inserted['checkin_date']);
        $feedback = zenzone_get_feedback($scores);
        $feedback['checkin_type'] = $inserted['checkin_type'];
        $feedback['latest_zenscore'] = $inserted['zenscore'];
        $feedback['daily_zenscore'] = $summary ? (float) $summary['daily_zenscore'] : $inserted['zenscore'];
        $feedback['total_checkins'] = $summary ? (int) $summary['total_checkins'] : 1;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$todaySummary = zenzone_get_daily_summary($pdo, $userId, $today);
$labels = zenzone_labels();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Check In</h1>
                <a href="trends.php" class="btn btn-outline-dark">View Trends</a>
            </div>

            <?php if ($todaySummary): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="text-muted small">Today’s ZenScore</div>
                                <div class="fs-3 fw-bold"><?= htmlspecialchars(number_format((float) $todaySummary['daily_zenscore'], 2)) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Latest ZenScore</div>
                                <div class="fs-3 fw-bold"><?= htmlspecialchars(number_format((float) $todaySummary['latest_zenscore'], 2)) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Check-Ins Today</div>
                                <div class="fs-3 fw-bold"><?= htmlspecialchars((string) $todaySummary['total_checkins']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($feedback): ?>
                <div class="alert alert-success">
                    <div class="fw-semibold mb-2"><?= htmlspecialchars($feedback['headline']) ?></div>
                    <div class="mb-2">Check-in type: <?= htmlspecialchars(ucfirst($feedback['checkin_type'])) ?></div>
                    <div class="mb-2">Latest ZenScore: <?= htmlspecialchars(number_format($feedback['latest_zenscore'], 2)) ?></div>
                    <div class="mb-2">Today’s ZenScore: <?= htmlspecialchars(number_format($feedback['daily_zenscore'], 2)) ?></div>
                    <div class="mb-2">Today’s check-ins: <?= htmlspecialchars((string) $feedback['total_checkins']) ?></div>
                    <div class="mb-2"><?= htmlspecialchars($feedback['insight']) ?></div>
                    <div><?= htmlspecialchars($feedback['recommendation']) ?></div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <?php foreach ($labels as $field => $label): ?>
                            <div class="mb-4">
                                <label for="<?= htmlspecialchars($field) ?>" class="form-label d-flex justify-content-between">
                                    <span><?= htmlspecialchars($label) ?></span>
                                    <span id="<?= htmlspecialchars($field) ?>_value"><?= isset($_POST[$field]) ? (int) $_POST[$field] : 4 ?></span>
                                </label>
                                <input
                                    type="range"
                                    class="form-range"
                                    min="1"
                                    max="7"
                                    step="1"
                                    id="<?= htmlspecialchars($field) ?>"
                                    name="<?= htmlspecialchars($field) ?>"
                                    value="<?= isset($_POST[$field]) ? (int) $_POST[$field] : 4 ?>"
                                    oninput="document.getElementById('<?= htmlspecialchars($field) ?>_value').textContent = this.value"
                                    required
                                >
                            </div>
                        <?php endforeach; ?>

                        <div class="mb-4">
                            <label for="activity_context" class="form-label">What are you doing?</label>
                            <textarea
                                class="form-control"
                                id="activity_context"
                                name="activity_context"
                                rows="3"
                            ><?= isset($_POST['activity_context']) ? htmlspecialchars((string) $_POST['activity_context']) : '' ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-dark">Submit Check-In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>