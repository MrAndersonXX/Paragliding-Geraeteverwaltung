<?php

require __DIR__ . '/_layout.php';

use Glider\Storage;

Storage::ensure();
$equipment = Storage::readEquipment();
$editId = (int) ($_GET['edit'] ?? 0);
$editItem = null;
foreach ($equipment as $existing) {
    if ((int) ($existing['id'] ?? 0) === $editId) {
        $editItem = $existing;
        break;
    }
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : 0;
    $id = $id ?: ((count($equipment) > 0 ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $equipment)) : 0) + 1);
    $item = [
        'id' => $id,
        'name' => trim((string) ($_POST['name'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? 'Gleitschirm')),
        'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
        'equipment_type' => trim((string) ($_POST['equipment_type'] ?? '')),
        'size' => trim((string) ($_POST['size'] ?? '')),
        'serial_number' => trim((string) ($_POST['serial_number'] ?? '')),
        'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
        'owner' => trim((string) ($_POST['owner'] ?? '')),
        'assigned_user' => trim((string) ($_POST['assigned_user'] ?? '')),
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

pageHeader($editItem ? 'Gerät bearbeiten' : 'Neues Gerät');
?>
<section class="card">
    <div class="section-actions"><a class="button-link" href="/equipment_list.php">Zur Geräteliste</a></div>
    <form method="post" class="stacked-form">
        <input type="hidden" name="id" value="<?= e($editItem['id'] ?? ''); ?>" />
        <div class="row two-col">
            <label>Gerätename<input type="text" name="name" value="<?= e($editItem['name'] ?? ''); ?>" required /></label>
            <label>Kategorie<select name="category"><?php foreach (['Gleitschirm', 'Rettungsgerät', 'Gurtzeug', 'Helm', 'Sonstiges'] as $category): ?><option <?= (($editItem['category'] ?? 'Gleitschirm') === $category) ? 'selected' : ''; ?>><?= e($category); ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="row three-col">
            <label>Hersteller<input type="text" name="manufacturer" value="<?= e($editItem['manufacturer'] ?? ''); ?>" /></label>
            <label>Gerätetyp<input type="text" name="equipment_type" value="<?= e($editItem['equipment_type'] ?? ''); ?>" /></label>
            <label>Größe<input type="text" name="size" value="<?= e($editItem['size'] ?? ''); ?>" /></label>
        </div>
        <div class="row three-col">
            <label>Seriennummer<input type="text" name="serial_number" value="<?= e($editItem['serial_number'] ?? ''); ?>" /></label>
            <label>Anschaffungsdatum<input type="date" name="purchase_date" value="<?= e($editItem['purchase_date'] ?? ''); ?>" /></label>
            <label>Status<select name="status"><option value="active" <?= (($editItem['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>aktiv</option><option value="inspection" <?= (($editItem['status'] ?? '') === 'inspection') ? 'selected' : ''; ?>>in Prüfung</option><option value="retired" <?= (($editItem['status'] ?? '') === 'retired') ? 'selected' : ''; ?>>ausgemustert</option></select></label>
        </div>
        <div class="row three-col">
            <label>Besitzer / Verbau<input type="text" name="owner" value="<?= e($editItem['owner'] ?? ''); ?>" /></label>
            <label>Verantwortliche Person<input type="text" name="assigned_user" value="<?= e($editItem['assigned_user'] ?? ''); ?>" /></label>
            <label>Prüfungsintervall in Tagen<input type="number" name="inspection_interval_days" min="0" value="<?= e($editItem['inspection_interval_days'] ?? '365'); ?>" /></label>
        </div>
        <div class="row three-col">
            <label>Beginn Prüfungsdatum<input type="date" name="inspection_start_date" value="<?= e($editItem['inspection_start_date'] ?? ''); ?>" /></label>
            <label>Letzte Prüfung<input type="date" name="last_inspection_date" value="<?= e($editItem['last_inspection_date'] ?? ''); ?>" /></label>
            <label>Nächste Prüfung<input type="date" name="next_inspection_date" value="<?= e($editItem['next_inspection_date'] ?? ''); ?>" /></label>
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
<?php pageFooter();
