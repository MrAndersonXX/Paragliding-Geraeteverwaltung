<?php

require __DIR__ . '/_layout.php';

use Glider\Storage;
use Glider\Auth;

Auth::requireActiveAccount();
Storage::ensure();
$allEquipment = Storage::readEquipment();
$equipment = $allEquipment;
$users = Storage::readUsers();
$usersById = [];
foreach ($users as $user) {
    $usersById[(int) ($user['id'] ?? 0)] = $user;
}
$message = isset($_GET['saved']) ? 'Gerät wurde gespeichert.' : '';
$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
if (!$isAdmin) {
    $equipment = array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'archive_equipment') {
    $id = (int) ($_POST['id'] ?? 0);
    foreach ($allEquipment as $index => $existing) {
        if ((int) ($existing['id'] ?? 0) === $id && ($isAdmin || (int) ($existing['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0))) {
            $allEquipment[$index]['status'] = 'retired';
            $allEquipment[$index]['retired_at'] = date('Y-m-d');
            break;
        }
    }
    Storage::saveEquipment($allEquipment);
    $message = 'Gerät wurde archiviert.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_equipment' && $isAdmin) {
    $id = (int) ($_POST['id'] ?? 0);
    $allEquipment = array_values(array_filter($allEquipment, static fn ($existing) => (int) ($existing['id'] ?? 0) !== $id));
    Storage::saveEquipment($allEquipment);
    Storage::deleteEquipmentDocuments($id);
    $message = 'Gerät wurde endgültig gelöscht.';
    $equipment = $isAdmin ? $allEquipment : array_values(array_filter($allEquipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}

pageHeader(__('page.equipment'));
if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card list-toolbar">
    <div><h2>Erfasste Geräte</h2><p>Alle Geräte werden hier angezeigt und können bearbeitet oder archiviert werden.</p></div>
    <a class="button-link" href="/equipment_form.php">Neues Gerät</a>
</section>
<section class="card">
    <div class="list-filters">
        <label class="filter-field">Geräte filtern<input type="search" id="equipment-filter" placeholder="Name, Typ, Hersteller, Benutzer ..." /></label>
        <label class="checkbox-field"><input type="checkbox" id="hide-retired" <?= Auth::preference('hide_retired_equipment') ? 'checked' : ''; ?> /> <?= e(__('equipment.hide_retired')); ?></label>
    </div>
    <div class="table-wrap"><table id="equipment-table"><thead><tr><th><button type="button" class="sort-button" data-sort="0">Benutzer</button></th><th><button type="button" class="sort-button" data-sort="1">Gerätetyp</button></th><th><button type="button" class="sort-button" data-sort="2">Hersteller</button></th><th><button type="button" class="sort-button" data-sort="3">Gerätename</button></th><th><button type="button" class="sort-button" data-sort="4">Seriennummer</button></th><th><button type="button" class="sort-button" data-sort="5">Nächste geplante Prüfung</button></th><th><button type="button" class="sort-button" data-sort="6">Status</button></th><th>Aktionen</th></tr></thead><tbody>
    <?php if (!$equipment): ?><tr><td colspan="8">Noch keine Geräte erfasst.</td></tr><?php endif; ?>
    <?php foreach ($equipment as $item): ?><?php $user = $usersById[(int) ($item['user_id'] ?? 0)] ?? null; $itemId = (int) ($item['id'] ?? 0); $itemName = (string) ($item['name'] ?? ''); ?><tr class="equipment-row<?= ($item['status'] ?? 'active') === 'retired' ? ' equipment-row-retired' : ''; ?>" data-href="/equipment_form.php?id=<?= $itemId; ?>" tabindex="0"><td class="emoji-cell"><?= e($user['emoji'] ?? ''); ?></td><td><?= e($item['equipment_type'] ?? $item['category'] ?? ''); ?></td><td><?= e($item['manufacturer'] ?? ''); ?></td><td><?= e($item['name'] ?? ''); ?></td><td><?= e($item['serial_number'] ?? ''); ?></td><td><?= e($item['next_inspection_date'] ?? ''); ?></td><td><?= e($item['status'] ?? 'active'); ?></td><td class="actions"><?php if (($item['status'] ?? 'active') !== 'retired'): ?><a class="button-link" href="/inspection.php?id=<?= $itemId; ?>">Prüfung eintragen</a><?php endif; ?><?php if ($isAdmin): ?><form method="post" class="action-form" data-confirm="Gerät „<?= e($itemName); ?>“ inklusive Prüfungshistorie und Dokumenten endgültig löschen?"><input type="hidden" name="action" value="delete_equipment" /><input type="hidden" name="id" value="<?= $itemId; ?>" /><button type="submit" class="button-danger">Löschen</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<script>
const filter = document.getElementById('equipment-filter');
const table = document.getElementById('equipment-table');
const hideRetired = document.getElementById('hide-retired');
const applyVisibility = function () {
    if (!table) {
        return;
    }
    const query = filter ? filter.value.toLowerCase().trim() : '';
    table.querySelectorAll('tbody tr.equipment-row').forEach(function (row) {
        const matchesFilter = query === '' || row.textContent.toLowerCase().includes(query);
        const hiddenByRetired = !!hideRetired && hideRetired.checked && row.classList.contains('equipment-row-retired');
        row.hidden = !matchesFilter || hiddenByRetired;
    });
};
applyVisibility();
if (hideRetired) {
    hideRetired.addEventListener('change', function () {
        applyVisibility();
        fetch('/preferences.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=hide_retired_equipment&value=' + (hideRetired.checked ? '1' : '0'),
        });
    });
}
if (filter && table) {
    table.querySelectorAll('.equipment-row').forEach(function (row) {
        const open = function () { window.location.href = row.dataset.href; };
        row.addEventListener('click', function (event) { if (!event.target.closest('a, button, form')) open(); });
        row.addEventListener('keydown', function (event) { if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, form')) { event.preventDefault(); open(); } });
    });
    filter.addEventListener('input', applyVisibility);
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
