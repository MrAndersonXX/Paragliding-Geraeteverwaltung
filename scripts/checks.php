<?php

require __DIR__ . '/../src/Config.php';

use Glider\Config;

$config = Config::load();

$checks = [
    'app_name' => $config['name'] ?? 'Glider Equipment Tracker',
    'mail_configured' => !empty($config['mail']['host']),
    'date' => date('c'),
];

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
