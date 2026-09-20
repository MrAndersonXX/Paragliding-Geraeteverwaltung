<?php

namespace Glider;

require_once __DIR__ . '/AuditLog.php';

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
            ],
        ]);
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
