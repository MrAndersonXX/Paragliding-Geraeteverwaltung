<?php

require __DIR__ . '/_layout.php';

use Glider\Storage;

$types = Storage::readEquipmentTypes();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['type_name'] ?? ''));
    $exists = array_filter($types, static fn ($type) => strcasecmp((string) ($type['name'] ?? ''), $name) === 0);
    if ($name === '') {
        $message = 'Bitte einen Gerätetyp eingeben.';
    } elseif ($exists) {
        $message = 'Dieser Gerätetyp existiert bereits.';
    } else {
        $types[] = ['id' => (count($types) ? max(array_map(fn ($type) => (int) ($type['id'] ?? 0), $types)) : 0) + 1, 'name' => $name];
        Storage::saveEquipmentTypes($types);
        header('Location: /equipment_types.php?saved=1');
        exit;
    }
}

pageHeader('Gerätetypen');
if (isset($_GET['saved'])): ?><div class="alert">Gerätetyp wurde gespeichert.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card"><h2>Gerätetyp erweitern</h2><form method="post" class="inline-form"><input type="text" name="type_name" placeholder="z. B. Tandemschirm" required /><button type="submit">Gerätetyp anlegen</button></form></section>
<section class="card"><h2>Verfügbare Gerätetypen</h2><ul class="tag-list"><?php foreach ($types as $type): ?><li><?= e($type['name'] ?? ''); ?></li><?php endforeach; ?></ul></section>
<?php pageFooter();
