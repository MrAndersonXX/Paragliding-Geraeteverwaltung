<?php

require __DIR__ . '/_layout.php';

use Glider\Storage;

Storage::ensure();
$equipment = Storage::readEquipment();
$message = isset($_GET['saved']) ? 'Gerät wurde gespeichert.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive_equipment') {
    $id = (int) ($_POST['id'] ?? 0);
    foreach ($equipment as $index => $existing) {
        if ((int) ($existing['id'] ?? 0) === $id) {
            $equipment[$index]['status'] = 'retired';
            $equipment[$index]['retired_at'] = date('Y-m-d');
            break;
        }
    }
    Storage::saveEquipment($equipment);
    $message = 'Gerät wurde archiviert.';
}

pageHeader('Geräte');
if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card list-toolbar">
    <div><h2>Erfasste Geräte</h2><p>Alle Geräte werden hier angezeigt und können bearbeitet oder archiviert werden.</p></div>
    <a class="button-link" href="/equipment_form.php">Neues Gerät</a>
</section>
<section class="card">
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Kategorie</th><th>Hersteller</th><th>Seriennummer</th><th>Nächste Prüfung</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>
    <?php if (!$equipment): ?><tr><td colspan="7">Noch keine Geräte erfasst.</td></tr><?php endif; ?>
    <?php foreach ($equipment as $item): ?><tr><td><?= e($item['name'] ?? ''); ?></td><td><?= e($item['category'] ?? ''); ?></td><td><?= e($item['manufacturer'] ?? ''); ?></td><td><?= e($item['serial_number'] ?? ''); ?></td><td><?= e($item['next_inspection_date'] ?? ''); ?></td><td><?= e($item['status'] ?? 'active'); ?></td><td class="actions"><a class="button-link" href="/equipment_form.php?edit=<?= (int) ($item['id'] ?? 0); ?>">Edit</a><?php if (($item['status'] ?? 'active') !== 'retired'): ?><form method="post" class="action-form"><input type="hidden" name="action" value="archive_equipment" /><input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0); ?>" /><button type="submit" class="button-muted">Archivieren</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php pageFooter();
