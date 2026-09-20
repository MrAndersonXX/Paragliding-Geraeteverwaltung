<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Storage;
use Glider\Auth;

Storage::ensure();
$settings = Storage::readSettings();
$appName = $settings['app']['name'] ?? 'Glider Equipment Tracker';
$timezone = $settings['app']['timezone'] ?? 'Europe/Berlin';
$equipment = Storage::readEquipment();
$currentUser = Auth::user();
if (!Auth::isAdmin()) {
    $equipment = array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}
$counts = ['active' => 0, 'inspection' => 0, 'retired' => 0];
foreach ($equipment as $item) {
    $status = $item['status'] ?? 'active';
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}
?>
<?php pageHeader('Übersicht'); ?>
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
<?php pageFooter(); ?>
