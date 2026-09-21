<?php

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/ImageSearchService.php';

use Glider\Auth;
use Glider\Storage;
use Glider\ImageSearchService;

Auth::requireActiveAccount();
Storage::ensure();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => __('ajax.method_not_allowed')]);
    exit;
}

$equipmentId = (int) ($_POST['id'] ?? 0);
$imageUrl = trim((string) ($_POST['image_url'] ?? ''));
$equipment = Storage::readEquipment();
$index = null;
foreach ($equipment as $existingIndex => $existing) {
    if ((int) ($existing['id'] ?? 0) === $equipmentId) {
        $index = $existingIndex;
        break;
    }
}

$currentUser = Auth::user();
if ($index === null || (!Auth::isAdmin() && (int) ($equipment[$index]['user_id'] ?? 0) !== (int) ($currentUser['id'] ?? 0))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => __('ajax.access_denied')]);
    exit;
}

if ($imageUrl === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => __('ajax.image_url_missing')]);
    exit;
}

try {
    // The submitted URL is untrusted client input, so re-validate it independently of the earlier search results.
    $downloaded = ImageSearchService::downloadAndValidate($imageUrl);
    $storedName = Storage::saveEquipmentImageFile($equipmentId, $downloaded['tmpPath'], $downloaded['extension']);
    $equipment[$index]['image_file'] = $storedName;
    $equipment[$index]['image_source_url'] = $imageUrl;
    $equipment[$index]['image_updated_at'] = date('Y-m-d H:i:s');
    Storage::saveEquipment($equipment);
    echo json_encode(['success' => true, 'image_url' => '/equipment_image.php?id=' . $equipmentId . '&v=' . time()]);
} catch (\Throwable $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => __('ajax.image_import_failed')]);
}
