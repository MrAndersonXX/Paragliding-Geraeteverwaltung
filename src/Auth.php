<?php

namespace Glider;

class Auth
{
    private const DEFAULT_PASSWORD = 'GliderAdmin2026!';

    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }
        self::migrateUsers();
        self::$booted = true;
    }

    public static function requireLogin(): void
    {
        self::boot();
        if (self::user() === null) {
            $target = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login.php?redirect=' . rawurlencode($target));
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Zugriff verweigert.');
        }
    }

    public static function user(): ?array
    {
        self::boot();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        foreach (Storage::readUsers() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                return $user;
            }
        }
        unset($_SESSION['user_id']);
        return null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function login(string $email, string $password): bool
    {
        self::boot();
        foreach (Storage::readUsers() as $user) {
            if (strcasecmp((string) ($user['email'] ?? ''), trim($email)) === 0 && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                return true;
            }
        }
        return false;
    }

    public static function logout(): void
    {
        self::boot();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function defaultPassword(): string
    {
        return self::DEFAULT_PASSWORD;
    }

    private static function migrateUsers(): void
    {
        $users = Storage::readUsers();
        if ($users === []) {
            return;
        }
        $changed = false;
        $adminFound = false;
        foreach ($users as $index => $user) {
            if (!in_array(($user['role'] ?? ''), ['admin', 'user'], true)) {
                $users[$index]['role'] = 'admin';
                $changed = true;
            }
            if (empty($user['password_hash'])) {
                $users[$index]['password_hash'] = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);
                $changed = true;
            }
            if (($users[$index]['role'] ?? '') === 'admin') {
                $adminFound = true;
            }
        }
        if (!$adminFound) {
            $users[0]['role'] = 'admin';
            $changed = true;
        }
        if ($changed) {
            Storage::saveUsers($users);
        }
    }
}