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
            $codepointList = trim((string) preg_replace('/;\s*fully-qualified.*$/', '', $codepoints));
            $emoji = self::fromCodepoints($codepointList);
            $description = trim((string) preg_replace('/^\S+\s+E[\d.]+\s+/', '', trim($description)));
            if ($emoji === '' || $description === '') {
                continue;
            }
            $groups[$group][$subgroup][] = ['emoji' => $emoji, 'name' => $description];
        }

        return $groups;
    }

    private static function fromCodepoints(string $codepointList): string
    {
        $emoji = '';
        foreach (preg_split('/\s+/', $codepointList) as $codepoint) {
            $value = hexdec($codepoint);
            if ($value <= 0x7f) {
                $emoji .= chr($value);
            } elseif ($value <= 0x7ff) {
                $emoji .= chr(0xc0 | ($value >> 6));
                $emoji .= chr(0x80 | ($value & 0x3f));
            } elseif ($value <= 0xffff) {
                $emoji .= chr(0xe0 | ($value >> 12));
                $emoji .= chr(0x80 | (($value >> 6) & 0x3f));
                $emoji .= chr(0x80 | ($value & 0x3f));
            } else {
                $emoji .= chr(0xf0 | ($value >> 18));
                $emoji .= chr(0x80 | (($value >> 12) & 0x3f));
                $emoji .= chr(0x80 | (($value >> 6) & 0x3f));
                $emoji .= chr(0x80 | ($value & 0x3f));
            }
        }
        return $emoji;
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
