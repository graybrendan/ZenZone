<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'ok',
    'message' => 'ZenZone API is running'
]);