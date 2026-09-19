<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/CalendarClient.php';

use Glider\CalendarClient;
use Glider\Storage;

$settings = Storage::readSettings();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['calendar'] = [
        'url' => trim((string) ($_POST['calendar_url'] ?? '')),
        'username' => trim((string) ($_POST['calendar_username'] ?? '')),
        'password' => trim((string) ($_POST['calendar_password'] ?? '')),
    ];
    Storage::saveSettings($settings);
    $message = 'Kalendereinstellungen wurden gespeichert.';
    if (($_POST['action'] ?? '') === 'test_calendar') {
        $client = new CalendarClient($settings['calendar']['url'], $settings['calendar']['username'], $settings['calendar']['password']);
        $events = $client->fetchEvents();
        $message = count($events) > 0 ? count($events) . ' Kalenderereignisse geladen.' : 'Keine Ereignisse gefunden oder der Kalender ist nicht erreichbar.';
    }
}

$calendar = new CalendarClient($settings['calendar']['url'] ?? '', $settings['calendar']['username'] ?? '', $settings['calendar']['password'] ?? '');
$events = $calendar->fetchEvents();
pageHeader('Kalender');
if ($message !== ''): ?><div class="alert"><?= e($message); ?></div><?php endif; ?>
<section class="card"><h2>Kalenderzugriff</h2><form method="post" class="stacked-form">
    <label>iCal- oder CalDAV-URL<input type="url" name="calendar_url" value="<?= e($settings['calendar']['url'] ?? ''); ?>" /></label>
    <div class="row two-col"><label>Benutzername<input type="text" name="calendar_username" value="<?= e($settings['calendar']['username'] ?? ''); ?>" /></label><label>Passwort<input type="password" name="calendar_password" value="<?= e($settings['calendar']['password'] ?? ''); ?>" /></label></div>
    <div class="button-row"><button type="submit" name="action" value="save_calendar">Kalender speichern</button><button type="submit" name="action" value="test_calendar">Speichern und testen</button></div>
</form></section>
<section class="card"><h2>Ereignisse</h2><?php if (!$events): ?><p>Keine Kalenderereignisse gefunden.</p><?php else: ?><ul class="event-list"><?php foreach ($events as $event): ?><li><strong><?= e($event['summary'] ?? 'Ohne Titel'); ?></strong><span><?= e($event['start'] ?? ''); ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php pageFooter();
