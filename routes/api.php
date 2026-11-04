<?php
declare(strict_types=1);

use App\Controllers\{AccountRequestController, AdminController, AuthController, HealthController, NotificationController, SecurityController};
use App\Core\Router;

const AUTH = 'App\Middleware\Authenticate';
const CSRF = 'App\Middleware\VerifyCsrf';
function perm(string ...$c): string { return 'App\Middleware\Permission:' . implode(',', $c); }

/** All JSON API endpoints live under /api/v1. Every state-changing call requires a CSRF token. */
return static function (Router $router): void {
    $router->group('/api/v1', [], static function (Router $r): void {
        $r->get('/ping', [HealthController::class, 'ping']);
        $r->get('/health', [HealthController::class, 'health']);

        // Authentication
        $r->get('/auth/csrf', [AuthController::class, 'csrf']);
        $r->post('/auth/login', [AuthController::class, 'login'], [CSRF]);
        $r->post('/auth/forgot-password', [AuthController::class, 'forgot'], [CSRF]);
        $r->post('/auth/reset-password', [AuthController::class, 'reset'], [CSRF]);
        $r->get('/auth/me', [AuthController::class, 'me'], [AUTH]);
        $r->post('/auth/logout', [AuthController::class, 'logout'], [AUTH, CSRF]);
        $r->post('/auth/change-password', [AuthController::class, 'changePassword'], [AUTH, CSRF]);
        $r->post('/auth/avatar', [AuthController::class, 'avatar'], [AUTH, CSRF]);

        // Department staff may request an account; administrators create it only after approval.
        $r->post('/account-requests', [AccountRequestController::class, 'create'], [AUTH, CSRF]);

        // Notification centre (every signed-in user)
        $r->get('/notifications', [NotificationController::class, 'index'], [AUTH]);
        $r->get('/notifications/unread-count', [NotificationController::class, 'unreadCount'], [AUTH]);
        $r->post('/notifications/read-all', [NotificationController::class, 'readAll'], [AUTH, CSRF]);
        $r->post('/notifications/{id:\d+}/read', [NotificationController::class, 'read'], [AUTH, CSRF]);

        // Security workflows use the central IHMS database and the same session as every department.
        $r->group('/security', [AUTH], static function (Router $r): void {
            $r->get('/summary', [SecurityController::class, 'summary'], [perm('security.incident.view')]);
            $r->get('/users', [SecurityController::class, 'users'], [perm('security.incident.view')]);

            $r->get('/incidents', [SecurityController::class, 'incidents'], [perm('security.incident.view')]);
            $r->get('/incidents/{id:\d+}', [SecurityController::class, 'incidentShow'], [perm('security.incident.view')]);
            $r->post('/incidents', [SecurityController::class, 'incidentCreate'], [CSRF, perm('security.incident.manage')]);
            $r->post('/incidents/{id:\d+}/updates', [SecurityController::class, 'incidentUpdate'], [CSRF, perm('security.incident.manage')]);
            $r->post('/incidents/{id:\d+}/status', [SecurityController::class, 'incidentStatus'], [CSRF, perm('security.incident.manage')]);
            $r->post('/incidents/{id:\d+}/assign', [SecurityController::class, 'incidentAssign'], [CSRF, perm('security.incident.manage')]);

            $r->get('/cctv-references', [SecurityController::class, 'cctvReferences'], [perm('security.evidence.view')]);
            $r->post('/cctv-references', [SecurityController::class, 'cctvCreate'], [CSRF, perm('security.incident.manage')]);
            $r->get('/cameras', [SecurityController::class, 'cameras'], [perm('security.evidence.view')]);
            $r->post('/cameras', [SecurityController::class, 'cameraSave'], [CSRF, perm('security.camera.manage')]);
            $r->delete('/cameras/{id:\d+}', [SecurityController::class, 'cameraRemove'], [CSRF, perm('security.camera.manage')]);

            $r->get('/visitors', [SecurityController::class, 'visitors'], [perm('visitor.manage')]);
            $r->post('/visitors', [SecurityController::class, 'visitorCreate'], [CSRF, perm('visitor.manage')]);
            $r->post('/visitors/{id:\d+}/checkout', [SecurityController::class, 'visitorCheckout'], [CSRF, perm('visitor.manage')]);

            $r->get('/shifts', [SecurityController::class, 'shifts'], [perm('patrol.manage')]);
            $r->post('/shifts', [SecurityController::class, 'shiftCreate'], [CSRF, perm('patrol.manage')]);
            $r->post('/shifts/{id:\d+}/status', [SecurityController::class, 'shiftStatus'], [CSRF, perm('patrol.manage')]);

            $r->get('/handovers', [SecurityController::class, 'handovers'], [perm('patrol.manage')]);
            $r->post('/handovers', [SecurityController::class, 'handoverCreate'], [CSRF, perm('patrol.manage')]);
            $r->post('/handovers/{id:\d+}/acknowledge', [SecurityController::class, 'handoverAcknowledge'], [CSRF, perm('patrol.manage')]);

            $r->get('/patrol-schedules', [SecurityController::class, 'patrolSchedules'], [perm('patrol.manage')]);
            $r->post('/patrol-schedules', [SecurityController::class, 'patrolScheduleCreate'], [CSRF, perm('patrol.manage')]);
            $r->get('/checkpoints', [SecurityController::class, 'checkpoints'], [perm('patrol.manage')]);
            $r->post('/checkpoints', [SecurityController::class, 'checkpointCreate'], [CSRF, perm('patrol.manage')]);
            $r->get('/patrol-records', [SecurityController::class, 'patrolRecords'], [perm('patrol.manage')]);
            $r->post('/patrol-records', [SecurityController::class, 'patrolRecordCreate'], [CSRF, perm('patrol.manage')]);

            $r->get('/inspections', [SecurityController::class, 'inspections'], [perm('patrol.manage')]);
            $r->post('/inspections', [SecurityController::class, 'inspectionCreate'], [CSRF, perm('patrol.manage')]);
        });

        // Approvals
        $r->get('/approvals', [AdminController::class, 'approvals'], [AUTH, perm('approval.view')]);
        $r->post('/approvals/{id:\d+}/decide', [AdminController::class, 'approvalDecide'], [AUTH, CSRF, perm('approval.decide')]);

        // Admin
        $r->group('/admin', [AUTH], static function (Router $r): void {
            $r->get('/summary', [AdminController::class, 'summary'], [perm('admin.dashboard')]);
            $r->get('/analytics', [AdminController::class, 'analytics'], [perm('admin.dashboard')]);

            $r->post('/account-requests/{id:\d+}/decide', [AdminController::class, 'accountRequestDecide'], [CSRF, perm('user.manage')]);
            $r->get('/users', [AdminController::class, 'users'], [perm('user.view')]);
            $r->post('/users', [AdminController::class, 'userCreate'], [CSRF, perm('user.manage', 'admin.dashboard')]);
            $r->get('/users/{id:\d+}', [AdminController::class, 'userShow'], [perm('user.view')]);
            $r->put('/users/{id:\d+}', [AdminController::class, 'userUpdate'], [CSRF, perm('user.manage')]);
            $r->post('/users/{id:\d+}/status', [AdminController::class, 'userStatus'], [CSRF, perm('user.manage')]);
            $r->post('/users/{id:\d+}/reset-password', [AdminController::class, 'userResetPassword'], [CSRF, perm('user.manage')]);
            $r->put('/users/{id:\d+}/roles', [AdminController::class, 'userRoles'], [CSRF, perm('user.manage', 'role.view')]);

            $r->get('/departments', [AdminController::class, 'departments'], [perm('department.view')]);
            $r->put('/departments/{id:\d+}', [AdminController::class, 'departmentUpdate'], [CSRF, perm('department.manage')]);

            $r->get('/roles', [AdminController::class, 'roles'], [perm('role.view')]);
            $r->post('/roles', [AdminController::class, 'roleCreate'], [CSRF, perm('role.manage')]);
            $r->put('/roles/{id:\d+}', [AdminController::class, 'roleUpdate'], [CSRF, perm('role.manage')]);
            $r->delete('/roles/{id:\d+}', [AdminController::class, 'roleDelete'], [CSRF, perm('role.manage')]);
            $r->put('/roles/{id:\d+}/permissions', [AdminController::class, 'rolePermissions'], [CSRF, perm('role.manage')]);
            $r->get('/permissions', [AdminController::class, 'permissions'], [perm('role.view')]);

            $r->get('/settings', [AdminController::class, 'settings'], [perm('settings.view')]);
            $r->put('/settings', [AdminController::class, 'settingsUpdate'], [CSRF, perm('settings.manage')]);

            $r->get('/audit-logs', [AdminController::class, 'audit'], [perm('audit.view')]);
            $r->get('/login-attempts', [AdminController::class, 'logins'], [perm('login_activity.view')]);
            $r->get('/error-logs', [AdminController::class, 'errors'], [perm('error_log.view')]);
            $r->get('/sessions', [AdminController::class, 'sessions'], [perm('session.manage')]);
            $r->delete('/sessions/{handle:[a-f0-9]+}', [AdminController::class, 'sessionRevoke'], [CSRF, perm('session.manage')]);

            $r->get('/notifications', [NotificationController::class, 'adminIndex'], [perm('notification.manage')]);
            $r->post('/notifications/broadcast', [NotificationController::class, 'broadcast'], [CSRF, perm('notification.manage')]);

            $r->get('/database', [AdminController::class, 'database'], [perm('database.view')]);
            $r->get('/database/tables/{name:[a-z_0-9]+}', [AdminController::class, 'databaseTable'], [perm('database.view')]);

            $r->get('/backups', [AdminController::class, 'backups'], [perm('backup.manage')]);
            $r->post('/backups', [AdminController::class, 'backupCreate'], [CSRF, perm('backup.manage')]);
            $r->get('/backups/{name}/download', [AdminController::class, 'backupDownload'], [perm('backup.manage')]);
        });
    });
};
