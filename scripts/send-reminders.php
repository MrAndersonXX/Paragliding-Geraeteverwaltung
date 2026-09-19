<?php

require __DIR__ . '/../src/Storage.php';
require __DIR__ . '/../src/NotificationService.php';

use Glider\NotificationService;
use Glider\Storage;

$settings = Storage::readSettings();
$equipment = Storage::readEquipment();
$users = Storage::readUsers();
$usersById = [];
foreach ($users as $user) {
    $usersById[(int) ($user['id'] ?? 0)] = $user;
}

$today = new DateTimeImmutable('today');
$service = new NotificationService($settings['mail'] ?? []);
$sent = 0;
foreach ($equipment as $item) {
    if (($item['status'] ?? 'active') === 'retired' || empty($item['notifications']['due'])) {
        continue;
    }
    $dueDate = (string) ($item['next_inspection_date'] ?? '');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
    if (!$date || $date > $today) {
        continue;
    }
    $user = $usersById[(int) ($item['user_id'] ?? 0)] ?? null;
    if (!$user || !filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        continue;
    }
    $result = $service->sendInspectionReminder($user['email'], (string) ($item['name'] ?? 'Gerät'), $dueDate);
    if ($result['success']) {
        $sent++;
    }
    echo ($result['success'] ? 'OK: ' : 'FEHLER: ') . ($item['name'] ?? 'Gerät') . ' -> ' . $result['message'] . PHP_EOL;
}
echo "Versendet: {$sent}" . PHP_EOL;