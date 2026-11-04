<?php
declare(strict_types=1);

/** Phase 3 tests: auth, sessions, CSRF, RBAC, Admin APIs. Every test runs inside a transaction that is rolled back. */
use App\Core\{Context, Database, ErrorHandler, HttpException, Request, Router};
use App\Services\{AuthService, MailOutbox, UserService};
use App\Validators\{PasswordPolicy, Validator};

echo "Phase 3: Authentication, RBAC, Admin\n";

$router = new Router();
(require BASE_PATH . '/routes/api.php')($router);

/** Simulates one browser: keeps its own session id and CSRF token across requests. */
final class Client
{
    public ?string $sid = null;
    public ?string $csrf = null;
    private static int $n = 10;
    public string $ip;

    public function __construct(private Router $router) { $this->ip = '10.9.8.' . (self::$n++); }

    public function call(string $method, string $path, array $body = [], bool $withCsrf = true): array
    {
        session_id($this->sid ?? '');   // never inherit another simulated browser's session
        $headers = ['user-agent' => 'phpunit-lite'];
        if ($withCsrf && $this->csrf) { $headers['x-csrf-token'] = $this->csrf; }
        $req = new Request($method, $path, $method === 'GET' ? $body : [], $method === 'GET' ? [] : $body, $headers, $this->ip);
        Context::fromRequest($req);
        try { $resp = $this->router->dispatch($req); } catch (Throwable $e) { $resp = ErrorHandler::toResponse($e); }
        if (session_status() === PHP_SESSION_ACTIVE) { $this->sid = session_id(); session_write_close(); }
        return [$resp->status, $resp->payload ?? []];
    }

    public function fetchCsrf(): void { [, $b] = $this->call('GET', '/api/v1/auth/csrf'); $this->csrf = $b['data']['csrf_token']; }

    public function login(string $u, string $p): array
    {
        $this->fetchCsrf();
        $r = $this->call('POST', '/api/v1/auth/login', ['username' => $u, 'password' => $p]);
        if ($r[0] === 200) { $this->csrf = $r[1]['data']['csrf_token']; }
        return $r;
    }
}

/** Run $fn in a rolled-back transaction with a fresh set of users. */
function scoped(callable $fn): void
{
    Database::begin();
    try { $fn(); } finally { Database::rollback(); }
}

function mkUser(string $name, string $dept, array $roleCodes, string $pw = 'Str0ngPassw0rd', bool $mustChange = false, string $status = 'active'): int
{
    $deptId = (int) Database::fetchOne('SELECT id FROM departments WHERE code = ?', [$dept])['id'];
    $id = Database::insert('INSERT INTO users (department_id, employee_no, username, email, password_hash, first_name, last_name, status, must_change_password) VALUES (?,?,?,?,?,?,?,?,?)',
        [$deptId, 'T-' . $name, $name, "$name@test.local", password_hash($pw, PASSWORD_DEFAULT), 'Test', ucfirst($name), $status, $mustChange ? 1 : 0]);
    foreach ($roleCodes as $rc) {
        Database::execute('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$id, $rc]);
    }
    return $id;
}
function status(array $r): int { return $r[0]; }

if (!$dbUp || !$isTesting) { test('Phase 3 database tests', fn() => 'skip'); return; }

test('Security API routes are registered and require authentication', function () use ($router) {
    $client = new Client($router);
    same(status($client->call('GET', '/api/v1/security/summary')), 401);
    same(status($client->call('POST', '/api/v1/security/incidents')), 401);
    same(status($client->call('DELETE', '/api/v1/security/cameras/1')), 401);
});

test('Password policy accepts strong and rejects weak passwords', function () {
    same(PasswordPolicy::problems('Str0ngPassw0rd', 'bob'), []);
    check(count(PasswordPolicy::problems('short1')) >= 1);
    check(count(PasswordPolicy::problems('onlylettersnodigits')) === 1);
    check(PasswordPolicy::problems('bobbyPassw0rd99', 'bobby') !== []);
    check(PasswordPolicy::problems(PasswordPolicy::generate()) === [], 'generated password must satisfy the policy');
});

test('Validator reports field errors and casts types', function () {
    try { Validator::validate(['email' => 'nope', 'age' => 'x'], ['email' => 'required|email', 'age' => 'required|int', 'name' => 'required|string']); throw new RuntimeException('no error'); }
    catch (HttpException $e) { same($e->getStatus(), 422); same(array_keys($e->getErrors()), ['email', 'age', 'name']); }
    same(Validator::validate(['n' => '5', 'b' => 'true'], ['n' => 'required|int|min:1|max:9', 'b' => 'required|bool']), ['n' => 5, 'b' => true]);
});

test('Login succeeds, regenerates the session id and returns permissions', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('alice', 'ADMIN', ['admin']);
        $c = new Client($router);
        $c->fetchCsrf(); $before = $c->sid;
        [$st, $b] = $c->login('alice', 'Str0ngPassw0rd');
        same($st, 200);
        check($c->sid !== $before, 'session id must change after login (fixation protection)');
        check(in_array('user.manage', $b['data']['user']['permissions'], true), 'admin should hold user.manage');
        check(!isset($b['data']['user']['password_hash']), 'hash must never be returned');
        same(status($c->call('GET', '/api/v1/auth/me')), 200);
    });
});

