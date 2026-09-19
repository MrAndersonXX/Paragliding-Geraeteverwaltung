<?php

require __DIR__ . '/../src/Config.php';

$appConfig = \Glider\Config::load();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($appConfig['name']); ?></title>
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <h1><?= htmlspecialchars($appConfig['name']); ?></h1>
            <nav>
                <a href="/forms.php">Geräteverwaltung</a>
                <a href="/forms.php#settings">Einstellungen</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="card">
            <h2>Systemstatus</h2>
            <ul>
                <li>Umgebung: <?= htmlspecialchars($appConfig['env']); ?></li>
                <li>Zeitzone: <?= htmlspecialchars($appConfig['timezone']); ?></li>
                <li>SMTP: <?= !empty($appConfig['mail']['host']) ? 'konfiguriert' : 'nicht konfiguriert'; ?></li>
                <li>iCal: <?= !empty($appConfig['ical']['url']) ? 'konfiguriert' : 'nicht konfiguriert'; ?></li>
            </ul>
        </section>

        <section class="card">
            <h2>Verfügbare Gerätekategorien</h2>
            <ul>
                <li>Gleitschirm</li>
                <li>Rettungsgerät</li>
                <li>Gurtzeug</li>
                <li>Helm</li>
                <li>Sonstiges</li>
            </ul>
        </section>

        <section class="card">
            <h2>Funktionsumfang</h2>
            <ul>
                <li>Geräteverwaltung</li>
                <li>Prüfungsintervalle</li>
                <li>Herstellernachprüfung</li>
                <li>Dokumentenmanagement mit Kategorien</li>
                <li>Mail-Benachrichtigungen</li>
                <li>iCal-Kalenderzugriff</li>
            </ul>
        </section>
    </main>
</body>
</html>
