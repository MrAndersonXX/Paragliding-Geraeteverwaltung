<?php

namespace Glider;

require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/AuditLog.php';

/**
 * Exports and imports the complete application state (JSON data, uploaded
 * documents and equipment images) as a single portable ZIP archive.
 */
class BackupManager
{
    private const MANIFEST_APP = 'glider-equipment-tracker';
    private const DATA_FILES = [
        'equipment.json',
        'users.json',
        'settings.json',
        'document_categories.json',
        'equipment_types.json',
        'equipment_documents.json',
        'audit_log.json',
    ];

    private static function dataDir(): string
    {
        return __DIR__ . '/../storage/data';
    }

    private static function backupsDir(): string
    {
        $dir = __DIR__ . '/../storage/app/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Creates a ZIP archive with all data files, documents and images and
     * returns its filesystem path. The caller is responsible for deleting it.
     */
    public static function createArchive(string $label = 'export'): string
    {
        Storage::ensure();
        $timestamp = gmdate('Ymd-His');
        $path = sys_get_temp_dir() . '/glider-' . preg_replace('/[^a-z0-9-]/i', '', $label) . '-' . $timestamp . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP-Archiv konnte nicht erstellt werden.');
        }

        $zip->addFromString('manifest.json', json_encode([
            'app' => self::MANIFEST_APP,
            'exported_at' => gmdate('c'),
            'version' => trim((string) @file_get_contents(__DIR__ . '/../VERSION')),
        ], JSON_PRETTY_PRINT));

        foreach (self::DATA_FILES as $file) {
            $filePath = self::dataDir() . '/' . $file;
            if (is_file($filePath)) {
                $zip->addFile($filePath, 'data/' . $file);
            }
        }

        self::addDirectory($zip, self::dataDir() . '/uploads', 'uploads');
        self::addDirectory($zip, self::dataDir() . '/images', 'images');

        $zip->close();
        return $path;
    }

    private static function addDirectory(\ZipArchive $zip, string $directory, string $zipPrefix): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }
            $fullPath = $directory . '/' . $entry;
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $zipPrefix . '/' . $entry);
            }
        }
    }

    /**
     * Validates and restores a previously exported ZIP archive, overwriting
     * all current data. A safety backup of the current state is created
     * beforehand so the import can be manually reversed if needed.
     *
     * @return array{success: bool, message: string}
     */
    public static function restoreArchive(string $uploadedZipPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($uploadedZipPath) !== true) {
            return ['success' => false, 'message' => 'Die Datei ist kein gültiges ZIP-Archiv.'];
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $manifest = $manifestRaw !== false ? json_decode($manifestRaw, true) : null;
        if (!is_array($manifest) || ($manifest['app'] ?? '') !== self::MANIFEST_APP) {
            $zip->close();
            return ['success' => false, 'message' => 'Das Archiv stammt nicht aus einer Sicherung dieser Anwendung.'];
        }

        $safetyBackupPath = self::backupsDir() . '/pre-import-' . gmdate('Ymd-His') . '.zip';
        copy(self::createArchive('pre-import-safety'), $safetyBackupPath);

        self::clearDirectory(self::dataDir() . '/uploads');
        self::clearDirectory(self::dataDir() . '/images');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false || str_ends_with($entryName, '/')) {
                continue;
            }
            if (str_starts_with($entryName, 'data/')) {
                $filename = basename($entryName);
                if (in_array($filename, self::DATA_FILES, true)) {
                    file_put_contents(self::dataDir() . '/' . $filename, $zip->getFromIndex($i));
                }
            } elseif (str_starts_with($entryName, 'uploads/')) {
                file_put_contents(Storage::equipmentUploadDirectory() . '/' . basename($entryName), $zip->getFromIndex($i));
            } elseif (str_starts_with($entryName, 'images/')) {
                file_put_contents(Storage::equipmentImageDirectory() . '/' . basename($entryName), $zip->getFromIndex($i));
            }
        }

        $zip->close();

        AuditLog::recordEvent('technical', 'backup', 'imported', null, 'Datensicherung wiederhergestellt');

        return [
            'success' => true,
            'message' => 'Die Sicherung wurde eingespielt. Eine Kopie des vorherigen Standes liegt unter storage/app/backups/. Bitte melde dich erneut an.',
        ];
    }

    private static function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }
            $fullPath = $directory . '/' . $entry;
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    /** @return array<int, array{name: string, size: int, created_at: string}> */
    public static function listSafetyBackups(): array
    {
        $dir = self::backupsDir();
        $backups = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.zip')) {
                continue;
            }
            $fullPath = $dir . '/' . $entry;
            $backups[] = [
                'name' => $entry,
                'size' => filesize($fullPath) ?: 0,
                'created_at' => date('c', filemtime($fullPath) ?: time()),
            ];
        }
        usort($backups, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $backups;
    }
}