test('Wrong password and unknown user give the same generic message', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('bob', 'ADMIN', ['admin']);
        $a = (new Client($router))->login('bob', 'WrongPassw0rd1');
        $b = (new Client($router))->login('ghost', 'WrongPassw0rd1');
        same($a[0], 401); same($b[0], 401); same($a[1]['message'], $b[1]['message']);
    });
});

test('Login requires a CSRF token', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('carl', 'ADMIN', ['admin']);
        $c = new Client($router);
        same(status($c->call('POST', '/api/v1/auth/login', ['username' => 'carl', 'password' => 'Str0ngPassw0rd'], false)), 419);
    });
});

test('Account locks after repeated failures, even for the right password', function () use ($router) {
    scoped(function () use ($router) {
        $uid = mkUser('dana', 'ADMIN', ['admin']);
        $max = \App\Services\SettingService::int('security.max_login_attempts', 5);
        for ($i = 0; $i < $max; $i++) { (new Client($router))->login('dana', 'BadPassw0rd' . $i); }
        [$st] = (new Client($router))->login('dana', 'Str0ngPassw0rd');
        same($st, 423);
        check((int) Database::fetchOne('SELECT COUNT(*) c FROM audit_logs WHERE action = ? AND entity_id = ?', ['security.account_locked', $uid])['c'] === 1, 'lockout must be audited');
    });
});

test('Inactive accounts cannot sign in', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('erin', 'ADMIN', ['admin'], 'Str0ngPassw0rd', false, 'inactive');
        same(status((new Client($router))->login('erin', 'Str0ngPassw0rd')), 403);
    });
});

test('Protected endpoints return 401 without a session', function () use ($router) {
    $c = new Client($router);
    same(status($c->call('GET', '/api/v1/admin/users')), 401);
    same(status($c->call('GET', '/api/v1/auth/me')), 401);
});

test('Role-based access: non-admin gets 403, denial is audited', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('fred', 'FRONT_OFFICE', ['front_office']);
        $c = new Client($router); $c->login('fred', 'Str0ngPassw0rd');
        same(status($c->call('GET', '/api/v1/admin/users')), 403);
        same(status($c->call('POST', '/api/v1/admin/users', ['username' => 'x'])), 403);
        check((int) Database::fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='security.permission_denied'")['c'] >= 2);
    });
});

test('Admin role has no finance/security-evidence permissions; Management has no write permissions', function () {
    $adminPerms = array_column(Database::fetchAll("SELECT p.code FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='admin'"), 'code');
    foreach (['payment.record', 'refund.process', 'security.evidence.view', 'guest.manage', 'approval.decide'] as $c) { check(!in_array($c, $adminPerms, true), "admin must not hold $c"); }
    $mgmt = array_column(Database::fetchAll("SELECT p.code FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='management'"), 'code');
    foreach ($mgmt as $c) { check(!preg_match('/\.(manage|record|process|void|update|checkin|checkout|assign|decide|block)$/', $c), "management holds write permission $c"); }
});

test('CSRF is enforced on authenticated state-changing requests', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('gina', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('gina', 'Str0ngPassw0rd');
        same(status($c->call('POST', '/api/v1/admin/users', ['username' => 'zed'], false)), 419);
    });
});

