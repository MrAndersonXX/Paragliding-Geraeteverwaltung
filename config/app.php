<?php

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
};

return [
    'name' => $env('APP_NAME', 'Glider Equipment Tracker'),
    'env' => $env('APP_ENV', 'production'),
    'debug' => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => $env('APP_URL', 'https://tracker.example.com'),
    'timezone' => $env('TZ', 'Europe/Berlin'),
    'mail' => [
        'mailer' => $env('MAIL_MAILER', 'smtp'),
        'host' => $env('MAIL_HOST', 'mail.example.com'),
        'port' => (int) $env('MAIL_PORT', 587),
        'username' => $env('MAIL_USERNAME', ''),
        'password' => $env('MAIL_PASSWORD', ''),
        'encryption' => $env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => $env('MAIL_FROM_ADDRESS', 'tracker@example.com'),
        'from_name' => $env('MAIL_FROM_NAME', 'Glider Equipment Tracker'),
    ],
    'ical' => [
        'url' => $env('ICAL_URL', ''),
        'username' => $env('ICAL_USERNAME', ''),
        'password' => $env('ICAL_PASSWORD', ''),
    ],
];
