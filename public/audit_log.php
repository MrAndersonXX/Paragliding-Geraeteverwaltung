<?php

require __DIR__ . '/_layout.php';

use Glider\Auth;
use Glider\Storage;

Auth::requireAdmin();

function auditLabel(string $value): string
{
    return match ($value) {
        'business' => 'Fachliche Änderung',
        'technical' => 'Technischer Vorgang',
        'equipment' => 'Gerät',
        'inspection' => 'Prüfung',
        'document' => 'Dokument',
        'user' => 'Benutzer',
        'equipment_type' => 'Gerätetyp',
        'document_category' => 'Dokumentkategorie',
        'settings' => 'Einstellungen',
        'authentication' => 'Anmeldung',
        'created' => 'Angelegt',
        'updated' => 'Geändert',
        'deleted' => 'Gelöscht',
        'login' => 'Angemeldet',
        'logout' => 'Abgemeldet',
        'session_restored' => 'Sitzung wiederhergestellt',
        default => ucfirst(str_replace('_', ' ', $value)),
    };
}

function auditValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Leer';
    }
    if (is_bool($value)) {
        return $value ? 'Ja' : 'Nein';
    }
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Unlesbarer Wert';
    }
    return (string) $value;
}

$entries = Storage::readAuditLog();
usort($entries, static fn (array $left, array $right): int => strcmp((string) ($right['timestamp'] ?? ''), (string) ($left['timestamp'] ?? '')));

$filters = [
    'from' => trim((string) ($_GET['from'] ?? '')),
    'to' => trim((string) ($_GET['to'] ?? '')),
    'actor' => trim((string) ($_GET['actor'] ?? '')),
    'domain' => trim((string) ($_GET['domain'] ?? '')),
    'action' => trim((string) ($_GET['action'] ?? '')),
    'event_type' => trim((string) ($_GET['event_type'] ?? '')),
];
$actors = [];
$domains = [];
$actions = [];
$eventTypes = [];
foreach ($entries as $entry) {
    $actor = $entry['actor'] ?? [];
    $actorId = (string) ($actor['id'] ?? 'system');
    $actors[$actorId] = (string) ($actor['name'] ?? 'System');
    $domains[(string) ($entry['domain'] ?? '')] = true;
    $actions[(string) ($entry['action'] ?? '')] = true;
    $eventTypes[(string) ($entry['event_type'] ?? '')] = true;
}
asort($actors);
ksort($domains);
ksort($actions);
ksort($eventTypes);

$filteredEntries = array_values(array_filter($entries, static function (array $entry) use ($filters): bool {
    $timestamp = (string) ($entry['timestamp'] ?? '');
    $date = substr($timestamp, 0, 10);
    $actorId = (string) (($entry['actor']['id'] ?? null) ?? 'system');
    return ($filters['from'] === '' || $date >= $filters['from'])
        && ($filters['to'] === '' || $date <= $filters['to'])
        && ($filters['actor'] === '' || $actorId === $filters['actor'])
        && ($filters['domain'] === '' || ($entry['domain'] ?? '') === $filters['domain'])
        && ($filters['action'] === '' || ($entry['action'] ?? '') === $filters['action'])
        && ($filters['event_type'] === '' || ($entry['event_type'] ?? '') === $filters['event_type']);
}));

$perPage = 50;
$pageCount = max(1, (int) ceil(count($filteredEntries) / $perPage));
$page = max(1, min($pageCount, (int) ($_GET['page'] ?? 1)));
$pageEntries = array_slice($filteredEntries, ($page - 1) * $perPage, $perPage);
try {
    $timezone = new DateTimeZone((string) (Storage::readSettings()['app']['timezone'] ?? 'Europe/Berlin'));
} catch (Exception) {
    $timezone = new DateTimeZone('Europe/Berlin');
}

