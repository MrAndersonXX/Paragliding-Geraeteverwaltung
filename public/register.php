<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/NotificationService.php';

use Glider\Auth;
use Glider\NotificationService;
use Glider\Storage;

const REGISTRATION_CODE_TTL_SECONDS = 900;
const REGISTRATION_CODE_MAX_ATTEMPTS = 5;
const REGISTRATION_CODE_RESEND_COOLDOWN_SECONDS = 60;

Auth::boot();
if (Auth::user() !== null) {
    header('Location: /');
    exit;
}

$message = '';
$messageClass = 'alert alert-error';
$registrationId = (int) ($_SESSION['registration_user_id'] ?? 0);
$users = Storage::readUsers();
$registration = null;
foreach ($users as $user) {
    if ((int) ($user['id'] ?? 0) === $registrationId && Auth::accountStatus($user) === Auth::STATUS_PENDING_VERIFICATION) {
        $registration = $user;
        break;
    }
}

if (isset($_GET['approved'])) {
    $message = __('register.email_confirmed');
    $messageClass = 'alert';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $emailTaken = false;
    foreach ($users as $user) {
        if (strcasecmp((string) ($user['email'] ?? ''), $email) === 0) {
            $emailTaken = true;
            break;
        }
    }
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = __('register.required_fields');
    } elseif ($emailTaken) {
        $message = __('register.email_taken');
    } elseif (strlen($password) < 8) {
        $message = __('register.password_short');
    } else {
        $code = (string) random_int(100000, 999999);
        $settings = Storage::readSettings();
        $result = (new NotificationService($settings['mail'] ?? []))->sendRegistrationVerificationCode($email, $code);
        if (!$result['success']) {
            $message = __('register.code_failed', ['message' => $result['message']]);
        } else {
            $nextId = (count($users) > 0 ? max(array_map(static fn (array $user): int => (int) ($user['id'] ?? 0), $users)) : 0) + 1;
            $users[] = [
                'id' => $nextId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'emoji' => '😀',
                'role' => 'user',
                'account_status' => Auth::STATUS_PENDING_VERIFICATION,
                'preferences' => ['language' => \Glider\I18n::locale()],
                'registration_code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'registration_code_expires_at' => time() + REGISTRATION_CODE_TTL_SECONDS,
                'registration_code_attempts' => 0,
                'registration_code_requested_at' => time(),
            ];
            Storage::saveUsers($users);
            $_SESSION['registration_user_id'] = $nextId;
            header('Location: /register.php?code_sent=1');
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_code') {
    $code = trim((string) ($_POST['code'] ?? ''));
    if ($registration === null) {
        $message = __('register.no_pending');
    } elseif (time() > (int) ($registration['registration_code_expires_at'] ?? 0)) {
        $message = __('register.code_expired');
    } elseif ((int) ($registration['registration_code_attempts'] ?? 0) >= REGISTRATION_CODE_MAX_ATTEMPTS) {
        $message = __('register.too_many_attempts');
    } elseif (!password_verify($code, (string) ($registration['registration_code_hash'] ?? ''))) {
        $remaining = REGISTRATION_CODE_MAX_ATTEMPTS - ((int) ($registration['registration_code_attempts'] ?? 0) + 1);
        foreach ($users as $index => $user) {
            if ((int) ($user['id'] ?? 0) === $registrationId) {
                $users[$index]['registration_code_attempts'] = (int) ($user['registration_code_attempts'] ?? 0) + 1;
                break;
            }
        }
        Storage::saveUsers($users);
        $message = __('register.invalid_code', ['remaining' => $remaining]);
    } else {
        foreach ($users as $index => $user) {
            if ((int) ($user['id'] ?? 0) === $registrationId) {
                $users[$index]['account_status'] = Auth::STATUS_PENDING_APPROVAL;
                $users[$index]['email_verified_at'] = time();
                unset($users[$index]['registration_code_hash'], $users[$index]['registration_code_expires_at'], $users[$index]['registration_code_attempts'], $users[$index]['registration_code_requested_at']);
                $registration = $users[$index];
                break;
            }
        }
        Storage::saveUsers($users);
        $settings = Storage::readSettings();
        $service = new NotificationService($settings['mail'] ?? []);
        $name = trim(($registration['first_name'] ?? '') . ' ' . ($registration['last_name'] ?? ''));
        foreach (Auth::activeAdministrators() as $administrator) {
            $service->sendNewRegistrationNotification((string) ($administrator['email'] ?? ''), $name, (string) ($registration['email'] ?? ''));
        }
        unset($_SESSION['registration_user_id']);
        header('Location: /register.php?approved=1');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_code') {
    if ($registration === null) {
        $message = __('register.no_pending');
    } elseif (time() - (int) ($registration['registration_code_requested_at'] ?? 0) < REGISTRATION_CODE_RESEND_COOLDOWN_SECONDS) {
        $message = __('register.wait_before_resend');
    } else {
        $code = (string) random_int(100000, 999999);
        $settings = Storage::readSettings();
        $result = (new NotificationService($settings['mail'] ?? []))->sendRegistrationVerificationCode((string) $registration['email'], $code);
        if (!$result['success']) {
            $message = __('register.code_failed', ['message' => $result['message']]);
        } else {
            foreach ($users as $index => $user) {
                if ((int) ($user['id'] ?? 0) === $registrationId) {
                    $users[$index]['registration_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
                    $users[$index]['registration_code_expires_at'] = time() + REGISTRATION_CODE_TTL_SECONDS;
                    $users[$index]['registration_code_attempts'] = 0;
                    $users[$index]['registration_code_requested_at'] = time();
                    break;
                }
            }
            Storage::saveUsers($users);
            header('Location: /register.php?code_sent=1');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars(\Glider\I18n::locale(), ENT_QUOTES, 'UTF-8'); ?>">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title><?= htmlspecialchars(__('register.title'), ENT_QUOTES, 'UTF-8'); ?></title><link rel="stylesheet" href="/assets/styles.css" /></head>
<body><main class="container login-container"><section class="card login-card"><p class="eyebrow">Glider Equipment Tracker</p><div class="language-switcher" aria-label="<?= htmlspecialchars(__('language.choose'), ENT_QUOTES, 'UTF-8'); ?>"><?php foreach (\Glider\I18n::locales() as $language => $languageInfo): ?><form method="post" action="/language.php"><input type="hidden" name="language" value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>" /><input type="hidden" name="redirect" value="/register.php" /><button type="submit" class="language-option<?= \Glider\I18n::locale() === $language ? ' active' : ''; ?>" aria-label="<?= htmlspecialchars($languageInfo['label'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($languageInfo['label'], ENT_QUOTES, 'UTF-8'); ?>"><span aria-hidden="true"><?= htmlspecialchars($languageInfo['flag'], ENT_QUOTES, 'UTF-8'); ?></span><span><?= htmlspecialchars($languageInfo['label'], ENT_QUOTES, 'UTF-8'); ?></span></button></form><?php endforeach; ?></div><h1><?= htmlspecialchars(__('register.title'), ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if (isset($_GET['code_sent'])): ?><div class="alert"><?= htmlspecialchars(__('register.code_sent'), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="<?= htmlspecialchars($messageClass, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($registration !== null): ?>
<p><?= htmlspecialchars(__('register.code_prompt'), ENT_QUOTES, 'UTF-8'); ?> <strong><?= htmlspecialchars((string) $registration['email'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
<form method="post" class="stacked-form"><input type="hidden" name="action" value="confirm_code" /><label><?= htmlspecialchars(__('register.confirmation_code'), ENT_QUOTES, 'UTF-8'); ?><input type="text" name="code" class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus /></label><button type="submit"><?= htmlspecialchars(__('register.confirm_code'), ENT_QUOTES, 'UTF-8'); ?></button></form>
<form method="post" class="action-form"><input type="hidden" name="action" value="resend_code" /><button type="submit" class="button-secondary"><?= htmlspecialchars(__('register.resend_code'), ENT_QUOTES, 'UTF-8'); ?></button></form>
<?php elseif (!isset($_GET['approved'])): ?>
<form method="post" class="stacked-form"><input type="hidden" name="action" value="register" /><div class="row two-col"><label><?= htmlspecialchars(__('form.first_name'), ENT_QUOTES, 'UTF-8'); ?><input type="text" name="first_name" required autofocus /></label><label><?= htmlspecialchars(__('form.last_name'), ENT_QUOTES, 'UTF-8'); ?><input type="text" name="last_name" required /></label></div><label><?= htmlspecialchars(__('form.email'), ENT_QUOTES, 'UTF-8'); ?><input type="email" name="email" required /></label><label><?= htmlspecialchars(__('form.password'), ENT_QUOTES, 'UTF-8'); ?><input type="password" name="password" minlength="8" required /></label><button type="submit"><?= htmlspecialchars(__('register.request_code'), ENT_QUOTES, 'UTF-8'); ?></button></form>
<?php endif; ?>
<p class="form-hint"><a href="/login.php"><?= htmlspecialchars(__('register.login'), ENT_QUOTES, 'UTF-8'); ?></a></p></section></main></body></html>