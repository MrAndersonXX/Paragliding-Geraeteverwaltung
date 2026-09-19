<?php

require __DIR__ . '/../src/Storage.php';

use Glider\Storage;

Storage::ensure();
$settings = Storage::readSettings();
$appName = $settings['app']['name'] ?? 'Glider Equipment Tracker';
$timezone = $settings['app']['timezone'] ?? 'Europe/Berlin';
$equipment = Storage::readEquipment();
$counts = ['active' => 0, 'inspection' => 0, 'retired' => 0];
foreach ($equipment as $item) {
    $status = $item['status'] ?? 'active';
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($appName); ?></title>
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <h1><?= htmlspecialchars($appName); ?></h1>
            <nav>
                <a href="/">Übersicht</a>
                <a href="/equipment.php">Geräte</a>
                <a href="/categories.php">Dokumente</a>
                <a href="/calendar.php">Kalender</a>
                <a href="/settings.php">Einstellungen</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="card page-intro">
            <p class="eyebrow">Startseite</p>
            <h2><?= htmlspecialchars($appName); ?></h2>
            <p>Verwalte Geräte, Prüfungen, Dokumentkategorien, Benachrichtigungen und Kalenderzugriff über die einzelnen Bereiche.</p>
        </section>
        <section class="card stats-grid">
            <div class="stat-box"><span>aktive Geräte</span><strong><?= $counts['active']; ?></strong></div>
            <div class="stat-box"><span>Prüfung</span><strong><?= $counts['inspection']; ?></strong></div>
            <div class="stat-box"><span>ausgemustert</span><strong><?= $counts['retired']; ?></strong></div>
        </section>
        <section class="card">
            <h2>System</h2>
            <ul>
                <li>Zeitzone: <?= htmlspecialchars($timezone); ?></li>
                <li>SMTP: <?= !empty($settings['mail']['host']) ? 'konfiguriert' : 'nicht konfiguriert'; ?></li>
                <li>Kalender: <?= !empty($settings['calendar']['url']) ? 'konfiguriert' : 'nicht konfiguriert'; ?></li>
            </ul>
        </section>
    </main>
</body>
</html>
