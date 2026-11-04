<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};

final class SettingService
{
    private static ?array $cache = null;

    /** key => [type, validation rule summary] used by update() */
    private const RULES = [
        'hotel.name' => 'text', 'hotel.currency' => 'currency',
        'hotel.check_in_time' => 'time', 'hotel.check_out_time' => 'time',
        'billing.vat_rate' => 'rate', 'billing.service_charge' => 'rate',
        'security.session_minutes' => 'int:5:480', 'security.max_login_attempts' => 'int:3:20', 'security.lockout_minutes' => 'int:1:1440',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT setting_key, setting_value, value_type, description, updated_at FROM system_settings ORDER BY setting_key') as $r) {
                self::$cache[$r['setting_key']] = $r;
            }
        }
        return self::$cache;
    }

    public static function flush(): void { self::$cache = null; }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key]['setting_value'] ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        $v = self::get($key);
        return $v !== null && is_numeric($v) ? (int) $v : $default;
    }

    public static function update(array $values, int $actorId): array
    {
        $errors = [];
        $clean  = [];
        foreach ($values as $key => $raw) {
            $rule = self::RULES[$key] ?? null;
            if ($rule === null || !isset(self::all()[$key])) {
                $errors[$key] = 'Unknown setting.';
                continue;
            }
            $v = trim((string) $raw);
            [$kind, $min, $max] = array_pad(explode(':', $rule), 3, null);
            $ok = match ($kind) {
                'text'     => $v !== '' && mb_strlen($v) <= 120,
                'currency' => (bool) preg_match('/^[A-Z]{3}$/', $v),
                'time'     => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v),
                'rate'     => is_numeric($v) && (float) $v >= 0 && (float) $v <= 1,
                'int'      => filter_var($v, FILTER_VALIDATE_INT) !== false && (int) $v >= (int) $min && (int) $v <= (int) $max,
            };
            if (!$ok) {
                $errors[$key] = 'Invalid value.';
                continue;
            }
            $clean[$key] = $v;
        }
        if ($errors) {
            throw HttpException::unprocessable($errors);
        }
        $changed = [];
        Database::transaction(function () use ($clean, $actorId, &$changed) {
            foreach ($clean as $key => $v) {
                $old = self::all()[$key]['setting_value'] ?? null;
                if ($old === $v) { continue; }
                Database::execute('UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?', [$v, $actorId, $key]);
                AuditService::log('settings.update', 'system_setting', null, [$key => $old], [$key => $v], $actorId);
                $changed[$key] = $v;
            }
        });
        self::flush();
        if ($changed) {
            $keys = implode(', ', array_keys($changed));
            NotificationService::toPermission('settings.manage', 'settings.updated', 'System settings changed',
            count($changed) === 1 ? "The setting {$keys} was changed." : count($changed) . ' settings were changed: ' . $keys . '.', 'info', null, null, $actorId);
        }
        return self::all();
    }
}
