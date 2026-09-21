<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/EquipmentTimeline.php';
use Glider\Storage;
use Glider\Auth;
use Glider\EquipmentTimeline;

Auth::requireActiveAccount();
$equipment = Storage::readEquipment();
$currentUser = Auth::user();
if (!Auth::isAdmin()) {
    $equipment = array_values(array_filter($equipment, static fn ($item) => (int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)));
}
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
$year = max(2000, min(2100, $year));
$documents = Storage::readEquipmentDocuments();
$months = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
];
$eventsByDate = [];

foreach ($equipment as $item) {
    $name = trim((string) ($item['name'] ?? 'Ohne Bezeichnung'));
    $retiredAt = EquipmentTimeline::validDate($item['retired_at'] ?? '');
    $isRetiredEquipment = ($item['status'] ?? 'active') === 'retired';
    foreach (EquipmentTimeline::entries($item, $documents) as $entry) {
        $date = $entry['date'];
        if ($retiredAt !== null && $date > $retiredAt && !$entry['historical']) {
            continue;
        }
        if (substr($date, 0, 4) !== (string) $year) {
            continue;
        }
        $age = EquipmentTimeline::ageAtDate((string) ($item['purchase_date'] ?? ''), $date);
        $label = $entry['label'] . ' ' . $name;
        if ($age !== null) {
            $label .= ' · Alter: ' . $age . ' ' . ($age === 1 ? 'Jahr' : 'Jahre');
        }
        $event = [
            'label' => $label,
            'type' => $entry['type'],
            'equipment_id' => (int) ($item['id'] ?? 0),
            'retired' => $isRetiredEquipment,
            'title' => $age === null
                ? $label
                : $label . ' (berechnet aus Anschaffungsdatum und Termindatum)',
        ];
        $eventsByDate[$date][] = $event;
    }
}

pageHeader(__('page.calendar'));
?>
<section class="calendar-shell">
    <div class="calendar-toolbar">
        <div>
            <p class="eyebrow">Geräteplanung</p>
            <h2><?= e((string) $year); ?></h2>
        </div>
        <label class="checkbox-field"><input type="checkbox" id="hide-retired" <?= Auth::preference('hide_retired_equipment') ? 'checked' : ''; ?> /> <?= e(__('equipment.hide_retired')); ?></label>
        <div class="year-switcher">
            <a href="?year=<?= $year - 1; ?>" aria-label="Vorheriges Jahr">‹</a>
            <strong><?= e((string) $year); ?></strong>
            <a href="?year=<?= $year + 1; ?>" aria-label="Nächstes Jahr">›</a>
        </div>
    </div>

    <div class="calendar-legend" aria-label="Terminarten">
        <span><i class="legend-dot purchase"></i>Anschaffung</span>
        <span><i class="legend-dot inspection"></i>Tatsächliche Prüfung</span>
        <span><i class="legend-dot inspection-planned"></i>Nächste geplante Prüfung</span>
        <span><i class="legend-dot inspection-history"></i>Prüfungshistorie</span>
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
                                <a class="calendar-event <?= e($event['type']); ?><?= $event['retired'] ? ' calendar-event-retired' : ''; ?>" href="/equipment_form.php?edit=<?= (int) $event['equipment_id']; ?>" title="<?= e($event['title']); ?>"><?= e($event['label']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<script>
const hideRetiredCalendar = document.getElementById('hide-retired');
const applyCalendarRetiredVisibility = function () {
    if (!hideRetiredCalendar) {
        return;
    }
    document.querySelectorAll('.calendar-event-retired').forEach(function (event) {
        event.hidden = hideRetiredCalendar.checked;
    });
};
if (hideRetiredCalendar) {
    applyCalendarRetiredVisibility();
    hideRetiredCalendar.addEventListener('change', function () {
        applyCalendarRetiredVisibility();
        fetch('/preferences.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'key=hide_retired_equipment&value=' + (hideRetiredCalendar.checked ? '1' : '0'),
        });
    });
}
</script>
<?php pageFooter();
