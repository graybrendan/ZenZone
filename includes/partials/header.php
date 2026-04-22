<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (!function_exists('getCsrfToken') || !function_exists('getFlashMessage')) {
    require_once __DIR__ . '/../session.php';
}

$pageTitle = isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== '' ? trim($pageTitle) : 'ZenZone';
$pageEyebrow = isset($pageEyebrow) && is_string($pageEyebrow) ? trim($pageEyebrow) : '';
$pageHelper = isset($pageHelper) && is_string($pageHelper) ? trim($pageHelper) : '';
$activeNav = isset($activeNav) && is_string($activeNav) ? trim($activeNav) : 'home';
$showBackButton = isset($showBackButton) ? (bool) $showBackButton : false;
$backHref = isset($backHref) && is_string($backHref) && trim($backHref) !== '' ? trim($backHref) : BASE_URL . '/dashboard.php';
$hideBottomNav = isset($hideBottomNav) ? (bool) $hideBottomNav : false;
$lockPrimaryNav = isset($lockPrimaryNav) ? (bool) $lockPrimaryNav : false;
$pageDescription = isset($pageDescription) && is_string($pageDescription) && trim($pageDescription) !== ''
    ? trim($pageDescription)
    : 'ZenZone supports mindful daily check-ins, goals, and coaching guidance.';

$allowedNav = ['home', 'checkin', 'goals', 'coach', 'lessons'];
if (!in_array($activeNav, $allowedNav, true)) {
    $activeNav = 'home';
}

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

$projectRoot = dirname(__DIR__, 2);
$assetsImgDir = $projectRoot . '/public/assets/img';
$faviconFile = is_file($assetsImgDir . '/favicon.png') ? 'favicon.png' : 'logo.png';
$iconHref = BASE_URL . '/assets/img/' . $faviconFile;
$appleTouchHref = is_file($assetsImgDir . '/logo.png')
    ? BASE_URL . '/assets/img/logo.png'
    : $iconHref;

$zzBodyClasses = ['zz-body'];
if ($hideBottomNav) {
    $zzBodyClasses[] = 'zz-body--no-bottom-nav';
}

