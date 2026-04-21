<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

// This preview intentionally skips requireLogin() so shell UI can be tested directly.
if (!isset($_SESSION['user_name']) || !is_string($_SESSION['user_name']) || trim($_SESSION['user_name']) === '') {
    $_SESSION['user_name'] = 'Preview User';
}

$allowedNav = ['home', 'checkin', 'goals', 'coach', 'lessons'];
$requestedNav = strtolower(trim((string) ($_GET['nav'] ?? '')));
$requestedScenario = strtolower(trim((string) ($_GET['scenario'] ?? '')));

$pageTitle = 'Shell Preview';
$pageEyebrow = 'Design System';
$pageHelper = 'A demo of the app shell, navigation, page header, and flash messages.';
$activeNav = 'home';
$showBackButton = false;
$backHref = BASE_URL . '/shell-preview.php';
$hideBottomNav = false;

if ($requestedScenario === 'subpage') {
    $pageTitle = 'Goal Details';
    $pageEyebrow = 'Goals';
    $pageHelper = 'Sub-page shell state with a back button and Goals navigation active.';
    $activeNav = 'goals';
    $showBackButton = true;
    $backHref = BASE_URL . '/shell-preview.php';
} elseif (in_array($requestedNav, $allowedNav, true)) {
    $activeNav = $requestedNav;
}

$shellPreviewUrl = BASE_URL . '/shell-preview.php';
?>
<?php require_once __DIR__ . '/../includes/partials/header.php'; ?>

<section class="zz-stack">
    <article class="zz-card">
        <h2>What to test</h2>
        <p>Switch between desktop and mobile breakpoints, scroll to trigger appbar shadow, and tab through controls to verify focus rings and skip-link behavior.</p>
    </article>

    <article class="zz-card">
        <h2>Toast variants</h2>
        <p>Use these controls to inject temporary toasts into the flash region. They auto-dismiss after about 4.5 seconds.</p>
        <div class="zz-inline">
            <button type="button" class="zz-btn zz-btn--primary" data-zz-toast-trigger data-zz-toast-type="success" data-zz-toast-message="Baseline saved.">
                Trigger Success
            </button>
            <button type="button" class="zz-btn zz-btn--secondary" data-zz-toast-trigger data-zz-toast-type="info" data-zz-toast-message="Heads up: this is an info toast.">
                Trigger Info
            </button>
            <button type="button" class="zz-btn zz-btn--accent" data-zz-toast-trigger data-zz-toast-type="warning" data-zz-toast-message="Reminder: your next check-in is due today.">
                Trigger Warning
            </button>
            <button type="button" class="zz-btn zz-btn--danger" data-zz-toast-trigger data-zz-toast-type="danger" data-zz-toast-message="Unable to save your last action.">
                Trigger Danger
            </button>
        </div>
    </article>

    <article class="zz-card">
        <h2>Sub-page simulation</h2>
        <?php if ($requestedScenario === 'subpage'): ?>
            <p>Sub-page scenario is active. The appbar back button and Goals nav state are currently forced on.</p>
            <p><a href="<?= htmlspecialchars($shellPreviewUrl, ENT_QUOTES, 'UTF-8') ?>">Return to default shell preview</a></p>
        <?php else: ?>
            <p><a href="<?= htmlspecialchars($shellPreviewUrl . '?scenario=subpage', ENT_QUOTES, 'UTF-8') ?>">Simulate goal details page</a></p>
        <?php endif; ?>
    </article>

    <article class="zz-card">
        <h2>Active nav states</h2>
        <p>Choose a nav key to force active highlighting for both desktop and mobile navigation.</p>
        <div class="zz-inline">
            <?php foreach ($allowedNav as $navKey): ?>
                <a class="zz-btn zz-btn--ghost" href="<?= htmlspecialchars($shellPreviewUrl . '?nav=' . urlencode($navKey), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars(ucfirst($navKey), ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="zz-card">
        <h2>Scroll test filler</h2>
        <p>This section adds vertical space so you can verify appbar scroll shadow, fixed mobile bottom nav, and safe-area spacing.</p>
        <div class="zz-stack">
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus non nibh non justo molestie malesuada.</p>
            <p>Aliquam in ante mauris. Suspendisse ut justo et quam faucibus aliquet et nec sem.</p>
            <p>Integer faucibus risus eu vulputate ullamcorper. Curabitur imperdiet, est vitae dictum egestas, justo mi cursus nisl, a ullamcorper dui lorem vel erat.</p>
            <p>Praesent volutpat eros in lectus posuere, non rhoncus est varius. Duis suscipit elit in purus facilisis egestas.</p>
            <p>Donec gravida justo a nibh posuere, sed volutpat mauris malesuada. Sed a sem vel ipsum volutpat tincidunt.</p>
            <p>Morbi luctus sem vel tortor luctus, id pellentesque lacus suscipit. Cras blandit ex at urna tincidunt, eu fermentum erat fermentum.</p>
        </div>
    </article>
</section>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
