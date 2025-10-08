<?php
require_once('../connect/security_headers.php');
require_once('../connect/session.php');

header('Content-Type: application/json');

if (!isset($_SESSION['s_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit();
}

$config = require_once __DIR__ . '/../../config/env.php';

echo json_encode(['apiKey' => $config['GMAPS_API_KEY']]);

?>