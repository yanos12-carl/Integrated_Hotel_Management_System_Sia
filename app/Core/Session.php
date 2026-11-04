<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\SettingService;

/** Secure, lazily-started session. Idle timeout comes from system_settings (security.session_minutes). */
final class Session
{
    public static function lifetimeSeconds(): int
    {
        try {
            return SettingService::int('security.session_minutes', (int) Config::get('app.session.lifetime', 30)) * 60;
        } catch (\Throwable) {
            return (int) Config::get('app.session.lifetime', 30) * 60;
        }
    }

    private static bool $configured = false;

    /** One-time PHP session setup (must happen before any output). Safe to call repeatedly. */
    public static function configure(): void
    {
        if (self::$configured) {
            return;
        }
        $cfg  = Config::get('app.session');
        $life = self::lifetimeSeconds();
        ini_set('session.cache_limiter', '');        // Response sets its own Cache-Control
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $life);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
        session_name($cfg['name']);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (bool) $cfg['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        if (PHP_SAPI === 'cli') {
            ini_set('session.use_cookies', '0');   // test runs carry the id manually
        }
        session_set_save_handler(new DatabaseSessionHandler($life), true);
        self::$configured = true;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        self::configure();
        session_start();
        $_SESSION['_last'] = time();
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Called after successful authentication: new id (anti-fixation) + new CSRF token. */
    public static function login(int $userId): void
    {
        self::start();
        if (PHP_SAPI === 'cli' && headers_sent()) {
            // CLI test runs have already produced output; emulate regeneration (new id, old row removed).
            $old = session_id();
            session_write_close();
            session_id(bin2hex(random_bytes(20)));
            session_start();
            Database::execute('DELETE FROM sessions WHERE id = ?', [$old]);
        } else {
            session_regenerate_id(true);
        }
        $_SESSION = ['user_id' => $userId, '_last' => time(), '_csrf' => bin2hex(random_bytes(32))];
        Context::set('user_id', $userId);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        Context::set('user_id', null);
    }
}
