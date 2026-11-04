<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};
use App\Validators\Validator;

final class RoleService
{
    public static function listRoles(): array
    {
        $roles = Database::fetchAll(
            'SELECT r.id, r.code, r.name, r.description, r.is_system, r.department_id, d.name AS department,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
             FROM roles r LEFT JOIN departments d ON d.id = r.department_id ORDER BY r.is_system DESC, r.name'
        );
        $map = [];
        foreach (Database::fetchAll('SELECT role_id, permission_id FROM role_permissions') as $rp) {
            $map[$rp['role_id']][] = (int) $rp['permission_id'];
        }
        foreach ($roles as &$r) { $r['permission_ids'] = $map[$r['id']] ?? []; }
        return $roles;
    }

    public static function listPermissions(): array
    {
        return Database::fetchAll('SELECT id, code, name, module FROM permissions ORDER BY module, code');
    }

    public static function create(array $in, int $actor): array
    {
        $d = Validator::validate($in, ['code' => 'required|string|min:3|max:50|regex:/^[a-z][a-z0-9_]*$/', 'name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'department_id' => 'nullable|int']);
        if ($d['department_id'] && !Database::fetchOne('SELECT 1 x FROM departments WHERE id = ?', [$d['department_id']])) {
            throw HttpException::unprocessable(['department_id' => 'Department does not exist.']);
        }
        try {
            $id = Database::transaction(function () use ($d, $actor) {
                $id = Database::insert('INSERT INTO roles (code, name, description, department_id, is_system) VALUES (?,?,?,?,0)', [$d['code'], $d['name'], $d['description'], $d['department_id']]);
                AuditService::log('role.create', 'role', $id, null, $d, $actor);
                return $id;
            });
        } catch (\PDOException $e) {
            throw HttpException::unprocessable(['code' => 'That role code already exists.']);
        }
        NotificationService::toPermission('role.manage', 'role.created', 'Role created',
            "The role \"{$d['name']}\" ({$d['code']}) was created.", 'info', 'role', $id, $actor);
        return self::find($id);
    }

    public static function update(int $id, array $in, int $actor): array
    {
        $old = self::find($id);
        $d = Validator::validate($in, ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255']);
        Database::transaction(function () use ($id, $d, $old, $actor) {
            Database::execute('UPDATE roles SET name = ?, description = ? WHERE id = ?', [$d['name'], $d['description'], $id]);
            AuditService::log('role.update', 'role', $id, ['name' => $old['name'], 'description' => $old['description']], $d, $actor);
        });
        NotificationService::toPermission('role.manage', 'role.updated', 'Role updated',
            "The role \"{$old['name']}\" was renamed to \"{$d['name']}\".", 'info', 'role', $id, $actor);
        return self::find($id);
    }

    public static function delete(int $id, int $actor): void
    {
        $r = self::find($id);
        if ((int) $r['is_system'] === 1) { throw HttpException::conflict('Built-in roles cannot be deleted.'); }
        if ((int) $r['user_count'] > 0)  { throw HttpException::conflict('Remove this role from all employees before deleting it.'); }
        Database::transaction(function () use ($id, $r, $actor) {
            Database::execute('DELETE FROM roles WHERE id = ?', [$id]);
            AuditService::log('role.delete', 'role', $id, ['code' => $r['code'], 'name' => $r['name']], null, $actor);
        });
        NotificationService::toPermission('role.manage', 'role.deleted', 'Role deleted',
            "The role \"{$r['name']}\" ({$r['code']}) was deleted.", 'warning', 'role', $id, $actor);
    }

    public static function setPermissions(int $id, array $permissionIds, int $actor): array
    {
        $role = self::find($id);
        $ids = array_values(array_unique(array_filter(array_map('intval', $permissionIds), fn($x) => $x > 0)));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ((int) Database::fetchOne("SELECT COUNT(*) c FROM permissions WHERE id IN ($in)", $ids)['c'] !== count($ids)) {
                throw HttpException::unprocessable(['permission_ids' => 'One or more permissions do not exist.']);
            }
        }
        $old = array_map('intval', array_column(Database::fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$id]), 'permission_id'));
        // Keep the administrator able to administer: the admin role may never lose its core access-control permissions.
        if ($role['code'] === 'admin') {
            $need = array_column(Database::fetchAll("SELECT id FROM permissions WHERE code IN ('user.manage','role.manage','settings.manage')"), 'id');
            foreach ($need as $n) {
                if (!in_array((int) $n, $ids, true)) { throw HttpException::conflict('The System Administrator role must keep user, role and settings management.'); }
            }
        }
        Database::transaction(function () use ($id, $ids, $old, $actor) {
            Database::execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
            foreach ($ids as $pid) { Database::execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)', [$id, $pid]); }
            AuditService::log('role.permissions_changed', 'role', $id, ['permission_ids' => $old], ['permission_ids' => $ids,
                'added' => array_values(array_diff($ids, $old)), 'removed' => array_values(array_diff($old, $ids))], $actor);
        });
        $added = count(array_diff($ids, $old));
        $removed = count(array_diff($old, $ids));
        NotificationService::toPermission('role.manage', 'role.permissions_changed', 'Role permissions changed',
            "The role \"{$role['name']}\" now has $added new and $removed fewer permissions.", 'warning', 'role', $id, $actor);
        return self::find($id);
    }

    public static function find(int $id): array
    {
        return Database::fetchOne('SELECT r.id, r.code, r.name, r.description, r.is_system, r.department_id, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count FROM roles r WHERE r.id = ?', [$id])
            ?? throw HttpException::notFound('Role not found.');
    }
}
