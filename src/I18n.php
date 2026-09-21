<?php

namespace Glider;

class I18n
{
    public const DEFAULT_LOCALE = 'de';
    public const COOKIE_NAME = 'glider_language';

    private const LOCALES = [
        'de' => ['label' => 'Deutsch', 'flag' => '🇩🇪'],
        'en-GB' => ['label' => 'English (UK)', 'flag' => '🇬🇧'],
    ];

    private static ?string $locale = null;
    private static array $messages = [];

    public static function locales(): array
    {
        return self::LOCALES;
    }

    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $storedLocale = self::userLocale();
        if ($storedLocale === null && session_status() === PHP_SESSION_ACTIVE) {
            $storedLocale = (string) ($_SESSION['language'] ?? '');
        }
        if ($storedLocale === null || $storedLocale === '') {
            $storedLocale = (string) ($_COOKIE[self::COOKIE_NAME] ?? '');
        }

        self::$locale = self::isSupported($storedLocale) ? $storedLocale : self::DEFAULT_LOCALE;
        return self::$locale;
    }

    public static function setLocale(string $locale): bool
    {
        if (!self::isSupported($locale)) {
            return false;
        }
        self::$locale = $locale;
        return true;
    }

    public static function translate(string $key, array $replace = []): string
    {
        $messages = self::messages();
        $value = $messages[$key] ?? self::loadMessages(self::DEFAULT_LOCALE)[$key] ?? $key;
        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, (string) $replacement, $value);
        }
        return $value;
    }

    public static function isSupported(string $locale): bool
    {
        return isset(self::LOCALES[$locale]);
    }

    private static function messages(): array
    {
        if (self::$messages === []) {
            self::$messages = self::loadMessages(self::locale());
        }
        return self::$messages;
    }

    private static function loadMessages(string $locale): array
    {
        $path = __DIR__ . '/lang/' . $locale . '.php';
        $messages = is_file($path) ? require $path : [];
        return is_array($messages) ? $messages : [];
    }

    private static function userLocale(): ?string
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        foreach (Storage::readUsers() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                $locale = (string) ($user['preferences']['language'] ?? '');
                return self::isSupported($locale) ? $locale : null;
            }
        }
        return null;
    }
}

function __(string $key, array $replace = []): string
{
    return I18n::translate($key, $replace);
}