<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/zenscore.php';

requireLogin();

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];
$today = date('Y-m-d');

$labels = zenzone_labels();
$flash = getFlashMessage();
$todaySummary = zenzone_get_daily_summary($pdo, $userId, $today);

$formValues = ['activity_context' => ''];
foreach (array_keys($labels) as $field) {
    $formValues[$field] = 4;
}

if ((string) getOldInput('checkin_form', '') === 'checkin') {
    foreach (array_keys($labels) as $field) {
        $value = (int) getOldInput($field, '4');
        if ($value < 1 || $value > 7) {
            $value = 4;
        }

        $formValues[$field] = $value;
    }

    $formValues['activity_context'] = (string) getOldInput('activity_context', '');
    clearOldInput();
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flashClass(string $type): string
{
    if ($type === 'error') {
        return 'danger';
    }

    if ($type === 'success') {
        return 'success';
    }

    return 'secondary';
}
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
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h1 class="h3 mb-0">Check In</h1>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    <a href="trends.php" class="btn btn-outline-dark">View Trends</a>
                </div>
            </div>

            <?php if ($todaySummary): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="text-muted small">Today's ZenScore</div>
                                <div class="fs-3 fw-bold"><?= h(number_format((float) $todaySummary['daily_zenscore'], 2)) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Latest ZenScore</div>
                                <div class="fs-3 fw-bold"><?= h(number_format((float) $todaySummary['latest_zenscore'], 2)) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Check-Ins Today</div>
                                <div class="fs-3 fw-bold"><?= h((string) $todaySummary['total_checkins']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= h(flashClass((string) ($flash['type'] ?? ''))) ?>">
                    <?= h((string) ($flash['message'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="../api/checkin/submit.php">
                        <input type="hidden" name="csrf_token" value="<?= h(getCsrfToken()) ?>">

                        <?php foreach ($labels as $field => $label): ?>
                            <div class="mb-4">
                                <label for="<?= h($field) ?>" class="form-label d-flex justify-content-between">
                                    <span><?= h($label) ?></span>
                                    <span id="<?= h($field) ?>_value"><?= h((string) $formValues[$field]) ?></span>
                                </label>
                                <input
                                    type="range"
                                    class="form-range"
                                    min="1"
                                    max="7"
                                    step="1"
                                    id="<?= h($field) ?>"
                                    name="<?= h($field) ?>"
                                    value="<?= h((string) $formValues[$field]) ?>"
                                    oninput="document.getElementById('<?= h($field) ?>_value').textContent = this.value"
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
                                maxlength="1000"
                            ><?= h($formValues['activity_context']) ?></textarea>
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
