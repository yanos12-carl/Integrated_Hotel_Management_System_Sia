<?php
declare(strict_types=1);

/**
 * Notification coverage: every administrative process must leave a notification behind,
 * both for the employee it affects and for the staff who are allowed to act on it.
 */
use App\Core\Database;
use App\Services\NotificationService;

echo "Notifications\n";

if (!$dbUp || !$isTesting) { test('Notification tests', fn() => 'skip'); return; }

/** True when $userId has an unread-or-read notification of the given type. */
function notificationExists(string $type, int $userId): bool
{
    return (int) Database::fetchOne(
        'SELECT COUNT(*) c FROM notifications n JOIN notification_recipients nr ON nr.notification_id = n.id WHERE n.type = ? AND nr.user_id = ?',
        [$type, $userId]
    )['c'] > 0;
}

function notificationsFor(string $type): int
{
    return (int) Database::fetchOne('SELECT COUNT(*) c FROM notifications WHERE type = ?', [$type])['c'];
}

test('Creating an account notifies the new employee and the account managers', function () use ($router) {
    scoped(function () use ($router) {
        $adminId = mkUser('noteadmin', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('noteadmin', 'Str0ngPassw0rd');
        $dept = (int) Database::fetchOne("SELECT id FROM departments WHERE code='FRONT_OFFICE'")['id'];
        [$st, $b] = $c->call('POST', '/api/v1/admin/users', ['username' => 'notified', 'email' => 'notified@test.local', 'first_name' => 'Not', 'last_name' => 'Ified', 'department_id' => $dept]);
        same($st, 201);
        $newId = (int) $b['data']['user']['id'];
        check(notificationExists('user.account_created', $newId), 'the new employee must be told');
        check(notificationExists('user.created', $adminId), 'account managers must be told');
    });
});

test('Editing an account notifies the employee and the account managers', function () use ($router) {
    scoped(function () use ($router) {
        $adminId = mkUser('noteadmin2', 'ADMIN', ['admin']);
        $target  = mkUser('edited', 'FRONT_OFFICE', []);
        $c = new Client($router); $c->login('noteadmin2', 'Str0ngPassw0rd');
        [$st, $b] = $c->call('GET', "/api/v1/admin/users/$target");
        same($st, 200);
        $v = $b['data'];
        same(status($c->call('PUT', "/api/v1/admin/users/$target", ['email' => 'edited2@test.local', 'first_name' => 'Edit', 'last_name' => 'Ed', 'phone' => '', 'department_id' => $v['department_id']])), 200);
        check(notificationExists('user.profile_updated', $target), 'the edited employee must be told');
        check(notificationExists('user.updated', $adminId), 'account managers must be told');
    });
});

test('Deactivating an account notifies the employee even though the account is now inactive', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('noteadmin3', 'ADMIN', ['admin']);
        $target = mkUser('deactivated', 'FRONT_OFFICE', []);
        $c = new Client($router); $c->login('noteadmin3', 'Str0ngPassw0rd');
        same(status($c->call('POST', "/api/v1/admin/users/$target/status", ['action' => 'deactivate'])), 200);
        same((string) Database::fetchOne('SELECT status FROM users WHERE id = ?', [$target])['status'], 'inactive');
        check(notificationExists('user.deactivate', $target), 'a deactivated employee must still be told');
    });
});

test('Resetting a password notifies the employee and authorized admins and records the audit event', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('noteadmin4', 'ADMIN', ['admin']);
        $target = mkUser('resetme', 'FRONT_OFFICE', []);
        $c = new Client($router); $c->login('noteadmin4', 'Str0ngPassw0rd');
        same(status($c->call('POST', "/api/v1/admin/users/$target/reset-password")), 200);
        check(notificationExists('user.password_reset', $target), 'the employee must be told');
        check(notificationExists('user.password_reset_by_admin', (int) Database::fetchOne("SELECT id FROM users WHERE username='noteadmin4'")['id']), 'authorized admins must be told');
        same((int) Database::fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='user.password_reset_by_admin' AND entity_id=?", [$target])['c'], 1);
    });
});

test('Changing roles notifies the employee', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('noteadmin5', 'ADMIN', ['admin']);
        $target = mkUser('roleme', 'FRONT_OFFICE', []);
        $role = (int) Database::fetchOne("SELECT id FROM roles WHERE code='front_office'")['id'];
        $c = new Client($router); $c->login('noteadmin5', 'Str0ngPassw0rd');
        same(status($c->call('PUT', "/api/v1/admin/users/$target/roles", ['role_ids' => [$role]])), 200);
        check(notificationExists('user.roles_changed', $target), 'the employee must be told about the access change');
    });
});

