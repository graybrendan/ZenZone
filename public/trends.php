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
    return $selectedRange === $buttonRange
        ? 'zz-btn zz-btn--primary zz-btn--sm'
        : 'zz-btn zz-btn--secondary zz-btn--sm';
}

$pageTitle = 'ZenScore Trends';
$pageEyebrow = 'Insights';
$pageHelper = 'Track your patterns over time and adjust your next action.';
$activeNav = 'checkin';
$showBackButton = true;
$backHref = $backToResultUrl !== null
    ? BASE_URL . '/' . ltrim($backToResultUrl, '/')
    : BASE_URL . '/dashboard.php';
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-trends-page" aria-labelledby="zz-trends-page-title">
    <h2 id="zz-trends-page-title" class="zz-visually-hidden">ZenScore trends</h2>

    <div class="zz-action-row zz-trends-actions">
        <a href="dashboard.php" class="zz-btn zz-btn--secondary">Back to Dashboard</a>
        <?php if ($backToResultUrl !== null): ?>
            <a href="<?= h($backToResultUrl) ?>" class="zz-btn zz-btn--ghost">Back to Latest Result</a>
        <?php endif; ?>
    </div>

    <div class="zz-trends-metrics" role="list" aria-label="Trend overview metrics">
        <article class="zz-card zz-trends-metric" role="listitem">
            <p class="zz-trends-metric__label">Latest ZenScore</p>
            <p class="zz-trends-metric__value"><?= h(metricValue($overview['latest_zenscore'] ?? null)) ?></p>
        </article>
        <article class="zz-card zz-trends-metric" role="listitem">
            <p class="zz-trends-metric__label">Average ZenScore</p>
            <p class="zz-trends-metric__value"><?= h(metricValue($overview['average_zenscore'] ?? null)) ?></p>
        </article>
        <article class="zz-card zz-trends-metric" role="listitem">
            <p class="zz-trends-metric__label">Total Check-Ins</p>
            <p class="zz-trends-metric__value"><?= h((string) ($overview['total_checkins'] ?? 0)) ?></p>
        </article>
        <article class="zz-card zz-trends-metric" role="listitem">
            <p class="zz-trends-metric__label">Days Active</p>
            <p class="zz-trends-metric__value"><?= h((string) ($overview['active_days'] ?? 0)) ?></p>
        </article>
    </div>

    <nav class="zz-trends-range" aria-label="Trend range filter">
        <a href="trends.php?range=7d<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="<?= h(rangeButtonClass($selectedRange, '7d')) ?>" <?= $selectedRange === '7d' ? 'aria-current="page"' : '' ?>>7d</a>
        <a href="trends.php?range=30d<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="<?= h(rangeButtonClass($selectedRange, '30d')) ?>" <?= $selectedRange === '30d' ? 'aria-current="page"' : '' ?>>30d</a>
        <a href="trends.php?range=all<?= $resultId > 0 ? '&result_id=' . (int) $resultId : '' ?>" class="<?= h(rangeButtonClass($selectedRange, 'all')) ?>" <?= $selectedRange === 'all' ? 'aria-current="page"' : '' ?>>All Time</a>
    </nav>

    <?php if (empty($trendPoints)): ?>
        <article class="zz-card zz-empty-state zz-trends-empty">
            <svg class="zz-empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12h18"></path>
                <path d="M7 16l3-4 3 2 4-6"></path>
            </svg>
            <h2>No trend data yet</h2>
            <p>Complete more check-ins to see your patterns over time.</p>
            <a href="checkin.php" class="zz-btn zz-btn--primary">Complete a Check-In</a>
        </article>
    <?php else: ?>
        <article class="zz-card zz-trends-chart-card">
            <h2 class="zz-trends-card-title">ZenScore Over Time</h2>
            <div class="zz-trends-chart-wrap">
                <canvas id="zenscoreChart" role="img" aria-label="ZenScore over time line chart"></canvas>
            </div>
            <p id="zenscoreChartFallback" class="zz-help zz-trends-chart-fallback" hidden>Chart unavailable right now. Refresh to try again.</p>
        </article>

        <article class="zz-card zz-trends-chart-card">
            <h2 class="zz-trends-card-title">Stress and Key Dimensions (1-7)</h2>
            <p class="zz-help zz-trends-chart-note">Stress is shown as a proxy from emotional balance. Focus uses readiness.</p>
            <div class="zz-trends-chart-wrap">
                <canvas id="dimensionChart" role="img" aria-label="Stress and key dimensions over time line chart"></canvas>
            </div>
            <p id="dimensionChartFallback" class="zz-help zz-trends-chart-fallback" hidden>Chart unavailable right now. Refresh to try again.</p>
        </article>

        <article class="zz-card zz-trends-table-card">
            <h2 class="zz-trends-card-title">Recent Points</h2>
            <div class="zz-trends-table-wrap">
                <table class="zz-trends-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">ZenScore</th>
                            <th scope="col">Confidence</th>
                            <th scope="col">Recovery</th>
                            <th scope="col">Focus</th>
                            <th scope="col">Stress</th>
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
        </article>
    <?php endif; ?>
</section>

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

    const zenscoreFallback = document.getElementById('zenscoreChartFallback');
    const dimensionFallback = document.getElementById('dimensionChartFallback');

    if (!window.Chart) {
        if (zenscoreFallback) {
            zenscoreFallback.removeAttribute('hidden');
        }

        if (dimensionFallback) {
            dimensionFallback.removeAttribute('hidden');
        }

        return;
    }

    const zenscoreCanvas = document.getElementById('zenscoreChart');
    if (zenscoreCanvas) {
        new Chart(zenscoreCanvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'ZenScore',
                        data: zenscoreSeries,
                        borderColor: '#5C8D7B',
                        backgroundColor: 'rgba(92, 141, 123, 0.18)',
                        tension: 0.25,
                        fill: true,
                        pointRadius: 3
                    }
                ]
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
                        borderColor: '#B5553F',
                        backgroundColor: 'rgba(181, 85, 63, 0.10)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Focus',
                        data: focusSeries,
                        borderColor: '#3A7CA5',
                        backgroundColor: 'rgba(58, 124, 165, 0.10)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Confidence',
                        data: confidenceSeries,
                        borderColor: '#5C8D7B',
                        backgroundColor: 'rgba(92, 141, 123, 0.10)',
                        tension: 0.2,
                        pointRadius: 2
                    },
                    {
                        label: 'Recovery',
                        data: recoverySeries,
                        borderColor: '#6C63A8',
                        backgroundColor: 'rgba(108, 99, 168, 0.10)',
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

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
