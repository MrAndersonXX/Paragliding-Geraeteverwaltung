<?php

require __DIR__ . '/_layout.php';

use Glider\Auth;
use Glider\Storage;

Auth::requireActiveAccount();
Storage::ensure();
$equipmentId = (int) ($_GET['equipment_id'] ?? 0);
$documentId = (int) ($_GET['id'] ?? 0);
$equipment = null;
foreach (Storage::readEquipment() as $item) {
    if ((int) ($item['id'] ?? 0) === $equipmentId) {
        $equipment = $item;
        break;
    }
}
if ($equipment === null || (!Auth::isAdmin() && (int) ($equipment['user_id'] ?? 0) !== (int) (Auth::user()['id'] ?? 0))) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}
$document = Storage::readEquipmentDocument($documentId, $equipmentId);
$storedName = $document === null ? '' : basename((string) ($document['stored_name'] ?? ''));
$path = $storedName !== '' ? Storage::equipmentUploadDirectory() . '/' . $storedName : '';
if ($document === null || $path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Dokument nicht gefunden.');
}
header('Content-Type: ' . ((string) ($document['mime_type'] ?? 'application/octet-stream')));
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', (string) ($document['original_name'] ?? 'Dokument')) . '"');
readfile($path);
