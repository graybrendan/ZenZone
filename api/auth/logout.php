<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/remember_me.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isLoggedIn()) {
        authRedirect('dashboard.php');
    }

    authRedirect('login.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    if (isLoggedIn()) {
        authRedirect('dashboard.php');
    }

    authRedirect('login.php');
}

try {
    zz_remember_revoke(getDB());
} catch (Throwable $e) {
    error_log('Remember-me logout revoke failed: ' . $e->getMessage());
}

logoutUser();

authRedirect('login.php', ['status' => 'logged_out']);