$zzCsrfToken = function_exists('getCsrfToken') ? getCsrfToken() : '';
$zzUserName = isset($_SESSION['user_name']) ? trim((string) $_SESSION['user_name']) : '';
if ($zzUserName === '') {
    $zzUserName = 'ZenZone User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#7A9B76">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> &mdash; ZenZone</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="<?= htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($appleTouchHref, ENT_QUOTES, 'UTF-8') ?>">
    <!-- If a page also loads Bootstrap, include Bootstrap before zenzone.css. -->
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . '/assets/css/zenzone.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="<?= htmlspecialchars(implode(' ', $zzBodyClasses), ENT_QUOTES, 'UTF-8') ?>">
    <a class="zz-skip-link" href="#zz-main-content">Skip to main content</a>

    <svg class="zz-icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="icon-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 10.5L12 3l9 7.5"></path>
            <path d="M5 9.8V21h14V9.8"></path>
            <path d="M9.5 21v-6h5v6"></path>
        </symbol>
        <symbol id="icon-checkin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
        </symbol>
        <symbol id="icon-goals" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8"></circle>
            <circle cx="12" cy="12" r="4"></circle>
            <circle cx="12" cy="12" r="1"></circle>
        </symbol>
        <symbol id="icon-coach" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H9l-4 4V6.5z"></path>
            <circle cx="15.5" cy="10" r="1"></circle>
        </symbol>
        <symbol id="icon-lessons" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h4.5a3 3 0 0 1 2 1 3 3 0 0 1 2-1h4.5A2.5 2.5 0 0 1 21 6.5V18a1 1 0 0 1-1.5.86A6.5 6.5 0 0 0 16 18a6.5 6.5 0 0 0-4 1.38A6.5 6.5 0 0 0 8 18a6.5 6.5 0 0 0-3.5.86A1 1 0 0 1 3 18V6.5z"></path>
        </symbol>
        <symbol id="icon-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6"></path>
        </symbol>
        <symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7h16"></path>
            <path d="M4 12h16"></path>
            <path d="M4 17h16"></path>
        </symbol>
        <symbol id="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18"></path>
            <path d="M6 6l12 12"></path>
        </symbol>
        <symbol id="icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7L9 18l-5-5"></path>
        </symbol>
    </svg>

    <div class="zz-shell">
        <header class="zz-appbar" role="banner">
            <div class="zz-container zz-appbar__inner">
                <div class="zz-appbar__zone zz-appbar__zone--left">
                    <?php if ($showBackButton): ?>
                        <a class="zz-appbar__back" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="Back">
                            <svg class="zz-appbar__icon" aria-hidden="true">
                                <use xlink:href="#icon-back"></use>
                            </svg>
                            <span class="zz-appbar__back-label">Back</span>
                        </a>
                    <?php else: ?>
                        <?php if ($lockPrimaryNav): ?>
                            <span class="zz-appbar__logo" aria-label="ZenZone">
                                <img src="<?= htmlspecialchars($appleTouchHref, ENT_QUOTES, 'UTF-8') ?>" alt="ZenZone">
                            </span>
                        <?php else: ?>
                            <a class="zz-appbar__logo" href="<?= htmlspecialchars(BASE_URL . '/dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
                                <img src="<?= htmlspecialchars($appleTouchHref, ENT_QUOTES, 'UTF-8') ?>" alt="ZenZone">
                            </a>
                            <div class="zz-appbar__menu-wrap zz-appbar__menu-wrap--nav" data-zz-menu-wrap>
                                <button
                                    type="button"
                                    class="zz-appbar__menu"
                                    aria-label="Open navigation menu"
                                    aria-expanded="false"
                                    aria-controls="zz-primary-menu"
                                    data-zz-menu-toggle
                                >
                                    <svg class="zz-appbar__icon zz-appbar__icon--menu" aria-hidden="true">
                                        <use xlink:href="#icon-menu"></use>
                                    </svg>
                                    <svg class="zz-appbar__icon zz-appbar__icon--close" aria-hidden="true">
                                        <use xlink:href="#icon-close"></use>
                                    </svg>
                                </button>
                                <div id="zz-primary-menu" class="zz-appbar__dropdown zz-appbar__dropdown--nav" hidden data-zz-menu-panel>
                                    <nav class="zz-appbar__mobile-nav" aria-label="Primary">
                                        <?php foreach ($zzPrimaryNavItems as $item): ?>
                                            <?php $isNavActive = $activeNav === $item['key']; ?>
                                            <a
                                                class="zz-appbar__mobile-nav-link<?= $isNavActive ? ' is-active' : '' ?>"
                                                href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-zz-nav-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $isNavActive ? 'aria-current="page"' : '' ?>
                                            >
                                                <svg class="zz-appbar__mobile-nav-icon" aria-hidden="true">
                                                    <use xlink:href="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></use>
                                                </svg>
                                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="zz-appbar__zone zz-appbar__zone--center">
                    <span class="zz-appbar__mobile-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="zz-appbar__zone zz-appbar__zone--right">
                    <?php if (!$lockPrimaryNav): ?>
                        <nav class="zz-appbar__nav" aria-label="Primary">
                            <?php foreach ($zzPrimaryNavItems as $item): ?>
                                <?php $isActive = $activeNav === $item['key']; ?>
                                <a
                                    class="zz-appbar__nav-link<?= $isActive ? ' is-active' : '' ?>"
                                    href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-zz-nav-key="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $isActive ? 'aria-current="page"' : '' ?>
                                >
                                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <?php if (!$lockPrimaryNav): ?>
                        <div class="zz-appbar__menu-wrap" data-zz-menu-wrap>
                            <button
                                type="button"
                                class="zz-appbar__menu"
                                aria-label="Open account menu"
                                aria-expanded="false"
                                aria-controls="zz-account-menu"
                                data-zz-menu-toggle
                            >
                                <svg class="zz-appbar__icon zz-appbar__icon--menu" aria-hidden="true">
                                    <use xlink:href="#icon-menu"></use>
                                </svg>
                                <svg class="zz-appbar__icon zz-appbar__icon--close" aria-hidden="true">
                                    <use xlink:href="#icon-close"></use>
                                </svg>
                            </button>
                            <div id="zz-account-menu" class="zz-appbar__dropdown" hidden data-zz-menu-panel>
                                <p class="zz-appbar__menu-label">Signed in as</p>
                                <p class="zz-appbar__menu-value"><?= htmlspecialchars($zzUserName, ENT_QUOTES, 'UTF-8') ?></p>
                                <form method="post" action="<?= htmlspecialchars(BASE_URL . '/api/auth/logout.php', ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($zzCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="zz-appbar__logout">Log out</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="zz-flash-region" aria-live="polite" aria-atomic="true">
            <div class="zz-container">
                <?php require __DIR__ . '/flash.php'; ?>
            </div>
        </div>

        <main id="zz-main-content" class="zz-shell__main" role="main" tabindex="-1">
            <?php if ($pageTitle !== ''): ?>
                <div class="zz-page-header">
                    <div class="zz-container">
                        <?php if ($pageEyebrow !== ''): ?>
                            <p class="zz-section-title"><?= htmlspecialchars($pageEyebrow, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <h1 class="zz-page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if ($pageHelper !== ''): ?>
                            <p class="zz-page-helper"><?= htmlspecialchars($pageHelper, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="zz-page-body zz-container">
