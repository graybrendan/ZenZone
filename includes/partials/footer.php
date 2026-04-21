<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

$activeNav = isset($activeNav) && is_string($activeNav) ? trim($activeNav) : 'home';
$hideBottomNav = isset($hideBottomNav) ? (bool) $hideBottomNav : false;

if (!isset($zzPrimaryNavItems) || !is_array($zzPrimaryNavItems) || $zzPrimaryNavItems === []) {
    $zzPrimaryNavItems = [
        [
            'key' => 'home',
            'label' => 'Home',
            'href' => BASE_URL . '/dashboard.php',
            'icon' => '#icon-home',
        ],
        [
            'key' => 'checkin',
            'label' => 'Check-in',
            'href' => BASE_URL . '/checkin.php',
            'icon' => '#icon-checkin',
        ],
        [
            'key' => 'goals',
            'label' => 'Goals',
            'href' => BASE_URL . '/goals/index.php',
            'icon' => '#icon-goals',
        ],
        [
            'key' => 'coach',
            'label' => 'Coach',
            'href' => BASE_URL . '/coach/index.php',
            'icon' => '#icon-coach',
        ],
        [
            'key' => 'lessons',
            'label' => 'Lessons',
            'href' => BASE_URL . '/content/index.php',
            'icon' => '#icon-lessons',
        ],
    ];
}
?>
            </div>
        </main>

        <?php if (!$hideBottomNav): ?>
            <nav class="zz-bottomnav" aria-label="Primary">
                <?php foreach ($zzPrimaryNavItems as $item): ?>
                    <?php $isActive = $activeNav === $item['key']; ?>
                    <a
                        href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                        class="zz-bottomnav__item<?= $isActive ? ' is-active' : '' ?>"
                        data-zz-nav-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <svg class="zz-bottomnav__icon" aria-hidden="true">
                            <use xlink:href="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></use>
                        </svg>
                        <span class="zz-bottomnav__label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <footer class="zz-footer zz-container">
            <p class="zz-footer__text">ZenZone &middot; Capstone Project</p>
            <p class="zz-footer__text">v0.1</p>
        </footer>
    </div>

    <script src="<?= htmlspecialchars(BASE_URL . '/assets/js/zenzone.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
