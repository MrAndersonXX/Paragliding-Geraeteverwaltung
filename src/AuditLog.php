<?php

namespace Glider;

class AuditLog
{
    private const MASKED_VALUE = '[geschuetzt]';

    public static function recordEvent(string $eventType, string $domain, string $action, ?int $entityId, string $entityLabel): void
    {
        self::append([
            'event_type' => $eventType,
            'domain' => $domain,
            'action' => $action,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'changes' => [],
        ]);
    }

    public static function record(
        string $eventType,
        string $domain,
        string $action,
        ?int $entityId,
        string $entityLabel,
        array $before,
        array $after
    ): void {
        $changes = self::changes($before, $after);
        if ($changes === []) {
            return;
        }

        self::append([
            'event_type' => $eventType,
            'domain' => $domain,
            'action' => $action,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'changes' => $changes,
        ]);
    }

    private static function append(array $entry): void
    {
        $entries = Storage::readAuditLog();
        $entries[] = array_merge([
            'id' => self::nextId($entries),
            'timestamp' => gmdate('c'),
            'actor' => self::actor(),
        ], $entry);
        Storage::saveAuditLog($entries);
    }

    private static function nextId(array $entries): int
    {
        $highestId = 0;
        foreach ($entries as $entry) {
            $highestId = max($highestId, (int) ($entry['id'] ?? 0));
        }
        return $highestId + 1;
    }

    private static function actor(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['id' => null, 'name' => 'System'];
        }

        foreach (Storage::readUsers() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                $name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                return ['id' => $userId, 'name' => $name !== '' ? $name : 'Unbekannter Benutzer'];
            }
        }

        return ['id' => $userId, 'name' => 'Entfernter Benutzer'];
    }

    private static function changes(array $before, array $after): array
    {
        $changes = [];
        self::collectChanges($before, $after, '', $changes);
        return $changes;
    }

    private static function collectChanges(mixed $before, mixed $after, string $path, array &$changes): void
    {
        if (is_array($before) && is_array($after) && self::isAssociative($before) && self::isAssociative($after)) {
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $nextPath = $path === '' ? (string) $key : $path . '.' . $key;
                self::collectChanges($before[$key] ?? null, $after[$key] ?? null, $nextPath, $changes);
            }
            return;
        }

        if ($before === $after) {
            return;
        }

        $changes[$path !== '' ? $path : 'Wert'] = [
            'before' => self::isSensitivePath($path) ? self::MASKED_VALUE : $before,
            'after' => self::isSensitivePath($path) ? self::MASKED_VALUE : $after,
        ];
    }

    private static function isAssociative(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private static function isSensitivePath(string $path): bool
    {
        $normalized = strtolower($path);
        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'mail.username');
    }
}