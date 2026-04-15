<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

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

logoutUser();

authRedirect('login.php', ['status' => 'logged_out']);