test('Role and permission changes notify the role managers', function () use ($router) {
    scoped(function () use ($router) {
        $adminId = mkUser('noteadmin6', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('noteadmin6', 'Str0ngPassw0rd');
        [$st, $b] = $c->call('POST', '/api/v1/admin/roles', ['code' => 'night_auditor', 'name' => 'Night Auditor']);
        same($st, 201);
        $rid = (int) $b['data']['id'];
        check(notificationExists('role.created', $adminId), 'role managers must be told about a new role');
        $pid = (int) Database::fetchOne("SELECT id FROM permissions WHERE code='report.view'")['id'];
        same(status($c->call('PUT', "/api/v1/admin/roles/$rid/permissions", ['permission_ids' => [$pid]])), 200);
        check(notificationExists('role.permissions_changed', $adminId), 'role managers must be told about new permissions');
        same(status($c->call('DELETE', "/api/v1/admin/roles/$rid")), 200);
        check(notificationExists('role.deleted', $adminId), 'role managers must be told about a deleted role');
    });
});

test('Changing settings notifies the settings managers', function () use ($router) {
    scoped(function () use ($router) {
        $adminId = mkUser('noteadmin7', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('noteadmin7', 'Str0ngPassw0rd');
        same(status($c->call('PUT', '/api/v1/admin/settings', ['settings' => ['hotel.name' => 'Seaside ' . bin2hex(random_bytes(3))]])), 200);
        \App\Services\SettingService::flush();
        check(notificationExists('settings.updated', $adminId), 'settings managers must be told');
    });
});

test('Revoking a session notifies the signed-in employee', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('noteadmin8', 'ADMIN', ['admin']);
        $target = mkUser('signedin', 'FRONT_OFFICE', []);
        $victim = new Client($router); $victim->login('signedin', 'Str0ngPassw0rd');
        $c = new Client($router); $c->login('noteadmin8', 'Str0ngPassw0rd');
        [, $sb] = $c->call('GET', '/api/v1/admin/sessions');
        $handle = null;
        foreach ($sb['data'] as $s) { if ($s['username'] === 'signedin') { $handle = $s['handle']; } }
        check($handle !== null, 'the employee session must be listed');
        same(status($c->call('DELETE', "/api/v1/admin/sessions/$handle")), 200);
        check(notificationExists('session.revoked', $target), 'the employee must be told their session ended');
    });
});

test('Staff without the permission are not pulled into administrative notifications', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('noteadmin9', 'ADMIN', ['admin']);
        $clerk = mkUser('plainclerk', 'FRONT_OFFICE', ['front_office']);
        $c = new Client($router); $c->login('noteadmin9', 'Str0ngPassw0rd');
        $dept = (int) Database::fetchOne("SELECT id FROM departments WHERE code='FRONT_OFFICE'")['id'];
        same(status($c->call('POST', '/api/v1/admin/users', ['username' => 'quiet', 'email' => 'quiet@test.local', 'first_name' => 'Quiet', 'last_name' => 'User', 'department_id' => $dept])), 201);
        check(!notificationExists('user.created', $clerk), 'a clerk without user.manage must not be notified');
    });
});

test('toUser reaches an account that is inactive or locked', function () {
    scoped(function () {
        $uid = mkUser('hidden', 'FRONT_OFFICE', [], 'Str0ngPassw0rd', false, 'inactive');
        NotificationService::toUser($uid, 'test.hidden', 'Hidden notice', 'Still delivered.', 'warning', 'user', $uid, $uid);
        check(notificationExists('test.hidden', $uid), 'inactive accounts must still receive notices');
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM notifications WHERE type = ?', ['test.hidden'])['c'], 1);
    });
});

test('Notifications never leak a password or a hash', function () {
    scoped(function () {
        $uid = mkUser('secretive', 'FRONT_OFFICE', []);
        NotificationService::toUser($uid, 'test.secret', 'Secret notice', 'No secrets here.', 'info', 'user', $uid, $uid);
        $row = Database::fetchOne("SELECT title, message FROM notifications WHERE type='test.secret' ORDER BY id DESC LIMIT 1");
        $hash = (string) Database::fetchOne('SELECT password_hash FROM users WHERE id = ?', [$uid])['password_hash'];
        check(!str_contains($row['title'] . $row['message'], $hash), 'a hash must never appear in a notification');
        check(!str_contains($row['title'] . $row['message'], 'password_hash'), 'a hash must never appear in a notification');
    });
});
