<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Config, Context, Csrf, Database, HttpException, Session};
use App\Repositories\UserRepository;
use App\Validators\PasswordPolicy;

final class AuthService
{
    private static ?string $dummyHash = null;

    public static function login(string $login, string $password): array
    {
        $max  = SettingService::int('security.max_login_attempts', 5);
        $lock = SettingService::int('security.lockout_minutes', 15);
        $ip   = (string) Context::get('ip', '0.0.0.0');

        // Per-IP throttle (blocks password spraying across many usernames).
        $ipFails = (int) Database::fetchOne(
            'SELECT COUNT(*) c FROM login_attempts WHERE ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$ip, $lock]
        )['c'];
        if ($ipFails >= $max * 4) {
            self::record($login, null, false, 'ip_throttled');
            throw new HttpException(429, 'Too many failed attempts from this location. Please try again later.');
        }

        $user = UserRepository::findByLogin($login);

        if ($user && (int) $user['is_locked'] === 1) {          // refuse WITHOUT checking the password during lockout
            self::record($login, (int) $user['id'], false, 'locked');
            throw new HttpException(423, 'This account is temporarily locked. Try again later or contact the administrator.');
        }

        $valid = password_verify($password, $user['password_hash'] ?? self::dummyHash());   // equal work for unknown users
        if (!$user || !$valid) {
            self::record($login, $user ? (int) $user['id'] : null, false, 'bad_credentials');
            if ($user) { self::registerFailure($user, $max, $lock); }
            throw new HttpException(401, 'Invalid username or password.');
        }
        if ($user['status'] !== 'active') {
            self::record($login, (int) $user['id'], false, 'inactive');
            throw new HttpException(403, 'This account is not active. Contact the administrator.');
        }
        Database::transaction(function () use ($user, $login, $password) {
            Database::execute('UPDATE users SET last_login_at = NOW(), locked_until = NULL WHERE id = ?', [$user['id']]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            self::record($login, (int) $user['id'], true, null);
            AuditService::log('auth.login', 'user', (int) $user['id'], null, null, (int) $user['id']);
        });
        Session::login((int) $user['id']);

        return ['user' => self::currentUser((int) $user['id']), 'csrf_token' => Csrf::token()];
    }

    public static function logout(): void
    {
        $uid = Session::userId();
        if ($uid) { AuditService::log('auth.logout', 'user', $uid, null, null, $uid); }
        Session::destroy();
    }

    /** Fresh view of an active, unlocked user with roles and permissions; null if the session must end. */
    public static function currentUser(int $id): ?array
    {
        $u = UserRepository::findById($id);
        if (!$u || $u['status'] !== 'active' || (int) $u['is_locked'] === 1) {
            return null;
        }
        $dept = Database::fetchOne('SELECT id, code, name FROM departments WHERE id = ?', [$u['department_id']]);
        $u = UserRepository::publicView($u);
        $u['department']  = $dept;
        $u['roles']       = UserRepository::rolesFor($id);
        $u['permissions'] = UserRepository::permissionsFor($id);
        $u['avatar_url']  = self::avatarUrl($u['avatar_path'] ?? null);
        return $u;
    }

    public static function avatarUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        $clean = ltrim((string) $path, '/');
        return rtrim((string) Config::get('app.url'), '/') . '/' . $clean;
    }

    public static function uploadAvatar(int $userId, string $imageData): void
    {
        $data = preg_replace('/^data:image\/(png|jpeg|jpg|gif|webp);base64,/', '', $imageData);
        if ($data === null || $data === '') {
            throw HttpException::unprocessable(['avatar' => 'Image upload failed.']);
        }
        $binary = base64_decode($data, true);
        if ($binary === false) {
            throw HttpException::unprocessable(['avatar' => 'Invalid image data.']);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            throw HttpException::unprocessable(['avatar' => 'Only PNG, JPG, GIF, or WebP images are allowed.']);
        }
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $dir = BASE_PATH . '/public/uploads/avatars';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $file = sprintf('%s/%d_%s.%s', $dir, $userId, bin2hex(random_bytes(8)), $ext);
        if (file_put_contents($file, $binary) === false) {
            throw HttpException::unprocessable(['avatar' => 'Unable to save image.']);
        }
        $relative = 'uploads/avatars/' . basename($file);
        $old = Database::fetchOne('SELECT avatar_path FROM users WHERE id = ?', [$userId])['avatar_path'] ?? null;
        if ($old && $old !== '' && $old !== $relative) {
            $oldFile = BASE_PATH . '/public/' . ltrim($old, '/');
            if (is_file($oldFile)) { unlink($oldFile); }
        }
        Database::execute('UPDATE users SET avatar_path = ? WHERE id = ?', [$relative, $userId]);
    }

