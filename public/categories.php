<?php

require __DIR__ . '/_layout.php';

$categories = Storage::readDocumentCategories();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['category_name'] ?? ''));
    if ($name !== '') {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $categories[] = ['id' => (count($categories) ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $categories)) : 0) + 1, 'name' => $name, 'slug' => $slug];
        Storage::saveDocumentCategories($categories);
        $message = 'Dokumentkategorie wurde angelegt.';
    }
}

pageHeader('Dokumentkategorien');
if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <h2>Neue Kategorie</h2>
    <form method="post" class="inline-form"><input type="text" name="category_name" placeholder="z. B. Wartung" required /><button type="submit">Kategorie anlegen</button></form>
</section>
<section class="card"><h2>Vorhandene Kategorien</h2><ul class="tag-list"><?php foreach ($categories as $category): ?><li><?= e($category['name'] ?? ''); ?></li><?php endforeach; ?></ul></section>
<?php pageFooter();
