<?php
declare(strict_types=1);

/**
 * CLI migration runner.
 *   php database/migrate.php migrate            create DB if missing, apply pending migrations
 *   php database/migrate.php seed               run idempotent seeds
 *   php database/migrate.php status             list applied/pending
 *   php database/migrate.php fresh --confirm=<dbname>   DEV ONLY: drop all tables then migrate + seed
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;

$cfg = Config::get('database');
$cmd = $argv[1] ?? 'status';

function connect(array $cfg, bool $withDb): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;%scharset=%s', $cfg['host'], $cfg['port'], $withDb ? 'dbname=' . $cfg['name'] . ';' : '', $cfg['charset']);
    // Emulated prepares allow multi-statement SQL files; migrations are trusted local files, never user input.
    return new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
}

function ensureDatabase(array $cfg): PDO
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cfg['name'])) {
        fwrite(STDERR, "Invalid DB_NAME.\n");
        exit(1);
    }
    connect($cfg, false)->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $cfg['name']));
    $pdo = connect($cfg, true);
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(190) NOT NULL UNIQUE,
        checksum CHAR(64) NOT NULL,
        batch INT NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $applied = $pdo->query('SELECT migration, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $batch   = (int) $pdo->query('SELECT COALESCE(MAX(batch),0)+1 FROM schema_migrations')->fetchColumn();
    $files   = glob(__DIR__ . '/migrations/*.sql') ?: [];
    sort($files);
    $ran = 0;
    foreach ($files as $file) {
        $name = basename($file);
        $sum  = hash('sha256', (string) file_get_contents($file));
        if (isset($applied[$name])) {
            if ($applied[$name] !== $sum) {
                fwrite(STDERR, "WARNING: $name was modified after being applied. Add a new migration instead.\n");
            }
            continue;
        }
        echo "Applying $name ... ";
        $pdo->exec((string) file_get_contents($file));   // DDL auto-commits in MySQL
        $pdo->prepare('INSERT INTO schema_migrations (migration, checksum, batch) VALUES (?,?,?)')->execute([$name, $sum, $batch]);
        echo "done\n";
        $ran++;
    }
    echo $ran ? "Applied $ran migration(s).\n" : "Nothing to migrate.\n";
}

function seed(PDO $pdo): void
{
    $files = glob(__DIR__ . '/seeds/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        echo 'Seeding ' . basename($file) . " ... ";
        $pdo->exec((string) file_get_contents($file));
        echo "done\n";
    }
}

try {
    switch ($cmd) {
        case 'migrate':
            migrate(ensureDatabase($cfg));
            break;
        case 'seed':
            seed(ensureDatabase($cfg));
            break;
        case 'status':
            $pdo = ensureDatabase($cfg);
            $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            foreach (glob(__DIR__ . '/migrations/*.sql') ?: [] as $f) {
                echo (in_array(basename($f), $applied, true) ? '[applied] ' : '[pending] ') . basename($f) . "\n";
            }
            break;
        case 'fresh':
            $env = Config::get('app.env');
            if (!in_array($env, ['local', 'testing'], true)) {
                fwrite(STDERR, "Refusing: 'fresh' is only allowed when APP_ENV is local or testing (currently '$env').\n");
                exit(1);
            }
            if (($argv[2] ?? '') !== '--confirm=' . $cfg['name']) {
                fwrite(STDERR, "This DELETES ALL DATA in '{$cfg['name']}'. Re-run with --confirm={$cfg['name']}\n");
                exit(1);
            }
            $pdo = ensureDatabase($cfg);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $pdo->exec('DROP TABLE `' . str_replace('`', '', $t) . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo = ensureDatabase($cfg);
            migrate($pdo);
            seed($pdo);
            break;
        default:
            fwrite(STDERR, "Unknown command '$cmd'. Use: migrate | seed | status | fresh\n");
            exit(1);
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'Database error: ' . $e->getMessage() . "\nCheck that MySQL is running in XAMPP and your .env credentials are correct.\n");
    exit(1);
}