    public static function changePassword(int $userId, string $current, string $new): void
    {
        $u = UserRepository::findById($userId);
        if (!$u || !password_verify($current, $u['password_hash'])) {
            throw HttpException::unprocessable(['current_password' => 'Current password is incorrect.']);
        }
        if (password_verify($new, $u['password_hash'])) {
            throw HttpException::unprocessable(['new_password' => 'Choose a password different from the current one.']);
        }
        PasswordPolicy::assert($new, $u['username']);
        Database::transaction(function () use ($userId, $new) {
            Database::execute('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]);
            Database::execute('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$userId, session_id()]);   // sign out other devices
            AuditService::log('auth.password_changed', 'user', $userId, null, null, $userId);
        });
        NotificationService::toUser($userId, 'security.password_changed', 'Your password was changed',
            'Your password was changed and your other devices were signed out.', 'info', 'user', $userId, $userId);
        NotificationService::toPermission('login_activity.view', 'security.password_changed', 'Password changed',
            "The password of {$u['username']} was changed.", 'info', 'user', $userId, $userId);
    }

    /** Always behaves the same whether or not the email exists (no account enumeration). */
    public static function requestPasswordReset(string $email): void
    {
        $user = UserRepository::findByEmail($email);
        if (!$user || $user['status'] !== 'active') { return; }
        $recent = (int) Database::fetchOne('SELECT COUNT(*) c FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$user['id']])['c'];
        if ($recent >= 3) { return; }
        $token = bin2hex(random_bytes(32));
        Database::transaction(function () use ($user, $token) {
            Database::execute('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            Database::execute('INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE),?)', [$user['id'], hash('sha256', $token), Context::get('ip')]);
            AuditService::log('auth.password_reset_requested', 'user', (int) $user['id'], null, null, (int) $user['id']);
        });
        $link = rtrim((string) Config::get('app.url'), '/') . '/reset-password?token=' . $token;
        MailOutbox::send($user['email'], 'Reset your password', "Use this link within 30 minutes:\n$link\n\nIf you did not request this, ignore this message.");
        // No account details here: the same notice is sent whatever the address, so nobody can probe for accounts.
        NotificationService::toPermission('login_activity.view', 'security.password_reset_requested', 'Password reset requested',
            'A password reset was requested for an account.', 'info', null, null, null);
    }

    public static function resetPassword(string $token, string $new): void
    {
        $row = Database::fetchOne(
            'SELECT pr.id, pr.user_id, u.username FROM password_resets pr JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()', [hash('sha256', $token)]
        );
        if (!$row) {
            throw HttpException::unprocessable(['token' => 'This reset link is invalid or has expired.']);
        }
        PasswordPolicy::assert($new, $row['username']);
        Database::transaction(function () use ($row, $new) {
            Database::execute('UPDATE users SET password_hash = ?, must_change_password = 0, locked_until = NULL WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $row['user_id']]);
            Database::execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);
            Database::execute('DELETE FROM sessions WHERE user_id = ?', [$row['user_id']]);
            AuditService::log('auth.password_reset_completed', 'user', (int) $row['user_id'], null, null, (int) $row['user_id']);
        });
        NotificationService::toUser((int) $row['user_id'], 'security.password_reset_completed', 'Your password was reset',
            'Your password was reset with a recovery link and all sessions were ended.', 'info', 'user', (int) $row['user_id'], (int) $row['user_id']);
        NotificationService::toPermission('login_activity.view', 'security.password_reset_completed', 'Password reset completed',
            "The password of {$row['username']} was reset with a recovery link.", 'info', 'user', (int) $row['user_id'], (int) $row['user_id']);
    }

    private static function registerFailure(array $user, int $max, int $lockMinutes): void
    {
        $since = Database::fetchOne('SELECT MAX(attempted_at) m FROM login_attempts WHERE user_id = ? AND success = 1', [$user['id']])['m'] ?? '1970-01-02 00:00:00';
        $fails = (int) Database::fetchOne(
            "SELECT COUNT(*) c FROM login_attempts WHERE user_id = ? AND success = 0 AND attempted_at > GREATEST(?, DATE_SUB(NOW(), INTERVAL ? MINUTE))",
            [$user['id'], $since, $lockMinutes]
        )['c'];
        if ($fails >= $max) {
            Database::execute('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?', [$lockMinutes, $user['id']]);
            AuditService::log('security.account_locked', 'user', (int) $user['id'], null, ['failed_attempts' => $fails], (int) $user['id']);
            NotificationService::toPermission('login_activity.view', 'security.lockout', 'Account locked',
                "User {$user['username']} was locked after $fails failed sign-in attempts.", 'warning', 'user', (int) $user['id']);
        }
    }

    private static function record(string $login, ?int $userId, bool $success, ?string $reason): void
    {
        Database::execute(
            'INSERT INTO login_attempts (username, user_id, ip_address, user_agent, success, reason) VALUES (?,?,?,?,?,?)',
            [mb_substr($login, 0, 190), $userId, Context::get('ip', '0.0.0.0'), Context::get('ua'), $success ? 1 : 0, $reason]
        );
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }
}
