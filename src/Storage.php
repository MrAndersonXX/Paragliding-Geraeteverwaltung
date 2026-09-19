<?php

namespace Glider;

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
        self::writeJson('equipment.json', $equipment);
    }

    public static function readUsers(): array
    {
        return self::readJson('users.json', []);
    }

    public static function saveUsers(array $users): void
    {
        self::writeJson('users.json', $users);
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
        self::writeJson('document_categories.json', $categories);
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
        self::writeJson('equipment_types.json', $types);
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
        ]);
        return is_array($settings) ? $settings : [];
    }

    public static function saveSettings(array $settings): void
    {
        self::writeJson('settings.json', $settings);
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
                'inspection_interval_days' => 365,
                'inspection_start_date' => '2023-01-15',
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
                'inspection_interval_days' => 730,
                'inspection_start_date' => '2021-04-18',
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
