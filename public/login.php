<?php

require_once __DIR__ . '/../src/Storage.php';
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
    $message = 'Bitte bestätige zuerst deine E-Mail-Adresse über den zugesendeten Code.';
} elseif ($accountStatus === Auth::STATUS_PENDING_APPROVAL) {
    $message = 'Deine Registrierung wartet noch auf die Freigabe durch einen Administrator.';
} elseif ($accountStatus === Auth::STATUS_DEACTIVATED) {
    $message = 'Dieses Benutzerkonto wurde deaktiviert.';
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
        Auth::STATUS_PENDING_VERIFICATION => 'Bitte bestätige zuerst deine E-Mail-Adresse über den zugesendeten Code.',
        Auth::STATUS_PENDING_APPROVAL => 'Deine Registrierung wartet noch auf die Freigabe durch einen Administrator.',
        Auth::STATUS_DEACTIVATED => 'Dieses Benutzerkonto wurde deaktiviert.',
        default => 'E-Mail-Adresse oder Passwort ist nicht korrekt.',
    };
}
?><!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Anmelden</title><link rel="stylesheet" href="/assets/styles.css" /></head>
<body><main class="container login-container"><section class="card login-card"><p class="eyebrow">Glider Equipment Tracker</p><h1>Anmelden</h1><?php if ($message !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="post" class="stacked-form"><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>" /><label>E-Mail-Adresse<input type="email" name="email" required autofocus /></label><label>Passwort<input type="password" name="password" required /></label><button type="submit">Anmelden</button></form><p class="form-hint">Noch kein Konto? <a href="/register.php">Jetzt registrieren</a></p></section></main></body></html>