<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

if (isLoggedIn()) {
    authRedirect('dashboard.php');
}

authRedirect('login.php');
