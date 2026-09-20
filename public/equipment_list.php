<?php

require __DIR__ . '/_layout.php';

use Glider\Storage;

Storage::ensure();
$allEquipment = Storage::readEquipment();
$equipment = $allEquipment;
$users = Storage::readUsers();
$usersById = [];
foreach ($users as $user) {
    $usersById[(int) ($user['id'] ?? 0)] = $user;
}
$message = isset($_GET['saved']) ? 'Gerät wurde gespeichert.' : '';
use Glider\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive_equipment') {
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
if (!$isAdmin) {
    $equipment = array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}
    $id = (int) ($_POST['id'] ?? 0);
    foreach ($allEquipment as $index => $existing) {
        if ((int) ($existing['id'] ?? 0) === $id && ($isAdmin || (int) ($existing['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0))) {
            $equipment[$index]['status'] = 'retired';
            $equipment[$index]['retired_at'] = date('Y-m-d');
            break;
        }
    }
    Storage::saveEquipment($allEquipment);
    $message = 'Gerät wurde archiviert.';
}

pageHeader('Geräte');
if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card list-toolbar">
    <div><h2>Erfasste Geräte</h2><p>Alle Geräte werden hier angezeigt und können bearbeitet oder archiviert werden.</p></div>
    <a class="button-link" href="/equipment_form.php">Neues Gerät</a>
</section>
<section class="card">
    <label class="filter-field">Geräte filtern<input type="search" id="equipment-filter" placeholder="Name, Typ, Hersteller, Benutzer ..." /></label>
    <div class="table-wrap"><table id="equipment-table"><thead><tr><th><button type="button" class="sort-button" data-sort="0">Benutzer</button></th><th><button type="button" class="sort-button" data-sort="1">Gerätetyp</button></th><th><button type="button" class="sort-button" data-sort="2">Hersteller</button></th><th><button type="button" class="sort-button" data-sort="3">Gerätename</button></th><th><button type="button" class="sort-button" data-sort="4">Seriennummer</button></th><th><button type="button" class="sort-button" data-sort="5">Nächste Prüfung</button></th><th><button type="button" class="sort-button" data-sort="6">Status</button></th><th>Aktionen</th></tr></thead><tbody>
    <?php if (!$equipment): ?><tr><td colspan="8">Noch keine Geräte erfasst.</td></tr><?php endif; ?>
    <?php foreach ($equipment as $item): ?><?php $user = $usersById[(int) ($item['user_id'] ?? 0)] ?? null; ?><tr><td class="emoji-cell"><?= e($user['emoji'] ?? ''); ?></td><td><?= e($item['equipment_type'] ?? $item['category'] ?? ''); ?></td><td><?= e($item['manufacturer'] ?? ''); ?></td><td><?= e($item['name'] ?? ''); ?></td><td><?= e($item['serial_number'] ?? ''); ?></td><td><?= e($item['next_inspection_date'] ?? ''); ?></td><td><?= e($item['status'] ?? 'active'); ?></td><td class="actions"><a class="button-link" href="/equipment_form.php?edit=<?= (int) ($item['id'] ?? 0); ?>">Edit</a><?php if (($item['status'] ?? 'active') !== 'retired'): ?><a class="button-link" href="/inspection.php?id=<?= (int) ($item['id'] ?? 0); ?>">Prüfung eintragen</a><form method="post" class="action-form"><input type="hidden" name="action" value="archive_equipment" /><input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0); ?>" /><button type="submit" class="button-muted">Archivieren</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<script>
const filter = document.getElementById('equipment-filter');
const table = document.getElementById('equipment-table');
if (filter && table) {
    filter.addEventListener('input', function () {
        const query = filter.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
        });
    });
    table.querySelectorAll('.sort-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const column = Number(button.dataset.sort);
            const body = table.querySelector('tbody');
            const rows = Array.from(body.querySelectorAll('tr')).filter(function (row) {
                return row.children.length > column;
            });
            const direction = button.dataset.direction === 'asc' ? -1 : 1;
            button.dataset.direction = direction === 1 ? 'asc' : 'desc';
            rows.sort(function (a, b) {
                return direction * a.children[column].textContent.trim().localeCompare(b.children[column].textContent.trim(), 'de', { numeric: true, sensitivity: 'base' });
            });
            rows.forEach(function (row) { body.appendChild(row); });
        });
    });
}
</script>
<?php pageFooter();
