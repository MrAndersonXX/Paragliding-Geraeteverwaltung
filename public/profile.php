<?php

require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/EmojiCatalog.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/NotificationService.php';

use Glider\Storage;
use Glider\EmojiCatalog;
use Glider\Auth;
use Glider\NotificationService;

const EMAIL_CODE_TTL_SECONDS = 900;
const EMAIL_CODE_MAX_ATTEMPTS = 5;
const EMAIL_CODE_RESEND_COOLDOWN_SECONDS = 60;

Auth::requireLogin();
$user = Auth::user();
$users = Storage::readUsers();
$emojiGroups = EmojiCatalog::grouped();
$message = '';
$messageClass = 'alert';

$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $selectedEmoji = trim((string) ($_POST['emoji'] ?? ''));

    $emailTaken = false;
    foreach ($users as $existing) {
        if ((int) ($existing['id'] ?? 0) !== (int) $user['id'] && strcasecmp((string) ($existing['email'] ?? ''), $email) === 0) {
            $emailTaken = true;
            break;
        }
    }

    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Bitte Vorname, Nachname und eine gültige E-Mail-Adresse eingeben.';
        $messageClass = 'alert alert-error';
    } elseif ($emailTaken) {
        $message = 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet.';
        $messageClass = 'alert alert-error';
    } elseif ($password !== '' && strlen($password) < 8) {
        $message = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        $messageClass = 'alert alert-error';
    } else {
        $fields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'emoji' => EmojiCatalog::contains($selectedEmoji) ? $selectedEmoji : ($user['emoji'] ?? '😀'),
        ];
        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $emailChanged = strcasecmp($email, (string) ($user['email'] ?? '')) !== 0;
        $codeSendFailed = false;
        if ($emailChanged) {
            $now = time();
            $lastRequestedAt = (int) ($user['pending_email_requested_at'] ?? 0);
            if ($lastRequestedAt > 0 && $now - $lastRequestedAt < EMAIL_CODE_RESEND_COOLDOWN_SECONDS) {
                $message = 'Bitte kurz warten, bevor ein weiterer Bestätigungscode angefordert wird.';
                $messageClass = 'alert alert-error';
                $codeSendFailed = true;
            } else {
                $code = (string) random_int(100000, 999999);
                $mailSettings = Storage::readSettings();
                $result = (new NotificationService($mailSettings['mail'] ?? []))->sendVerificationCode($email, $code);
                if ($result['success']) {
                    $fields['pending_email'] = $email;
                    $fields['pending_email_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
                    $fields['pending_email_expires_at'] = $now + EMAIL_CODE_TTL_SECONDS;
                    $fields['pending_email_attempts'] = 0;
                    $fields['pending_email_requested_at'] = $now;
                    $message = 'Ein Bestätigungscode wurde an die neue Adresse gesendet. Bitte unten eingeben, um die Änderung abzuschließen.';
                } else {
                    $message = 'Der Bestätigungscode konnte nicht versendet werden (' . $result['message'] . '). Die E-Mail-Adresse wurde nicht geändert.';
                    $messageClass = 'alert alert-error';
                    $codeSendFailed = true;
                }
            }
        }

        Auth::updateCurrentUser($fields);
        // Redirect only on full success, otherwise keep rendering so the error/pending-code message survives.
        if (!$codeSendFailed) {
            $redirectMessage = $emailChanged ? 'code_sent=1' : 'saved=1';
            header('Location: /profile.php?' . $redirectMessage);
            exit;
        }
        $user = Auth::user();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'confirm_email_code') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $pendingEmail = (string) ($user['pending_email'] ?? '');
    $expiresAt = (int) ($user['pending_email_expires_at'] ?? 0);
    $attempts = (int) ($user['pending_email_attempts'] ?? 0);

    if ($pendingEmail === '') {
        $message = 'Es liegt keine ausstehende E-Mail-Änderung vor.';
        $messageClass = 'alert alert-error';
    } elseif (time() > $expiresAt) {
        Auth::updateCurrentUser(['pending_email' => null, 'pending_email_code_hash' => null, 'pending_email_expires_at' => null, 'pending_email_attempts' => null, 'pending_email_requested_at' => null]);
        header('Location: /profile.php?expired=1');
        exit;
    } elseif ($attempts >= EMAIL_CODE_MAX_ATTEMPTS) {
        Auth::updateCurrentUser(['pending_email' => null, 'pending_email_code_hash' => null, 'pending_email_expires_at' => null, 'pending_email_attempts' => null, 'pending_email_requested_at' => null]);
        header('Location: /profile.php?locked=1');
        exit;
    } elseif (password_verify($code, (string) ($user['pending_email_code_hash'] ?? ''))) {
        Auth::updateCurrentUser(['email' => $pendingEmail, 'pending_email' => null, 'pending_email_code_hash' => null, 'pending_email_expires_at' => null, 'pending_email_attempts' => null, 'pending_email_requested_at' => null]);
        header('Location: /profile.php?confirmed=1');
        exit;
    } else {
        Auth::updateCurrentUser(['pending_email_attempts' => $attempts + 1]);
        $remaining = EMAIL_CODE_MAX_ATTEMPTS - ($attempts + 1);
        $message = "Der Code ist ungültig. Verbleibende Versuche: {$remaining}.";
        $messageClass = 'alert alert-error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel_email_change') {
    Auth::updateCurrentUser(['pending_email' => null, 'pending_email_code_hash' => null, 'pending_email_expires_at' => null, 'pending_email_attempts' => null, 'pending_email_requested_at' => null]);
    header('Location: /profile.php?cancelled=1');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'deactivate_profile') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
        $message = 'Das aktuelle Passwort ist nicht korrekt.';
        $messageClass = 'alert alert-error';
    } elseif (!Auth::deactivateUser((int) $user['id'])) {
        $message = 'Das letzte aktive Administratorkonto kann nicht deaktiviert werden.';
        $messageClass = 'alert alert-error';
    } else {
        Auth::logout();
        header('Location: /login.php?account=' . Auth::STATUS_DEACTIVATED);
        exit;
    }
}