test('Admin creates a user: temp password shown once, only a hash stored, audit has no secrets', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('hank', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('hank', 'Str0ngPassw0rd');
        $dept = (int) Database::fetchOne("SELECT id FROM departments WHERE code='FRONT_OFFICE'")['id'];
        $role = (int) Database::fetchOne("SELECT id FROM roles WHERE code='front_office'")['id'];
        [$st, $b] = $c->call('POST', '/api/v1/admin/users', ['username' => 'newclerk', 'email' => 'clerk@test.local', 'first_name' => 'New', 'last_name' => 'Clerk', 'department_id' => $dept, 'role_ids' => [$role]]);
        same($st, 201);
        $temp = $b['data']['temporary_password'];
        $row = Database::fetchOne("SELECT password_hash, must_change_password, employee_no FROM users WHERE username='newclerk'");
        check($row['password_hash'] !== $temp && password_verify($temp, $row['password_hash']), 'stored value must be a hash');
        same((int) $row['must_change_password'], 1);
        check(str_starts_with($row['employee_no'], 'EMP-'));
        $audit = Database::fetchOne("SELECT new_values FROM audit_logs WHERE action='user.create' ORDER BY id DESC LIMIT 1")['new_values'];
        check(!str_contains($audit, $temp), 'temporary password must not be in audit log');
        // duplicate username -> 422 with field error
        [$st2, $b2] = $c->call('POST', '/api/v1/admin/users', ['username' => 'newclerk', 'email' => 'other@test.local', 'first_name' => 'A', 'last_name' => 'B', 'department_id' => $dept]);
        same($st2, 422); check(isset($b2['errors']['username']));
    });
});

test('Forced password change blocks everything else until completed', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('ivy', 'ADMIN', ['admin'], 'Temp0rary1234', true);
        $c = new Client($router); $c->login('ivy', 'Temp0rary1234');
        [$st, $b] = $c->call('GET', '/api/v1/admin/users');
        same($st, 403); same($b['errors']['code'], 'password_change_required');
        same(status($c->call('POST', '/api/v1/auth/change-password', ['current_password' => 'Temp0rary1234', 'new_password' => 'weak'])), 422);
        same(status($c->call('POST', '/api/v1/auth/change-password', ['current_password' => 'Temp0rary1234', 'new_password' => 'BrandNewPassw0rd'])), 200);
        same(status($c->call('GET', '/api/v1/admin/users')), 200);
    });
});

test('Deactivating a user ends their active session immediately', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('jack', 'ADMIN', ['admin']);
        $target = mkUser('kate', 'FRONT_OFFICE', ['front_office']);
        $admin = new Client($router); $admin->login('jack', 'Str0ngPassw0rd');
        $victim = new Client($router); $victim->login('kate', 'Str0ngPassw0rd');
        same(status($victim->call('GET', '/api/v1/auth/me')), 200);
        same(status($admin->call('POST', "/api/v1/admin/users/$target/status", ['action' => 'deactivate'])), 200);
        same(status($victim->call('GET', '/api/v1/auth/me')), 401);
    });
});

test('Admin cannot deactivate self or remove the last active administrator', function () use ($router) {
    scoped(function () use ($router) {
        Database::execute("UPDATE users SET status='inactive' WHERE id IN (SELECT user_id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE r.code='admin')");
        $me = mkUser('liam', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('liam', 'Str0ngPassw0rd');
        same(status($c->call('POST', "/api/v1/admin/users/$me/status", ['action' => 'deactivate'])), 409);
        same(status($c->call('PUT', "/api/v1/admin/users/$me/roles", ['role_ids' => []])), 409);
    });
});

test('Roles: built-in cannot be deleted; permissions change is audited; admin keeps core access', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('mona', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('mona', 'Str0ngPassw0rd');
        $adminRole = (int) Database::fetchOne("SELECT id FROM roles WHERE code='admin'")['id'];
        same(status($c->call('DELETE', "/api/v1/admin/roles/$adminRole")), 409);
        same(status($c->call('PUT', "/api/v1/admin/roles/$adminRole/permissions", ['permission_ids' => []])), 409);
        [$st, $b] = $c->call('POST', '/api/v1/admin/roles', ['code' => 'night_auditor', 'name' => 'Night Auditor']);
        same($st, 201);
        $pid = (int) Database::fetchOne("SELECT id FROM permissions WHERE code='report.view'")['id'];
        same(status($c->call('PUT', "/api/v1/admin/roles/{$b['data']['id']}/permissions", ['permission_ids' => [$pid]])), 200);
        check((int) Database::fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='role.permissions_changed'")['c'] === 1);
        same(status($c->call('DELETE', "/api/v1/admin/roles/{$b['data']['id']}")), 200);
    });
});

