<?php

namespace Glider;

require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/RegionalSettings.php';

class Storage
{
    private const DATA_DIR = __DIR__ . '/../storage/data';

    public static function ensure(): void
    {
        if (!is_dir(self::DATA_DIR)) {
            mkdir(self::DATA_DIR, 0777, true);
        }
    }

    public static function readJson(string $filename, array $default = []): array
    {
        self::ensure();
        $path = self::DATA_DIR . '/' . $filename;

        if (!file_exists($path)) {
            self::writeJson($filename, $default);
            return $default;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function writeJson(string $filename, array $data): void
    {
        self::ensure();
        $path = self::DATA_DIR . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function readEquipment(): array
    {
        $equipment = self::readJson('equipment.json', []);
        return is_array($equipment) ? $equipment : [];
    }

    public static function saveEquipment(array $equipment): void
    {
        self::saveCollection('equipment.json', $equipment, 'equipment', static fn (array $item): string => trim((string) ($item['name'] ?? '')) ?: 'Unbenanntes Gerät');
    }

    public static function readUsers(): array
    {
        return self::readJson('users.json', []);
    }

    public static function saveUsers(array $users): void
    {
        self::saveCollection('users.json', $users, 'user', static function (array $item): string {
            $name = trim((string) (($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')));
            return $name !== '' ? $name : 'Unbenannter Benutzer';
        });
    }

    public static function readDocumentCategories(): array
    {
        $categories = self::readJson('document_categories.json', [
            ['id' => 1, 'name' => 'Kaufbeleg', 'slug' => 'kaufbeleg'],
            ['id' => 2, 'name' => 'Prüfprotokoll', 'slug' => 'pruefprotokoll'],
            ['id' => 3, 'name' => 'Herstellerinfo', 'slug' => 'herstellerinfo'],
            ['id' => 4, 'name' => 'Nachprüfung', 'slug' => 'nachpruefung'],
            ['id' => 5, 'name' => 'Sonstiges', 'slug' => 'sonstiges'],
        ]);
        return is_array($categories) ? $categories : [];
    }

    public static function saveDocumentCategories(array $categories): void
    {
        self::saveCollection('document_categories.json', $categories, 'document_category', static fn (array $item): string => trim((string) ($item['name'] ?? '')) ?: 'Unbenannte Kategorie');
    }

    public static function readEquipmentDocuments(): array
    {
        return self::readJson('equipment_documents.json', []);
    }

    public static function saveEquipmentDocuments(array $documents): void
    {
        self::saveCollection('equipment_documents.json', $documents, 'document', static fn (array $item): string => trim((string) ($item['original_name'] ?? $item['name'] ?? '')) ?: 'Unbenanntes Dokument');
    }

    public static function storeUploadedDocuments(int $equipmentId, array $uploadedDocuments, array $documentCategoryIds, ?string $inspectionDate = null): array
    {
        $documents = self::readEquipmentDocuments();
        $categories = self::readDocumentCategories();
        $categoryIds = array_fill_keys(array_map('intval', array_column($categories, 'id')), true);
        $names = $uploadedDocuments['name'] ?? [];
        $errors = $uploadedDocuments['error'] ?? [];
        $sizes = $uploadedDocuments['size'] ?? [];
        $temporaryPaths = $uploadedDocuments['tmp_name'] ?? [];
        $uploadCount = is_array($names) ? count($names) : 0;
        if ($uploadCount !== count($documentCategoryIds)) {
            return ['documents' => [], 'error' => 'Bitte jedem Dokument eine Dokumentenkategorie zuordnen.'];
        }

        $pendingDocuments = [];
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'];
        foreach ($documentCategoryIds as $index => $categoryId) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                if (trim((string) $categoryId) !== '') {
                    return ['documents' => [], 'error' => 'Bitte lade für jede ausgewählte Dokumentenkategorie eine Datei hoch.'];
                }
                continue;
            }
            if ($error !== UPLOAD_ERR_OK || !isset($categoryIds[(int) $categoryId])) {
                return ['documents' => [], 'error' => 'Jedes Dokument muss erfolgreich hochgeladen und einer gültigen Dokumentenkategorie zugeordnet werden.'];
            }
            if ((int) ($sizes[$index] ?? 0) > 10 * 1024 * 1024) {
                return ['documents' => [], 'error' => 'Dokumente dürfen maximal 10 MB groß sein.'];
            }
            $temporaryPath = (string) ($temporaryPaths[$index] ?? '');
            $mimeType = $temporaryPath !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) : false;
            if (!is_string($mimeType) || !in_array($mimeType, $allowedMimeTypes, true)) {
                return ['documents' => [], 'error' => 'Erlaubt sind PDF-, JPG-, PNG-, WebP- und Textdateien.'];
            }
            $originalName = basename((string) ($names[$index] ?? 'Dokument'));
            $pendingDocuments[] = [
                'equipment_id' => $equipmentId,
                'category_id' => (int) $categoryId,
                'original_name' => $originalName,
                'stored_name' => bin2hex(random_bytes(16)) . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName),
                'mime_type' => $mimeType,
                'file_size' => (int) ($sizes[$index] ?? 0),
                'uploaded_at' => date('c'),
            ];
            if ($inspectionDate !== null && $inspectionDate !== '') {
                $pendingDocuments[array_key_last($pendingDocuments)]['inspection_date'] = $inspectionDate;
            }
            $pendingDocuments[array_key_last($pendingDocuments)]['temporary_path'] = $temporaryPath;
        }

        $nextDocumentId = $documents === [] ? 0 : max(array_map(static fn ($document) => (int) ($document['id'] ?? 0), $documents));
        $newDocuments = [];
        foreach ($pendingDocuments as $pendingDocument) {
            $storedPath = self::equipmentUploadDirectory() . '/' . $pendingDocument['stored_name'];
            if (!move_uploaded_file($pendingDocument['temporary_path'], $storedPath)) {
                foreach ($newDocuments as $newDocument) {
                    @unlink(self::equipmentUploadDirectory() . '/' . $newDocument['stored_name']);
                }
                return ['documents' => [], 'error' => 'Ein Dokument konnte nicht gespeichert werden.'];
            }
            unset($pendingDocument['temporary_path']);
            $pendingDocument['id'] = ++$nextDocumentId;
            $newDocuments[] = $pendingDocument;
        }
        if ($newDocuments !== []) {
            self::saveEquipmentDocuments(array_merge($documents, $newDocuments));
        }
        return ['documents' => $newDocuments, 'error' => ''];
    }

    public static function readEquipmentDocument(int $documentId, int $equipmentId): ?array
    {
        foreach (self::readEquipmentDocuments() as $document) {
            if ((int) ($document['id'] ?? 0) === $documentId && (int) ($document['equipment_id'] ?? 0) === $equipmentId) {
                return $document;
            }
        }
        return null;
    }

    public static function deleteEquipmentDocument(int $documentId, int $equipmentId): bool
    {
        $documents = self::readEquipmentDocuments();
        $remaining = [];
        $deleted = false;
        foreach ($documents as $document) {
            if ((int) ($document['id'] ?? 0) === $documentId && (int) ($document['equipment_id'] ?? 0) === $equipmentId) {
                $storedName = basename((string) ($document['stored_name'] ?? ''));
                if ($storedName !== '') {
                    @unlink(self::equipmentUploadDirectory() . '/' . $storedName);
                }
                $deleted = true;
                continue;
            }
            $remaining[] = $document;
        }
        if ($deleted) {
            self::saveEquipmentDocuments($remaining);
        }
        return $deleted;
    }

    public static function deleteEquipmentDocuments(int $equipmentId): void
    {
        $documents = self::readEquipmentDocuments();
        $remaining = [];
        foreach ($documents as $document) {
            if ((int) ($document['equipment_id'] ?? 0) === $equipmentId) {
                $storedName = (string) ($document['stored_name'] ?? '');
                if ($storedName !== '') {
                    @unlink(self::equipmentUploadDirectory() . '/' . $storedName);
                }
                continue;
            }
            $remaining[] = $document;
        }
        self::saveEquipmentDocuments($remaining);
    }

    public static function equipmentUploadDirectory(): string
    {
        $directory = self::DATA_DIR . '/uploads';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        return $directory;
    }

    public static function equipmentImageDirectory(): string
    {
        $directory = self::DATA_DIR . '/images';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        return $directory;
    }

    public static function saveEquipmentImageFile(int $equipmentId, string $tmpPath, string $extension): string
    {
        $directory = self::equipmentImageDirectory();
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension)) ?: 'jpg';
        $storedName = $equipmentId . '-' . bin2hex(random_bytes(16)) . '.' . $extension;

        $equipment = self::readEquipment();
        foreach ($equipment as $item) {
            if ((int) ($item['id'] ?? 0) === $equipmentId) {
                $previousFile = (string) ($item['image_file'] ?? '');
                if ($previousFile !== '') {
                    @unlink($directory . '/' . $previousFile);
                }
                break;
            }
        }

        if (!@rename($tmpPath, $directory . '/' . $storedName)) {
            copy($tmpPath, $directory . '/' . $storedName);
            @unlink($tmpPath);
        }

        return $storedName;
    }

