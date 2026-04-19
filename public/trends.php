<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/checkin_functions.php';

requireLogin();

$pdo = getDB();
$userId = (int) $_SESSION['user_id'];

$selectedRange = zenzone_normalize_trend_range((string) ($_GET['range'] ?? '30d'));
$trendPoints = zenzone_get_trend_points($pdo, $userId, $selectedRange);
$overview = zenzone_get_trend_overview_metrics($pdo, $userId);

$resultId = isset($_GET['result_id']) ? (int) $_GET['result_id'] : 0;
$backToResultUrl = null;
if ($resultId > 0 && zenzone_user_owns_checkin($pdo, $userId, $resultId)) {
    $backToResultUrl = 'checkin_result.php?id=' . $resultId;
}

$chartLabels = [];
$zenscoreSeries = [];
$confidenceSeries = [];
$recoverySeries = [];
$focusSeries = [];
$stressSeries = [];

foreach ($trendPoints as $point) {
    $chartLabels[] = (string) ($point['date'] ?? '');
    $zenscoreSeries[] = (float) ($point['zenscore'] ?? 0);
    $confidenceSeries[] = (float) ($point['confidence'] ?? 0);
    $recoverySeries[] = (float) ($point['recovery'] ?? 0);
    $focusSeries[] = (float) ($point['focus'] ?? 0);
    $stressSeries[] = (float) ($point['stress'] ?? 0);
}

$recentPoints = array_slice(array_reverse($trendPoints), 0, 20);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function metricValue($value, int $precision = 2): string
{
    if ($value === null) {
        return 'N/A';
    }

    return number_format((float) $value, $precision);
}

function rangeButtonClass(string $selectedRange, string $buttonRange): string
{
    return $selectedRange === $buttonRange ? 'btn-dark' : 'btn-outline-dark';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trends - ZenZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .chart-card {
            min-height: 360px;
        }

        .chart-wrap {
            position: relative;
            height: 290px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h1 class="h3 mb-0">ZenScore Trends</h1>
                    <p class="text-muted mb-0">Track your patterns over time and adjust your next action.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    <?php if ($backToResultUrl !== null): ?>
                        <a href="<?= h($backToResultUrl) ?>" class="btn btn-outline-dark">Back to Latest Result</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Latest ZenScore</div>
                            <div class="fs-4 fw-bold"><?= h(metricValue($overview['latest_zenscore'] ?? null)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Average ZenScore</div>
                            <div class="fs-4 fw-bold"><?= h(metricValue($overview['average_zenscore'] ?? null)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Total Check-Ins</div>
                            <div class="fs-4 fw-bold"><?= h((string) ($overview['total_checkins'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small">Days Active</div>
                            <div class="fs-4 fw-bold"><?= h((string) ($overview['active_days'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="btn-group" role="group" aria-label="Trend range filter">
                    <a href="trends.php?range=7d<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="btn <?= h(rangeButtonClass($selectedRange, '7d')) ?>">7d</a>
                    <a href="trends.php?range=30d<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="btn <?= h(rangeButtonClass($selectedRange, '30d')) ?>">30d</a>
                    <a href="trends.php?range=all<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="btn <?= h(rangeButtonClass($selectedRange, 'all')) ?>">All Time</a>
                </div>
            </div>

            <?php if (empty($trendPoints)): ?>
                <div class="card">
                    <div class="card-body">
                        <p class="mb-3">No trend data yet. Complete more check-ins to see your patterns.</p>
                        <a href="checkin.php" class="btn btn-dark">Complete a Check-In</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card chart-card mb-3">
                    <div class="card-body">
                        <h2 class="h5">ZenScore Over Time</h2>
                        <div class="chart-wrap">
                            <canvas id="zenscoreChart"></canvas>
                        </div>
                        <p id="zenscoreChartFallback" class="text-muted mt-2 d-none">Chart unavailable right now. Refresh to try again.</p>
                    </div>
                </div>

                <div class="card chart-card mb-3">
                    <div class="card-body">
                        <h2 class="h5">Stress and Key Dimensions (1-7)</h2>
                        <p class="text-muted small mb-2">Stress is shown as a proxy from emotional balance. Focus uses readiness.</p>
                        <div class="chart-wrap">
                            <canvas id="dimensionChart"></canvas>
                        </div>
                        <p id="dimensionChartFallback" class="text-muted mt-2 d-none">Chart unavailable right now. Refresh to try again.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h5">Recent Points</h2>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>ZenScore</th>
                                        <th>Confidence</th>
                                        <th>Recovery</th>
                                        <th>Focus</th>
                                        <th>Stress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPoints as $point): ?>
                                        <tr>
                                            <td><?= h((string) ($point['date'] ?? '')) ?></td>
                                            <td><?= h(number_format((float) ($point['zenscore'] ?? 0), 2)) ?></td>
                                            <td><?= h(number_format((float) ($point['confidence'] ?? 0), 2)) ?></td>
                                            <td><?= h(number_format((float) ($point['recovery'] ?? 0), 2)) ?></td>
                                            <td><?= h(number_format((float) ($point['focus'] ?? 0), 2)) ?></td>
                                            <td><?= h(number_format((float) ($point['stress'] ?? 0), 2)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($trendPoints)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const zenscoreSeries = <?= json_encode($zenscoreSeries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const confidenceSeries = <?= json_encode($confidenceSeries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const recoverySeries = <?= json_encode($recoverySeries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const focusSeries = <?= json_encode($focusSeries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const stressSeries = <?= json_encode($stressSeries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    if (!window.Chart) {
        document.getElementById('zenscoreChartFallback').classList.remove('d-none');
        document.getElementById('dimensionChartFallback').classList.remove('d-none');
        return;
    }

    const zenscoreCanvas = document.getElementById('zenscoreChart');
    if (zenscoreCanvas) {
        new Chart(zenscoreCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ZenScore',
                    data: zenscoreSeries,
                    borderColor: '#1f2937',
                    backgroundColor: 'rgba(31, 41, 55, 0.12)',
                    tension: 0.25,
                    fill: true,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 10
                        }
                    }
                }
            }
        });
    }

    const dimensionCanvas = document.getElementById('dimensionChart');
    if (dimensionCanvas) {
        new Chart(dimensionCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Stress (proxy)',
                        data: stressSeries,
                        borderColor: '#b91c1c',
                        backgroundColor: 'rgba(185, 28, 28, 0.08)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Focus',
                        data: focusSeries,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.08)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Confidence',
                        data: confidenceSeries,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.08)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Recovery',
                        data: recoverySeries,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.08)',
                        tension: 0.2,
                        pointRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 1,
                        max: 7,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
})();
</script>
<?php endif; ?>
</body>
</html>
