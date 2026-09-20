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
            <a href="/logout.php">Abmelden</a>
        </nav>
    </aside>
    <main class="container">
        <div class="page-heading">
            <p class="eyebrow">Verwaltung</p>
            <h1><?= e($title); ?></h1>
        </div>
    <?php
}

function pageFooter(): void
{
    ?>
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