test('Department-scoped role cannot be given to another department; nine departments stay', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('nina', 'ADMIN', ['admin']);
        $kate = mkUser('oscar', 'FRONT_OFFICE', []);
        $c = new Client($router); $c->login('nina', 'Str0ngPassw0rd');
        $fin = (int) Database::fetchOne("SELECT id FROM roles WHERE code='finance'")['id'];
        same(status($c->call('PUT', "/api/v1/admin/users/$kate/roles", ['role_ids' => [$fin]])), 422);
        $admDept = (int) Database::fetchOne("SELECT id FROM departments WHERE code='ADMIN'")['id'];
        same(status($c->call('PUT', "/api/v1/admin/departments/$admDept", ['name' => 'Admin', 'is_active' => false])), 409);
        [, $b] = $c->call('GET', '/api/v1/admin/departments'); same(count($b['data']), 9);
    });
});

test('Settings validate type and range and are audited', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('pete', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('pete', 'Str0ngPassw0rd');
        same(status($c->call('PUT', '/api/v1/admin/settings', ['settings' => ['billing.vat_rate' => '5']])), 422);
        same(status($c->call('PUT', '/api/v1/admin/settings', ['settings' => ['made.up' => '1']])), 422);
        $count = fn() => (int) Database::fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='settings.update'")['c'];
        $before = $count();
        $current = \App\Services\SettingService::get('billing.vat_rate');
        same(status($c->call('PUT', '/api/v1/admin/settings', ['settings' => ['billing.vat_rate' => $current, 'hotel.name' => 'Seaside Hotel ' . bin2hex(random_bytes(3))]])), 200);
        same($count() - $before, 1);   // only the changed key is audited
        \App\Services\SettingService::flush();
    });
});

test('Password reset: token is hashed, single-use, expires, revokes sessions; no account enumeration', function () use ($router) {
    scoped(function () use ($router) {
        $uid = mkUser('quinn', 'FRONT_OFFICE', ['front_office']);
        $c = new Client($router); $c->fetchCsrf();
        $same1 = $c->call('POST', '/api/v1/auth/forgot-password', ['email' => 'quinn@test.local']);
        $same2 = $c->call('POST', '/api/v1/auth/forgot-password', ['email' => 'nobody@test.local']);
        same($same1[0], 200); same($same1[1]['message'], $same2[1]['message']);
        $log = file_get_contents(BASE_PATH . '/storage/private/mail-outbox.log');
        preg_match_all('/token=([a-f0-9]{64})/', $log, $m); $token = end($m[1]);
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM password_resets WHERE token_hash = ?', [$token])['c'], 0);   // raw token never stored
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)])['c'], 1);
        same(status($c->call('POST', '/api/v1/auth/reset-password', ['token' => $token, 'new_password' => 'weak'])), 422);
        same(status($c->call('POST', '/api/v1/auth/reset-password', ['token' => $token, 'new_password' => 'FreshPassw0rd77'])), 200);
        same(status($c->call('POST', '/api/v1/auth/reset-password', ['token' => $token, 'new_password' => 'AnotherPassw0rd8'])), 422);   // single use
        same(status((new Client($router))->login('quinn', 'FreshPassw0rd77')), 200);
        // expired token
        Database::execute('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,DATE_SUB(NOW(), INTERVAL 1 MINUTE))', [$uid, hash('sha256', 'expiredtoken')]);
        same(status($c->call('POST', '/api/v1/auth/reset-password', ['token' => 'expiredtoken', 'new_password' => 'AnotherPassw0rd8'])), 422);
    });
});

test('Notifications: recipients only see their own, read status updates, inactive users skipped', function () use ($router) {
    scoped(function () use ($router) {
        $a = mkUser('rita', 'ADMIN', ['admin']); $b = mkUser('sam', 'FRONT_OFFICE', ['front_office']); $z = mkUser('zoe', 'FRONT_OFFICE', [], 'Str0ngPassw0rd', false, 'inactive');
        $nid = \App\Services\NotificationService::create('test.event', 'Hello', 'World', 'info', 'reservation', 7, [$a, $z]);
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM notification_recipients WHERE notification_id = ?', [$nid])['c'], 1);
        $ca = new Client($router); $ca->login('rita', 'Str0ngPassw0rd');
        $cb = new Client($router); $cb->login('sam', 'Str0ngPassw0rd');
        same($ca->call('GET', '/api/v1/notifications/unread-count')[1]['data']['count'], 1);
        same($cb->call('GET', '/api/v1/notifications/unread-count')[1]['data']['count'], 0);
        same(status($cb->call('POST', "/api/v1/notifications/$nid/read")), 404);      // not a recipient
        same(status($ca->call('POST', "/api/v1/notifications/$nid/read")), 200);
        same($ca->call('GET', '/api/v1/notifications/unread-count')[1]['data']['count'], 0);
    });
});

