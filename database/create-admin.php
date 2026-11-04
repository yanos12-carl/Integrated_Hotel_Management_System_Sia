<?php
declare(strict_types=1);

/**
 * Creates the first System Administrator. Run once after migrate + seed:
 *   php database/create-admin.php
 * Non-interactive (CI/scripts): set IHMS_ADMIN_PASSWORD and pass --username= --email= --first= --last=
 * The password is never written to disk or logs; only its hash is stored. The account must change it at first sign-in.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Validators\{PasswordPolicy, Validator};

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) { $opts[$m[1]] = $m[2]; }
    if ($a === '--force') { $opts['force'] = '1'; }
}

function ask(string $label, ?string $preset = null): string
{
    if ($preset !== null && $preset !== '') { return $preset; }
    echo $label . ': ';
    return trim((string) fgets(STDIN));
}

function askSecret(string $label): string
{
    $env = getenv('IHMS_ADMIN_PASSWORD');
    if ($env !== false && $env !== '') { return $env; }
    echo $label . ': ';
    if (PHP_OS_FAMILY === 'Windows') {
        $pw = shell_exec('powershell -NoProfile -Command "$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))"');
        return trim((string) $pw);
    }
    shell_exec('stty -echo');
    $pw = trim((string) fgets(STDIN));
    shell_exec('stty echo');
    echo "\n";
    return $pw;
}

try {
    $existing = (int) Database::fetchOne("SELECT COUNT(*) c FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.code = 'admin'")['c'];
    if ($existing > 0 && !isset($opts['force'])) {
        fwrite(STDERR, "An administrator already exists. Use the Admin dashboard to manage accounts (or pass --force to add another).\n");
        exit(1);
    }
    $role = Database::fetchOne("SELECT id FROM roles WHERE code = 'admin'");
    $dept = Database::fetchOne("SELECT id FROM departments WHERE code = 'ADMIN'");
    if (!$role || !$dept) { fwrite(STDERR, "Run migrations and seeds first:\n  php database/migrate.php migrate\n  php database/migrate.php seed\n"); exit(1); }

    $in = [
        'username'   => ask('Username', $opts['username'] ?? null),
        'email'      => ask('Email', $opts['email'] ?? null),
        'first_name' => ask('First name', $opts['first'] ?? null),
        'last_name'  => ask('Last name', $opts['last'] ?? null),
    ];
    $d = Validator::validate($in, ['username' => 'required|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/', 'email' => 'required|email|max:190', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80']);
    $pw = askSecret('Temporary password (min 10 chars, letters + numbers)');
    PasswordPolicy::assert($pw, $d['username'], 'password');

    Database::transaction(function () use ($d, $pw, $role, $dept) {
        $id = Database::insert(
            'INSERT INTO users (department_id, employee_no, username, email, password_hash, first_name, last_name, status, must_change_password) VALUES (?,?,?,?,?,?,?,?,1)',
            [$dept['id'], 'TMP-' . bin2hex(random_bytes(6)), $d['username'], $d['email'], password_hash($pw, PASSWORD_DEFAULT), $d['first_name'], $d['last_name'], 'active']
        );
        Database::execute('UPDATE users SET employee_no = ? WHERE id = ?', [sprintf('EMP-%05d', $id), $id]);
        Database::execute('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)', [$id, $role['id']]);
        \App\Services\AuditService::log('user.create_initial_admin', 'user', $id, null, ['username' => $d['username']], $id);
    });
    echo "Administrator '{$d['username']}' created. Sign in and you will be asked to choose a new password.\n";
} catch (App\Core\HttpException $e) {
    fwrite(STDERR, 'Invalid input: ' . json_encode($e->getErrors()) . "\n");
    exit(1);
} catch (PDOException $e) {
    fwrite(STDERR, 'Database error: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'that username or email already exists.' : 'check your .env settings.') . "\n");
    exit(1);
}
