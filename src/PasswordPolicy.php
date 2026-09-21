<?php

namespace Glider;

class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Returns each password requirement and whether the supplied password fulfils it.
     *
     * @return array<string, bool>
     */
    public static function requirements(string $password): array
    {
        return [
            'minimum_length' => self::characterCount($password) >= self::MIN_LENGTH,
            'uppercase' => preg_match('/[A-Z]/', $password) === 1,
            'lowercase' => preg_match('/[a-z]/', $password) === 1,
            'number' => preg_match('/[0-9]/', $password) === 1,
            'special_character' => preg_match('/[^\p{L}\p{N}]/u', $password) === 1,
        ];
    }

    public static function isValid(string $password): bool
    {
        return !in_array(false, self::requirements($password), true);
    }

    private static function characterCount(string $value): int
    {
        return preg_match_all('/./us', $value) ?: 0;
    }
}