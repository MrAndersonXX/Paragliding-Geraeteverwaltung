<?php

header('Location: /equipment.php');
exit;

require __DIR__ . '/../src/Storage.php';
require __DIR__ . '/../src/CalendarClient.php';

use Glider\CalendarClient;
use Glider\Storage;

Storage::ensure();
$equipment = Storage::readEquipment();
$documentCategories = Storage::readDocumentCategories();
$settings = Storage::readSettings();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_equipment') {
        $newId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : ((count($equipment) > 0 ? max(array_map(fn($item) => (int) ($item['id'] ?? 0), $equipment)) : 0) + 1);

        $item = [
            'id' => $newId,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
            'equipment_type' => trim((string) ($_POST['equipment_type'] ?? '')),
            'size' => trim((string) ($_POST['size'] ?? '')),
            'serial_number' => trim((string) ($_POST['serial_number'] ?? '')),
            'purchase_date' => trim((string) ($_POST['purchase_date'] ?? '')),
            'owner' => trim((string) ($_POST['owner'] ?? '')),
            'assigned_user' => trim((string) ($_POST['assigned_user'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
            'inspection_interval_days' => (int) ($_POST['inspection_interval_days'] ?? 0),
            'inspection_start_date' => trim((string) ($_POST['inspection_start_date'] ?? '')),
            'last_inspection_date' => trim((string) ($_POST['last_inspection_date'] ?? '')),
            'next_inspection_date' => trim((string) ($_POST['next_inspection_date'] ?? '')),
            'manufacturer_check_date' => trim((string) ($_POST['manufacturer_check_date'] ?? '')),
            'manufacturer_validity_days' => (int) ($_POST['manufacturer_validity_days'] ?? 0),
            'max_operating_days' => (int) ($_POST['max_operating_days'] ?? 0),
            'retired_at' => trim((string) ($_POST['retired_at'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'notifications' => [
                '30_days' => !empty($_POST['notification_30_days']),
                '14_days' => !empty($_POST['notification_14_days']),
                '7_days' => !empty($_POST['notification_7_days']),
                'due' => !empty($_POST['notification_due']),
                'retired' => !empty($_POST['notification_retired']),
            ],
        ];

        $updated = false;
        foreach ($equipment as $index => $existing) {
            if ((int) ($existing['id'] ?? 0) === $newId) {
                $equipment[$index] = $item;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $equipment[] = $item;
        }

        Storage::saveEquipment($equipment);
        $message = 'Gerät wurde gespeichert.';
    }

    if ($action === 'save_category') {
        $name = trim((string) ($_POST['category_name'] ?? ''));
        if ($name !== '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $documentCategories[] = [
                'id' => (count($documentCategories) > 0 ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $documentCategories)) : 0) + 1,
                'name' => $name,
                'slug' => $slug,
            ];
            Storage::saveDocumentCategories($documentCategories);
            $message = 'Dokumentkategorie wurde angelegt.';
        }
    }

    if ($action === 'save_settings') {
        $settings = [
            'mail' => [
                'host' => trim((string) ($_POST['mail_host'] ?? '')),
                'port' => trim((string) ($_POST['mail_port'] ?? '587')),
                'username' => trim((string) ($_POST['mail_username'] ?? '')),
                'password' => trim((string) ($_POST['mail_password'] ?? '')),
                'encryption' => trim((string) ($_POST['mail_encryption'] ?? 'tls')),
                'from_address' => trim((string) ($_POST['mail_from_address'] ?? '')),
                'from_name' => trim((string) ($_POST['mail_from_name'] ?? 'Glider Equipment Tracker')),
            ],
            'calendar' => [
                'url' => trim((string) ($_POST['calendar_url'] ?? '')),
                'username' => trim((string) ($_POST['calendar_username'] ?? '')),
                'password' => trim((string) ($_POST['calendar_password'] ?? '')),
            ],
        ];
        Storage::saveSettings($settings);
        $message = 'E-Mail- und Kalender-Einstellungen wurden gespeichert.';
    }

    if ($action === 'test_mail') {
        $to = trim((string) ($_POST['mail_test_to'] ?? ''));
        if ($to !== '') {
            $subject = 'Testbenachrichtigung Glider Equipment Tracker';
            $body = "Hallo,\n\ndies ist eine Test-E-Mail der Glider Equipment Tracker Anwendung.\n\nWenn diese E-Mail ankommt, ist der SMTP-Server korrekt konfiguriert.";
            $headers = "From: " . ($settings['mail']['from_name'] ?? 'Glider Equipment Tracker') . " <" . ($settings['mail']['from_address'] ?? 'tracker@example.com') . ">\r\n";
            $result = mail($to, $subject, $body, $headers);
            $message = $result ? 'Test-E-Mail wurde versendet.' : 'Test-E-Mail konnte nicht versendet werden.';
        }
    }

    if ($action === 'test_calendar') {
        $calendarUrl = $settings['calendar']['url'] ?? '';
        $calendarClient = new CalendarClient($calendarUrl, $settings['calendar']['username'] ?? '', $settings['calendar']['password'] ?? '');
        $events = $calendarClient->fetchEvents();
        $message = count($events) > 0 ? 'Kalenderzugriff erfolgreich. ' . count($events) . ' Ereignisse geladen.' : 'Kalenderzugriff fehlgeschlagen oder keine Ereignisse gefunden.';
    }
}

$calendar = new CalendarClient($settings['calendar']['url'] ?? '', $settings['calendar']['username'] ?? '', $settings['calendar']['password'] ?? '');
$calendarEvents = $calendar->fetchEvents();

$equipmentByStatus = [
    'active' => 0,
    'inspection' => 0,
    'retired' => 0,
];
foreach ($equipment as $item) {
    $status = $item['status'] ?? 'active';
    if (isset($equipmentByStatus[$status])) {
        $equipmentByStatus[$status]++;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Glider Equipment Tracker</title>
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <h1>Glider Equipment Tracker</h1>
            <nav>
                <a href="/forms.php">Übersicht</a>
                <a href="/forms.php#equipment-form">Gerät erfassen</a>
                <a href="/forms.php#settings">Einstellungen</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($message !== ''): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <section class="card stats-grid">
            <div class="stat-box">
                <span>aktive Geräte</span>
                <strong><?= (int) $equipmentByStatus['active']; ?></strong>
            </div>
            <div class="stat-box">
                <span>Prüfungsbedarf</span>
                <strong><?= (int) $equipmentByStatus['inspection']; ?></strong>
            </div>
            <div class="stat-box">
                <span>ausgemustert</span>
                <strong><?= (int) $equipmentByStatus['retired']; ?></strong>
            </div>
        </section>

        <section class="card" id="equipment-form">
            <h2>Gerät erfassen / bearbeiten</h2>
            <form method="post" class="stacked-form">
                <input type="hidden" name="action" value="save_equipment" />
                <div class="row two-col">
                    <label>Gerätename
                        <input type="text" name="name" required />
                    </label>
                    <label>Kategorie
                        <select name="category">
                            <option>Gleitschirm</option>
                            <option>Rettungsgerät</option>
                            <option>Gurtzeug</option>
                            <option>Helm</option>
                            <option>Sonstiges</option>
                        </select>
                    </label>
                </div>

                <div class="row three-col">
                    <label>Hersteller
                        <input type="text" name="manufacturer" />
                    </label>
                    <label>Gerätetyp
                        <input type="text" name="equipment_type" />
                    </label>
                    <label>Größe
                        <input type="text" name="size" />
                    </label>
                </div>

                <div class="row three-col">
                    <label>Seriennummer
                        <input type="text" name="serial_number" />
                    </label>
                    <label>Anschaffungsdatum
                        <input type="date" name="purchase_date" />
                    </label>
                    <label>Status
                        <select name="status">
                            <option value="active">aktiv</option>
                            <option value="inspection">in Prüfung</option>
                            <option value="retired">ausgemustert</option>
                        </select>
                    </label>
                </div>

                <div class="row three-col">
                    <label>Besitzer / Verbau
                        <input type="text" name="owner" />
                    </label>
                    <label>Verantwortliche Person
                        <input type="text" name="assigned_user" />
                    </label>
                    <label>Prüfungsinterval in Tagen
                        <input type="number" name="inspection_interval_days" min="0" value="365" />
                    </label>
                </div>

                <div class="row three-col">
                    <label>Beginn Prüfungsdatum
                        <input type="date" name="inspection_start_date" />
                    </label>
                    <label>Letzte Prüfung
                        <input type="date" name="last_inspection_date" />
                    </label>
                    <label>Nächste Prüfung
                        <input type="date" name="next_inspection_date" />
                    </label>
                </div>

                <div class="row three-col">
                    <label>Hersteller-Nachprüfung
                        <input type="date" name="manufacturer_check_date" />
                    </label>
                    <label>Gültigkeit in Tagen
                        <input type="number" name="manufacturer_validity_days" min="0" value="0" />
                    </label>
                    <label>Max. Betriebsdauer in Tagen
                        <input type="number" name="max_operating_days" min="0" value="0" />
                    </label>
                </div>

                <div class="row two-col">
                    <label>Ausmusterungsdatum
                        <input type="date" name="retired_at" />
                    </label>
                    <label>Notiz
                        <textarea name="notes" rows="3"></textarea>
                    </label>
                </div>

                <fieldset>
                    <legend>Benachrichtigungen pro Gerät</legend>
                    <div class="checkbox-row">
                        <label><input type="checkbox" name="notification_30_days" value="1" /> 30 Tage vor Fälligkeit</label>
                        <label><input type="checkbox" name="notification_14_days" value="1" /> 14 Tage vor Fälligkeit</label>
                        <label><input type="checkbox" name="notification_7_days" value="1" /> 7 Tage vor Fälligkeit</label>
                        <label><input type="checkbox" name="notification_due" value="1" /> bei Überfälligkeit</label>
                        <label><input type="checkbox" name="notification_retired" value="1" /> bei Ausmusterung</label>
                    </div>
                </fieldset>

                <button type="submit">Gerät speichern</button>
            </form>
        </section>

        <section class="card">
            <h2>Gerätesammlung</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Kategorie</th>
                        <th>Hersteller</th>
                        <th>Seriennummer</th>
                        <th>nächste Prüfung</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($equipment as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($item['category'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($item['manufacturer'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($item['serial_number'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($item['next_inspection_date'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($item['status'] ?? 'active'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" id="document-categories">
            <h2>Dokumentenkategorien</h2>
            <form method="post" class="inline-form">
                <input type="hidden" name="action" value="save_category" />
                <input type="text" name="category_name" placeholder="Neue Kategorie" required />
                <button type="submit">Kategorie anlegen</button>
            </form>
            <ul class="tag-list">
                <?php foreach ($documentCategories as $category): ?>
                    <li><?= htmlspecialchars($category['name'] ?? ''); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card" id="settings">
            <h2>E-Mail und Kalender</h2>

            <form method="post" class="stacked-form">
                <input type="hidden" name="action" value="save_settings" />
                <div class="row two-col">
                    <label>SMTP Host
                        <input type="text" name="mail_host" value="<?= htmlspecialchars($settings['mail']['host'] ?? ''); ?>" />
                    </label>
                    <label>SMTP Port
                        <input type="number" name="mail_port" value="<?= htmlspecialchars($settings['mail']['port'] ?? '587'); ?>" />
                    </label>
                </div>
                <div class="row three-col">
                    <label>SMTP Nutzer
                        <input type="text" name="mail_username" value="<?= htmlspecialchars($settings['mail']['username'] ?? ''); ?>" />
                    </label>
                    <label>SMTP Passwort
                        <input type="password" name="mail_password" value="<?= htmlspecialchars($settings['mail']['password'] ?? ''); ?>" />
                    </label>
                    <label>Verschlüsselung
                        <select name="mail_encryption">
                            <option value="tls" <?= (($settings['mail']['encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?= (($settings['mail']['encryption'] ?? 'tls') === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                            <option value="" <?= (($settings['mail']['encryption'] ?? 'tls') === '') ? 'selected' : ''; ?>>Keine</option>
                        </select>
                    </label>
                </div>
                <div class="row two-col">
                    <label>Absenderadresse
                        <input type="email" name="mail_from_address" value="<?= htmlspecialchars($settings['mail']['from_address'] ?? ''); ?>" />
                    </label>
                    <label>Absendername
                        <input type="text" name="mail_from_name" value="<?= htmlspecialchars($settings['mail']['from_name'] ?? 'Glider Equipment Tracker'); ?>" />
                    </label>
                </div>

                <div class="row three-col">
                    <label>iCal URL
                        <input type="url" name="calendar_url" value="<?= htmlspecialchars($settings['calendar']['url'] ?? ''); ?>" />
                    </label>
                    <label>Kalender Benutzer
                        <input type="text" name="calendar_username" value="<?= htmlspecialchars($settings['calendar']['username'] ?? ''); ?>" />
                    </label>
                    <label>Kalender Passwort
                        <input type="password" name="calendar_password" value="<?= htmlspecialchars($settings['calendar']['password'] ?? ''); ?>" />
                    </label>
                </div>

                <button type="submit">Einstellungen speichern</button>
            </form>

            <div class="button-row">
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="test_mail" />
                    <input type="email" name="mail_test_to" placeholder="E-Mail Testadresse" required />
                    <button type="submit">Mail testen</button>
                </form>

                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="test_calendar" />
                    <button type="submit">Kalender testen</button>
                </form>
            </div>
        </section>

        <section class="card">
            <h2>Kalenderereignisse</h2>
            <?php if (count($calendarEvents) === 0): ?>
                <p>Keine Kalenderereignisse gefunden oder kein gültiger Feed konfiguriert.</p>
            <?php else: ?>
                <ul class="event-list">
                    <?php foreach ($calendarEvents as $event): ?>
                        <li>
                            <strong><?= htmlspecialchars($event['summary'] ?? 'Ohne Titel'); ?></strong>
                            <span><?= htmlspecialchars($event['start'] ?? ''); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
