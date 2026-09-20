<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/NotificationService.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Storage;
use Glider\NotificationService;
use Glider\Auth;

Auth::requireAdmin();
$settings = Storage::readSettings();
$editMode = isset($_GET['edit']) || $_SERVER['REQUEST_METHOD'] === 'POST';
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
    $submittedSerpApiKey = trim((string) ($_POST['image_search_serpapi_key'] ?? ''));
    $settings['image_search'] = [
        'enabled' => !empty($_POST['image_search_enabled']),
        'serpapi_key' => $submittedSerpApiKey !== '' ? $submittedSerpApiKey : (string) ($settings['image_search']['serpapi_key'] ?? ''),
    ];
    Storage::saveSettings($settings);
    $returnTo = (string) ($_POST['return_to'] ?? '');
    if ($returnTo !== '' && str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//') && ($_POST['action'] ?? '') === 'save_settings') {
        header('Location: ' . $returnTo);
        exit;
    }
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
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Systemverwaltung</p><h2>Anwendung &amp; Mailversand</h2></div><?php if ($editMode): ?><a class="button-link button-secondary" href="/settings.php">Abbrechen</a><?php else: ?><a class="button-link" href="/settings.php?edit=1">Bearbeiten</a><?php endif; ?></div>
    <form method="post" class="stacked-form"<?= $editMode ? ' data-edit-form' : ''; ?>>
        <?php if ($editMode): ?><input type="hidden" name="return_to" value="" /><?php endif; ?>
        <?php if (!$editMode): ?>
            <div class="detail-sections">
                <section class="detail-section"><h3>Anwendung</h3><div class="detail-grid"><div class="detail-row"><span class="detail-label">Anwendungsname</span><span class="detail-value"><?= e($settings['app']['name'] ?? 'Glider Equipment Tracker'); ?></span></div><div class="detail-row"><span class="detail-label">Zeitzone</span><span class="detail-value"><?= e($settings['app']['timezone'] ?? 'Europe/Berlin'); ?></span></div></div></section>
                <section class="detail-section"><h3>Mailversand</h3><div class="detail-grid"><div class="detail-row"><span class="detail-label">SMTP Host</span><span class="detail-value"><?= e($settings['mail']['host'] ?? 'Nicht konfiguriert'); ?></span></div><div class="detail-row"><span class="detail-label">SMTP Port</span><span class="detail-value"><?= e($settings['mail']['port'] ?? '587'); ?></span></div><div class="detail-row"><span class="detail-label">Benutzername</span><span class="detail-value"><?= e($settings['mail']['username'] ?? 'Nicht konfiguriert'); ?></span></div><div class="detail-row"><span class="detail-label">Verschlüsselung</span><span class="detail-value"><?= e($settings['mail']['encryption'] ?? 'tls'); ?></span></div><div class="detail-row"><span class="detail-label">Absenderadresse</span><span class="detail-value"><?= e($settings['mail']['from_address'] ?? 'Nicht konfiguriert'); ?></span></div><div class="detail-row"><span class="detail-label">Absendername</span><span class="detail-value"><?= e($settings['mail']['from_name'] ?? 'Glider Equipment Tracker'); ?></span></div></div></section>
                <section class="detail-section"><h3>Bildersuche</h3><div class="detail-grid"><div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><?= !empty($settings['image_search']['enabled']) ? 'Aktiviert' : 'Deaktiviert'; ?></span></div><div class="detail-row"><span class="detail-label">SerpApi-Key</span><span class="detail-value"><?= ($settings['image_search']['serpapi_key'] ?? '') !== '' ? 'Hinterlegt (echte Produktfotos)' : 'Nicht konfiguriert (Fallback: Openverse)'; ?></span></div></div></section>
            </div>
        <?php else: ?>
            <div class="form-sections">
                <section class="form-section"><h3>Anwendung</h3><div class="row two-col"><label>Anwendungsname<input type="text" name="app_name" value="<?= e($settings['app']['name'] ?? 'Glider Equipment Tracker'); ?>" required /></label><label>Zeitzone<input type="text" name="timezone" value="<?= e($settings['app']['timezone'] ?? 'Europe/Berlin'); ?>" required /></label></div></section>
                <section class="form-section"><h3>SMTP-Mailversand</h3><div class="row two-col"><label>SMTP Host<input type="text" name="mail_host" value="<?= e($settings['mail']['host'] ?? ''); ?>" /></label><label>SMTP Port<input type="number" name="mail_port" value="<?= e($settings['mail']['port'] ?? '587'); ?>" /></label></div><p class="form-hint">Übliche Einstellungen: Port 587 mit TLS oder Port 465 mit SSL.</p><div class="row three-col"><label>Benutzername<input type="text" name="mail_username" value="<?= e($settings['mail']['username'] ?? ''); ?>" /></label><label>Passwort<input type="password" name="mail_password" value="<?= e($settings['mail']['password'] ?? ''); ?>" /></label><label>Verschlüsselung<select name="mail_encryption"><option value="tls" <?= (($settings['mail']['encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS</option><option value="ssl" <?= (($settings['mail']['encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL</option><option value="">Keine</option></select></label></div><div class="row two-col"><label>Absenderadresse<input type="email" name="mail_from_address" value="<?= e($settings['mail']['from_address'] ?? ''); ?>" /></label><label>Absendername<input type="text" name="mail_from_name" value="<?= e($settings['mail']['from_name'] ?? 'Glider Equipment Tracker'); ?>" /></label></div></section>
                <section class="form-section"><h3>Bildersuche</h3><div class="row two-col"><label><input type="checkbox" name="image_search_enabled" value="1" <?= !empty($settings['image_search']['enabled']) ? 'checked' : ''; ?> /> Automatische Artikelbildsuche aktivieren</label></div><div class="row two-col"><label>SerpApi-Key (optional)<input type="password" name="image_search_serpapi_key" value="" placeholder="<?= ($settings['image_search']['serpapi_key'] ?? '') !== '' ? 'Unverändert lassen' : 'z.B. von serpapi.com (100 Suchen/Monat gratis)'; ?>" autocomplete="off" /></label></div><p class="form-hint">Mit SerpApi-Key werden echte Google-Produktfotos gesucht (kostenloses Kontingent: 100 Suchen/Monat). Ohne Key wird automatisch auf die kostenlose, unbegrenzte Openverse-Bildersuche (frei lizenzierte Bilder) zurückgegriffen.</p></section>
            </div>
            <div class="button-row"><button type="submit" name="action" value="save_settings">Einstellungen speichern</button><button type="submit" name="action" value="test_smtp">SMTP-Verbindung testen</button></div>
            <div class="inline-form"><input type="email" name="test_email_to" placeholder="Empfänger der Test-E-Mail" /><button type="submit" name="action" value="send_test_email">Test-E-Mail senden</button></div>
        <?php endif; ?>
    </form>
</section>
<?php pageFooter();
