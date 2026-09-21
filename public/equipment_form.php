<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/InspectionCalculator.php';
require_once __DIR__ . '/../src/EquipmentTimeline.php';
require_once __DIR__ . '/../src/ImageSearchService.php';

use Glider\Storage;
use Glider\InspectionCalculator;
use Glider\EquipmentTimeline;
use Glider\ImageSearchService;
use Glider\Auth;

Auth::requireActiveAccount();
Storage::ensure();
$equipment = Storage::readEquipment();
$documents = Storage::readEquipmentDocuments();
$documentCategories = Storage::readDocumentCategories();
$users = Storage::readUsers();
$equipmentTypes = Storage::readEquipmentTypes();
$editId = (int) ($_GET['id'] ?? $_GET['edit'] ?? $_POST['id'] ?? 0);
$editItem = null;
foreach ($equipment as $existing) {
    if ((int) ($existing['id'] ?? 0) === $editId) {
        $editItem = $existing;
        break;
    }
}
$message = isset($_GET['saved']) ? 'Gerät wurde gespeichert.' : '';
$formEquipmentType = '';
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$visibleEquipment = $isAdmin ? $equipment : array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
$isNew = $editItem === null;
$editMode = $isNew || isset($_GET['edit']) || $_SERVER['REQUEST_METHOD'] === 'POST';
$timelineEntries = $editItem === null ? [] : EquipmentTimeline::entries($editItem, $documents);
$equipmentDocuments = $editItem === null ? [] : array_values(array_filter($documents, static fn ($document) => (int) ($document['equipment_id'] ?? 0) === (int) $editItem['id']));
if (!$isAdmin && $editItem !== null && (int) ($editItem['user_id'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}
$assignedEquipmentId = 0;
$assignedEquipmentName = 'Nicht zugeordnet';
$assignedUserName = 'Nicht zugeordnet';
if ($editItem !== null) {
    foreach ($equipment as $relatedEquipment) {
        if ((int) ($relatedEquipment['id'] ?? 0) === (int) ($editItem['assigned_equipment_id'] ?? 0)) {
            $assignedEquipmentName = trim((string) ($relatedEquipment['name'] ?? '')) ?: 'Nicht zugeordnet';
            break;
        }
    }
    foreach ($users as $user) {
        if ((int) ($user['id'] ?? 0) === (int) ($editItem['user_id'] ?? 0)) {
            $assignedUserName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : 0;
    if (($_POST['action'] ?? '') === 'archive_equipment') {
        foreach ($equipment as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $equipment[$index]['status'] = 'retired';
                $equipment[$index]['retired_at'] = date('Y-m-d');
                Storage::saveEquipment($equipment);
                header('Location: /equipment_list.php?saved=1');
                exit;
            }
        }
        $message = 'Gerät konnte nicht archiviert werden.';
    }
    if (($_POST['action'] ?? '') === 'delete_equipment' && $isAdmin) {
        $equipment = array_values(array_filter($equipment, static fn ($existing) => (int) ($existing['id'] ?? 0) !== $id));
        Storage::saveEquipment($equipment);
        Storage::deleteEquipmentDocuments($id);
        header('Location: /equipment_list.php?saved=1');
        exit;
    }
    if (($_POST['action'] ?? '') === 'upload_documents' && $editItem !== null) {
        $uploadedDocuments = $_FILES['documents'] ?? [];
        $documentCategoryIds = $_POST['document_category_ids'] ?? [];
        $documentCategoryIds = is_array($documentCategoryIds) ? $documentCategoryIds : [];
        $result = Storage::storeUploadedDocuments($editId, $uploadedDocuments, $documentCategoryIds);
        if ($result['error'] !== '') {
            $message = $result['error'];
            $editMode = false;
        } else {
            header('Location: /equipment_form.php?id=' . $editId . '&saved=1');
            exit;
        }
    }
    if (($_POST['action'] ?? '') === 'delete_document' && $editItem !== null) {
        if (Storage::deleteEquipmentDocument((int) ($_POST['document_id'] ?? 0), $editId)) {
            header('Location: /equipment_form.php?id=' . $editId . '&saved=1');
            exit;
        }
        $message = 'Dokument konnte nicht gelöscht werden.';
        $editMode = false;
    }
    $id = $id ?: ((count($equipment) > 0 ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $equipment)) : 0) + 1);
    $formEquipmentType = trim((string) ($_POST['equipment_type'] ?? ''));
    $assignedEquipmentId = (int) ($_POST['assigned_equipment_id'] ?? 0);
    $assignedEquipment = null;
    foreach ($visibleEquipment as $existing) {
        if ((int) ($existing['id'] ?? 0) === $assignedEquipmentId) {
            $assignedEquipment = $existing;
            break;
        }
    }
    if ($formEquipmentType === 'Rettungsgerät' && ($assignedEquipment === null || !in_array(($assignedEquipment['equipment_type'] ?? $assignedEquipment['category'] ?? ''), ['Gurtzeug', 'Frontcontainer'], true))) {
        $message = 'Ein Rettungsgerät muss einem Gerät vom Typ Gurtzeug oder Frontcontainer zugeordnet werden.';
    }
    $submittedUserId = $isAdmin ? (int) ($_POST['user_id'] ?? 0) : (int) ($currentUser['id'] ?? 0);
    $item = array_merge(is_array($editItem) ? $editItem : [], [
        'id' => $id,
        'name' => trim((string) ($_POST['name'] ?? '')),
        'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
        'equipment_type' => $formEquipmentType,
        'assigned_equipment_id' => $assignedEquipmentId,
        'size' => trim((string) ($_POST['size'] ?? '')),
        'serial_number' => trim((string) ($_POST['serial_number'] ?? '')),
        'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
        'last_inspection_date' => trim((string) ($_POST['last_inspection_date'] ?? '')),
        'user_id' => $submittedUserId,
        'status' => trim((string) ($_POST['status'] ?? 'active')),
        'inspection_interval_months' => (int) ($_POST['inspection_interval_months'] ?? 0),
        'manufacturer_check_date' => trim((string) ($_POST['manufacturer_check_date'] ?? '')),
        'manufacturer_validity_days' => (int) ($_POST['manufacturer_validity_days'] ?? 0),
        'max_operating_days' => (int) ($_POST['max_operating_days'] ?? 0),
        'retired_at' => trim((string) ($_POST['retired_at'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'notifications' => [
            '30_days' => !empty($_POST['notification_30_days']),
            '14_days' => !empty($_POST['notification_14_days']),
            '7_days' => !empty($_POST['notification_7_days']),
            'due' => !empty($_POST['notification_due']),
            'retired' => !empty($_POST['notification_retired']),
        ],
    ]);
    if ($item['purchase_date'] === '' || InspectionCalculator::nextDate($item['purchase_date'], 1) === '') {
        $message = 'Bitte ein Anschaffungsdatum angeben.';
    } elseif ($item['inspection_interval_months'] <= 0) {
        $message = 'Bitte ein Prüfungsintervall größer als 0 Monate angeben.';
    } elseif ($item['last_inspection_date'] !== '' && (InspectionCalculator::nextDate($item['last_inspection_date'], 1) === '' || $item['last_inspection_date'] < $item['purchase_date'])) {
        $message = 'Die letzte tatsächliche Prüfung darf nicht vor dem Anschaffungsdatum liegen.';
    }
    if ($message === '') {
        $baseDate = $item['last_inspection_date'] !== '' ? $item['last_inspection_date'] : $item['purchase_date'];
        $item['next_inspection_date'] = InspectionCalculator::nextDate($baseDate, $item['inspection_interval_months']);
        if ($item['next_inspection_date'] === '') {
            $message = 'Bitte gültige Datumswerte angeben.';
        }
    }
    if ($message === '') {
        $updated = false;
        foreach ($equipment as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $equipment[$index] = $item;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $equipment[] = $item;
        }
        Storage::saveEquipment($equipment);

        $settings = Storage::readSettings();
        if (empty($item['image_file']) && $item['manufacturer'] !== '' && $item['name'] !== '' && !empty($settings['image_search']['enabled'])) {
            try {
                $candidates = ImageSearchService::searchImages($item['manufacturer'], $item['name'], $settings['image_search']);
                if ($candidates !== []) {
                    $downloaded = ImageSearchService::downloadAndValidate($candidates[0]['link']);
                    $storedName = Storage::saveEquipmentImageFile($id, $downloaded['tmpPath'], $downloaded['extension']);
                    foreach ($equipment as $index => $existing) {
                        if ((int) ($existing['id'] ?? 0) === $id) {
                            $equipment[$index]['image_file'] = $storedName;
                            $equipment[$index]['image_source_url'] = $candidates[0]['link'];
                            $equipment[$index]['image_updated_at'] = date('Y-m-d H:i:s');
                            break;
                        }
                    }
                    Storage::saveEquipment($equipment);
                }
            } catch (\Throwable $exception) {
                // Auto-fetch failures must never block saving the equipment record.
                error_log('Image auto-fetch failed: ' . $exception->getMessage());
            }
        }

        $returnTo = (string) ($_POST['return_to'] ?? '');
        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/equipment_form.php?id=' . $id . '&saved=1';
        }
        header('Location: ' . $returnTo);
        exit;
    }
}

pageHeader($editMode ? ($editItem ? __('page.equipment_edit') : __('page.equipment_new')) : __('page.equipment_details'));
?>
<?php if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Geräteverwaltung</p><h2><?= $editMode ? ($editItem ? 'Gerät bearbeiten' : 'Neues Gerät') : 'Gerätedetails'; ?></h2></div><a class="button-link button-secondary" href="/equipment_list.php">Zur Geräteliste</a></div>
<?php if ($editMode): ?><form method="post" class="stacked-form" data-edit-form>
        <input type="hidden" name="id" value="<?= e($editItem['id'] ?? ''); ?>" />
        <input type="hidden" name="return_to" value="" />
        <input type="hidden" name="last_inspection_date" value="<?= e($editItem['last_inspection_date'] ?? ''); ?>" />
        <div class="form-sections">
        <?php if ($editItem !== null): ?>
            <section class="form-section equipment-photo-section">
                <button type="button" class="equipment-photo-button" data-open-image-picker data-equipment-id="<?= (int) $editItem['id']; ?>" aria-label="Artikelbild ändern">
                    <?php if (($editItem['image_file'] ?? '') !== ''): ?>
                        <img class="equipment-photo" src="/equipment_image.php?id=<?= (int) $editItem['id']; ?>" alt="Artikelbild <?= e($editItem['name'] ?? ''); ?>" />
                    <?php else: ?>
                        <span class="equipment-photo equipment-photo-placeholder">Kein Bild vorhanden</span>
                    <?php endif; ?>
                </button>
                <span class="info-field" tabindex="0" aria-label="Information zum Artikelbild">i<span class="info-explanation" role="tooltip">Klicke auf das Bild, um ein anderes Artikelbild auszuwählen.</span></span>
            </section>
        <?php endif; ?>
        <section class="form-section"><h3>Gerät</h3>
        <div class="row two-col">
            <label><span class="field-label">Gerätename</span><input type="text" name="name" value="<?= e($editItem['name'] ?? ''); ?>" required /></label>
            <label><span class="field-label">Gerätetyp</span><select name="equipment_type" id="equipment-type" required><?php foreach ($equipmentTypes as $type): ?><?php $typeName = (string) ($type['name'] ?? ''); ?><option value="<?= e($typeName); ?>" <?= (($formEquipmentType ?: ($editItem['equipment_type'] ?? $editItem['category'] ?? '')) === $typeName) ? 'selected' : ''; ?>><?= e($typeName); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="row three-col">
            <label><span class="field-label">Hersteller</span><input type="text" name="manufacturer" value="<?= e($editItem['manufacturer'] ?? ''); ?>" /></label>
            <label><span class="field-label">Größe</span><input type="text" name="size" value="<?= e($editItem['size'] ?? ''); ?>" /></label>
        </div>
        <div class="row three-col">
            <label><span class="field-label">Seriennummer</span><input type="text" name="serial_number" value="<?= e($editItem['serial_number'] ?? ''); ?>" /></label>
            <label><span class="field-label">Anschaffungsdatum</span><input type="date" name="purchase_date" value="<?= e($editItem['purchase_date'] ?? ''); ?>" required /></label>
            <label><span class="field-label">Status</span><select name="status"><option value="active" <?= (($editItem['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>aktiv</option><option value="inspection" <?= (($editItem['status'] ?? '') === 'inspection') ? 'selected' : ''; ?>>in Prüfung</option><option value="retired" <?= (($editItem['status'] ?? '') === 'retired') ? 'selected' : ''; ?>>ausgemustert</option></select></label>
        </div>
        </section>
        <section class="form-section"><h3>Zuordnung &amp; Status</h3>
        <div class="row stacked-fields">
            <label><span class="field-label">Zugeordnetes Gerät</span><select name="assigned_equipment_id" id="assigned-equipment"><option value="0">Nicht zugeordnet</option><?php foreach ($visibleEquipment as $otherEquipment): ?><?php $otherId = (int) ($otherEquipment['id'] ?? 0); ?><?php if ($otherId === (int) ($editItem['id'] ?? 0)) { continue; } ?><?php $otherType = (string) ($otherEquipment['equipment_type'] ?? $otherEquipment['category'] ?? ''); ?><option value="<?= $otherId; ?>" data-equipment-type="<?= e($otherType); ?>" <?= ((int) ($editItem['assigned_equipment_id'] ?? 0) === $otherId || $assignedEquipmentId === $otherId) ? 'selected' : ''; ?>><?= e(($otherEquipment['name'] ?? '') . ' (' . $otherType . ')'); ?></option><?php endforeach; ?></select></label>
            <?php if ($isAdmin): ?><label><span class="field-label">Zugeordneter Benutzer</span><select name="user_id"><option value="0">Nicht zugeordnet</option><?php foreach ($users as $user): ?><option value="<?= (int) ($user['id'] ?? 0); ?>" <?= ((int) ($editItem['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) ? 'selected' : ''; ?>><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['emoji'] ?? '')); ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="user_id" value="<?= (int) ($currentUser['id'] ?? 0); ?>" /><?php endif; ?>
            <label><span class="field-label">Prüfungsintervall in Monaten</span><input type="number" name="inspection_interval_months" min="1" value="<?= e($editItem['inspection_interval_months'] ?? '12'); ?>" required /></label>
        </div>
        </section>
        <section class="form-section"><h3>Prüfungen</h3>
        <div class="row three-col">
            <div class="field-readonly"><span class="field-label">Letzte tatsächliche Prüfung</span><span><?= e($editItem['last_inspection_date'] ?? 'Noch keine Prüfung erfasst'); ?></span></div>
            <label><span class="field-label">Nächste geplante Prüfung <span class="info-field" tabindex="0" aria-label="Information zur Berechnung">i<span class="info-explanation" role="tooltip">Wird aus der letzten tatsächlichen Prüfung und dem Prüfungsintervall berechnet. Ohne Prüfung wird das Anschaffungsdatum verwendet.</span></span></span><input type="date" name="next_inspection_date" value="<?= e($editItem['next_inspection_date'] ?? ''); ?>" readonly /></label>
        </div>
        </section>
        <section class="form-section"><h3>Hersteller &amp; Notizen</h3>
        <div class="row three-col">
            <label><span class="field-label">Hersteller-Nachprüfung</span><input type="date" name="manufacturer_check_date" value="<?= e($editItem['manufacturer_check_date'] ?? ''); ?>" /></label>
            <label><span class="field-label">Gültigkeit in Tagen</span><input type="number" name="manufacturer_validity_days" min="0" value="<?= e($editItem['manufacturer_validity_days'] ?? '0'); ?>" /></label>
            <label><span class="field-label">Max. Betriebsdauer in Tagen</span><input type="number" name="max_operating_days" min="0" value="<?= e($editItem['max_operating_days'] ?? '0'); ?>" /></label>
        </div>
        <div class="row two-col">
            <label><span class="field-label">Ausmusterungsdatum</span><input type="date" name="retired_at" value="<?= e($editItem['retired_at'] ?? ''); ?>" /></label>
            <label><span class="field-label">Notiz</span><textarea name="notes" rows="3"><?= e($editItem['notes'] ?? ''); ?></textarea></label>
        </div>
        </section>
        </div>
        <fieldset><legend>Benachrichtigungen</legend><div class="checkbox-row">
            <label><input type="checkbox" name="notification_30_days" value="1" <?= !empty($editItem['notifications']['30_days']) || !$editItem ? 'checked' : ''; ?> /> 30 Tage</label>
            <label><input type="checkbox" name="notification_14_days" value="1" <?= !empty($editItem['notifications']['14_days']) ? 'checked' : ''; ?> /> 14 Tage</label>
            <label><input type="checkbox" name="notification_7_days" value="1" <?= !empty($editItem['notifications']['7_days']) ? 'checked' : ''; ?> /> 7 Tage</label>
            <label><input type="checkbox" name="notification_due" value="1" <?= !empty($editItem['notifications']['due']) || !$editItem ? 'checked' : ''; ?> /> überfällig</label>
            <label><input type="checkbox" name="notification_retired" value="1" <?= !empty($editItem['notifications']['retired']) ? 'checked' : ''; ?> /> Ausmusterung</label>
        </div></fieldset>
        <button type="submit"><?= $editItem ? 'Gerät speichern' : 'Gerät anlegen'; ?></button>
    </form>
<?php else: ?>
    <div class="detail-sections">
        <section class="detail-section equipment-photo-section">
            <?php if (($editItem['image_file'] ?? '') !== ''): ?>
                <button type="button" class="equipment-photo-button" data-open-image-viewer aria-label="Artikelbild vergrößern">
                    <img class="equipment-photo" src="/equipment_image.php?id=<?= (int) ($editItem['id'] ?? 0); ?>" alt="Artikelbild <?= e($editItem['name'] ?? ''); ?>" />
                </button>
                <span class="info-field" tabindex="0" aria-label="Information zum Artikelbild">i<span class="info-explanation" role="tooltip">Klicke auf das Bild, um es vergrößert anzuzeigen.</span></span>
            <?php else: ?>
                <span class="equipment-photo equipment-photo-placeholder">Kein Bild vorhanden</span>
            <?php endif; ?>
        </section>
        <section class="detail-section">
            <h3>Gerät</h3>
            <div class="detail-grid">
                <div class="detail-row"><span class="detail-label">Gerätename</span><span class="detail-value"><?= e($editItem['name'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Gerätetyp</span><span class="detail-value"><?= e($editItem['equipment_type'] ?? $editItem['category'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Hersteller</span><span class="detail-value"><?= e($editItem['manufacturer'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Größe</span><span class="detail-value"><?= e($editItem['size'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Seriennummer</span><span class="detail-value"><?= e($editItem['serial_number'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Anschaffungsdatum</span><span class="detail-value"><?= e($editItem['purchase_date'] ?? ''); ?></span></div>
            </div>
        </section>
        <section class="detail-section">
            <h3>Zuordnung &amp; Status</h3>
            <div class="detail-grid">
                <div class="detail-row"><span class="detail-label">Zugeordnetes Gerät</span><span class="detail-value"><?= e($assignedEquipmentName); ?></span></div>
                <div class="detail-row"><span class="detail-label">Benutzer</span><span class="detail-value"><?= e($assignedUserName); ?></span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><?= e($editItem['status'] ?? 'active'); ?></span></div>
                <div class="detail-row"><span class="detail-label">Ausmusterungsdatum</span><span class="detail-value"><?= e($editItem['retired_at'] ?? ''); ?></span></div>
            </div>
        </section>
        <section class="detail-section">
            <h3>Prüfungen</h3>
            <div class="detail-grid">
                <div class="detail-row"><span class="detail-label">Prüfungsintervall</span><span class="detail-value"><?= (int) ($editItem['inspection_interval_months'] ?? 0); ?> Monate</span></div>
                <div class="detail-row"><span class="detail-label">Letzte tatsächliche Prüfung</span><span class="detail-value"><?= e($editItem['last_inspection_date'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Nächste geplante Prüfung</span><span class="detail-value"><?= e($editItem['next_inspection_date'] ?? ''); ?></span></div>
            </div>
        </section>
        <section class="detail-section">
            <h3>Hersteller &amp; Notizen</h3>
            <div class="detail-grid">
                <div class="detail-row"><span class="detail-label">Hersteller-Nachprüfung</span><span class="detail-value"><?= e($editItem['manufacturer_check_date'] ?? ''); ?></span></div>
                <div class="detail-row"><span class="detail-label">Gültigkeit</span><span class="detail-value"><?= (int) ($editItem['manufacturer_validity_days'] ?? 0); ?> Tage</span></div>
                <div class="detail-row"><span class="detail-label">Max. Betriebsdauer</span><span class="detail-value"><?= (int) ($editItem['max_operating_days'] ?? 0); ?> Tage</span></div>
                <div class="detail-row detail-row-wide"><span class="detail-label">Notiz</span><span class="detail-value"><?= e($editItem['notes'] ?? ''); ?></span></div>
            </div>
        </section>
        <section class="detail-section">
            <h3>Dokumente</h3>
            <?php if ($equipmentDocuments === []): ?><p class="form-hint">Noch keine Dokumente hinterlegt.</p><?php else: ?><div class="table-wrap documents-table-wrap"><table class="documents-table"><thead><tr><th>Datei</th><th>Kategorie</th><th>Hochgeladen</th><th>Aktionen</th></tr></thead><tbody><?php foreach ($equipmentDocuments as $document): ?><?php $categoryName = 'Unbekannt'; foreach ($documentCategories as $category) { if ((int) ($category['id'] ?? 0) === (int) ($document['category_id'] ?? 0)) { $categoryName = (string) ($category['name'] ?? $categoryName); break; } } ?><tr><td data-label="Datei"><a class="document-file-link" href="/equipment_document.php?id=<?= (int) ($document['id'] ?? 0); ?>&amp;equipment_id=<?= (int) $editItem['id']; ?>" target="_blank" rel="noopener noreferrer" title="Dokument in einem neuen Fenster öffnen"><?= e($document['original_name'] ?? 'Dokument'); ?></a></td><td data-label="Kategorie"><?= e($categoryName); ?></td><td data-label="Hochgeladen"><?= e(date('d.m.Y H:i', strtotime((string) ($document['uploaded_at'] ?? 'now')))); ?></td><td data-label="Aktionen" class="actions"><form method="post" class="action-form" data-confirm="Dokument wirklich löschen?"><input type="hidden" name="action" value="delete_document" /><input type="hidden" name="id" value="<?= (int) $editItem['id']; ?>" /><input type="hidden" name="document_id" value="<?= (int) ($document['id'] ?? 0); ?>" /><button type="submit" class="document-delete-button" aria-label="Dokument löschen" title="Dokument löschen"><span aria-hidden="true">🗑</span></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            <button type="button" class="button-link document-add-button" data-open-document-upload>Dokument hinzufügen</button>
        </section>
        <section class="detail-section timeline-section">
            <h3>Zeitstrahl</h3>
            <?php if ($timelineEntries === []): ?>
                <p class="timeline-empty">Noch keine Datumsangaben erfasst.</p>
            <?php else: ?>
                <ol class="equipment-timeline">
                    <?php foreach ($timelineEntries as $timelineEntry): ?>
                        <li class="timeline-entry <?= e($timelineEntry['type']); ?>">
                            <time datetime="<?= e($timelineEntry['date']); ?>"><?= e(date('d.m.Y', strtotime($timelineEntry['date']))); ?></time>
                            <span><?= e($timelineEntry['label']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
    <div class="button-row"><a class="button-link" href="/equipment_form.php?id=<?= (int) $editItem['id']; ?>&edit=1">Bearbeiten</a><?php if (($editItem['status'] ?? 'active') !== 'retired'): ?><form method="post" class="action-form"><input type="hidden" name="action" value="archive_equipment" /><input type="hidden" name="id" value="<?= (int) $editItem['id']; ?>" /><button type="submit" class="button-muted">Archivieren</button></form><?php endif; ?><?php if ($isAdmin): ?><form method="post" class="action-form" data-confirm="Gerät „<?= e($editItem['name'] ?? ''); ?>“ inklusive Prüfungshistorie und Dokumenten endgültig löschen?"><input type="hidden" name="action" value="delete_equipment" /><input type="hidden" name="id" value="<?= (int) $editItem['id']; ?>" /><button type="submit" class="button-danger">Löschen</button></form><?php endif; ?></div>
    <div class="image-viewer-overlay" data-image-viewer hidden>
        <div class="image-viewer-modal" role="dialog" aria-modal="true" aria-label="Artikelbild vergrößert anzeigen">
            <button type="button" class="button-muted image-viewer-close" data-close-image-viewer>Schließen</button>
            <button type="button" class="image-viewer-image" data-close-image-viewer aria-label="Artikelbild verkleinern">
                <img src="/equipment_image.php?id=<?= (int) ($editItem['id'] ?? 0); ?>" alt="Artikelbild <?= e($editItem['name'] ?? ''); ?>" />
            </button>
        </div>
    </div>
    <div class="document-upload-overlay" data-document-upload hidden>
        <div class="document-upload-modal" role="dialog" aria-modal="true" aria-labelledby="document-upload-title">
            <div class="image-picker-header"><h3 id="document-upload-title">Dokument hinzufügen</h3><button type="button" class="button-secondary" data-close-document-upload>Schließen</button></div>
            <form method="post" class="stacked-form document-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_documents" />
                <input type="hidden" name="id" value="<?= (int) $editItem['id']; ?>" />
                <div id="equipment-document-upload-list"><div class="document-upload-row"><input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt" required /><select name="document_category_ids[]" required><option value="">Dokumentenkategorie wählen</option><?php foreach ($documentCategories as $category): ?><option value="<?= (int) ($category['id'] ?? 0); ?>"><?= e($category['name'] ?? ''); ?></option><?php endforeach; ?></select></div></div>
                <div class="button-row"><button type="button" class="button-muted" id="add-equipment-document">Weiteres Dokument</button><button type="submit">Dokumente speichern</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if ($editMode && $editItem !== null): ?>
    <div class="image-picker-overlay" data-image-picker hidden>
        <div class="image-picker-modal" role="dialog" aria-modal="true" aria-label="Artikelbild auswählen">
            <div class="image-picker-header"><h3>Artikelbild auswählen</h3><button type="button" class="button-muted" data-close-image-picker>Schließen</button></div>
            <div class="image-picker-body" data-image-picker-body>
                <p>Bilder werden geladen…</p>
            </div>
        </div>
    </div>
<?php endif; ?>
</section>
<script>
const equipmentType = document.getElementById('equipment-type');
const assignedEquipment = document.getElementById('assigned-equipment');
const inspectionForm = document.querySelector('[data-edit-form]');
const nextInspectionDate = inspectionForm?.querySelector('[name="next_inspection_date"]');
const inspectionDateFields = inspectionForm ? [
    inspectionForm.querySelector('[name="last_inspection_date"]'),
    inspectionForm.querySelector('[name="purchase_date"]'),
    inspectionForm.querySelector('[name="inspection_interval_months"]'),
] : [];
const updateNextInspectionDate = function () {
    if (!nextInspectionDate) {
        return;
    }
    const [lastInspection, purchaseDate, interval] = inspectionDateFields.map(function (field) {
        return field?.value || '';
    });
    const baseDate = lastInspection || purchaseDate;
    if (!baseDate || !interval || Number(interval) <= 0) {
        nextInspectionDate.value = '';
        return;
    }
    const date = new Date(baseDate + 'T00:00:00');
    const targetMonth = new Date(date.getFullYear(), date.getMonth() + Number(interval), 1);
    const lastDayOfTargetMonth = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
    const year = targetMonth.getFullYear();
    const month = String(targetMonth.getMonth() + 1).padStart(2, '0');
    const day = String(Math.min(date.getDate(), lastDayOfTargetMonth)).padStart(2, '0');
    nextInspectionDate.value = `${year}-${month}-${day}`;
};
inspectionDateFields.forEach(function (field) {
    field?.addEventListener('input', updateNextInspectionDate);
    field?.addEventListener('change', updateNextInspectionDate);
});
updateNextInspectionDate();
if (equipmentType && assignedEquipment) {
    const updateAssignmentRequirement = function () {
        const rescueSelected = equipmentType.value === 'Rettungsgerät';
        assignedEquipment.required = rescueSelected;
        assignedEquipment.closest('label').firstChild.textContent = rescueSelected ? 'Zugeordnetes Gerät *' : 'Zugeordnetes Gerät';
    };
    updateAssignmentRequirement();
    equipmentType.addEventListener('change', updateAssignmentRequirement);
}
const equipmentDocumentUploadList = document.getElementById('equipment-document-upload-list');
const addEquipmentDocumentButton = document.getElementById('add-equipment-document');
if (equipmentDocumentUploadList && addEquipmentDocumentButton) {
    addEquipmentDocumentButton.addEventListener('click', function () {
        const row = equipmentDocumentUploadList.firstElementChild.cloneNode(true);
        row.querySelector('input').value = '';
        row.querySelector('select').value = '';
        equipmentDocumentUploadList.appendChild(row);
    });
}

const imagePickerTrigger = document.querySelector('[data-open-image-picker]');
const imagePickerOverlay = document.querySelector('[data-image-picker]');
const imagePickerBody = document.querySelector('[data-image-picker-body]');
const imagePickerCloseButton = document.querySelector('[data-close-image-picker]');
const imageViewerTrigger = document.querySelector('[data-open-image-viewer]');
const imageViewerOverlay = document.querySelector('[data-image-viewer]');
const documentUploadTrigger = document.querySelector('[data-open-document-upload]');
const documentUploadOverlay = document.querySelector('[data-document-upload]');
const documentUploadCloseButton = document.querySelector('[data-close-document-upload]');

const closeImagePicker = function () {
    if (imagePickerOverlay) {
        imagePickerOverlay.hidden = true;
    }
};
const closeImageViewer = function () {
    if (imageViewerOverlay) {
        imageViewerOverlay.hidden = true;
    }
};
const closeDocumentUpload = function () {
    if (documentUploadOverlay) {
        documentUploadOverlay.hidden = true;
    }
};

const renderImageCandidates = function (equipmentId, candidates) {
    if (!imagePickerBody) {
        return;
    }
    if (!candidates.length) {
        imagePickerBody.innerHTML = '<p>Keine passenden Bilder gefunden.</p>';
        return;
    }
    imagePickerBody.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'image-picker-grid';
    candidates.forEach(function (candidate) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'image-picker-item';
        item.title = candidate.title || '';
        const img = document.createElement('img');
        img.src = candidate.thumbnail;
        img.alt = candidate.title || 'Bildvorschlag';
        item.appendChild(img);
        item.addEventListener('click', function () {
            selectImage(equipmentId, candidate.link, item);
        });
        grid.appendChild(item);
    });
    imagePickerBody.appendChild(grid);
};

const selectImage = function (equipmentId, imageUrl, triggerElement) {
    if (imagePickerBody) {
        imagePickerBody.setAttribute('aria-busy', 'true');
    }
    if (triggerElement) {
        triggerElement.disabled = true;
    }
    const body = new URLSearchParams();
    body.set('id', equipmentId);
    body.set('image_url', imageUrl);
    fetch('/equipment_image_select.php', { method: 'POST', body })
        .then(function (response) { return response.json(); })
        .then(function (result) {
            if (result.success) {
                const photo = document.querySelector('.equipment-photo-button');
                if (photo) {
                    photo.innerHTML = `<img class="equipment-photo" src="${result.image_url}" alt="Artikelbild" />`;
                }
                closeImagePicker();
            } else if (imagePickerBody) {
                imagePickerBody.innerHTML = `<p>${result.message || 'Bild konnte nicht übernommen werden.'}</p>`;
            }
        })
        .catch(function () {
            if (imagePickerBody) {
                imagePickerBody.innerHTML = '<p>Bild konnte nicht übernommen werden.</p>';
            }
        });
};

if (imagePickerTrigger && imagePickerOverlay && imagePickerBody) {
    imagePickerTrigger.addEventListener('click', function () {
        const equipmentId = imagePickerTrigger.dataset.equipmentId;
        imagePickerOverlay.hidden = false;
        imagePickerBody.innerHTML = '<p>Bilder werden geladen…</p>';
        fetch(`/equipment_image_search.php?id=${equipmentId}`)
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success) {
                    renderImageCandidates(equipmentId, result.candidates);
                } else {
                    imagePickerBody.innerHTML = `<p>${result.message || 'Bildersuche ist nicht verfügbar.'}</p>`;
                }
            })
            .catch(function () {
                imagePickerBody.innerHTML = '<p>Bildersuche ist nicht verfügbar.</p>';
            });
    });
}
imagePickerCloseButton?.addEventListener('click', closeImagePicker);
imagePickerOverlay?.addEventListener('click', function (event) {
    if (event.target === imagePickerOverlay) {
        closeImagePicker();
    }
});
imageViewerTrigger?.addEventListener('click', function () {
    imageViewerOverlay.hidden = false;
});
document.querySelectorAll('[data-close-image-viewer]').forEach(function (element) {
    element.addEventListener('click', closeImageViewer);
});
imageViewerOverlay?.addEventListener('click', function (event) {
    if (event.target === imageViewerOverlay) {
        closeImageViewer();
    }
});
documentUploadTrigger?.addEventListener('click', function () {
    documentUploadOverlay.hidden = false;
    documentUploadOverlay.querySelector('input[type="file"]')?.focus();
});
documentUploadCloseButton?.addEventListener('click', closeDocumentUpload);
documentUploadOverlay?.addEventListener('click', function (event) {
    if (event.target === documentUploadOverlay) {
        closeDocumentUpload();
    }
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeImagePicker();
        closeImageViewer();
        closeDocumentUpload();
    }
});
</script>
<?php pageFooter();
