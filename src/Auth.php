<?php

namespace Glider;

class Auth
{
    private const REMEMBER_COOKIE = 'glider_remember';
    private const REMEMBER_SECONDS = 2419200;
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEACTIVATED = 'deactivated';

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
        if (self::requiresInitialSetup()) {
            header('Location: /setup.php');
            exit;
        }
        if (self::user() === null) {
            $target = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login.php?redirect=' . rawurlencode($target));
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireActiveAccount();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Zugriff verweigert.');
        }
    }

    public static function requireActiveAccount(): void
    {
        self::requireLogin();
        $accountStatus = self::accountStatus();
        if ($accountStatus !== self::STATUS_ACTIVE) {
            self::logout();
            header('Location: /login.php?account=' . rawurlencode($accountStatus));
            exit;
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

    public static function requiresInitialSetup(): bool
    {
        return Storage::readUsers() === [];
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function accountStatus(?array $user = null): string
    {
        $user ??= self::user();
        return (string) ($user['account_status'] ?? self::STATUS_ACTIVE);
    }

    public static function loginFailureReason(): string
    {
        return (string) ($_SESSION['login_failure_reason'] ?? '');
    }

    public static function preference(string $key, bool $default = false): bool
    {
        $user = self::user();
        if ($user === null) {
            return $default;
        }
        return (bool) ($user['preferences'][$key] ?? $default);
    }

    public static function language(): string
    {
        $user = self::user();
        $language = (string) ($user['preferences']['language'] ?? '');
        return \Glider\I18n::isSupported($language) ? $language : \Glider\I18n::DEFAULT_LOCALE;
    }

    public static function setLanguage(string $language): bool
    {
        if (!\Glider\I18n::isSupported($language) || self::user() === null) {
            return false;
        }
        $user = self::user();
        self::updateCurrentUser([
            'preferences' => array_merge((array) ($user['preferences'] ?? []), ['language' => $language]),
        ]);
        return true;
    }

    public static function consentGranted(string $key, bool $default = false): bool
    {
        return self::preference($key, $default);
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
                $users[$index]['preferences'] = is_array($users[$index]['preferences'] ?? null) ? $users[$index]['preferences'] : [];
                $users[$index]['preferences'][$key] = $value;
                Storage::saveUsers($users);
                return;
            }
        }
    }

    public static function grantConsent(string $key, bool $value = true): void
    {
        self::setPreference($key, $value);
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
                if (self::accountStatus($user) !== self::STATUS_ACTIVE) {
                    $_SESSION['login_failure_reason'] = self::accountStatus($user);
                    return false;
                }
                self::startRememberedSession($user);
                unset($_SESSION['login_failure_reason']);
                AuditLog::recordEvent('technical', 'authentication', 'login', (int) $user['id'], self::userLabel($user));
                return true;
            }
        }
        unset($_SESSION['login_failure_reason']);
        return false;
    }

    public static function logout(): void
    {
        self::boot();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $user = self::findUser($userId);
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
        if ($user !== null) {
            AuditLog::recordEvent('technical', 'authentication', 'logout', $userId, self::userLabel($user));
        }
        self::clearRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function activeAdministrators(): array
    {
        return array_values(array_filter(Storage::readUsers(), static fn (array $user): bool =>
            ($user['role'] ?? '') === 'admin' && self::accountStatus($user) === self::STATUS_ACTIVE
        ));
    }

    public static function canDeactivateUser(int $userId): bool
    {
        $user = self::findUser($userId);
        return $user === null || ($user['role'] ?? '') !== 'admin' || self::accountStatus($user) !== self::STATUS_ACTIVE || count(self::activeAdministrators()) > 1;
    }

    public static function deactivateUser(int $userId): bool
    {
        $user = self::findUser($userId);
        if ($user === null || self::accountStatus($user) === self::STATUS_DEACTIVATED || !self::canDeactivateUser($userId)) {
            return false;
        }

        $users = Storage::readUsers();
        foreach ($users as $index => $storedUser) {
            if ((int) ($storedUser['id'] ?? 0) === $userId) {
                $users[$index]['account_status'] = self::STATUS_DEACTIVATED;
                unset($users[$index]['remember_token_hash'], $users[$index]['remember_expires_at']);
                break;
            }
        }
        Storage::saveUsers($users);

        $equipment = Storage::readEquipment();
        foreach ($equipment as $index => $item) {
            if ((int) ($item['user_id'] ?? 0) === $userId) {
                $equipment[$index]['status'] = 'retired';
                $equipment[$index]['retired_at'] = date('Y-m-d');
            }
        }
        Storage::saveEquipment($equipment);
        return true;
    }

    public static function activateUser(int $userId, string $role): bool
    {
        if (!in_array($role, ['user', 'admin'], true)) {
            return false;
        }
        $users = Storage::readUsers();
        foreach ($users as $index => $user) {
            if ((int) ($user['id'] ?? 0) === $userId) {
                if (!in_array(self::accountStatus($user), [self::STATUS_PENDING_APPROVAL, self::STATUS_DEACTIVATED], true)) {
                    return false;
                }
                $users[$index]['account_status'] = self::STATUS_ACTIVE;
                $users[$index]['role'] = $role;
                Storage::saveUsers($users);
                return true;
            }
        }
        return false;
    }

    public static function permanentlyDeleteDeactivatedUser(int $userId): bool
    {
        $user = self::findUser($userId);
        if ($user === null || self::accountStatus($user) !== self::STATUS_DEACTIVATED) {
            return false;
        }
        $users = array_values(array_filter(Storage::readUsers(), static fn (array $storedUser): bool => (int) ($storedUser['id'] ?? 0) !== $userId));
        Storage::saveUsers($users);

        $equipment = Storage::readEquipment();
        foreach ($equipment as $index => $item) {
            if ((int) ($item['user_id'] ?? 0) === $userId) {
                $equipment[$index]['user_id'] = 0;
                $equipment[$index]['status'] = 'retired';
                $equipment[$index]['retired_at'] = $equipment[$index]['retired_at'] ?: date('Y-m-d');
            }
        }
        Storage::saveEquipment($equipment);
        return true;
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
            if (!is_array($users[$index]['preferences'] ?? null)) {
                $users[$index]['preferences'] = [];
                $changed = true;
            }
            if (!in_array(($users[$index]['account_status'] ?? ''), [self::STATUS_PENDING_VERIFICATION, self::STATUS_PENDING_APPROVAL, self::STATUS_ACTIVE, self::STATUS_DEACTIVATED], true)) {
                $users[$index]['account_status'] = self::STATUS_ACTIVE;
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
                AuditLog::recordEvent('technical', 'authentication', 'session_restored', (int) $user['id'], self::userLabel($user));
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

    private static function userLabel(array $user): string
    {
        $name = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        return $name !== '' ? $name : 'Unbekannter Benutzer';
    }
}