$user = Auth::user();

pageHeader('Mein Profil');
if (isset($_GET['saved'])): ?><div class="alert">Profil wurde gespeichert.</div><?php endif; ?>
<?php if (isset($_GET['code_sent'])): ?><div class="alert">Profil wurde gespeichert. Ein Bestätigungscode wurde an die neue E-Mail-Adresse gesendet.</div><?php endif; ?>
<?php if (isset($_GET['confirmed'])): ?><div class="alert">E-Mail-Adresse wurde bestätigt und geändert.</div><?php endif; ?>
<?php if (isset($_GET['cancelled'])): ?><div class="alert">Die E-Mail-Änderung wurde abgebrochen.</div><?php endif; ?>
<?php if (isset($_GET['expired'])): ?><div class="alert alert-error">Der Bestätigungscode ist abgelaufen. Bitte E-Mail-Änderung erneut anfordern.</div><?php endif; ?>
<?php if (isset($_GET['locked'])): ?><div class="alert alert-error">Zu viele Fehlversuche. Bitte E-Mail-Änderung erneut anfordern.</div><?php endif; ?>
<?php if ($message !== ''): ?><div class="<?= e($messageClass); ?>"><?= e($message); ?></div><?php endif; ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Profil</p><h2>Meine Daten</h2></div></div>
    <form method="post" class="stacked-form" data-edit-form>
        <input type="hidden" name="action" value="update_profile" />
        <div class="form-sections">
            <section class="form-section">
                <h3>Kontaktdaten</h3>
                <div class="row two-col">
                    <label>Vorname<input type="text" name="first_name" value="<?= e($user['first_name'] ?? ''); ?>" required /></label>
                    <label>Nachname<input type="text" name="last_name" value="<?= e($user['last_name'] ?? ''); ?>" required /></label>
                </div>
                <label>E-Mail-Adresse<input type="email" name="email" value="<?= e($user['email'] ?? ''); ?>" required /></label>
                <p class="form-hint">Wird eine neue E-Mail-Adresse eingetragen, muss diese über einen per Mail zugesendeten Bestätigungscode freigeschaltet werden.</p>
                <label>Aktueller Userlevel<input type="text" value="<?= ($user['role'] ?? 'admin') === 'admin' ? 'Administrator' : 'Benutzer'; ?>" disabled title="Der Userlevel kann nur von einem Administrator über die Benutzerverwaltung geändert werden." /></label>
                <label>Kontostatus<input type="text" value="<?= e(match (Auth::accountStatus($user)) { Auth::STATUS_PENDING_VERIFICATION => 'E-Mail-Bestätigung ausstehend', Auth::STATUS_PENDING_APPROVAL => 'Freigabe ausstehend', Auth::STATUS_DEACTIVATED => 'Deaktiviert', default => 'Aktiv' }); ?>" disabled title="Der Kontostatus wird durch Registrierung oder Benutzerverwaltung festgelegt." /></label>
            </section>
            <section class="form-section">
                <h3>Passwort</h3>
                <label>Neues Passwort (optional ändern)<input type="password" name="password" minlength="8" /></label>
            </section>
            <section class="form-section">
                <h3>Emoticon</h3>
                <?php emojiPicker($emojiGroups, (string) ($user['emoji'] ?? '')); ?>
            </section>
        </div>
        <button type="submit">Profil speichern</button>
    </form>
</section>
<?php if (!empty($user['pending_email'])): ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Profil</p><h2>E-Mail-Bestätigung ausstehend</h2></div></div>
    <p>Ein Bestätigungscode wurde an <strong><?= e($user['pending_email']); ?></strong> gesendet. Der Code ist gültig bis <?= e(date('H:i', (int) ($user['pending_email_expires_at'] ?? 0))); ?> Uhr.</p>
    <form method="post" class="stacked-form">
        <input type="hidden" name="action" value="confirm_email_code" />
        <label>Bestätigungscode<input type="text" name="code" class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus /></label>
        <div class="row two-col">
            <button type="submit">Bestätigen</button>
            <button type="submit" form="cancel-email-form" class="button-secondary">Abbrechen</button>
        </div>
    </form>
    <form method="post" id="cancel-email-form">
        <input type="hidden" name="action" value="cancel_email_change" />
    </form>
</section>
<?php endif; ?>
<section class="card edit-surface">
    <div class="edit-header"><div><p class="eyebrow">Profil</p><h2>Profil deaktivieren</h2></div></div>
    <p>Dein Konto wird deaktiviert und alle dir zugeordneten Geräte werden archiviert. Ein Administrator kann dein Konto später wieder aktivieren.</p>
    <form method="post" class="stacked-form" data-confirm="Profil wirklich deaktivieren? Deine Geräte werden archiviert und du wirst abgemeldet.">
        <input type="hidden" name="action" value="deactivate_profile" />
        <label>Aktuelles Passwort<input type="password" name="current_password" required /></label>
        <button type="submit" class="button-danger">Profil deaktivieren</button>
    </form>
</section>
<?php pageFooter();
