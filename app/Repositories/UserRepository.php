<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    private const COLS = 'u.id, u.department_id, u.employee_no, u.username, u.email, u.password_hash, u.first_name, u.last_name, u.phone, u.avatar_path, u.status, u.must_change_password, u.locked_until, u.last_login_at, u.created_at';

    public static function findById(int $id): ?array
    {
        return Database::fetchOne('SELECT ' . self::COLS . ', (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked FROM users u WHERE u.id = ?', [$id]);
    }

    public static function findByLogin(string $login): ?array
    {
        return Database::fetchOne('SELECT ' . self::COLS . ', (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked FROM users u WHERE u.username = ? OR u.email = ? LIMIT 1', [$login, $login]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne('SELECT ' . self::COLS . ' FROM users u WHERE u.email = ? LIMIT 1', [$email]);
    }

    /** @return string[] permission codes from every role the user holds */
    public static function permissionsFor(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT DISTINCT p.code FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ? ORDER BY p.code',
            [$userId]
        );
        return array_column($rows, 'code');

    }

    public static function rolesFor(int $userId): array
    {
        return Database::fetchAll('SELECT r.id, r.code, r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.name', [$userId]);
    }

    public static function publicView(array $u): array
    {
        unset($u['password_hash']);
        return $u;
    }

    /** Active, unlocked admins that hold the given role code (used for last-admin protection). */
    public static function countActiveWithRole(string $roleCode, ?int $excludeUserId = null): int
    {
        return (int) Database::fetchOne(
            "SELECT COUNT(DISTINCT u.id) c FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
             WHERE r.code = ? AND u.status = 'active' AND u.id <> ?",
            [$roleCode, $excludeUserId ?? 0]
        )['c'];
    }
}