test('Approvals: separation of duties and permission required', function () use ($router) {
    scoped(function () use ($router) {
        $req = mkUser('tina', 'FRONT_OFFICE', ['front_office']); $fin = mkUser('uma', 'FINANCE', ['finance']);
        $id = \App\Services\ApprovalService::request('refund', 'payment', 1, 'approval.decide', $req, 'Guest overcharged');
        $c1 = new Client($router); $c1->login('tina', 'Str0ngPassw0rd');
        same(status($c1->call('POST', "/api/v1/approvals/$id/decide", ['decision' => 'approved'])), 403);
        $c2 = new Client($router); $c2->login('uma', 'Str0ngPassw0rd');
        same(status($c2->call('POST', "/api/v1/approvals/$id/decide", ['decision' => 'approved', 'note' => 'ok'])), 200);
        same(status($c2->call('POST', "/api/v1/approvals/$id/decide", ['decision' => 'rejected'])), 409);
        check((int) Database::fetchOne("SELECT COUNT(*) c FROM approval_history WHERE approval_request_id = ?", [$id])['c'] === 2);
    });
});

test('Accounts requested by other departments require administrator approval', function () use ($router) {
    scoped(function () use ($router) {
        Database::execute(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'user.manage'
             WHERE r.code IN ('housekeeping','management')"
        );
        mkUser('wade', 'HOUSEKEEPING', ['housekeeping']);
        mkUser('xena', 'ADMIN', ['admin']);
        mkUser('milo', 'MANAGEMENT', ['management']);
        $staff = new Client($router); $staff->login('wade', 'Str0ngPassw0rd');
        $admin = new Client($router); $admin->login('xena', 'Str0ngPassw0rd');
        $manager = new Client($router); $manager->login('milo', 'Str0ngPassw0rd');
        same(status($staff->call('POST', '/api/v1/admin/users', [
            'username' => 'bypass', 'email' => 'bypass@test.local', 'first_name' => 'No', 'last_name' => 'Approval',
            'department_id' => 1,
        ])), 403);

        [$status, $response] = $staff->call('POST', '/api/v1/account-requests', [
            'username' => 'newmaid', 'email' => 'newmaid@test.local', 'first_name' => 'New', 'last_name' => 'Maid',
            'phone' => '555-0100', 'job_title' => 'Housekeeping Attendant', 'department_id' => 1, 'role_ids' => [1],
        ]);
        same($status, 201);
        $approvalId = (int) $response['data']['approval_id'];
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM users WHERE username = ?', ['newmaid'])['c'], 0);

        [, $adminQueue] = $admin->call('GET', '/api/v1/approvals', ['status' => 'pending']);
        $request = array_values(array_filter($adminQueue['data'], fn($a) => (int) $a['id'] === $approvalId))[0] ?? null;
        check($request !== null, 'the pending request should appear in the administrator approval queue');
        same($request['request_data']['department_name'], 'Housekeeping');
        check($request['can_decide'], 'the administrator should be able to decide the account request');
        [, $managerQueue] = $manager->call('GET', '/api/v1/approvals', ['status' => 'pending']);
        check(!in_array($approvalId, array_column($managerQueue['data'], 'id')), 'account request details must not be exposed to unrelated approval viewers');
        same(status($manager->call('POST', "/api/v1/admin/account-requests/$approvalId/decide", ['decision' => 'approved'])), 403);

        [$status, $decision] = $admin->call('POST', "/api/v1/admin/account-requests/$approvalId/decide", ['decision' => 'approved']);
        same($status, 200);
        check(!empty($decision['data']['temporary_password']), 'the approver should receive the new account temporary password once');
        same((int) $decision['data']['account']['department_id'], (int) Database::fetchOne("SELECT id FROM departments WHERE code = 'HOUSEKEEPING'")['id']);
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM user_roles ur JOIN roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE u.username=? AND r.code=?', ['newmaid', 'housekeeping'])['c'], 1);
        same((int) Database::fetchOne('SELECT must_change_password FROM users WHERE username = ?', ['newmaid'])['must_change_password'], 1);
        same(Database::fetchOne('SELECT job_title FROM users WHERE username = ?', ['newmaid'])['job_title'], 'Housekeeping Attendant');

        [$status] = (new Client($router))->login('newmaid', $decision['data']['temporary_password']);
        same($status, 200);

        [$status, $managementRequest] = $manager->call('POST', '/api/v1/account-requests', [
            'username' => 'newmanagerstaff', 'email' => 'newmanagerstaff@test.local',
            'first_name' => 'New', 'last_name' => 'ManagerStaff', 'job_title' => 'Operations Coordinator',
        ]);
        same($status, 201);
        $managementApprovalId = (int) $managementRequest['data']['approval_id'];
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM users WHERE username = ?', ['newmanagerstaff'])['c'], 0);
        [, $managementApproval] = $admin->call('GET', '/api/v1/approvals', ['status' => 'pending']);
        $managementApproval = array_values(array_filter(
            $managementApproval['data'],
            fn($a) => (int) $a['id'] === $managementApprovalId
        ))[0] ?? null;
        check($managementApproval !== null, 'the Management account request should appear in the Admin approval queue');
        same($managementApproval['request_data']['department_name'], 'Management');
        same(status($admin->call('POST', "/api/v1/admin/account-requests/$managementApprovalId/decide", ['decision' => 'approved'])), 200);
        same((int) Database::fetchOne(
            'SELECT department_id FROM users WHERE username = ?',
            ['newmanagerstaff']
        )['department_id'], (int) Database::fetchOne(
            "SELECT id FROM departments WHERE code = 'MANAGEMENT'"
        )['id']);
        same(Database::fetchOne('SELECT job_title FROM users WHERE username = ?', ['newmanagerstaff'])['job_title'], 'Operations Coordinator');

        [$status, $rejected] = $staff->call('POST', '/api/v1/account-requests', [
            'username' => 'newcook', 'email' => 'newcook@test.local', 'first_name' => 'New', 'last_name' => 'Cook',
        ]);
        same($status, 201);
        $rejectedId = (int) $rejected['data']['approval_id'];
        same(status($admin->call('POST', "/api/v1/admin/account-requests/$rejectedId/decide", ['decision' => 'rejected'])), 200);
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM users WHERE username = ?', ['newcook'])['c'], 0);
    });
});

