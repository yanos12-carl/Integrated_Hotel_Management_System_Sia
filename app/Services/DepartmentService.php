<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};
use App\Validators\Validator;

/** The hotel has exactly nine departments: they can be edited or deactivated but never created or deleted here. */
final class DepartmentService
{
    public static function list(): array
    {
        return Database::fetchAll(
            "SELECT d.id, d.code, d.name, d.description, d.is_active,
                    (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS user_count,
                    (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.status = 'active') AS active_user_count
             FROM departments d ORDER BY d.id"
        );
    }

    public static function update(int $id, array $in, int $actor): array
    {
        $old = Database::fetchOne('SELECT * FROM departments WHERE id = ?', [$id]) ?? throw HttpException::notFound('Department not found.');
        $d = Validator::validate($in, ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'is_active' => 'required|bool']);
        if (!$d['is_active'] && $old['code'] === 'ADMIN') {
            throw HttpException::conflict('The Admin department cannot be deactivated.');
        }
        if (!$d['is_active']) {
            $n = (int) Database::fetchOne("SELECT COUNT(*) c FROM users WHERE department_id = ? AND status = 'active'", [$id])['c'];
            if ($n > 0) { throw HttpException::conflict("Deactivate or move the $n active employee(s) first."); }
        }
        try {
            Database::transaction(function () use ($id, $d, $old, $actor) {
                Database::execute('UPDATE departments SET name = ?, description = ?, is_active = ? WHERE id = ?', [$d['name'], $d['description'], $d['is_active'] ? 1 : 0, $id]);
                AuditService::log('department.update', 'department', $id,
                    ['name' => $old['name'], 'description' => $old['description'], 'is_active' => (int) $old['is_active']],
                    ['name' => $d['name'], 'description' => $d['description'], 'is_active' => $d['is_active'] ? 1 : 0], $actor);
            });
        } catch (\PDOException $e) {
            throw HttpException::unprocessable(['name' => 'Another department already uses that name.']);
        }
        NotificationService::toPermission('department.manage', 'department.updated', 'Department updated',
            "The department \"{$d['name']}\" was updated.", 'info', 'department', $id, $actor);
        if ((int) $old['is_active'] === 1 && !$d['is_active']) {
            NotificationService::toPermission('user.manage', 'department.deactivated', 'Department deactivated',
                "The department \"{$d['name']}\" was deactivated and can no longer receive employees.", 'warning', 'department', $id, $actor);
        }
        return array_values(array_filter(self::list(), fn($r) => (int) $r['id'] === $id))[0];
    }
}
