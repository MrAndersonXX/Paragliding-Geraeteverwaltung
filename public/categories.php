<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Storage;
use Glider\Auth;

Auth::requireAdmin();
$categories = Storage::readDocumentCategories();
$documents = Storage::readEquipmentDocuments();
$usage = [];
foreach ($documents as $document) {
    $categoryId = (int) ($document['category_id'] ?? 0);
    if ($categoryId > 0) {
        $usage[$categoryId] = ($usage[$categoryId] ?? 0) + 1;
    }
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        if (($usage[$id] ?? 0) > 0) {
            $message = 'Die Kategorie kann nicht gelöscht werden, solange sie von Dokumenten verwendet wird.';
        } else {
            $categories = array_values(array_filter($categories, static fn ($category) => (int) ($category['id'] ?? 0) !== $id));
            Storage::saveDocumentCategories($categories);
            header('Location: /categories.php?saved=1');
            exit;
        }
    }
    $name = trim((string) ($_POST['category_name'] ?? ''));
    if ($action === 'create' && $name !== '') {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $categories[] = ['id' => (count($categories) ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $categories)) : 0) + 1, 'name' => $name, 'slug' => $slug];
        Storage::saveDocumentCategories($categories);
        $message = 'Dokumentkategorie wurde angelegt.';
    }
}

pageHeader(__('page.categories'));
if (isset($_GET['saved'])): ?><div class="alert">Dokumentkategorie wurde gelöscht.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <h2>Neue Kategorie</h2>
    <form method="post" class="inline-form"><input type="text" name="category_name" placeholder="z. B. Wartung" required /><button type="submit">Kategorie anlegen</button></form>
</section>
<section class="card"><h2>Vorhandene Kategorien</h2><div class="table-wrap"><table><thead><tr><th>Kategorie</th><th>Verwendung</th><th>Aktionen</th></tr></thead><tbody><?php foreach ($categories as $category): ?><?php $categoryId = (int) ($category['id'] ?? 0); ?><?php $categoryUsage = (int) ($usage[$categoryId] ?? 0); ?><tr><td><?= e($category['name'] ?? ''); ?></td><td><?= $categoryUsage; ?></td><td class="actions"><form method="post" class="action-form"><input type="hidden" name="action" value="delete" /><input type="hidden" name="id" value="<?= $categoryId; ?>" /><button type="submit" class="button-muted" <?= $categoryUsage > 0 ? 'disabled title="Noch von Dokumenten verwendet"' : ''; ?>>Löschen</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php pageFooter();
