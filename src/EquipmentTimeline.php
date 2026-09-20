<?php

namespace Glider;

class EquipmentTimeline
{
    private const DATE_FIELDS = [
        'purchase_date' => ['label' => 'Anschaffung', 'type' => 'purchase'],
        'last_inspection_date' => ['label' => 'Tatsächliche Prüfung', 'type' => 'inspection'],
        'next_inspection_date' => ['label' => 'Nächste geplante Prüfung', 'type' => 'inspection-planned'],
        'manufacturer_check_date' => ['label' => 'Herstellerprüfung', 'type' => 'manufacturer'],
        'retired_at' => ['label' => 'Archivierung', 'type' => 'retired'],
    ];

    public static function entries(array $equipment, array $documents = []): array
    {
        $entries = [];
        foreach (self::DATE_FIELDS as $field => $definition) {
            $date = self::validDate($equipment[$field] ?? '');
            if ($date === null) {
                continue;
            }
            $entries[] = [
                'date' => $date,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'historical' => false,
            ];
        }

        $history = is_array($equipment['inspection_history'] ?? null) ? $equipment['inspection_history'] : [];
        foreach ($documents as $document) {
            if ((int) ($document['equipment_id'] ?? 0) !== (int) ($equipment['id'] ?? 0)) {
                continue;
            }
            $date = self::validDate($document['inspection_date'] ?? '');
            if ($date !== null) {
                $history[] = ['date' => $date, 'label' => 'Prüfung'];
            }
        }

        $seenHistory = [];
        foreach ($history as $inspection) {
            $date = self::validDate($inspection['date'] ?? $inspection['inspection_date'] ?? '');
            if ($date === null) {
                continue;
            }
            $key = $date . '|' . trim((string) ($inspection['label'] ?? 'Prüfung'));
            if (isset($seenHistory[$key])) {
                continue;
            }
            $seenHistory[$key] = true;
            $entries[] = [
                'date' => $date,
                'label' => trim((string) ($inspection['label'] ?? 'Prüfung')) ?: 'Prüfung',
                'type' => 'inspection-history',
                'historical' => true,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => strcmp($right['date'], $left['date']));
        return $entries;
    }

    public static function validDate(mixed $value): ?string
    {
        $date = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null;
    }

    public static function ageAtDate(string $purchaseDate, string $eventDate): ?int
    {
        $purchaseDate = self::validDate($purchaseDate);
        $eventDate = self::validDate($eventDate);
        if ($purchaseDate === null || $eventDate === null || $eventDate <= $purchaseDate) {
            return null;
        }

        $purchase = new \DateTimeImmutable($purchaseDate);
        $event = new \DateTimeImmutable($eventDate);
        $age = (int) $purchase->diff($event)->y;
        return $event->format('Y') > $purchase->format('Y') ? $age : null;
    }
}