<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};
use App\Repositories\UserRepository;
use App\Validators\{PasswordPolicy, Validator};

final class UserService
{
    public static function list(array $f, string $order, int $limit, int $offset): array
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['q'])) {
            $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_no LIKE ?)';
            $like = '%' . addcslashes((string) $f['q'], '%_') . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        if (!empty($f['department_id'])) { $where[] = 'u.department_id = ?'; $p[] = (int) $f['department_id']; }
        if (!empty($f['status']) && in_array($f['status'], ['active', 'inactive', 'locked'], true)) {
            $where[] = $f['status'] === 'locked' ? "(u.locked_until IS NOT NULL AND u.locked_until > NOW())" : 'u.status = ?';
            if ($f['status'] !== 'locked') { $p[] = $f['status']; }
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM users u WHERE $w", $p)['c'];
        $rows = Database::fetchAll(
            "SELECT u.id, u.employee_no, u.username, u.email, u.first_name, u.last_name, u.phone, u.status, u.department_id, d.name AS department,
                    (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked, u.last_login_at, u.created_at,
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id) AS roles,
                    (SELECT GROUP_CONCAT(ur.role_id) FROM user_roles ur WHERE ur.user_id = u.id) AS role_ids
             FROM users u JOIN departments d ON d.id = u.department_id WHERE $w ORDER BY $order LIMIT ? OFFSET ?",
            array_merge($p, [$limit, $offset])
        );
        return [$rows, $total];
    }

    public static function get(int $id): array
    {
        $u = UserRepository::findById($id) ?? throw HttpException::notFound('User not found.');
        $u = UserRepository::publicView($u);
        $u['roles'] = UserRepository::rolesFor($id);
        $u['extra_department_ids'] = array_map('intval', array_column(Database::fetchAll('SELECT department_id FROM user_departments WHERE user_id = ?', [$id]), 'department_id'));
        return $u;
    }

    public static function create(array $in, int $actorId): array
    {
        $d = Validator::validate($in, [
            'username' => 'required|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/',
            'email' => 'required|email|max:190', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:30', 'job_title' => 'nullable|string|max:100',
            'department_id' => 'required|int', 'role_ids' => 'nullable|array', 'extra_department_ids' => 'nullable|array',
        ]);
        self::assertDepartment($d['department_id']);
        $roleIds = self::cleanIds($d['role_ids']);
        self::assertRolesAllowed($roleIds, $d['department_id'], self::cleanIds($d['extra_department_ids']));
        $temp = PasswordPolicy::generate();

        $id = Database::transaction(function () use ($d, $roleIds, $temp, $actorId) {
            try {
                $id = Database::insert(
                    'INSERT INTO users (department_id, employee_no, username, email, password_hash, first_name, last_name, phone, job_title, status, must_change_password, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)',
                    [$d['department_id'], 'TMP-' . bin2hex(random_bytes(6)), $d['username'], $d['email'], password_hash($temp, PASSWORD_DEFAULT), $d['first_name'], $d['last_name'], $d['phone'], $d['job_title'], 'active', $actorId]
                );
            } catch (\PDOException $e) {
                throw self::duplicate($e);
            }
            Database::execute('UPDATE users SET employee_no = ? WHERE id = ?', [sprintf('EMP-%05d', $id), $id]);
            self::syncRoles($id, $roleIds, $actorId);
            self::syncExtraDepartments($id, self::cleanIds($d['extra_department_ids']), $d['department_id']);
            AuditService::log('user.create', 'user', $id, null, ['username' => $d['username'], 'email' => $d['email'], 'department_id' => $d['department_id'], 'role_ids' => $roleIds], $actorId);
            return $id;
        });
        $user = self::get($id);
        NotificationService::toUser($id, 'user.account_created', 'Your account is ready',
            "Your IHMS account {$d['username']} was created. Sign in with the temporary password you were given and change it at first sign-in.",
            'info', 'user', $id, $actorId);
        NotificationService::toPermission('user.manage', 'user.created', 'New employee account',
            "Account {$d['username']} ({$d['email']}) was created in {$d['department_id']}.", 'info', 'user', $id, $actorId);
        return ['user' => $user, 'temporary_password' => $temp];   // shown once; only the hash is stored
    }

    public static function requestAccount(array $in, int $actorId): int
    {
        $d = Validator::validate($in, [
            'username' => 'required|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/',
            'email' => 'required|email|max:190', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:30', 'job_title' => 'nullable|string|max:100',
        ]);
        $requester = Database::fetchOne(
            'SELECT u.department_id, d.name AS department, d.is_active FROM users u JOIN departments d ON d.id = u.department_id WHERE u.id = ?',
            [$actorId]
        );
        if (!$requester || (int) $requester['is_active'] !== 1) {
            throw HttpException::unprocessable(['department' => 'Your department is inactive and cannot request accounts.']);
        }
        foreach (['username' => 'username', 'email' => 'email'] as $field => $column) {
            if (Database::fetchOne("SELECT id FROM users WHERE $column = ?", [$d[$field]])) {
                throw HttpException::unprocessable([$field => ucfirst($field) . ' is already in use.']);
            }
            if (Database::fetchOne(
                "SELECT id FROM approval_requests WHERE request_type = 'user.create' AND status = 'pending'
                 AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.$field')) = ? LIMIT 1",
                [$d[$field]]
            )) {
                throw HttpException::unprocessable([$field => 'A pending account request already uses this value.']);
            }
        }
        $payload = $d + [
            'department_id' => (int) $requester['department_id'],
            'department_name' => $requester['department'],
        ];
        $id = ApprovalService::request(
            'user.create',
            'account_request',
            $actorId,
            'user.manage',
            $actorId,
            'New employee account requested: ' . $d['first_name'] . ' ' . $d['last_name'],
            $payload
        );
        NotificationService::toUser(
            $actorId,
            'user.account_request_pending',
            'Account request submitted',
            "Your request to add {$d['first_name']} {$d['last_name']} is waiting for administrator approval.",
            'info',
            'approval_request',
            $id,
            $actorId
        );
        return $id;
    }

    public static function approveAccountRequest(array $request, int $actorId): array
    {
        $role = Database::fetchOne(
            "SELECT id FROM roles WHERE department_id = ? AND code <> 'admin' ORDER BY is_system DESC, id LIMIT 1",
            [(int) $request['department_id']]
        );
        return self::create([
            'username' => $request['username'],
            'email' => $request['email'],
            'first_name' => $request['first_name'],
            'last_name' => $request['last_name'],
            'phone' => $request['phone'] ?? null,
            'job_title' => $request['job_title'] ?? null,
            'department_id' => (int) $request['department_id'],
            'role_ids' => $role ? [(int) $role['id']] : [],
        ], $actorId);
    }

    public static function update(int $id, array $in, int $actorId): array
    {
        $old = self::get($id);
        $d = Validator::validate($in, [
            'email' => 'required|email|max:190', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:30', 'department_id' => 'required|int', 'extra_department_ids' => 'nullable|array',
        ]);
        self::assertDepartment($d['department_id']);
        $extra = self::cleanIds($d['extra_department_ids']);
        self::assertRolesAllowed(array_map(fn($r) => (int) $r['id'], $old['roles']), $d['department_id'], $extra);
        Database::transaction(function () use ($id, $d, $old, $extra, $actorId) {
            try {
                Database::execute('UPDATE users SET email=?, first_name=?, last_name=?, phone=?, department_id=? WHERE id=?', [$d['email'], $d['first_name'], $d['last_name'], $d['phone'], $d['department_id'], $id]);
            } catch (\PDOException $e) { throw self::duplicate($e); }
            self::syncExtraDepartments($id, $extra, $d['department_id']);
            AuditService::log('user.update', 'user', $id,
                ['email' => $old['email'], 'first_name' => $old['first_name'], 'last_name' => $old['last_name'], 'phone' => $old['phone'], 'department_id' => (int) $old['department_id']],
                ['email' => $d['email'], 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'phone' => $d['phone'], 'department_id' => $d['department_id']], $actorId);
        });
        NotificationService::toUser($id, 'user.profile_updated', 'Your details were updated',
            'An administrator updated your name, contact details or department. Sign in again if anything looks wrong.',
            'info', 'user', $id, $actorId);
        NotificationService::toPermission('user.manage', 'user.updated', 'Employee details updated',
            "The profile of {$old['username']} was updated.", 'info', 'user', $id, $actorId);
        return self::get($id);
    }

    /** action: activate | deactivate | unlock */
    public static function changeStatus(int $id, string $action, int $actorId): array
    {
        if (!in_array($action, ['activate', 'deactivate', 'unlock'], true)) {
            throw HttpException::unprocessable(['action' => 'Action must be activate, deactivate or unlock.']);
        }
        $u = self::get($id);
        if ($action === 'deactivate') {
            if ($id === $actorId) { throw HttpException::conflict('You cannot deactivate your own account.'); }
            self::assertNotLastAdmin($id);
        }
        Database::transaction(function () use ($id, $action, $u, $actorId) {
            if ($action === 'deactivate') {
                Database::execute("UPDATE users SET status='inactive' WHERE id=?", [$id]);
                Database::execute('DELETE FROM sessions WHERE user_id = ?', [$id]);   // ends active sessions immediately
            } else {
                Database::execute("UPDATE users SET status='active', locked_until=NULL WHERE id=?", [$id]);
            }
            AuditService::log('user.' . $action, 'user', $id, ['status' => $u['status']], ['action' => $action], $actorId);
        });
        $self = [
            'activate'  => ['Your account was reactivated', 'An administrator reactivated your IHMS account. You can sign in again.', 'info'],
            'deactivate' => ['Your account was deactivated', 'An administrator deactivated your IHMS account and ended your sessions. Contact the front office if you need access.', 'warning'],
            'unlock'    => ['Your account was unlocked', 'Your account was unlocked after too many failed sign-in attempts. You can sign in again.', 'info'],
        ][$action];
        $title = ['activate' => 'Account activated', 'deactivate' => 'Account deactivated', 'unlock' => 'Account unlocked'][$action];
        $verb  = ['activate' => 'activated', 'deactivate' => 'deactivated', 'unlock' => 'unlocked'][$action];
        NotificationService::toUser($id, 'user.' . $action, $self[0], $self[1], $self[2], 'user', $id, $actorId);
        NotificationService::toPermission('user.manage', 'user.' . $action, $title,
            "The account of {$u['username']} was $verb.", $action === 'deactivate' ? 'warning' : 'info', 'user', $id, $actorId);
        return self::get($id);
    }

    public static function resetPassword(int $id, int $actorId): array
    {
        $u = self::get($id);
        $temp = PasswordPolicy::generate();
        Database::transaction(function () use ($id, $temp, $actorId) {
            Database::execute('UPDATE users SET password_hash=?, must_change_password=1, locked_until=NULL WHERE id=?', [password_hash($temp, PASSWORD_DEFAULT), $id]);
            Database::execute('DELETE FROM sessions WHERE user_id = ?', [$id]);
            AuditService::log('user.password_reset_by_admin', 'user', $id, null, null, $actorId);
        });
        NotificationService::toUser($id, 'user.password_reset', 'Your password was reset',
            'An administrator reset your password and ended your sessions. Sign in with the temporary password and change it immediately.',
            'warning', 'user', $id, $actorId);
        NotificationService::toPermission('login_activity.view', 'user.password_reset_by_admin', 'Password reset for an account',
            "An administrator reset the password of {$u['username']}; their sessions were ended.", 'warning', 'user', $id, $actorId);
        return ['temporary_password' => $temp];
    }

    public static function setRoles(int $id, array $roleIds, int $actorId): array
    {
        $u = self::get($id);
        $roleIds = self::cleanIds($roleIds);
        self::assertRolesAllowed($roleIds, (int) $u['department_id'], $u['extra_department_ids']);
        $oldIds = array_map(fn($r) => (int) $r['id'], $u['roles']);
        Database::transaction(function () use ($id, $roleIds, $oldIds, $actorId) {
            self::syncRoles($id, $roleIds, $actorId);
            if (UserRepository::countActiveWithRole('admin') === 0) {
                throw HttpException::conflict('At least one active System Administrator must remain.');
            }
            AuditService::log('user.roles_changed', 'user', $id, ['role_ids' => $oldIds], ['role_ids' => $roleIds], $actorId);
        });
        NotificationService::toUser($id, 'user.roles_changed', 'Your access changed',
            'An administrator updated the roles assigned to you. Sign in again to pick up your new permissions.',
            'info', 'user', $id, $actorId);
        NotificationService::toPermission('role.manage', 'user.roles_changed', 'Roles reassigned',
            "The roles of {$u['username']} were updated.", 'info', 'user', $id, $actorId);
        return self::get($id);
    }

    private static function syncRoles(int $id, array $roleIds, int $actorId): void
    {
        Database::execute('DELETE FROM user_roles WHERE user_id = ?', [$id]);
        foreach ($roleIds as $rid) {
            Database::execute('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?,?,?)', [$id, $rid, $actorId]);
        }
    }

    private static function syncExtraDepartments(int $id, array $extra, int $primary): void
    {
        Database::execute('DELETE FROM user_departments WHERE user_id = ?', [$id]);
        foreach ($extra as $did) {
            if ($did !== $primary) { Database::execute('INSERT INTO user_departments (user_id, department_id) VALUES (?,?)', [$id, $did]); }
        }
    }

    private static function assertNotLastAdmin(int $id): void
    {
        $isAdmin = Database::fetchOne("SELECT 1 x FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code = 'admin'", [$id]);
        if ($isAdmin && UserRepository::countActiveWithRole('admin', $id) === 0) {
            throw HttpException::conflict('At least one active System Administrator must remain.');
        }
    }

    private static function assertDepartment(int $deptId): void
    {
        $d = Database::fetchOne('SELECT is_active FROM departments WHERE id = ?', [$deptId]);
        if (!$d || (int) $d['is_active'] !== 1) {
            throw HttpException::unprocessable(['department_id' => 'Choose an active department.']);
        }
    }

    /** A department-scoped role may only go to users who belong to that department. */
    private static function assertRolesAllowed(array $roleIds, int $primaryDept, array $extraDepts): void
    {
        if (!$roleIds) { return; }
        $in = implode(',', array_fill(0, count($roleIds), '?'));
        $rows = Database::fetchAll("SELECT id, name, department_id FROM roles WHERE id IN ($in)", $roleIds);
        if (count($rows) !== count($roleIds)) {
            throw HttpException::unprocessable(['role_ids' => 'One or more roles do not exist.']);
        }
        foreach ($rows as $r) {
            if ($r['department_id'] !== null && (int) $r['department_id'] !== $primaryDept && !in_array((int) $r['department_id'], $extraDepts, true)) {
                throw HttpException::unprocessable(['role_ids' => "Role '{$r['name']}' belongs to a different department than the employee."]);
            }
        }
    }

    private static function cleanIds(mixed $v): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $v), fn($x) => $x > 0)));
    }

    private static function duplicate(\PDOException $e): HttpException
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $field = str_contains($e->getMessage(), 'uq_users_email') ? 'email' : 'username';
            return HttpException::unprocessable([$field => ucfirst($field) . ' is already in use.']);
        }
        return new HttpException(500, 'Could not save the user.', [], $e);
    }
}
