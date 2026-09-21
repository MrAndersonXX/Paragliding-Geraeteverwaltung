<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;
use Glider\I18n;
use Glider\Storage;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pageHeader(string $title): void
{
    Auth::requireLogin();
    Storage::ensure();
    $locale = I18n::locale();
    $settings = Storage::readSettings();
    $appName = $settings['app']['name'] ?? 'Glider Equipment Tracker';
    ?>
    <!DOCTYPE html>
    <html lang="<?= e($locale); ?>">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?= e($title); ?> | <?= e($appName); ?></title>
        <link rel="stylesheet" href="/assets/styles.css" />
    </head>
    <body>
    <aside class="sidebar" data-sidebar>
        <a class="brand" href="/"><?= e($appName); ?></a>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="primary-navigation" aria-expanded="false">
            <span class="sidebar-toggle-icon" aria-hidden="true"></span>
            <span><?= e(__('app.menu')); ?></span>
        </button>
        <nav class="sidebar-nav" id="primary-navigation">
            <a href="/"><?= e(__('nav.overview')); ?></a>
            <a href="/equipment_list.php"><?= e(__('nav.equipment')); ?></a>
            <a href="/calendar.php"><?= e(__('nav.calendar')); ?></a>
            <?php if (Auth::isAdmin()): ?>
                <a href="/equipment_types.php"><?= e(__('nav.equipment_types')); ?></a>
                <a href="/users.php"><?= e(__('nav.users')); ?></a>
                <a href="/categories.php"><?= e(__('nav.documents')); ?></a>
                <a href="/settings.php"><?= e(__('nav.settings')); ?></a>
                <a href="/backup.php"><?= e(__('nav.import_export')); ?></a>
                <a href="/audit_log.php"><?= e(__('nav.audit_log')); ?></a>
            <?php endif; ?>
        </nav>
        <?php $currentUser = Auth::user(); ?>
        <div class="sidebar-footer">
            <a class="sidebar-user" href="/profile.php">
                <span class="sidebar-user-emoji"><?= e($currentUser['emoji'] ?? '👤'); ?></span>
                <span class="sidebar-user-name"><?= e(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''))); ?></span>
                <span class="role-badge"><?= e((($currentUser['role'] ?? 'admin') === 'admin') ? __('nav.administrator') : __('nav.user')); ?></span>
            </a>
            <details class="language-switcher">
                <summary aria-label="<?= e(__('language.choose')); ?>" title="<?= e(__('language.choose')); ?>"><img src="<?= e(I18n::locales()[$locale]['flag']); ?>" alt="" /></summary>
                <div class="language-options">
                    <?php foreach (I18n::locales() as $language => $languageInfo): ?>
                        <?php if ($language === $locale) { continue; } ?>
                        <form method="post" action="/language.php">
                            <input type="hidden" name="language" value="<?= e($language); ?>" />
                            <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/'); ?>" />
                            <button type="submit" class="language-option" aria-label="<?= e($languageInfo['label']); ?>" title="<?= e($languageInfo['label']); ?>"><img src="<?= e($languageInfo['flag']); ?>" alt="" /></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </details>
            <a href="/logout.php"><?= e(__('nav.logout')); ?></a>
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
            <span><?= e(__('emoji.choose')); ?></span>
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
    <script src="/assets/table-sort.js"></script>
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
                const leaveAndSave = window.confirm(<?= json_encode(__('form.leave_without_saving'), JSON_UNESCAPED_UNICODE); ?>);
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
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (toggle) {
        const sidebar = toggle.closest('[data-sidebar]');
        if (!sidebar) {
            return;
        }
        toggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('menu-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
        sidebar.querySelectorAll('.sidebar-nav a, .sidebar-footer a').forEach(function (link) {
            link.addEventListener('click', function () {
                sidebar.classList.remove('menu-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    });
    </script>
    </main>
    </body>
    </html>
    <?php
}
