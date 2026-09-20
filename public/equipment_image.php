<?php

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Storage.php';

use Glider\Auth;
use Glider\Storage;

Auth::requireActiveAccount();
Storage::ensure();

$equipmentId = (int) ($_GET['id'] ?? 0);
$equipment = Storage::readEquipment();
$item = null;
foreach ($equipment as $existing) {
    if ((int) ($existing['id'] ?? 0) === $equipmentId) {
        $item = $existing;
        break;
    }
}

$currentUser = Auth::user();
if ($item === null || (!Auth::isAdmin() && (int) ($item['user_id'] ?? 0) !== (int) ($currentUser['id'] ?? 0))) {
    http_response_code(404);
    exit;
}

$imageFile = (string) ($item['image_file'] ?? '');
$path = $imageFile !== '' ? Storage::equipmentImageDirectory() . '/' . $imageFile : '';
if ($imageFile === '' || !is_file($path)) {
    http_response_code(404);
    exit;
}

$mime = @mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
