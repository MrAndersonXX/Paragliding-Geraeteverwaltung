<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/InspectionCalculator.php';

use Glider\Storage;
use Glider\InspectionCalculator;
use Glider\Auth;

Storage::ensure();
$equipment = Storage::readEquipment();
$users = Storage::readUsers();
$equipmentTypes = Storage::readEquipmentTypes();
$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$editItem = null;
foreach ($equipment as $existing) {
    if ((int) ($existing['id'] ?? 0) === $editId) {
        $editItem = $existing;
        break;
    }
}
$message = '';
$formEquipmentType = '';
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$visibleEquipment = $isAdmin ? $equipment : array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
$isNew = $editItem === null;
if (!$isAdmin && $editItem !== null && (int) ($editItem['user_id'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}
$assignedEquipmentId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : 0;
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
    $item = [
        'id' => $id,
        'name' => trim((string) ($_POST['name'] ?? '')),
        'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
        'equipment_type' => $formEquipmentType,
        'assigned_equipment_id' => $assignedEquipmentId,
        'size' => trim((string) ($_POST['size'] ?? '')),
        'serial_number' => trim((string) ($_POST['serial_number'] ?? '')),
        'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
        'user_id' => $submittedUserId,
        'status' => trim((string) ($_POST['status'] ?? 'active')),
        'inspection_interval_days' => (int) ($_POST['inspection_interval_days'] ?? 0),
        'inspection_start_date' => trim((string) ($_POST['inspection_start_date'] ?? '')),
        'last_inspection_date' => trim((string) ($_POST['last_inspection_date'] ?? '')),
        'next_inspection_date' => trim((string) ($_POST['next_inspection_date'] ?? '')),
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
    ];
    if ($message === '') {
        $item['next_inspection_date'] = InspectionCalculator::nextDate($item['last_inspection_date'], $item['inspection_interval_days']);
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
        header('Location: /equipment_list.php?saved=1');
        exit;
    }
}

pageHeader($editItem ? 'Gerät bearbeiten' : 'Neues Gerät');
?>
<?php if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <div class="section-actions"><a class="button-link" href="/equipment_list.php">Zur Geräteliste</a></div>
    <form method="post" class="stacked-form">
        <input type="hidden" name="id" value="<?= e($editItem['id'] ?? ''); ?>" />
        <div class="row two-col">
            <label>Gerätename<input type="text" name="name" value="<?= e($editItem['name'] ?? ''); ?>" required /></label>
            <label>Gerätetyp<select name="equipment_type" id="equipment-type" required><?php foreach ($equipmentTypes as $type): ?><?php $typeName = (string) ($type['name'] ?? ''); ?><option value="<?= e($typeName); ?>" <?= (($formEquipmentType ?: ($editItem['equipment_type'] ?? $editItem['category'] ?? '')) === $typeName) ? 'selected' : ''; ?>><?= e($typeName); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="row three-col">
            <label>Hersteller<input type="text" name="manufacturer" value="<?= e($editItem['manufacturer'] ?? ''); ?>" /></label>
            <label>Größe<input type="text" name="size" value="<?= e($editItem['size'] ?? ''); ?>" /></label>
        </div>
        <div class="row three-col">
            <label>Seriennummer<input type="text" name="serial_number" value="<?= e($editItem['serial_number'] ?? ''); ?>" /></label>
            <label>Anschaffungsdatum<input type="date" name="purchase_date" value="<?= e($editItem['purchase_date'] ?? ''); ?>" /></label>
            <label>Status<select name="status"><option value="active" <?= (($editItem['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>aktiv</option><option value="inspection" <?= (($editItem['status'] ?? '') === 'inspection') ? 'selected' : ''; ?>>in Prüfung</option><option value="retired" <?= (($editItem['status'] ?? '') === 'retired') ? 'selected' : ''; ?>>ausgemustert</option></select></label>
        </div>
        <div class="row three-col">
            <label>Zugeordnetes Gerät<select name="assigned_equipment_id" id="assigned-equipment"><option value="0">Nicht zugeordnet</option><?php foreach ($visibleEquipment as $otherEquipment): ?><?php $otherId = (int) ($otherEquipment['id'] ?? 0); ?><?php if ($otherId === (int) ($editItem['id'] ?? 0)) { continue; } ?><?php $otherType = (string) ($otherEquipment['equipment_type'] ?? $otherEquipment['category'] ?? ''); ?><option value="<?= $otherId; ?>" data-equipment-type="<?= e($otherType); ?>" <?= ((int) ($editItem['assigned_equipment_id'] ?? 0) === $otherId || $assignedEquipmentId === $otherId) ? 'selected' : ''; ?>><?= e(($otherEquipment['name'] ?? '') . ' (' . $otherType . ')'); ?></option><?php endforeach; ?></select></label>
            <?php if ($isAdmin): ?><label>Zugeordneter Benutzer<select name="user_id"><option value="0">Nicht zugeordnet</option><?php foreach ($users as $user): ?><option value="<?= (int) ($user['id'] ?? 0); ?>" <?= ((int) ($editItem['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) ? 'selected' : ''; ?>><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['emoji'] ?? '')); ?></option><?php endforeach; ?></select></label><?php else: ?><input type="hidden" name="user_id" value="<?= (int) ($currentUser['id'] ?? 0); ?>" /><?php endif; ?>
            <label>Prüfungsintervall in Tagen<input type="number" name="inspection_interval_days" min="0" value="<?= e($editItem['inspection_interval_days'] ?? '365'); ?>" /></label>
        </div>
        <div class="row three-col">
            <label>Beginn Prüfungsdatum<input type="date" name="inspection_start_date" value="<?= e($editItem['inspection_start_date'] ?? ''); ?>" /></label>
            <label>Letzte Prüfung<input type="date" name="last_inspection_date" value="<?= e($editItem['last_inspection_date'] ?? ''); ?>" /></label>
            <label>Nächste Prüfung <span class="info-field" tabindex="0" aria-label="Information zur Berechnung">i<span class="info-explanation" role="tooltip">Wird automatisch aus der letzten Prüfung und dem Prüfungsintervall berechnet.</span></span><input type="date" name="next_inspection_date" value="<?= e($editItem['next_inspection_date'] ?? ''); ?>" readonly /></label>
        </div>
        <div class="row three-col">
            <label>Hersteller-Nachprüfung<input type="date" name="manufacturer_check_date" value="<?= e($editItem['manufacturer_check_date'] ?? ''); ?>" /></label>
            <label>Gültigkeit in Tagen<input type="number" name="manufacturer_validity_days" min="0" value="<?= e($editItem['manufacturer_validity_days'] ?? '0'); ?>" /></label>
            <label>Max. Betriebsdauer in Tagen<input type="number" name="max_operating_days" min="0" value="<?= e($editItem['max_operating_days'] ?? '0'); ?>" /></label>
        </div>
        <div class="row two-col">
            <label>Ausmusterungsdatum<input type="date" name="retired_at" value="<?= e($editItem['retired_at'] ?? ''); ?>" /></label>
            <label>Notiz<textarea name="notes" rows="3"><?= e($editItem['notes'] ?? ''); ?></textarea></label>
        </div>
        <fieldset><legend>Benachrichtigungen</legend><div class="checkbox-row">
            <label><input type="checkbox" name="notification_30_days" value="1" <?= !empty($editItem['notifications']['30_days']) || !$editItem ? 'checked' : ''; ?> /> 30 Tage</label>
            <label><input type="checkbox" name="notification_14_days" value="1" <?= !empty($editItem['notifications']['14_days']) ? 'checked' : ''; ?> /> 14 Tage</label>
            <label><input type="checkbox" name="notification_7_days" value="1" <?= !empty($editItem['notifications']['7_days']) ? 'checked' : ''; ?> /> 7 Tage</label>
            <label><input type="checkbox" name="notification_due" value="1" <?= !empty($editItem['notifications']['due']) || !$editItem ? 'checked' : ''; ?> /> überfällig</label>
            <label><input type="checkbox" name="notification_retired" value="1" <?= !empty($editItem['notifications']['retired']) ? 'checked' : ''; ?> /> Ausmusterung</label>
        </div></fieldset>
        <button type="submit"><?= $editItem ? 'Änderungen speichern' : 'Gerät speichern'; ?></button>
    </form>
</section>
<script>
const equipmentType = document.getElementById('equipment-type');
const assignedEquipment = document.getElementById('assigned-equipment');
if (equipmentType && assignedEquipment) {
    const updateAssignmentRequirement = function () {
        const rescueSelected = equipmentType.value === 'Rettungsgerät';
        assignedEquipment.required = rescueSelected;
        assignedEquipment.closest('label').firstChild.textContent = rescueSelected ? 'Zugeordnetes Gerät *' : 'Zugeordnetes Gerät';
    };
    updateAssignmentRequirement();
    equipmentType.addEventListener('change', updateAssignmentRequirement);
}
</script>
<?php pageFooter();
