<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;
use Glider\I18n;

Auth::boot();
$language = (string) ($_POST['language'] ?? '');
$redirect = (string) ($_POST['redirect'] ?? '/');
if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

if (I18n::isSupported($language)) {
    if (Auth::user() !== null) {
        Auth::setLanguage($language);
    } else {
        $_SESSION['language'] = $language;
        setcookie(I18n::COOKIE_NAME, $language, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    I18n::setLocale($language);
}

header('Location: ' . $redirect);
exit;