<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/NotificationService.php';

use Glider\Storage;
use Glider\NotificationService;

$settings = Storage::readSettings();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['app'] = [
        'name' => trim((string) ($_POST['app_name'] ?? 'Glider Equipment Tracker')),
        'timezone' => trim((string) ($_POST['timezone'] ?? 'Europe/Berlin')),
    ];
    $settings['mail'] = [
        'host' => trim((string) ($_POST['mail_host'] ?? '')),
        'port' => trim((string) ($_POST['mail_port'] ?? '587')),
        'username' => trim((string) ($_POST['mail_username'] ?? '')),
        'password' => trim((string) ($_POST['mail_password'] ?? '')),
        'encryption' => trim((string) ($_POST['mail_encryption'] ?? 'tls')),
        'from_address' => trim((string) ($_POST['mail_from_address'] ?? '')),
        'from_name' => trim((string) ($_POST['mail_from_name'] ?? 'Glider Equipment Tracker')),
    ];
    Storage::saveSettings($settings);
    if (($_POST['action'] ?? '') === 'test_smtp') {
        $result = (new NotificationService($settings['mail']))->testConnection();
        $message = $result['message'];
        $messageClass = $result['success'] ? 'alert' : 'alert alert-error';
    } elseif (($_POST['action'] ?? '') === 'send_test_email') {
        $result = (new NotificationService($settings['mail']))->sendTestEmail(trim((string) ($_POST['test_email_to'] ?? '')));
        $message = $result['message'];
        $messageClass = $result['success'] ? 'alert' : 'alert alert-error';
    } else {
        $message = 'Einstellungen wurden gespeichert.';
    }
}

pageHeader('Einstellungen');
if ($message !== ''): ?><div class="<?= e($messageClass ?? 'alert'); ?>"><?= e($message); ?></div><?php endif; ?>
<section class="card"><h2>Anwendung</h2><form method="post" class="stacked-form">
    <div class="row two-col"><label>Anwendungsname<input type="text" name="app_name" value="<?= e($settings['app']['name'] ?? 'Glider Equipment Tracker'); ?>" required /></label><label>Zeitzone<input type="text" name="timezone" value="<?= e($settings['app']['timezone'] ?? 'Europe/Berlin'); ?>" required /></label></div>
    <h2>SMTP-Mailversand</h2>
    <div class="row two-col"><label>SMTP Host<input type="text" name="mail_host" value="<?= e($settings['mail']['host'] ?? ''); ?>" /></label><label>SMTP Port<input type="number" name="mail_port" value="<?= e($settings['mail']['port'] ?? '587'); ?>" /></label></div>
    <p class="form-hint">Übliche Einstellungen: Port 587 mit TLS oder Port 465 mit SSL.</p>
    <div class="row three-col"><label>Benutzername<input type="text" name="mail_username" value="<?= e($settings['mail']['username'] ?? ''); ?>" /></label><label>Passwort<input type="password" name="mail_password" value="<?= e($settings['mail']['password'] ?? ''); ?>" /></label><label>Verschlüsselung<select name="mail_encryption"><option value="tls" <?= (($settings['mail']['encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option><option value="ssl" <?= (($settings['mail']['encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL</option><option value="">Keine</option></select></label></div>
    <div class="row two-col"><label>Absenderadresse<input type="email" name="mail_from_address" value="<?= e($settings['mail']['from_address'] ?? ''); ?>" /></label><label>Absendername<input type="text" name="mail_from_name" value="<?= e($settings['mail']['from_name'] ?? 'Glider Equipment Tracker'); ?>" /></label></div>
    <div class="button-row"><button type="submit" name="action" value="save_settings">Einstellungen speichern</button><button type="submit" name="action" value="test_smtp">SMTP-Verbindung testen</button></div>
    <div class="inline-form"><input type="email" name="test_email_to" placeholder="Empfänger der Test-E-Mail" /><button type="submit" name="action" value="send_test_email">Test-E-Mail senden</button></div>
</form></section>
<?php pageFooter();
