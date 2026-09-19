<?php

namespace Glider;

class Config
{
    public static function load(): array
    {
        $configPath = __DIR__ . '/../config/app.php';
        $config = require $configPath;

        if (!is_array($config)) {
            return [];
        }

        return $config;
    }
}
