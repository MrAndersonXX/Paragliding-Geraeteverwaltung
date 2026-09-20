<?php

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/ImageSearchService.php';

use Glider\Auth;
use Glider\Storage;
use Glider\ImageSearchService;

Auth::requireActiveAccount();
Storage::ensure();
header('Content-Type: application/json');

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
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert.']);
    exit;
}

$settings = Storage::readSettings();
if (empty($settings['image_search']['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'Die Bildersuche ist nicht aktiviert.']);
    exit;
}

$candidates = ImageSearchService::searchImages(
    (string) ($item['manufacturer'] ?? ''),
    (string) ($item['name'] ?? ''),
    $settings['image_search']
);

echo json_encode(['success' => true, 'candidates' => $candidates]);
