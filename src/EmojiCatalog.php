<?php

namespace Glider;

class EmojiCatalog
{
    private const DATA_FILE = __DIR__ . '/../resources/emoji-test.txt';

    public static function grouped(): array
    {
        if (!is_file(self::DATA_FILE)) {
            return [];
        }

        $groups = [];
        $group = 'Weitere';
        $subgroup = 'Weitere';
        foreach (file(self::DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '# group:')) {
                $group = trim(substr($line, 8));
                $subgroup = 'Weitere';
                continue;
            }
            if (str_starts_with($line, '# subgroup:')) {
                $subgroup = trim(substr($line, 11));
                continue;
            }
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '; fully-qualified')) {
                continue;
            }

            [$codepoints, $description] = array_pad(explode('#', $line, 2), 2, '');
            $emoji = trim((string) preg_replace('/;\s*fully-qualified.*$/', '', $codepoints));
            $description = trim((string) preg_replace('/^\S+\s+E[\d.]+\s+/', '', trim($description)));
            if ($emoji === '' || $description === '') {
                continue;
            }
            $groups[$group][$subgroup][] = ['emoji' => $emoji, 'name' => $description];
        }

        return $groups;
    }

    public static function contains(string $emoji): bool
    {
        foreach (self::grouped() as $subgroups) {
            foreach ($subgroups as $items) {
                foreach ($items as $item) {
                    if ($item['emoji'] === $emoji) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
