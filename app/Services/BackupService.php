<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Config, Env, HttpException, Logger};

/** Database dumps stored under storage/private/backups (never web-accessible). Uses mysqldump without a shell. */
final class BackupService
{
    private static function dir(): string
    {
        $d = BASE_PATH . '/storage/private/backups';
        if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) { throw new \RuntimeException('Cannot create backup directory.'); }
        return $d;
    }

    public static function list(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/ihms-*.sql') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size_bytes' => filesize($f), 'created_at' => date('Y-m-d H:i:s', filemtime($f))];
        }
        usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    public static function create(int $actorId): array
    {
        $c    = Config::get('database');
        $name = 'ihms-' . date('Ymd-His') . '.sql';
        $path = self::dir() . '/' . $name;
        $bin  = Env::get('DB_DUMP_BIN', 'mysqldump');
        $cmd  = [$bin, '--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username'], '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file=' . $path, $c['name']];
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv() ?: [], ['MYSQL_PWD' => (string) $c['password']]));
        if (!is_resource($proc)) {
            throw new HttpException(500, 'mysqldump could not be started. Set DB_DUMP_BIN in .env (XAMPP: C:\\xampp\\mysql\\bin\\mysqldump.exe).');
        }
        stream_get_contents($pipes[1]);
        $err  = stream_get_contents($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || !is_file($path) || filesize($path) === 0) {
            @unlink($path);
            Logger::error('Backup failed', ['exit' => $code, 'stderr' => mb_substr((string) $err, 0, 300)]);
            throw new HttpException(500, 'Backup failed. Check DB_DUMP_BIN and database credentials; details are in the log.');
        }
        @chmod($path, 0640);
        AuditService::log('backup.create', 'backup', null, null, ['file' => $name, 'size' => filesize($path)], $actorId);
        NotificationService::toPermission('backup.manage', 'backup.created', 'Backup created',
            "A database backup ({$name}) was created.", 'info', 'backup', null, $actorId);
        return ['name' => $name, 'size_bytes' => filesize($path)];
    }

    public static function path(string $name): string
    {
        if (!preg_match('/^ihms-\d{8}-\d{6}\.sql$/', $name) || !is_file(self::dir() . '/' . $name)) {
            throw HttpException::notFound('Backup not found.');
        }
        return self::dir() . '/' . $name;
    }
}
