<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/NotificationService.php';
require_once __DIR__ . '/../src/RegionalSettings.php';
require_once __DIR__ . '/_password_requirements.php';

use Glider\Auth;
use Glider\I18n;
use Glider\NotificationService;
use Glider\PasswordPolicy;
use Glider\RegionalSettings;
use Glider\Storage;

function setup_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

Auth::boot();
if (!Auth::requiresInitialSetup()) {
    header('Location: /login.php');
    exit;
}

$settings = Storage::readSettings();
$regional = RegionalSettings::normalize((array) ($settings['regional'] ?? []));
$message = '';
$messageClass = 'alert alert-error';

$form = [
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'app_name' => trim((string) ($_POST['app_name'] ?? ($settings['app']['name'] ?? 'Glider Equipment Tracker'))),
    'language' => (string) ($_POST['language'] ?? $regional['language']),
    'country' => (string) ($_POST['country'] ?? $regional['country']),
    'timezone' => (string) ($_POST['timezone'] ?? $regional['timezone']),
    'date_format' => (string) ($_POST['date_format'] ?? $regional['date_format']),
    'mail_host' => trim((string) ($_POST['mail_host'] ?? ($settings['mail']['host'] ?? ''))),
    'mail_port' => trim((string) ($_POST['mail_port'] ?? ($settings['mail']['port'] ?? '587'))),
    'mail_username' => trim((string) ($_POST['mail_username'] ?? ($settings['mail']['username'] ?? ''))),
    'mail_password' => trim((string) ($_POST['mail_password'] ?? ($settings['mail']['password'] ?? ''))),
    'mail_encryption' => trim((string) ($_POST['mail_encryption'] ?? ($settings['mail']['encryption'] ?? 'tls'))),
    'mail_from_address' => trim((string) ($_POST['mail_from_address'] ?? ($settings['mail']['from_address'] ?? ''))),
    'mail_from_name' => trim((string) ($_POST['mail_from_name'] ?? ($settings['mail']['from_name'] ?? 'Glider Equipment Tracker'))),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $submittedRegional = RegionalSettings::normalize([
        'language' => $form['language'],
        'country' => $form['country'],
        'timezone' => $form['timezone'],
        'date_format' => $form['date_format'],
    ]);
    $mail = [
        'host' => $form['mail_host'],
        'port' => $form['mail_port'],
        'username' => $form['mail_username'],
        'password' => $form['mail_password'],
        'encryption' => $form['mail_encryption'],
        'from_address' => $form['mail_from_address'],
        'from_name' => $form['mail_from_name'],
    ];

    if ($form['first_name'] === '' || $form['last_name'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $message = __('setup.error_admin_required');
    } elseif (!PasswordPolicy::isValid($password)) {
        $message = __('password.invalid');
    } elseif ($password !== $passwordConfirm) {
        $message = __('setup.error_password_confirm');
    } elseif ($form['app_name'] === '') {
        $message = __('setup.error_app_name');
    } elseif (!filter_var($form['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
        $message = __('setup.error_sender');
    } else {
        $smtpResult = (new NotificationService($mail))->testConnection();
        if (!$smtpResult['success']) {
            $message = __('setup.error_smtp', ['message' => $smtpResult['message']]);
        } else {
            $settings['app'] = [
                'name' => $form['app_name'],
                'timezone' => $submittedRegional['timezone'],
            ];
            $settings['regional'] = $submittedRegional;
            $settings['mail'] = $mail;
            $settings['image_search'] = $settings['image_search'] ?? [
                'enabled' => false,
                'serpapi_key' => '',
            ];
            Storage::saveSettings($settings);
            Storage::saveUsers([[
                'id' => 1,
                'first_name' => $form['first_name'],
                'last_name' => $form['last_name'],
                'email' => $form['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'emoji' => '😀',
                'role' => 'admin',
                'account_status' => Auth::STATUS_ACTIVE,
                'preferences' => ['language' => $submittedRegional['language']],
                'email_verified_at' => time(),
                'created_at' => time(),
            ]]);
            I18n::setLocale($submittedRegional['language']);
            header('Location: /login.php?setup=1');
            exit;
        }
    }
}

?><!DOCTYPE html>
<html lang="<?= setup_e(I18n::locale()); ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= setup_e(__('setup.title')); ?></title>
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<main class="container login-container">
    <section class="card login-card edit-surface">
        <p class="eyebrow">Glider Equipment Tracker</p>
        <h1><?= setup_e(__('setup.title')); ?></h1>
        <p class="form-hint"><?= setup_e(__('setup.intro')); ?></p>
        <?php if ($message !== ''): ?><div class="<?= setup_e($messageClass); ?>"><?= setup_e($message); ?></div><?php endif; ?>
        <form method="post" class="stacked-form">
            <div class="form-sections">
                <section class="form-section">
                    <h3><?= setup_e(__('setup.admin_section')); ?></h3>
                    <div class="row two-col">
                        <label><?= setup_e(__('form.first_name')); ?><input type="text" name="first_name" value="<?= setup_e($form['first_name']); ?>" required autofocus /></label>
                        <label><?= setup_e(__('form.last_name')); ?><input type="text" name="last_name" value="<?= setup_e($form['last_name']); ?>" required /></label>
                    </div>
                    <label><?= setup_e(__('form.email')); ?><input type="email" name="email" value="<?= setup_e($form['email']); ?>" required /></label>
                    <div class="row two-col">
                        <label><?= setup_e(__('form.password')); ?><input type="password" name="password" minlength="12" required /></label>
                        <label><?= setup_e(__('form.password_confirm')); ?><input type="password" name="password_confirm" minlength="12" required /></label>
                    </div>
                    <?php passwordRequirements(); ?>
                </section>
                <section class="form-section">
                    <h3><?= setup_e(__('setup.regional_section')); ?></h3>
                    <div class="row two-col">
                        <label><?= setup_e(__('setup.app_name')); ?><input type="text" name="app_name" value="<?= setup_e($form['app_name']); ?>" required /></label>
                        <label><?= setup_e(__('setup.language')); ?><select name="language" required><?php foreach (RegionalSettings::languages() as $language => $languageInfo): ?><option value="<?= setup_e($language); ?>" <?= $form['language'] === $language ? 'selected' : ''; ?>><?= setup_e($languageInfo['label']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="row three-col">
                        <label><?= setup_e(__('setup.country')); ?><select name="country" required><?php foreach (RegionalSettings::countries() as $country => $label): ?><option value="<?= setup_e($country); ?>" <?= $form['country'] === $country ? 'selected' : ''; ?>><?= setup_e($label); ?></option><?php endforeach; ?></select></label>
                        <label><?= setup_e(__('setup.timezone')); ?><select name="timezone" required><?php foreach (RegionalSettings::timezones() as $timezone => $label): ?><option value="<?= setup_e($timezone); ?>" <?= $form['timezone'] === $timezone ? 'selected' : ''; ?>><?= setup_e($label); ?></option><?php endforeach; ?></select></label>
                        <label><?= setup_e(__('setup.date_format')); ?><select name="date_format" required><?php foreach (RegionalSettings::dateFormats() as $dateFormat => $label): ?><option value="<?= setup_e($dateFormat); ?>" <?= $form['date_format'] === $dateFormat ? 'selected' : ''; ?>><?= setup_e($label); ?></option><?php endforeach; ?></select></label>
                    </div>
                </section>
                <section class="form-section">
                    <h3><?= setup_e(__('setup.smtp_section')); ?></h3>
                    <div class="row two-col">
                        <label>SMTP Host<input type="text" name="mail_host" value="<?= setup_e($form['mail_host']); ?>" required /></label>
                        <label>SMTP Port<input type="number" name="mail_port" value="<?= setup_e($form['mail_port']); ?>" required /></label>
                    </div>
                    <div class="row three-col">
                        <label><?= setup_e(__('setup.smtp_username')); ?><input type="text" name="mail_username" value="<?= setup_e($form['mail_username']); ?>" /></label>
                        <label><?= setup_e(__('setup.smtp_password')); ?><input type="password" name="mail_password" value="<?= setup_e($form['mail_password']); ?>" /></label>
                        <label><?= setup_e(__('setup.smtp_encryption')); ?><select name="mail_encryption"><option value="tls" <?= $form['mail_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option><option value="ssl" <?= $form['mail_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="" <?= $form['mail_encryption'] === '' ? 'selected' : ''; ?>><?= setup_e(__('setup.smtp_none')); ?></option></select></label>
                    </div>
                    <div class="row two-col">
                        <label><?= setup_e(__('setup.smtp_from_address')); ?><input type="email" name="mail_from_address" value="<?= setup_e($form['mail_from_address']); ?>" required /></label>
                        <label><?= setup_e(__('setup.smtp_from_name')); ?><input type="text" name="mail_from_name" value="<?= setup_e($form['mail_from_name']); ?>" required /></label>
                    </div>
                    <p class="form-hint"><?= setup_e(__('setup.smtp_hint')); ?></p>
                </section>
            </div>
            <button type="submit"><?= setup_e(__('setup.submit')); ?></button>
        </form>
    </section>
</main>
<script src="/assets/password-requirements.js"></script>
</body>
</html>