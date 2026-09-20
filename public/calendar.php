<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Auth.php';
use Glider\Storage;
use Glider\Auth;

Auth::requireLogin();
$equipment = Storage::readEquipment();
$currentUser = Auth::user();
if (!Auth::isAdmin()) {
    $equipment = array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
$year = max(2000, min(2100, $year));
$months = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
];
$eventsByDate = [];

foreach ($equipment as $item) {
    $name = trim((string) ($item['name'] ?? 'Ohne Bezeichnung'));
    $dates = [
        'next_inspection_date' => ['label' => 'Prüfung ' . $name, 'type' => 'inspection'],
        'manufacturer_check_date' => ['label' => 'Herstellerprüfung ' . $name, 'type' => 'manufacturer'],
        'retired_at' => ['label' => 'Stilllegung ' . $name, 'type' => 'retired'],
    ];

    foreach ($dates as $field => $event) {
        $date = trim((string) ($item[$field] ?? ''));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || substr($date, 0, 4) !== (string) $year) {
            continue;
        }
        $eventsByDate[$date][] = $event;
    }
}

pageHeader('Kalender');
?>
<section class="calendar-shell">
    <div class="calendar-toolbar">
        <div>
            <p class="eyebrow">Geräteplanung</p>
            <h2><?= e((string) $year); ?></h2>
        </div>
        <div class="year-switcher">
            <a href="?year=<?= $year - 1; ?>" aria-label="Vorheriges Jahr">‹</a>
            <strong><?= e((string) $year); ?></strong>
            <a href="?year=<?= $year + 1; ?>" aria-label="Nächstes Jahr">›</a>
        </div>
    </div>

    <div class="calendar-legend" aria-label="Terminarten">
        <span><i class="legend-dot inspection"></i>Prüfung</span>
        <span><i class="legend-dot manufacturer"></i>Herstellerprüfung</span>
        <span><i class="legend-dot retired"></i>Stilllegung</span>
    </div>

    <div class="calendar-grid">
        <?php foreach ($months as $monthNumber => $monthName): ?>
            <?php $daysInMonth = (int) date('t', mktime(0, 0, 0, $monthNumber, 1, $year)); ?>
            <section class="month-row">
                <h3><?= e($monthName); ?></h3>
                <div class="month-days" style="--days: <?= $daysInMonth; ?>;">
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php $date = sprintf('%04d-%02d-%02d', $year, $monthNumber, $day); ?>
                        <div class="day-cell<?= $date === date('Y-m-d') ? ' today' : ''; ?>">
                            <span class="day-number"><?= $day; ?></span>
                            <?php foreach ($eventsByDate[$date] ?? [] as $event): ?>
                                <span class="calendar-event <?= e($event['type']); ?>" title="<?= e($event['label']); ?>"><?= e($event['label']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<?php pageFooter();
