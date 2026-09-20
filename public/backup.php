<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/BackupManager.php';

use Glider\Auth;
use Glider\BackupManager;

Auth::requireAdmin();

$message = '';
$messageClass = 'alert';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'export') {
        $archivePath = BackupManager::createArchive('export');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="glider-backup-' . gmdate('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($archivePath));
        readfile($archivePath);
        unlink($archivePath);
        exit;
    }

    if ($action === 'import') {
        if (empty($_POST['confirm_overwrite'])) {
            $message = 'Bitte bestätige, dass alle aktuellen Daten überschrieben werden.';
            $messageClass = 'alert alert-error';
        } elseif (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Es wurde keine gültige Sicherungsdatei hochgeladen.';
            $messageClass = 'alert alert-error';
        } else {
            $result = BackupManager::restoreArchive($_FILES['backup_file']['tmp_name']);
            $message = $result['message'];
            $messageClass = $result['success'] ? 'alert' : 'alert alert-error';
            if ($result['success']) {
                Auth::logout();
                header('Location: /login.php?imported=1');
                exit;
            }
        }
    }
}

$safetyBackups = BackupManager::listSafetyBackups();

pageHeader('Import / Export');
if ($message !== ''): ?><div class="<?= e($messageClass); ?>"><?= e($message); ?></div><?php endif; ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Systemverwaltung</p><h2>Datensicherung</h2></div></div>
    <p class="form-hint">Der Export enthält alle Geräte, Benutzer, Einstellungen, Kategorien, Gerätetypen, Dokumente und Gerätebilder als ein ZIP-Archiv.</p>
    <form method="post">
        <div class="button-row">
            <button type="submit" name="action" value="export">Alle Daten exportieren (ZIP)</button>
        </div>
    </form>
</section>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Systemverwaltung</p><h2>Datenwiederherstellung</h2></div></div>
    <p class="form-hint">Beim Import werden alle aktuellen Geräte, Benutzer, Einstellungen, Kategorien, Gerätetypen, Dokumente und Gerätebilder durch den Inhalt des hochgeladenen Archivs ersetzt. Vor dem Import wird automatisch eine Sicherheitskopie des bisherigen Standes unter <code>storage/app/backups/</code> abgelegt.</p>
    <form method="post" enctype="multipart/form-data" class="stacked-form" data-confirm="Alle aktuellen Daten werden durch den Inhalt des Archivs ersetzt. Fortfahren?">
        <div class="row two-col">
            <label>Sicherungsdatei (ZIP)<input type="file" name="backup_file" accept=".zip" required /></label>
        </div>
        <div class="row two-col">
            <label><input type="checkbox" name="confirm_overwrite" value="1" required /> Ich bestätige, dass alle aktuellen Daten überschrieben werden.</label>
        </div>
        <div class="button-row">
            <button type="submit" name="action" value="import">Sicherung importieren</button>
        </div>
    </form>
</section>
<?php if ($safetyBackups !== []): ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Systemverwaltung</p><h2>Automatische Sicherheitskopien</h2></div></div>
    <p class="form-hint">Vor jedem Import wird hier automatisch eine Kopie des vorherigen Standes abgelegt (<code>storage/app/backups/</code>, Zugriff nur über das Dateisystem/die Konsole).</p>
    <div class="detail-grid">
        <?php foreach ($safetyBackups as $backup): ?>
            <div class="detail-row">
                <span class="detail-label"><?= e($backup['name']); ?></span>
                <span class="detail-value"><?= e(date('d.m.Y H:i', strtotime($backup['created_at']))); ?> · <?= e(number_format($backup['size'] / 1024, 0, ',', '.')); ?> KB</span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php pageFooter();
