<?php

require_once __DIR__ . '/../src/Storage.php';

use Glider\Storage;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pageHeader(string $title): void
{
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
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="/"><?= e($appName); ?></a>
            <nav>
                <a href="/">Übersicht</a>
                <a href="/equipment_list.php">Geräte</a>
                <a href="/categories.php">Dokumente</a>
                <a href="/calendar.php">Kalender</a>
                <a href="/settings.php">Einstellungen</a>
            </nav>
        </div>
    </header>
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
    </main>
    </body>
    </html>
    <?php
}
