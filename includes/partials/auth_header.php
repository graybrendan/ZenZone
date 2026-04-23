<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (!function_exists('getCsrfToken') || !function_exists('getFlashMessage')) {
    require_once __DIR__ . '/../session.php';
}

$authVariant = isset($authVariant) && $authVariant === 'landing' ? 'landing' : 'card';
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? trim($pageTitle) : '';
$pageDescription = isset($pageDescription) && is_string($pageDescription) && trim($pageDescription) !== ''
    ? trim($pageDescription)
    : 'ZenZone supports mindful check-ins, focused goals, and grounded coaching moments.';

if ($authVariant === 'landing') {
    $documentTitle = $pageTitle !== '' ? $pageTitle : 'ZenZone — Mindfulness for Athletes';
} else {
    $titlePrefix = $pageTitle !== '' ? $pageTitle : 'ZenZone';
    $documentTitle = $titlePrefix . ' — ZenZone';
}

$logoHref = BASE_URL . '/assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#7A9B76">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ZenZone">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="manifest" href="<?= htmlspecialchars(BASE_URL . '/manifest.json', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL . '/assets/css/zenzone.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="zz-auth-body">
    <svg class="zz-icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 7L9 18l-5-5"></path>
        </symbol>
        <symbol id="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6L6 18"></path>
            <path d="M6 6l12 12"></path>
        </symbol>
        <symbol id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </symbol>
        <symbol id="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3l18 18"></path>
            <path d="M10.6 10.6A3 3 0 0 0 13.4 13.4"></path>
            <path d="M9.9 5.1A11.1 11.1 0 0 1 12 5c6.5 0 10 6 10 6a17.3 17.3 0 0 1-3.3 4.1"></path>
            <path d="M6.1 6.1A17.1 17.1 0 0 0 2 12s3.5 6 10 6c1 0 1.9-.1 2.8-.4"></path>
        </symbol>
    </svg>

    <main class="zz-auth" role="main">
        <a class="zz-auth__brand" href="<?= htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8') ?>" aria-label="ZenZone home">
            <img src="<?= htmlspecialchars($logoHref, ENT_QUOTES, 'UTF-8') ?>" alt="ZenZone" class="zz-auth__logo">
        </a>
        <div class="zz-auth__flash" aria-live="polite" aria-atomic="true">
            <?php require __DIR__ . '/flash.php'; ?>
        </div>
        <?php // authVariant="landing" renders the landing wrapper; default "card" renders the auth card shell. ?>
        <?php if ($authVariant === 'landing'): ?>
            <div class="zz-landing">
        <?php else: ?>
            <div class="zz-auth__card">
        <?php endif; ?>
