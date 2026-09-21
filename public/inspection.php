<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/InspectionCalculator.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\InspectionCalculator;
use Glider\Storage;
use Glider\Auth;

Auth::requireActiveAccount();
Storage::ensure();
$equipment = Storage::readEquipment();
$documentCategories = Storage::readDocumentCategories();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$itemIndex = null;
$item = null;
foreach ($equipment as $index => $existing) {
    if ((int) ($existing['id'] ?? 0) === $id) {
        $itemIndex = $index;
        $item = $existing;
        break;
    }
}

if ($item === null) {
    header('Location: /equipment_list.php');
    exit;
}
if (!Auth::isAdmin() && (int) ($item['user_id'] ?? 0) !== (int) (Auth::user()['id'] ?? 0)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

$message = '';
$lastInspectionDate = (string) ($item['last_inspection_date'] ?? '');
$inspectionIntervalMonths = (int) ($item['inspection_interval_months'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastInspectionDate = trim((string) ($_POST['last_inspection_date'] ?? ''));
    $inspectionIntervalMonths = (int) ($_POST['inspection_interval_months'] ?? 0);
    $purchaseDate = trim((string) ($item['purchase_date'] ?? ''));
    $nextInspectionDate = InspectionCalculator::nextDate($lastInspectionDate, $inspectionIntervalMonths);
    $uploadedDocuments = $_FILES['documents'] ?? [];
    $documentCategoryIds = $_POST['document_category_ids'] ?? [];
    $documentCategoryIds = is_array($documentCategoryIds) ? $documentCategoryIds : [];
    $documents = Storage::readEquipmentDocuments();
    $newDocuments = [];
    $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
    ];
    $uploadCount = is_array($uploadedDocuments['name'] ?? null) ? count($uploadedDocuments['name']) : 0;
    if ($purchaseDate === '' || InspectionCalculator::nextDate($purchaseDate, 1) === '') {
        $message = 'Bitte zuerst ein gültiges Anschaffungsdatum am Gerät hinterlegen.';
    } elseif ($inspectionIntervalMonths <= 0) {
        $message = 'Bitte ein Prüfungsintervall größer als 0 Monate angeben.';
    } elseif ($lastInspectionDate === '' || $lastInspectionDate < $purchaseDate || $nextInspectionDate === '') {
        $message = 'Bitte ein gültiges tatsächliches Prüfungsdatum ab dem Anschaffungsdatum angeben.';
    } elseif ($uploadCount !== count($documentCategoryIds)) {
        $message = 'Bitte jedem Dokument eine Dokumentenkategorie zuordnen.';
    } else {
        $categoryIds = array_fill_keys(array_map('intval', array_column($documentCategories, 'id')), true);
        $pendingDocuments = [];
        foreach ($documentCategoryIds as $index => $categoryId) {
            $error = (int) ($uploadedDocuments['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                if (trim((string) $categoryId) !== '') {
                    $message = 'Bitte lade für jede ausgewählte Dokumentenkategorie eine Datei hoch.';
                    break;
                }
                continue;
            }
            if ($error !== UPLOAD_ERR_OK || !isset($categoryIds[(int) $categoryId])) {
                $message = 'Jedes Dokument muss erfolgreich hochgeladen und einer gültigen Dokumentenkategorie zugeordnet werden.';
                break;
            }
            if ((int) ($uploadedDocuments['size'][$index] ?? 0) > 10 * 1024 * 1024) {
                $message = 'Dokumente dürfen maximal 10 MB groß sein.';
                break;
            }
            $temporaryPath = $uploadedDocuments['tmp_name'][$index] ?? '';
            $mimeType = $temporaryPath !== '' ? (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) : false;
            if (!is_string($mimeType) || !in_array($mimeType, $allowedMimeTypes, true)) {
                $message = 'Erlaubt sind PDF-, JPG-, PNG-, WebP- und Textdateien.';
                break;
            }
            $originalName = basename((string) ($uploadedDocuments['name'][$index] ?? 'Dokument'));
            $storedName = bin2hex(random_bytes(16)) . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $pendingDocuments[] = [
                'equipment_id' => $id,
                'category_id' => (int) $categoryId,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'mime_type' => $mimeType,
                'file_size' => (int) $uploadedDocuments['size'][$index],
                'uploaded_at' => date('c'),
                'inspection_date' => $lastInspectionDate,
                'temporary_path' => $temporaryPath,
            ];
        }
        if ($message === '') {
            $nextDocumentId = $documents === [] ? 0 : max(array_map(fn ($document) => (int) ($document['id'] ?? 0), $documents));
            foreach ($pendingDocuments as $pendingDocument) {
                $storedPath = Storage::equipmentUploadDirectory() . '/' . $pendingDocument['stored_name'];
                if (!move_uploaded_file($pendingDocument['temporary_path'], $storedPath)) {
                    $message = 'Ein Dokument konnte nicht gespeichert werden.';
                    break;
                }
                unset($pendingDocument['temporary_path']);
                $pendingDocument['id'] = ++$nextDocumentId;
                $newDocuments[] = $pendingDocument;
            }
            if ($message !== '') {
                foreach ($newDocuments as $newDocument) {
                    @unlink(Storage::equipmentUploadDirectory() . '/' . $newDocument['stored_name']);
                }
                $newDocuments = [];
            }
        }
        if ($message === '') {
            $equipment[$itemIndex]['last_inspection_date'] = $lastInspectionDate;
            $equipment[$itemIndex]['inspection_interval_months'] = $inspectionIntervalMonths;
            $equipment[$itemIndex]['next_inspection_date'] = $nextInspectionDate;
            $inspectionHistory = is_array($equipment[$itemIndex]['inspection_history'] ?? null)
                ? $equipment[$itemIndex]['inspection_history']
                : [];
            $historyExists = false;
            foreach ($inspectionHistory as $inspection) {
                if (trim((string) ($inspection['date'] ?? '')) === $lastInspectionDate) {
                    $historyExists = true;
                    break;
                }
            }
            if (!$historyExists) {
                $nextHistoryId = $inspectionHistory === []
                    ? 0
                    : max(array_map(static fn ($inspection) => (int) ($inspection['id'] ?? 0), $inspectionHistory));
                $inspectionHistory[] = [
                    'id' => $nextHistoryId + 1,
                    'date' => $lastInspectionDate,
                    'label' => 'Prüfung',
                    'recorded_at' => date('c'),
                ];
                $equipment[$itemIndex]['inspection_history'] = $inspectionHistory;
            }
            if ($newDocuments !== []) {
                $documents = array_merge($documents, $newDocuments);
                Storage::saveEquipmentDocuments($documents);
            }
            Storage::saveEquipment($equipment);
            header('Location: /equipment_list.php?saved=1');
            exit;
        }
    }
}

pageHeader(__('page.inspection'));
if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <h2><?= e($item['name'] ?? 'Gerät'); ?></h2>
    <p>Die nächste geplante Prüfung wird aus dem tatsächlichen Prüfungsdatum und dem Prüfungsintervall berechnet.</p>
    <form method="post" class="stacked-form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int) $id; ?>" />
        <label>Durchgeführte Prüfung<input type="date" name="last_inspection_date" value="<?= e($lastInspectionDate); ?>" required /></label>
        <label>Prüfungsintervall für zukünftige Prüfungen (Monate)<input type="number" name="inspection_interval_months" min="1" value="<?= $inspectionIntervalMonths; ?>" required /></label>
        <p class="form-hint">Anschaffungsdatum: <?= e($item['purchase_date'] ?? 'Nicht hinterlegt'); ?></p>
        <fieldset>
            <legend>Dokumente zur Prüfung</legend>
            <div id="document-upload-list">
                <div class="document-upload-row">
                    <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt" />
                    <select name="document_category_ids[]"><option value="">Dokumentenkategorie wählen</option><?php foreach ($documentCategories as $category): ?><option value="<?= (int) ($category['id'] ?? 0); ?>"><?= e($category['name'] ?? ''); ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <button type="button" class="button-muted" id="add-document">Weiteres Dokument</button>
        </fieldset>
        <div class="button-row"><button type="submit">Prüfung speichern</button><a class="button-link" href="/equipment_list.php">Abbrechen</a></div>
    </form>
</section>
<script>
const documentUploadList = document.getElementById('document-upload-list');
const addDocumentButton = document.getElementById('add-document');
if (documentUploadList && addDocumentButton) {
    const updateDocumentRequirement = function (row) {
        const file = row.querySelector('input[type="file"]');
        const category = row.querySelector('select');
        category.required = file.files.length > 0;
    };
    documentUploadList.querySelectorAll('.document-upload-row').forEach(function (row) {
        row.querySelector('input[type="file"]').addEventListener('change', function () { updateDocumentRequirement(row); });
    });
    addDocumentButton.addEventListener('click', function () {
        const row = documentUploadList.firstElementChild.cloneNode(true);
        row.querySelector('input').value = '';
        row.querySelector('select').value = '';
        row.querySelector('input[type="file"]').addEventListener('change', function () { updateDocumentRequirement(row); });
        documentUploadList.appendChild(row);
    });
}
</script>
<?php pageFooter();
