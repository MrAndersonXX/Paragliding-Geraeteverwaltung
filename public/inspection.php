<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/InspectionCalculator.php';

use Glider\InspectionCalculator;
use Glider\Storage;

Storage::ensure();
$equipment = Storage::readEquipment();
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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastInspectionDate = trim((string) ($_POST['last_inspection_date'] ?? ''));
    $nextInspectionDate = InspectionCalculator::nextDate($lastInspectionDate, (int) ($item['inspection_interval_days'] ?? 0));
    if ($nextInspectionDate === '') {
        $message = 'Bitte ein gültiges Datum und ein Prüfungsintervall größer als 0 hinterlegen.';
    } else {
        $equipment[$itemIndex]['last_inspection_date'] = $lastInspectionDate;
        $equipment[$itemIndex]['next_inspection_date'] = $nextInspectionDate;
        Storage::saveEquipment($equipment);
        header('Location: /equipment_list.php?saved=1');
        exit;
    }
}

pageHeader('Prüfung eintragen');
if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <h2><?= e($item['name'] ?? 'Gerät'); ?></h2>
    <p>Das Datum der nächsten Prüfung wird automatisch aus der letzten Prüfung und dem Prüfungsintervall berechnet.</p>
    <form method="post" class="stacked-form">
        <input type="hidden" name="id" value="<?= (int) $id; ?>" />
        <label>Letzte Prüfung<input type="date" name="last_inspection_date" value="<?= e($item['last_inspection_date'] ?? ''); ?>" required /></label>
        <p class="form-hint">Prüfungsintervall: <?= (int) ($item['inspection_interval_days'] ?? 0); ?> Tage</p>
        <div class="button-row"><button type="submit">Prüfung speichern</button><a class="button-link" href="/equipment_list.php">Abbrechen</a></div>
    </form>
</section>
<?php pageFooter();
