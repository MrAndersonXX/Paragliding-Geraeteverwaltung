<?php

namespace Glider;

class RegionalSettings
{
    public static function languages(): array
    {
        if (class_exists(I18n::class)) {
            return I18n::locales();
        }
        return [
            'de' => ['label' => 'Deutsch', 'flag' => '/assets/flags/de.svg'],
            'en-GB' => ['label' => 'English (UK)', 'flag' => '/assets/flags/gb.svg'],
        ];
    }

    public static function countries(): array
    {
        return [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz',
            'GB' => 'United Kingdom',
            'FR' => 'Frankreich',
            'IT' => 'Italien',
            'ES' => 'Spanien',
            'NL' => 'Niederlande',
        ];
    }

    public static function timezones(): array
    {
        return [
            'Europe/Berlin' => 'Europe/Berlin',
            'Europe/Vienna' => 'Europe/Vienna',
            'Europe/Zurich' => 'Europe/Zurich',
            'Europe/London' => 'Europe/London',
            'Europe/Paris' => 'Europe/Paris',
            'Europe/Rome' => 'Europe/Rome',
            'Europe/Madrid' => 'Europe/Madrid',
            'Europe/Amsterdam' => 'Europe/Amsterdam',
        ];
    }

    public static function dateFormats(): array
    {
        return [
            'd.m.Y' => '31.12.2026',
            'd/m/Y' => '31/12/2026',
            'Y-m-d' => '2026-12-31',
            'm/d/Y' => '12/31/2026',
        ];
    }

    public static function defaults(): array
    {
        return [
            'language' => 'de',
            'country' => 'DE',
            'timezone' => 'Europe/Berlin',
            'date_format' => 'd.m.Y',
        ];
    }

    public static function normalize(array $regional): array
    {
        $defaults = self::defaults();
        $language = (string) ($regional['language'] ?? $defaults['language']);
        $country = (string) ($regional['country'] ?? $defaults['country']);
        $timezone = (string) ($regional['timezone'] ?? $defaults['timezone']);
        $dateFormat = (string) ($regional['date_format'] ?? $defaults['date_format']);

        return [
            'language' => array_key_exists($language, self::languages()) ? $language : $defaults['language'],
            'country' => array_key_exists($country, self::countries()) ? $country : $defaults['country'],
            'timezone' => array_key_exists($timezone, self::timezones()) ? $timezone : $defaults['timezone'],
            'date_format' => array_key_exists($dateFormat, self::dateFormats()) ? $dateFormat : $defaults['date_format'],
        ];
    }
}