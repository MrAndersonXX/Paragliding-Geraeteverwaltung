<?php

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/CalendarClient.php';

use Glider\CalendarClient;
use Glider\Config;

$config = Config::load();

$calendar = new CalendarClient(
    $config['ical']['url'] ?? '',
    $config['ical']['username'] ?? '',
    $config['ical']['password'] ?? ''
);

$events = $calendar->fetchEvents();

$checks = [
    'app_name' => $config['name'] ?? 'Glider Equipment Tracker',
    'mail_configured' => !empty($config['mail']['host']),
    'calendar_configured' => !empty($config['ical']['url']),
    'calendar_events' => count($events),
    'date' => date('c'),
];

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