test('Admin dashboard summary, audit, login activity and session listing work and hide session ids', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('vera', 'ADMIN', ['admin']);
        $c = new Client($router); $c->login('vera', 'Str0ngPassw0rd');
        [$st, $b] = $c->call('GET', '/api/v1/admin/summary'); same($st, 200); check($b['data']['users']['total'] >= 1);
        same(status($c->call('GET', '/api/v1/admin/audit-logs', ['q' => 'auth'])), 200);
        same(status($c->call('GET', '/api/v1/admin/login-attempts')), 200);
        [$st, $b] = $c->call('GET', '/api/v1/admin/sessions'); same($st, 200);
        check(!str_contains(json_encode($b), (string) $c->sid), 'raw session id must never be exposed');
        same(status($c->call('DELETE', '/api/v1/admin/sessions/' . hash('sha256', (string) $c->sid))), 409);   // not your own
    });
});

test('Errors are logged for admins and never leak details to clients', function () use ($router) {
    scoped(function () {
        Context::set('user_id', null);   // earlier tests' rolled-back users no longer exist
        $resp = ErrorHandler::toResponse(new RuntimeException('boom secret'));
        $id = $resp->payload['error_id'];
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM error_logs WHERE error_id = ?', [$id])['c'], 1);
        check(!str_contains(json_encode($resp->payload), 'boom secret'));
    });
});

test('Session revocation by admin signs the other user out', function () use ($router) {
    scoped(function () use ($router) {
        mkUser('wade', 'ADMIN', ['admin']); mkUser('xena', 'FRONT_OFFICE', ['front_office']);
        $a = new Client($router); $a->login('wade', 'Str0ngPassw0rd');
        $x = new Client($router); $x->login('xena', 'Str0ngPassw0rd');
        same(status($x->call('GET', '/api/v1/auth/me')), 200);
        same(status($a->call('DELETE', '/api/v1/admin/sessions/' . hash('sha256', (string) $x->sid))), 200);
        same(status($x->call('GET', '/api/v1/auth/me')), 401);
    });
});

