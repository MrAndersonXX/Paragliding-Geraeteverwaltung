<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;
use Glider\Storage;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pageHeader(string $title): void
{
    Auth::requireLogin();
    Storage::ensure();
    $settings = Storage::readSettings();
    $appName = $settings['app']['name'] ?? 'Glider Equipment Tracker';
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?= e($title); ?> | <?= e($appName); ?></title>
        <link rel="stylesheet" href="/assets/styles.css" />
    </head>
    <body>
    <aside class="sidebar">
        <a class="brand" href="/"><?= e($appName); ?></a>
        <nav class="sidebar-nav">
            <a href="/">Übersicht</a>
            <a href="/equipment_list.php">Geräte</a>
            <a href="/calendar.php">Kalender</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="/equipment_types.php">Gerätetypen</a>
                <a href="/users.php">Benutzer</a>
                <a href="/categories.php">Dokumente</a>
                <a href="/settings.php">Einstellungen</a>
            <?php endif; ?>
        </nav>
        <?php $currentUser = Auth::user(); ?>
        <div class="sidebar-footer">
            <a class="sidebar-user" href="/profile.php">
                <span class="sidebar-user-emoji"><?= e($currentUser['emoji'] ?? '👤'); ?></span>
                <span class="sidebar-user-name"><?= e(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))); ?></span>
                <span class="role-badge"><?= (($currentUser['role'] ?? 'admin') === 'admin') ? 'Administrator' : 'Benutzer'; ?></span>
            </a>
            <a href="/logout.php">Abmelden</a>
        </div>
    </aside>
    <main class="container">
        <div class="page-heading">
            <p class="eyebrow">Verwaltung</p>
            <h1><?= e($title); ?></h1>
        </div>
    <?php
}

/**
 * Renders a compact popup emoji picker: categories as tabs, skin-tone variants
 * are hidden behind the base emoji and only offered once that emoji is chosen.
 */
function emojiPicker(array $groups, string $selected, string $fieldName = 'emoji'): void
{
    $fieldId = 'selected-' . preg_replace('/[^a-z0-9_-]/i', '', $fieldName);
    ?>
    <div class="emoji-picker" data-emoji-picker>
        <input type="hidden" name="<?= e($fieldName); ?>" id="<?= e($fieldId); ?>" value="<?= e($selected); ?>" required />
        <button type="button" class="emoji-picker-trigger" data-emoji-trigger aria-haspopup="true" aria-expanded="false">
            <span class="emoji-picker-preview" data-emoji-preview><?= e($selected !== '' ? $selected : '😀'); ?></span>
            <span>Emoticon wählen</span>
        </button>
        <div class="emoji-popup" data-emoji-popup hidden>
            <div class="emoji-popup-tabs" role="tablist">
                <?php $first = true;
                foreach ($groups as $groupName => $subgroups): ?>
                    <button type="button" class="emoji-tab<?= $first ? ' active' : ''; ?>" data-emoji-tab="<?= e($groupName); ?>" role="tab"><?= e($groupName); ?></button>
                    <?php $first = false;
                endforeach; ?>
            </div>
            <div class="emoji-popup-body">
                <?php $first = true;
                foreach ($groups as $groupName => $subgroups): ?>
                    <div class="emoji-tab-panel<?= $first ? ' active' : ''; ?>" data-emoji-panel="<?= e($groupName); ?>">
                        <?php foreach ($subgroups as $subgroupName => $items): ?>
                            <?php if (count($subgroups) > 1): ?><h4 class="emoji-subgroup-title"><?= e($subgroupName); ?></h4><?php endif; ?>
                            <div class="emoji-grid">
                                <?php foreach ($items as $item):
                                    $variantEmojis = array_column($item['variants'], 'emoji');
                                    $isSelected = $selected === $item['emoji'] || in_array($selected, $variantEmojis, true);
                                    $swatches = $item['variants'] ? array_merge([['emoji' => $item['emoji'], 'name' => $item['name']]], $item['variants']) : [];
                                    ?>
                                    <button type="button" class="emoji-option<?= $isSelected ? ' selected' : ''; ?>" data-emoji="<?= e($item['emoji']); ?>" title="<?= e($item['name']); ?>" aria-label="<?= e($item['name']); ?>"<?= $swatches ? ' data-variants="' . e(json_encode($swatches, JSON_UNESCAPED_UNICODE)) . '"' : ''; ?>><?= e($item['emoji']); ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php $first = false;
                endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function pageFooter(): void
{
    ?>
    <script src="/assets/emoji-picker.js"></script>
    <script>
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
    document.querySelectorAll('form[data-edit-form]').forEach(function (form) {
        let changed = false;
        form.addEventListener('input', function () { changed = true; });
        form.addEventListener('change', function () { changed = true; });
        form.addEventListener('submit', function () { changed = false; });
        document.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!changed || link.target === '_blank' || link.href === window.location.href) {
                    return;
                }
                const leaveAndSave = window.confirm('Änderungen speichern und Seite verlassen?\nAbbrechen bleibt auf dieser Seite.');
                if (!leaveAndSave) {
                    event.preventDefault();
                    return;
                }
                event.preventDefault();
                const returnTo = form.querySelector('input[name="return_to"]');
                if (returnTo) {
                    const target = new URL(link.href);
                    returnTo.value = target.pathname + target.search;
                }
                form.submit();
            });
        });
    });
    </script>
    </main>
    </body>
    </html>
    <?php
}
