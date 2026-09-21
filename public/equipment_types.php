<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Storage;
use Glider\Auth;

Auth::requireAdmin();
$types = Storage::readEquipmentTypes();
$equipment = Storage::readEquipment();
$editId = (int) ($_GET['edit'] ?? 0);
$editType = null;
$usage = [];
foreach ($equipment as $item) {
    $name = (string) ($item['equipment_type'] ?? $item['category'] ?? '');
    if ($name !== '') {
        $usage[$name] = ($usage[$name] ?? 0) + 1;
    }
}
foreach ($types as $type) {
    if ((int) ($type['id'] ?? 0) === $editId) {
        $editType = $type;
        break;
    }
}
usort($types, static fn ($left, $right) => strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['type_name'] ?? ''));
    $oldName = '';
    foreach ($types as $type) {
        if ((int) ($type['id'] ?? 0) === $id) {
            $oldName = (string) ($type['name'] ?? '');
            break;
        }
    }
    $exists = array_filter($types, static fn ($type) => (int) ($type['id'] ?? 0) !== $id && strcasecmp((string) ($type['name'] ?? ''), $name) === 0);
    if ($action === 'delete') {
        if (($usage[$oldName] ?? 0) > 0) {
            $message = 'Der Gerätetyp kann nicht gelöscht werden, solange er Geräten zugeordnet ist.';
        } else {
            $types = array_values(array_filter($types, static fn ($type) => (int) ($type['id'] ?? 0) !== $id));
            Storage::saveEquipmentTypes($types);
            header('Location: /equipment_types.php?saved=1');
            exit;
        }
    } elseif ($name === '') {
        $message = 'Bitte einen Gerätetyp eingeben.';
    } elseif ($exists) {
        $message = 'Dieser Gerätetyp existiert bereits.';
    } elseif ($action === 'edit') {
        foreach ($types as $index => $type) {
            if ((int) ($type['id'] ?? 0) === $id) {
                $types[$index]['name'] = $name;
            }
        }
        if ($oldName !== $name) {
            foreach ($equipment as $index => $item) {
                if (($item['equipment_type'] ?? $item['category'] ?? '') === $oldName) {
                    $equipment[$index]['equipment_type'] = $name;
                    unset($equipment[$index]['category']);
                }
            }
            Storage::saveEquipment($equipment);
        }
        Storage::saveEquipmentTypes($types);
        header('Location: /equipment_types.php?saved=1');
        exit;
    } else {
        $types[] = ['id' => (count($types) ? max(array_map(fn ($type) => (int) ($type['id'] ?? 0), $types)) : 0) + 1, 'name' => $name];
        Storage::saveEquipmentTypes($types);
        header('Location: /equipment_types.php?saved=1');
        exit;
    }
}

pageHeader(__('page.equipment_types'));
if (isset($_GET['saved'])): ?><div class="alert">Gerätetyp wurde gespeichert.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card edit-surface"><div class="edit-header"><div><p class="eyebrow">Stammdaten</p><h2><?= $editType ? 'Gerätetyp editieren' : 'Gerätetyp erweitern'; ?></h2></div><?php if ($editType): ?><a class="button-link button-secondary" href="/equipment_types.php">Abbrechen</a><?php endif; ?></div><form method="post" class="inline-form"><input type="hidden" name="action" value="<?= $editType ? 'edit' : 'create'; ?>" /><input type="hidden" name="id" value="<?= (int) ($editType['id'] ?? 0); ?>" /><label class="compact-field">Bezeichnung<input type="text" name="type_name" value="<?= e($editType['name'] ?? ''); ?>" placeholder="z. B. Tandemschirm" required /></label><button type="submit"><?= $editType ? 'Gerätetyp speichern' : 'Gerätetyp anlegen'; ?></button></form></section>
<section class="card"><h2>Verfügbare Gerätetypen</h2><div class="table-wrap"><table id="equipment-types-table"><thead><tr><th><button type="button" class="sort-button" data-sort="0" data-direction="asc">Gerätetyp</button></th><th><button type="button" class="sort-button" data-sort="1">Verwendung bei Geräten</button></th><th>Aktionen</th></tr></thead><tbody><?php foreach ($types as $type): ?><?php $typeName = (string) ($type['name'] ?? ''); ?><tr><td><?= e($typeName); ?></td><td><?= (int) ($usage[$typeName] ?? 0); ?></td><td class="actions"><a class="button-link" href="/equipment_types.php?edit=<?= (int) ($type['id'] ?? 0); ?>">Bearbeiten</a><form method="post" class="action-form"><input type="hidden" name="action" value="delete" /><input type="hidden" name="id" value="<?= (int) ($type['id'] ?? 0); ?>" /><button type="submit" class="button-muted" <?= (($usage[$typeName] ?? 0) > 0) ? 'disabled title="Noch verwendeter Gerätetyp"' : ''; ?>>Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<script>
const typesTable = document.getElementById('equipment-types-table');
if (typesTable) {
    typesTable.querySelectorAll('.sort-button').forEach(function (button) {
        button.addEventListener('click', function () {
            const column = Number(button.dataset.sort);
            const body = typesTable.querySelector('tbody');
            const rows = Array.from(body.querySelectorAll('tr')).filter(function (row) { return row.children.length > column; });
            const direction = button.dataset.direction === 'asc' ? -1 : 1;
            typesTable.querySelectorAll('.sort-button').forEach(function (other) { other.dataset.direction = ''; });
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