    public static function readEquipmentTypes(): array
    {
        return self::readJson('equipment_types.json', [
            ['id' => 1, 'name' => 'Gleitschirm'],
            ['id' => 2, 'name' => 'Rettungsgerät'],
            ['id' => 3, 'name' => 'Gurtzeug'],
            ['id' => 4, 'name' => 'Frontcontainer'],
            ['id' => 5, 'name' => 'Helm'],
            ['id' => 6, 'name' => 'Sonstiges'],
        ]);
    }

    public static function saveEquipmentTypes(array $types): void
    {
        self::saveCollection('equipment_types.json', $types, 'equipment_type', static fn (array $item): string => trim((string) ($item['name'] ?? '')) ?: 'Unbenannter Gerätetyp');
    }

    public static function readSettings(): array
    {
        $settings = self::readJson('settings.json', [
            'app' => [
                'name' => 'Glider Equipment Tracker',
                'timezone' => 'Europe/Berlin',
            ],
            'regional' => RegionalSettings::defaults(),
            'mail' => [
                'host' => '',
                'port' => '587',
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from_address' => '',
                'from_name' => 'Glider Equipment Tracker',
            ],
            'image_search' => [
                'enabled' => false,
                'serpapi_key' => '',
            ],
        ]);
        if (!is_array($settings)) {
            return [];
        }
        $settings['regional'] = RegionalSettings::normalize(array_merge(
            ['timezone' => (string) ($settings['app']['timezone'] ?? 'Europe/Berlin')],
            (array) ($settings['regional'] ?? [])
        ));
        return is_array($settings) ? $settings : [];
    }

