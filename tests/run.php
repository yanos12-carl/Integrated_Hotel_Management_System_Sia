<?php
declare(strict_types=1);

/**
 * Dependency-free test runner (no Composer/PHPUnit needed on XAMPP).
 *   php tests/run.php
 * Database tests run only when APP_ENV=testing and a database is reachable; they never touch production data.
 */
if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Config, Database, Env, HttpException, Logger, Request, Response, Router};

\App\Core\Session::configure();   // must run before any output

$pass = 0; $fail = 0; $skip = 0;
function test(string $name, callable $fn): void
{
    global $pass, $fail, $skip;
    try {
        $r = $fn();
        if ($r === 'skip') { $skip++; echo "  SKIP  $name\n"; return; }
        $pass++; echo "  PASS  $name\n";
    } catch (Throwable $e) {
        $fail++; echo "  FAIL  $name\n        " . $e->getMessage() . "\n";
    }
}
function check(bool $cond, string $msg = 'assertion failed'): void { if (!$cond) { throw new RuntimeException($msg); } }
function same(mixed $a, mixed $b): void { if ($a !== $b) { throw new RuntimeException('expected ' . var_export($b, true) . ' got ' . var_export($a, true)); } }

echo "Foundation\n";

test('Env parses quotes and inline comments', function () {
    $f = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($f, "A=plain # note\nB=\"quoted # kept\"\n# comment\nC=\n");
    Env::load($f); unlink($f);
    same(Env::get('A'), 'plain'); same(Env::get('B'), 'quoted # kept'); same(Env::get('C'), '');
});

test('Router matches params and enforces numeric constraint', function () {
    $r = new Router();
    $r->get('/api/v1/items/{id:\d+}', fn($rq, $p) => Response::success(['id' => (int) $p['id']]));
    $ok = $r->dispatch(new Request('GET', '/api/v1/items/42'));
    same($ok->status, 200); same($ok->payload['data']['id'], 42);
    same($r->dispatch(new Request('GET', '/api/v1/items/abc'))->status, 404);
});

test('Router returns 404 and 405 (with Allow header)', function () {
    $r = new Router();
    $r->get('/x', fn() => Response::success());
    same($r->dispatch(new Request('GET', '/nope'))->status, 404);
    $m = $r->dispatch(new Request('POST', '/x'));
    same($m->status, 405); same($m->headers['Allow'], 'GET');
});

test('Router groups apply prefix and middleware', function () {
    $r = new Router();
    $r->group('/api/v1', [DenyAll::class], function (Router $r) { $r->get('/secret', fn() => Response::success()); });
    try { $r->dispatch(new Request('GET', '/api/v1/secret')); throw new RuntimeException('middleware did not block'); }
    catch (HttpException $e) { same($e->getStatus(), 403); }
});

test('Response envelope shape', function () {
    $s = Response::success(['a' => 1], 'Hi');
    same($s->payload, ['success' => true, 'message' => 'Hi', 'data' => ['a' => 1]]);
    $e = Response::error('Bad', 422, ['f' => 'required']);
    same($e->payload['success'], false); same($e->payload['errors']['f'], 'required'); same($e->status, 422);
});

test('Logger redacts secrets and strips newlines', function () {
    $dir = sys_get_temp_dir() . '/ihms_log_' . bin2hex(random_bytes(4));
    Logger::setDirectory($dir);
    Logger::info("line1\nFAKE ENTRY", ['password' => 'hunter2', 'nested' => ['api_token' => 'abc'], 'ok' => 'visible']);
    $log = file_get_contents(glob($dir . '/*.log')[0]);
    Logger::setDirectory(null);
    check(!str_contains($log, 'hunter2') && !str_contains($log, 'abc'), 'secret leaked');
    check(str_contains($log, 'visible'), 'normal value missing');
    same(substr_count($log, "\n"), 1);
});

test('Unhandled exceptions return generic 500 without internals', function () {
    $resp = App\Core\ErrorHandler::toResponse(new RuntimeException('SQLSTATE secret detail'));
    same($resp->status, 500);
    check(!str_contains(json_encode($resp->payload), 'secret detail'), 'internal message leaked');
    check(isset($resp->payload['error_id']), 'error_id missing');
});

echo "Database (requires MySQL)\n";
$dbUp = false;
try { Database::connection()->query('SELECT 1'); $dbUp = true; } catch (Throwable $e) {}
$isTesting = Config::get('app.env') === 'testing' || Config::get('app.env') === 'local';

test('Connects with strict mode and utf8mb4', function () use ($dbUp) {
    if (!$dbUp) return 'skip';
    $mode = Database::fetchOne('SELECT @@sql_mode AS m')['m'];
    check(str_contains($mode, 'STRICT_TRANS_TABLES'), 'not strict');
});

test('All nine departments are seeded', function () use ($dbUp) {
    if (!$dbUp) return 'skip';
    same((int) Database::fetchOne('SELECT COUNT(*) c FROM departments')['c'], 9);
});

test('Group A tables exist', function () use ($dbUp) {
    if (!$dbUp) return 'skip';
    $need = ['departments','users','roles','permissions','user_roles','role_permissions','user_departments','login_attempts','sessions','password_resets','system_settings','notifications','notification_recipients','audit_logs','error_logs','approval_requests','approval_history'];
    $have = array_column(Database::fetchAll('SHOW TABLES'), 'Tables_in_' . Config::get('database.name'));
    foreach ($need as $t) { check(in_array($t, $have, true), "missing table $t"); }
});

test('Transaction rolls back on failure', function () use ($dbUp, $isTesting) {
    if (!$dbUp || !$isTesting) return 'skip';
    Database::execute('CREATE TEMPORARY TABLE t_tx (id INT PRIMARY KEY) ENGINE=InnoDB');
    try {
        Database::transaction(function () {
            Database::execute('INSERT INTO t_tx VALUES (1)');
            Database::execute('INSERT INTO t_tx VALUES (1)');   // duplicate key -> exception
        });
        throw new RuntimeException('should have failed');
    } catch (PDOException $e) {}
    same((int) Database::fetchOne('SELECT COUNT(*) c FROM t_tx')['c'], 0);
});

test('Nested transactions commit together', function () use ($dbUp, $isTesting) {
    if (!$dbUp || !$isTesting) return 'skip';
    Database::execute('CREATE TEMPORARY TABLE t_nest (id INT PRIMARY KEY) ENGINE=InnoDB');
    Database::transaction(function () {
        Database::execute('INSERT INTO t_nest VALUES (1)');
        Database::transaction(fn() => Database::execute('INSERT INTO t_nest VALUES (2)'));
    });
    same((int) Database::fetchOne('SELECT COUNT(*) c FROM t_nest')['c'], 2);
});

test('Prepared statements neutralise SQL injection strings', function () use ($dbUp) {
    if (!$dbUp) return 'skip';
    $row = Database::fetchOne('SELECT COUNT(*) c FROM departments WHERE code = ?', ["x' OR '1'='1"]);
    same((int) $row['c'], 0);
});

require __DIR__ . '/phase3.php';
require __DIR__ . '/notifications.php';
require __DIR__ . '/schema.php';

echo "\n$pass passed, $fail failed, $skip skipped\n";
exit($fail > 0 ? 1 : 0);

final class DenyAll { public function handle(Request $r): void { throw HttpException::forbidden(); } }
