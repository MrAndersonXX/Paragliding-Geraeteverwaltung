<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;

Auth::requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$allowedKeys = ['hide_retired_equipment'];
$key = (string) ($_POST['key'] ?? '');
if (!in_array($key, $allowedKeys, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_key']);
    exit;
}

Auth::setPreference($key, ($_POST['value'] ?? '') === '1');
echo json_encode(['ok' => true]);
