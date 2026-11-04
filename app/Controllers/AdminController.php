<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Paginator, Request, Response};
use App\Services\{AnalyticsService, DatabaseOverviewService, AdminQueryService, ApprovalService, BackupService, DepartmentService, RoleService, SettingService, UserService};

final class AdminController extends BaseController
{
    public function summary(Request $r, array $p = []): Response { return Response::success(AdminQueryService::summary($this->uid($r))); }

    public function analytics(Request $r, array $p = []): Response
    {
        $days = AnalyticsService::range((int) ($r->query['days'] ?? 14));
        return Response::success(AnalyticsService::report($days));
    }

    // ---- Users
    public function users(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        $order = Paginator::order($r, ['name' => 'u.last_name', 'username' => 'u.username', 'department' => 'd.name', 'status' => 'u.status', 'last_login' => 'u.last_login_at', 'created' => 'u.id'], 'u.id', 'DESC');
        [$rows, $total] = UserService::list($r->query, $order, $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function userShow(Request $r, array $p = []): Response { return Response::success(UserService::get((int) $p['id'])); }
    public function userCreate(Request $r, array $p = []): Response { return Response::created(UserService::create($r->body, $this->uid($r)), 'Account created.'); }
    public function userUpdate(Request $r, array $p = []): Response { return Response::success(UserService::update((int) $p['id'], $r->body, $this->uid($r)), 'Account updated.'); }
    public function userStatus(Request $r, array $p = []): Response
    {
        $action = (string) ($r->body['action'] ?? '');
        $u = UserService::changeStatus((int) $p['id'], $action, $this->uid($r));
        $msg = ['activate' => 'Account activated.', 'deactivate' => 'Account deactivated.', 'unlock' => 'Account unlocked.'][$action] ?? 'Account updated.';
        return Response::success($u, $msg);
    }
    public function userResetPassword(Request $r, array $p = []): Response { return Response::success(UserService::resetPassword((int) $p['id'], $this->uid($r)), 'Temporary password generated.'); }
    public function userRoles(Request $r, array $p = []): Response { return Response::success(UserService::setRoles((int) $p['id'], (array) ($r->body['role_ids'] ?? []), $this->uid($r)), 'Roles updated.'); }

    // ---- Departments / roles / permissions / settings
    public function departments(Request $r, array $p = []): Response { return Response::success(DepartmentService::list()); }
    public function departmentUpdate(Request $r, array $p = []): Response { return Response::success(DepartmentService::update((int) $p['id'], $r->body, $this->uid($r)), 'Department updated.'); }
    public function roles(Request $r, array $p = []): Response { return Response::success(RoleService::listRoles()); }
    public function permissions(Request $r, array $p = []): Response { return Response::success(RoleService::listPermissions()); }
    public function roleCreate(Request $r, array $p = []): Response { return Response::created(RoleService::create($r->body, $this->uid($r)), 'Role created.'); }
    public function roleUpdate(Request $r, array $p = []): Response { return Response::success(RoleService::update((int) $p['id'], $r->body, $this->uid($r)), 'Role updated.'); }
    public function roleDelete(Request $r, array $p = []): Response { RoleService::delete((int) $p['id'], $this->uid($r)); return Response::success(null, 'Role deleted.'); }
    public function rolePermissions(Request $r, array $p = []): Response { return Response::success(RoleService::setPermissions((int) $p['id'], (array) ($r->body['permission_ids'] ?? []), $this->uid($r)), 'Permissions updated.'); }
    public function settings(Request $r, array $p = []): Response { return Response::success(array_values(SettingService::all())); }
    public function settingsUpdate(Request $r, array $p = []): Response { return Response::success(array_values(SettingService::update((array) ($r->body['settings'] ?? []), $this->uid($r))), 'Settings saved.'); }

    // ---- Monitoring
    public function audit(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 20);
        $order = Paginator::order($r, ['created' => 'a.id', 'action' => 'a.action', 'entity' => 'a.entity_type'], 'a.id', 'DESC');
        [$rows, $total] = AdminQueryService::audit($r->query, $order, $per, $off);
        foreach ($rows as &$row) {
            foreach (['old_values', 'new_values'] as $k) { $row[$k] = $row[$k] ? json_decode($row[$k], true) : null; }
        }
        return $this->paged($rows, $total, $page, $per);
    }
    public function logins(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 20);
        $order = Paginator::order($r, ['time' => 'l.id', 'username' => 'l.username', 'ip' => 'l.ip_address'], 'l.id', 'DESC');
        [$rows, $total] = AdminQueryService::logins($r->query, $order, $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function errors(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 20);
        [$rows, $total] = AdminQueryService::errors($r->query, $per, $off);
        foreach ($rows as &$row) { $row['context'] = $row['context'] ? json_decode($row['context'], true) : null; }
        return $this->paged($rows, $total, $page, $per);
    }
    public function sessions(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 20);
        [$rows, $total] = AdminQueryService::sessions($per, $off);
        return $this->paged($rows, $total, $page, $per);
    }
    public function sessionRevoke(Request $r, array $p = []): Response { AdminQueryService::revokeSession((string) $p['handle'], $this->uid($r)); return Response::success(null, 'Session revoked.'); }

    // ---- Approvals
    public function approvals(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r);
        [$rows, $total] = ApprovalService::list($r->query['status'] ?? null, $per, $off, $this->user($r)['permissions']);
        return $this->paged($rows, $total, $page, $per);
    }
    public function approvalDecide(Request $r, array $p = []): Response { return Response::success(ApprovalService::decide((int) $p['id'], $r->body, $this->uid($r), $this->user($r)['permissions']), 'Decision recorded.'); }
    public function accountRequestDecide(Request $r, array $p = []): Response { return Response::success(ApprovalService::decide((int) $p['id'], $r->body, $this->uid($r), $this->user($r)['permissions']), 'Decision recorded.'); }

    // ---- Central database
    public function database(Request $r, array $p = []): Response { return Response::success(DatabaseOverviewService::overview()); }
    public function databaseTable(Request $r, array $p = []): Response { return Response::success(DatabaseOverviewService::table((string) $p['name'])); }

    // ---- Backups
    public function backups(Request $r, array $p = []): Response { return Response::success(BackupService::list()); }
    public function backupCreate(Request $r, array $p = []): Response { return Response::created(BackupService::create($this->uid($r)), 'Backup created.'); }
    public function backupDownload(Request $r, array $p = []): Response
    {
        $path = BackupService::path((string) $p['name']);
        \App\Services\AuditService::log('backup.download', 'backup', null, null, ['file' => $p['name']], $this->uid($r));
        return Response::file($path, (string) $p['name']);
    }
}