test('Security users use the native IHMS pages and JSON API', function () use ($router) {
    scoped(function () use ($router) {
        $guard = mkUser('guard1', 'SECURITY', ['security']);
        $client = new Client($router);
        [$st, $body] = $client->login('guard1', 'Str0ngPassw0rd');
        same($st, 200);
        same($body['data']['user']['department']['code'], 'SECURITY');
        same((int) AuthService::currentUser($guard)['id'], $guard);
        same(status($client->call('GET', '/api/v1/auth/me')), 200);
        same(status($client->call('GET', '/api/v1/notifications')), 200);
        same(status($client->call('GET', '/api/v1/security/summary')), 200);
        same(status($client->call('GET', '/api/v1/security/incidents')), 200);
        same(status($client->call('GET', '/api/v1/security/cameras')), 200);
        [$cameraStatus, $cameraBody] = $client->call('POST', '/api/v1/security/cameras', [
            'name' => 'Test browser camera ' . bin2hex(random_bytes(3)),
            'location' => 'Test lobby',
            'browser_device_key' => hash('sha256', 'test-camera-device'),
        ]);
        same($cameraStatus, 200);
        same($cameraBody['data']['connection_type'], 'browser');
        same($cameraBody['data']['location'], 'Test lobby');
        mkUser('cameraViewer', 'FRONT_OFFICE', ['front_office']);
        $viewer = new Client($router);
        same(status($viewer->login('cameraViewer', 'Str0ngPassw0rd')), 200);
        same(status($viewer->call('DELETE', '/api/v1/security/cameras/' . $cameraBody['data']['id'])), 403);
        same(status($client->call('DELETE', '/api/v1/security/cameras/' . $cameraBody['data']['id'])), 200);
        same(status($client->call('DELETE', '/api/v1/security/cameras/' . $cameraBody['data']['id'])), 404);
        same((int) Database::fetchOne('SELECT COUNT(*) c FROM cctv_cameras WHERE id = ?', [$cameraBody['data']['id']])['c'], 0);
        same((int) Database::fetchOne(
            "SELECT COUNT(*) c FROM audit_logs WHERE action = 'security.camera_removed' AND entity_id = ?",
            [$cameraBody['data']['id']]
        )['c'], 1);

        $webRouter = new Router();
        (require BASE_PATH . '/routes/web.php')($webRouter);
        session_id($client->sid ?? '');
        Context::fromRequest(new Request('GET', '/home'));
        $home = $webRouter->dispatch(new Request('GET', '/home'));
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        same($home->status, 302);
        check(str_ends_with($home->headers['Location'], '/security'));

        session_id($client->sid ?? '');
        $dashboardRequest = new Request('GET', '/security');
        Context::fromRequest($dashboardRequest);
        $dashboard = $webRouter->dispatch($dashboardRequest);
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        same($dashboard->status, 200);
        check(str_contains((string) $dashboard->html, 'data-page="security-dashboard"'));
        check(str_contains((string) $dashboard->html, 'security.js'));

        session_id($client->sid ?? '');
        $legacyLaunchRequest = new Request('GET', '/security/launch');
        Context::fromRequest($legacyLaunchRequest);
        $legacyLaunch = $webRouter->dispatch($legacyLaunchRequest);
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        same($legacyLaunch->status, 302);
        check(str_ends_with($legacyLaunch->headers['Location'], '/security'));

        session_id($client->sid ?? '');
        $logoutPageRequest = new Request('GET', '/security/logout');
        Context::fromRequest($logoutPageRequest);
        $logoutPage = $webRouter->dispatch($logoutPageRequest);
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        same($logoutPage->status, 200);
        check(str_contains((string) $logoutPage->html, 'name="_csrf"'), 'Security logout should post with a central CSRF token');

        $logoutClient = new Client($webRouter);
        $logoutClient->sid = $client->sid;
        $logoutClient->csrf = $client->csrf;
        [$logoutStatus] = $logoutClient->call('POST', '/security/logout');
        same($logoutStatus, 302);
        same(status($client->call('GET', '/api/v1/auth/me')), 401);
        same(status($logoutClient->call('POST', '/security/logout')), 401);
    });
});