pageHeader('Änderungsprotokoll');
?>
<section class="card">
    <div class="list-toolbar"><div><h2>Audit-Historie</h2><p>Alle fachlichen und technischen Änderungen. Vertrauliche Werte werden nie angezeigt.</p></div></div>
    <form method="get" class="audit-filters">
        <label>Von<input type="date" name="from" value="<?= e($filters['from']); ?>" /></label>
        <label>Bis<input type="date" name="to" value="<?= e($filters['to']); ?>" /></label>
        <label>Benutzer<select name="actor"><option value="">Alle</option><?php foreach ($actors as $actorId => $actorName): ?><option value="<?= e($actorId); ?>" <?= $filters['actor'] === $actorId ? 'selected' : ''; ?>><?= e($actorName); ?></option><?php endforeach; ?></select></label>
        <label>Bereich<select name="domain"><option value="">Alle</option><?php foreach (array_keys($domains) as $domain): ?><option value="<?= e($domain); ?>" <?= $filters['domain'] === $domain ? 'selected' : ''; ?>><?= e(auditLabel($domain)); ?></option><?php endforeach; ?></select></label>
        <label>Aktion<select name="action"><option value="">Alle</option><?php foreach (array_keys($actions) as $action): ?><option value="<?= e($action); ?>" <?= $filters['action'] === $action ? 'selected' : ''; ?>><?= e(auditLabel($action)); ?></option><?php endforeach; ?></select></label>
        <label>Art<select name="event_type"><option value="">Alle</option><?php foreach (array_keys($eventTypes) as $eventType): ?><option value="<?= e($eventType); ?>" <?= $filters['event_type'] === $eventType ? 'selected' : ''; ?>><?= e(auditLabel($eventType)); ?></option><?php endforeach; ?></select></label>
        <div class="audit-filter-actions"><button type="submit">Filtern</button><a class="button-link button-secondary" href="/audit_log.php">Zurücksetzen</a></div>
    </form>
</section>
<section class="card">
    <div class="list-toolbar"><div><h2>Einträge</h2><p title="Anzahl der Einträge nach Anwendung der aktuellen Filter."><?= count($filteredEntries); ?> Treffer</p></div><p title="Die Seite wird aus maximal 50 gefilterten Einträgen berechnet.">Seite <?= $page; ?> von <?= $pageCount; ?></p></div>
    <div class="table-wrap"><table class="audit-table"><thead><tr><th>Zeit</th><th>Benutzer</th><th>Art</th><th>Bereich</th><th>Aktion</th><th>Objekt</th><th>Details</th></tr></thead><tbody>
    <?php if ($pageEntries === []): ?><tr><td colspan="7">Für diese Filter gibt es noch keine Einträge.</td></tr><?php endif; ?>
    <?php foreach ($pageEntries as $entry): ?>
        <?php $date = new DateTimeImmutable((string) ($entry['timestamp'] ?? 'now')); $changes = $entry['changes'] ?? []; ?>
        <tr>
            <td><time datetime="<?= e($date->format(DATE_ATOM)); ?>" title="Gespeichert in UTC: <?= e($date->setTimezone(new DateTimeZone('UTC'))->format('d.m.Y H:i:s T')); ?>"><?= e($date->setTimezone($timezone)->format('d.m.Y H:i:s')); ?></time></td>
            <td><?= e($entry['actor']['name'] ?? 'System'); ?></td>
            <td><?= e(auditLabel((string) ($entry['event_type'] ?? ''))); ?></td>
            <td><?= e(auditLabel((string) ($entry['domain'] ?? ''))); ?></td>
            <td><?= e(auditLabel((string) ($entry['action'] ?? ''))); ?></td>
            <td><?= e($entry['entity_label'] ?? ''); ?></td>
            <td><?php if ($changes !== []): ?><details><summary>Änderungen anzeigen</summary><dl class="audit-changes"><?php foreach ($changes as $field => $change): ?><div><dt><?= e(str_replace('_', ' ', $field)); ?></dt><dd><span><?= e(auditValue($change['before'] ?? null)); ?></span><span aria-hidden="true">→</span><span><?= e(auditValue($change['after'] ?? null)); ?></span></dd></div><?php endforeach; ?></dl></details><?php else: ?>Keine Feldänderungen<?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($pageCount > 1): ?><nav class="audit-pagination" aria-label="Seitennavigation"><?php if ($page > 1): ?><a class="button-link button-secondary" href="?<?= e(http_build_query([...array_filter($filters), 'page' => $page - 1])); ?>">Zurück</a><?php endif; ?><?php if ($page < $pageCount): ?><a class="button-link" href="?<?= e(http_build_query([...array_filter($filters), 'page' => $page + 1])); ?>">Weiter</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php pageFooter();