<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;

Auth::boot();
if (Auth::user() !== null) {
    header('Location: /');
    exit;
}

$message = '';
$accountStatus = (string) ($_GET['account'] ?? '');
if ($accountStatus === Auth::STATUS_PENDING_VERIFICATION) {
    $message = __('login.pending_verification');
} elseif ($accountStatus === Auth::STATUS_PENDING_APPROVAL) {
    $message = __('login.pending_approval');
} elseif ($accountStatus === Auth::STATUS_DEACTIVATED) {
    $message = __('login.deactivated');
} elseif (!empty($_GET['imported'])) {
    $message = __('login.imported');
}
$redirect = (string) ($_POST['redirect'] ?? $_GET['redirect'] ?? '/');
if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: ' . $redirect);
        exit;
    }
    $status = Auth::loginFailureReason();
    $message = match ($status) {
        Auth::STATUS_PENDING_VERIFICATION => __('login.pending_verification'),
        Auth::STATUS_PENDING_APPROVAL => __('login.pending_approval'),
        Auth::STATUS_DEACTIVATED => __('login.deactivated'),
        default => __('login.invalid_credentials'),
    };
}
    ?><!DOCTYPE html>
    <html lang="<?= htmlspecialchars(\Glider\I18n::locale(), ENT_QUOTES, 'UTF-8'); ?>">
    <head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title><?= htmlspecialchars(__('login.title'), ENT_QUOTES, 'UTF-8'); ?></title><link rel="stylesheet" href="/assets/styles.css" /></head>
    <body><main class="container login-container"><section class="card login-card"><p class="eyebrow">Glider Equipment Tracker</p><details class="language-switcher"><summary aria-label="<?= htmlspecialchars(__('language.choose'), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(__('language.choose'), ENT_QUOTES, 'UTF-8'); ?>"><img src="<?= htmlspecialchars(\Glider\I18n::locales()[\Glider\I18n::locale()]['flag'], ENT_QUOTES, 'UTF-8'); ?>" alt="" /></summary><div class="language-options"><?php foreach (\Glider\I18n::locales() as $language => $languageInfo): ?><?php if ($language === \Glider\I18n::locale()) { continue; } ?><form method="post" action="/language.php"><input type="hidden" name="language" value="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>" /><input type="hidden" name="redirect" value="/login.php" /><button type="submit" class="language-option" aria-label="<?= htmlspecialchars($languageInfo['label'], ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($languageInfo['label'], ENT_QUOTES, 'UTF-8'); ?>"><img src="<?= htmlspecialchars($languageInfo['flag'], ENT_QUOTES, 'UTF-8'); ?>" alt="" /></button></form><?php endforeach; ?></div></details><h1><?= htmlspecialchars(__('login.title'), ENT_QUOTES, 'UTF-8'); ?></h1><?php if ($message !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="post" class="stacked-form"><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>" /><label><?= htmlspecialchars(__('form.email'), ENT_QUOTES, 'UTF-8'); ?><input type="email" name="email" required autofocus /></label><label><?= htmlspecialchars(__('form.password'), ENT_QUOTES, 'UTF-8'); ?><input type="password" name="password" required /></label><button type="submit"><?= htmlspecialchars(__('login.submit'), ENT_QUOTES, 'UTF-8'); ?></button></form><p class="form-hint"><?= htmlspecialchars(__('login.no_account'), ENT_QUOTES, 'UTF-8'); ?> <a href="/register.php"><?= htmlspecialchars(__('login.register'), ENT_QUOTES, 'UTF-8'); ?></a></p></section></main></body></html>