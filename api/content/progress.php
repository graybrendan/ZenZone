<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';

requireLogin();

header('Location: ' . BASE_URL . '/content/index.php?status=feature_disabled');
exit;
