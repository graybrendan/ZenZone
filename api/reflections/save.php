<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';

requireLogin();

setFlashMessage('info', 'Reflections are currently disabled in this build.');
authRedirect('dashboard.php');
