<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/EmojiCatalog.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/NotificationService.php';
require_once __DIR__ . '/_password_requirements.php';

use Glider\Storage;
use Glider\EmojiCatalog;
use Glider\Auth;
use Glider\NotificationService;
use Glider\PasswordPolicy;

Storage::ensure();
Auth::requireAdmin();
$users = Storage::readUsers();
$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
foreach ($users as $user) {
    if ((int) ($user['id'] ?? 0) === $editId) {
        $editUser = $user;
        break;
    }
}
$showForm = $editUser !== null || isset($_GET['new']);
$message = '';
$emojiGroups = EmojiCatalog::grouped();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save_user');
    $actionUserId = (int) ($_POST['id'] ?? 0);
    if ($action === 'activate_user') {
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $activatedUser = null;
        foreach ($users as $user) {
            if ((int) ($user['id'] ?? 0) === $actionUserId) {
                $activatedUser = $user;
                break;
            }
        }
        if (!Auth::activateUser($actionUserId, $role)) {
            $message = 'Dieses Konto kann nicht freigegeben oder reaktiviert werden.';
        } else {
            $redirect = '/users.php?saved=1';
            if ($activatedUser !== null && Auth::accountStatus($activatedUser) === Auth::STATUS_PENDING_APPROVAL) {
                $name = trim((string) (($activatedUser['first_name'] ?? '') . ' ' . ($activatedUser['last_name'] ?? '')));
                $settings = Storage::readSettings();
                $result = (new NotificationService($settings['mail'] ?? []))->sendAccountApprovedNotification((string) ($activatedUser['email'] ?? ''), $name, $role);
                if (!$result['success']) {
                    $redirect .= '&mail_failed=1';
                }
            }
            header('Location: ' . $redirect);
            exit;
        }
    } elseif ($action === 'deactivate_user') {
        if (!Auth::deactivateUser($actionUserId)) {
            $message = 'Das letzte aktive Administratorkonto kann nicht deaktiviert werden.';
        } else {
            header('Location: /users.php?saved=1');
            exit;
        }
    } elseif ($action === 'delete_deactivated_user') {
        if (!Auth::permanentlyDeleteDeactivatedUser($actionUserId)) {
            $message = 'Nur deaktivierte Konten können endgültig gelöscht werden.';
        } else {
            header('Location: /users.php?saved=1');
            exit;
        }
    }
    if ($message === '') {
    $id = (int) ($_POST['id'] ?? 0);
    $id = $id ?: ((count($users) > 0 ? max(array_map(fn ($item) => (int) ($item['id'] ?? 0), $users)) : 0) + 1);
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $adminCount = count(Auth::activeAdministrators());
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Bitte Vorname, Nachname und eine gültige E-Mail-Adresse eingeben.';
    } elseif (!$editUser && !PasswordPolicy::isValid($password)) {
        $message = __('password.invalid');
    } elseif ($password !== '' && $password !== $passwordConfirm) {
        $message = __('password.confirmation_mismatch');
    } elseif ($editUser && ($editUser['role'] ?? 'admin') === 'admin' && Auth::accountStatus($editUser) === Auth::STATUS_ACTIVE && $role !== 'admin' && $adminCount <= 1) {
        $message = 'Mindestens ein Benutzer muss Admin bleiben.';
    } else {
        $selectedEmoji = trim((string) ($_POST['emoji'] ?? ''));
        $item = array_merge($editUser ?? [], ['id' => $id, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'emoji' => EmojiCatalog::contains($selectedEmoji) ? $selectedEmoji : '😀', 'role' => $role, 'account_status' => $editUser['account_status'] ?? Auth::STATUS_ACTIVE]);
        if ($password !== '') {
            if (!PasswordPolicy::isValid($password)) {
                $message = __('password.invalid');
            } else {
                $item['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
        }
        if ($editUser && !isset($item['password_hash'])) {
            $item['password_hash'] = $editUser['password_hash'];
        }
        if ($message === '') {
            $updated = false;
            foreach ($users as $index => $existing) {
                if ((int) ($existing['id'] ?? 0) === $id) {
                    $users[$index] = $item;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                $users[] = $item;
            }
            Storage::saveUsers($users);
            header('Location: /users.php?saved=1');
            exit;
        }
    }
    }
}

pageHeader(__('page.users'));
if (isset($_GET['saved'])): ?><div class="alert">Benutzer wurde gespeichert.</div><?php endif; ?>
<?php if (isset($_GET['mail_failed'])): ?><div class="alert alert-error">Das Konto wurde freigegeben, aber die Informationsmail konnte nicht versendet werden.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="alert alert-error"><?= e($message); ?></div><?php endif; ?>
<section class="card">
    <div class="list-toolbar"><div><h2>Benutzerverwaltung</h2><p>Benutzer werden für Gerätezuordnung und Prüfungsbenachrichtigungen verwendet.</p></div><a class="button-link" href="/users.php?new=1">Neuer Benutzer</a></div>
</section>
<?php if ($showForm): ?><section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Benutzerverwaltung</p><h2><?= $editUser ? 'Benutzer bearbeiten' : 'Neuen Benutzer anlegen'; ?></h2></div><a class="button-link button-secondary" href="/users.php">Abbrechen</a></div>
    <form method="post" class="stacked-form">
        <input type="hidden" name="id" value="<?= e($editUser['id'] ?? ''); ?>" />
        <div class="form-sections">
            <section class="form-section"><h3>Kontaktdaten</h3><div class="row two-col"><label>Vorname<input type="text" name="first_name" value="<?= e($editUser['first_name'] ?? ''); ?>" required /></label><label>Nachname<input type="text" name="last_name" value="<?= e($editUser['last_name'] ?? ''); ?>" required /></label></div><label>E-Mail-Adresse<input type="email" name="email" value="<?= e($editUser['email'] ?? ''); ?>" required /></label></section>
            <section class="form-section"><h3>Rolle &amp; Passwort</h3><div class="row two-col"><label>Rolle<select name="role"><option value="user" <?= (($editUser['role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>Benutzer</option><option value="admin" <?= (($editUser['role'] ?? 'admin') === 'admin') ? 'selected' : ''; ?>>Administrator</option></select></label><label>Passwort<?= $editUser ? ' (optional ändern)' : ''; ?><input type="password" name="password" minlength="12" <?= $editUser ? '' : 'required'; ?> /></label></div><div class="row two-col"><label><?= e(__('form.password_confirm')); ?><input type="password" name="password_confirm" minlength="12" <?= $editUser ? '' : 'required'; ?> /></label></div><?php passwordRequirements(optional: $editUser !== null); ?><?php if ($editUser && in_array(Auth::accountStatus($editUser), [Auth::STATUS_PENDING_APPROVAL, Auth::STATUS_DEACTIVATED], true)): ?><p class="form-hint">Dieses Konto ist <?= Auth::accountStatus($editUser) === Auth::STATUS_PENDING_APPROVAL ? 'zur Freigabe bereit' : 'deaktiviert'; ?>. Die ausgewählte Rolle wird beim Aktivieren übernommen.</p><?php endif; ?></section>
            <section class="form-section"><h3>Emoticon</h3><?php emojiPicker($emojiGroups, (string) ($editUser['emoji'] ?? '')); ?></section>
        </div>
        <div class="row two-col"><button type="submit" name="action" value="save_user">Benutzer speichern</button><?php if ($editUser && in_array(Auth::accountStatus($editUser), [Auth::STATUS_PENDING_APPROVAL, Auth::STATUS_DEACTIVATED], true)): ?><button type="submit" name="action" value="activate_user" class="button-secondary"><?= Auth::accountStatus($editUser) === Auth::STATUS_PENDING_APPROVAL ? 'Freigeben' : 'Reaktivieren'; ?></button><?php endif; ?></div>
    </form>
</section><?php endif; ?>
<section class="card"><h2>Benutzerliste</h2><div class="table-wrap"><table data-sortable><thead><tr><th><button type="button" class="sort-button" data-sort="0">Emoticon</button></th><th><button type="button" class="sort-button" data-sort="1">Vorname</button></th><th><button type="button" class="sort-button" data-sort="2">Nachname</button></th><th><button type="button" class="sort-button" data-sort="3">E-Mail-Adresse</button></th><th><button type="button" class="sort-button" data-sort="4">Rolle</button></th><th><button type="button" class="sort-button" data-sort="5">Status</button></th><th>Aktion</th></tr></thead><tbody>
<?php if (!$users): ?><tr data-sortable-row="false"><td colspan="7">Noch keine Benutzer erfasst.</td></tr><?php endif; ?>
<?php foreach ($users as $user): ?><?php $status = Auth::accountStatus($user); ?><tr><td class="emoji-cell"><?= e($user['emoji'] ?? ''); ?></td><td><?= e($user['first_name'] ?? ''); ?></td><td><?= e($user['last_name'] ?? ''); ?></td><td><?= e($user['email'] ?? ''); ?></td><td><?= ($user['role'] ?? 'admin') === 'admin' ? 'Administrator' : 'Benutzer'; ?></td><td><?= e(match ($status) { Auth::STATUS_PENDING_VERIFICATION => 'E-Mail-Bestätigung ausstehend', Auth::STATUS_PENDING_APPROVAL => 'Freigabe ausstehend', Auth::STATUS_DEACTIVATED => 'Deaktiviert', default => 'Aktiv' }); ?></td><td><a class="button-link" href="/users.php?edit=<?= (int) ($user['id'] ?? 0); ?>">Bearbeiten</a><?php if ($status === Auth::STATUS_ACTIVE): ?><form method="post" class="action-form" data-confirm="Konto wirklich deaktivieren? Die zugeordneten Geräte werden archiviert."><input type="hidden" name="action" value="deactivate_user" /><input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0); ?>" /><button type="submit" class="button-secondary">Deaktivieren</button></form><?php elseif ($status === Auth::STATUS_DEACTIVATED): ?><form method="post" class="action-form" data-confirm="Deaktiviertes Konto endgültig löschen? Die archivierten Geräte bleiben erhalten, werden aber nicht mehr zugeordnet."><input type="hidden" name="action" value="delete_deactivated_user" /><input type="hidden" name="id" value="<?= (int) ($user['id'] ?? 0); ?>" /><button type="submit" class="button-danger">Endgültig löschen</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<script src="/assets/password-requirements.js"></script>
<?php pageFooter();
