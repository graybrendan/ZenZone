<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';

requireLogin();

$goalId = (int) ($_GET['id'] ?? 0);
if ($goalId > 0) {
    authRedirect('goals/details.php', ['id' => $goalId]);
}

authRedirect('goals/index.php');
