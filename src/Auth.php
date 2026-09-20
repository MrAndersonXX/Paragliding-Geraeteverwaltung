<?php

namespace Glider;

class Auth
{
    private const DEFAULT_PASSWORD = 'GliderAdmin2026!';
    private const REMEMBER_COOKIE = 'glider_remember';
    private const REMEMBER_SECONDS = 2419200;

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
        self::restoreRememberedLogin();
        $user = self::findUser((int) ($_SESSION['user_id'] ?? 0));
        if ($user !== null && !empty($_COOKIE[self::REMEMBER_COOKIE])) {
            self::renewRememberToken($user, (string) $_COOKIE[self::REMEMBER_COOKIE]);
        }
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
        $user = self::findUser($userId);
        if ($user !== null) {
            return $user;
        }
        unset($_SESSION['user_id']);
        return null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function preference(string $key, bool $default = false): bool
    {
        $user = self::user();
        if ($user === null) {
            return $default;
        }
        return (bool) ($user['preferences'][$key] ?? $default);
    }

    public static function setPreference(string $key, bool $value): void
    {
        $user = self::user();
        if ($user === null) {
            return;
        }
        $users = Storage::readUsers();
        foreach ($users as $index => $storedUser) {
            if ((int) ($storedUser['id'] ?? 0) === (int) $user['id']) {
                $users[$index]['preferences'][$key] = $value;
                Storage::saveUsers($users);
                return;
            }
        }
    }

    /**
     * Merges the given fields into the currently logged-in user's stored record.
     */
    public static function updateCurrentUser(array $fields): void
    {
        $user = self::user();
        if ($user === null) {
            return;
        }
        $users = Storage::readUsers();
        foreach ($users as $index => $storedUser) {
            if ((int) ($storedUser['id'] ?? 0) === (int) $user['id']) {
                $users[$index] = array_merge($storedUser, $fields);
                Storage::saveUsers($users);
                return;
            }
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::boot();
        foreach (Storage::readUsers() as $user) {
            if (strcasecmp((string) ($user['email'] ?? ''), trim($email)) === 0 && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                self::startRememberedSession($user);
                return true;
            }
        }
        return false;
    }

    public static function logout(): void
    {
        self::boot();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            $users = Storage::readUsers();
            foreach ($users as $index => $user) {
                if ((int) ($user['id'] ?? 0) === $userId) {
                    unset($users[$index]['remember_token_hash'], $users[$index]['remember_expires_at']);
                    Storage::saveUsers(array_values($users));
                    break;
                }
            }
        }
        self::clearRememberCookie();
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

    private static function findUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        foreach (Storage::readUsers() as $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                return $user;
            }
        }
        return null;
    }

    private static function restoreRememberedLogin(): void
    {
        if (!empty($_SESSION['user_id']) || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        $token = (string) $_COOKIE[self::REMEMBER_COOKIE];
        $now = time();
        foreach (Storage::readUsers() as $user) {
            if (!empty($user['remember_token_hash']) && (int) ($user['remember_expires_at'] ?? 0) >= $now && password_verify($token, $user['remember_token_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                return;
            }
        }
        self::clearRememberCookie();
    }

    private static function startRememberedSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $token = bin2hex(random_bytes(32));
        $users = Storage::readUsers();
        foreach ($users as $index => $storedUser) {
            if ((int) ($storedUser['id'] ?? 0) === (int) $user['id']) {
                $users[$index]['remember_token_hash'] = password_hash($token, PASSWORD_DEFAULT);
                $users[$index]['remember_expires_at'] = time() + self::REMEMBER_SECONDS;
                Storage::saveUsers($users);
                self::setRememberCookie($token);
                return;
            }
        }
    }

    private static function renewRememberToken(array $user, string $token): void
    {
        if (empty($user['remember_token_hash']) || !password_verify($token, $user['remember_token_hash'])) {
            return;
        }
        $users = Storage::readUsers();
        foreach ($users as $index => $storedUser) {
            if ((int) ($storedUser['id'] ?? 0) === (int) $user['id']) {
                $users[$index]['remember_expires_at'] = time() + self::REMEMBER_SECONDS;
                Storage::saveUsers($users);
                self::setRememberCookie($token);
                return;
            }
        }
    }

    private static function setRememberCookie(string $token): void
    {
        setcookie(self::REMEMBER_COOKIE, $token, [
            'expires' => time() + self::REMEMBER_SECONDS,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}