    public static function saveSettings(array $settings): void
    {
        $previousSettings = self::readJson('settings.json', []);
        self::writeJson('settings.json', $settings);
        AuditLog::record('business', 'settings', 'updated', null, 'Anwendungseinstellungen', $previousSettings, $settings);
    }

    public static function readAuditLog(): array
    {
        return self::readJson('audit_log.json', []);
    }

    public static function saveAuditLog(array $entries): void
    {
        self::writeJson('audit_log.json', $entries);
    }

    private static function saveCollection(string $filename, array $items, string $domain, callable $label): void
    {
        $previousItems = self::readJson($filename, []);
        self::writeJson($filename, $items);

        $previousById = self::itemsById($previousItems);
        $itemsById = self::itemsById($items);
        foreach ($itemsById as $id => $item) {
            $previous = $previousById[$id] ?? [];
            $isNew = !array_key_exists($id, $previousById);
            if ($domain === 'user' && !$isNew && self::isRememberTokenMaintenance($previous, $item)) {
                continue;
            }
            AuditLog::record('business', $domain, $isNew ? 'created' : 'updated', (int) $id, $label($item), $previous, $item);
        }

        foreach ($previousById as $id => $previous) {
            if (!array_key_exists($id, $itemsById)) {
                AuditLog::record('business', $domain, 'deleted', (int) $id, $label($previous), $previous, []);
            }
        }
    }

    private static function itemsById(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $indexed[(int) $item['id']] = $item;
        }
        return $indexed;
    }

    private static function isRememberTokenMaintenance(array $before, array $after): bool
    {
        $technicalFields = ['remember_token_hash', 'remember_expires_at'];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null) && !in_array($field, $technicalFields, true)) {
                return false;
            }
        }
        return true;
    }

    public static function seedDemoData(): void
    {
        self::saveEquipment([
            [
                'id' => 1,
                'name' => 'Rettungsgerät RSC-301',
                'category' => 'Rettungsgerät',
                'manufacturer' => 'Mammut',
                'equipment_type' => 'Rescue',
                'size' => 'M',
                'serial_number' => 'RSC-2024-001',
                'purchase_date' => '2023-01-15',
                'status' => 'active',
                'inspection_interval_months' => 12,
                'last_inspection_date' => '2025-01-12',
                'next_inspection_date' => '2026-01-12',
                'manufacturer_check_date' => '2025-04-17',
                'manufacturer_validity_days' => 365,
                'max_operating_days' => 3650,
                'retired_at' => '',
                'notes' => 'Rettungsgerät mit Herstellerprüfung',
                'notifications' => ['30_days' => true, '14_days' => true, '7_days' => true, 'due' => true, 'retired' => true],
            ],
            [
                'id' => 2,
                'name' => 'Gurtzeug P-7',
                'category' => 'Gurtzeug',
                'manufacturer' => 'Adventure',
                'equipment_type' => 'Harness',
                'size' => 'L',
                'serial_number' => 'HAR-8801',
                'purchase_date' => '2021-04-18',
                'status' => 'active',
                'inspection_interval_months' => 24,
                'last_inspection_date' => '2025-02-02',
                'next_inspection_date' => '2026-02-02',
                'manufacturer_check_date' => '',
                'manufacturer_validity_days' => 0,
                'max_operating_days' => 0,
                'retired_at' => '',
                'notes' => 'Regelmäßige Hauptprüfung',
                'notifications' => ['30_days' => true, '14_days' => false, '7_days' => true, 'due' => true, 'retired' => false],
            ],
        ]);

        self::saveDocumentCategories([
            ['id' => 1, 'name' => 'Kaufbeleg', 'slug' => 'kaufbeleg'],
            ['id' => 2, 'name' => 'Prüfprotokoll', 'slug' => 'pruefprotokoll'],
            ['id' => 3, 'name' => 'Herstellerinfo', 'slug' => 'herstellerinfo'],
            ['id' => 4, 'name' => 'Nachprüfung', 'slug' => 'nachpruefung'],
            ['id' => 5, 'name' => 'Sonstiges', 'slug' => 'sonstiges'],
        ]);

        self::saveSettings([
            'app' => [
                'name' => 'Glider Equipment Tracker',
                'timezone' => 'Europe/Berlin',
            ],
            'mail' => [
                'host' => '',
                'port' => '587',
                'username' => '',
                'password' => '',
                'encryption' => 'tls',
                'from_address' => '',
                'from_name' => 'Glider Equipment Tracker',
            ],
        ]);
    }
}
