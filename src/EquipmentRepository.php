<?php

namespace Glider;

class EquipmentRepository
{
    public function listExampleItems(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'RZ-301 Rescue',
                'category' => 'Rettungsgerät',
                'manufacturer' => 'Mammut',
                'equipment_type' => 'Rescue',
                'size' => 'M',
                'serial_number' => 'RSC-2024-001',
                'purchase_date' => '2023-01-15',
                'inspection_interval_days' => 365,
                'last_inspection_date' => '2025-01-12',
                'next_inspection_date' => '2026-01-12',
                'max_operating_days' => 3650,
                'status' => 'active',
            ],
            [
                'id' => 2,
                'name' => 'P-7 Harness',
                'category' => 'Gurtzeug',
                'manufacturer' => 'Adventure',
                'equipment_type' => 'Harness',
                'size' => 'L',
                'serial_number' => 'HAR-8801',
                'purchase_date' => '2021-04-18',
                'inspection_interval_days' => 730,
                'last_inspection_date' => '2025-02-02',
                'next_inspection_date' => '2026-02-02',
                'status' => 'active',
            ],
        ];
    }
}
