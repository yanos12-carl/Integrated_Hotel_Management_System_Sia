<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Config, Database, HttpException};

/** Read-only view of the central database for Admin: structure, health and migration status. */
final class DatabaseOverviewService
{
    public static function overview(): array
    {
        $db = (string) Config::get('database.name');
        $groups = Config::get('schema_groups');
        $info = [];
        foreach (Database::fetchAll("SELECT table_name AS t, table_comment AS c, engine AS e, table_rows AS r, ROUND((data_length + index_length) / 1024) AS kb FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'", [$db]) as $r) { $info[$r['t']] = $r; }
        $fks = Database::fetchAll('SELECT table_name AS t, COUNT(*) AS n FROM information_schema.key_column_usage WHERE table_schema = ? AND referenced_table_name IS NOT NULL GROUP BY table_name', [$db]);
        $fkBy = array_column($fks, 'n', 't');
        $trg = array_column(Database::fetchAll('SELECT event_object_table AS t, COUNT(*) AS n FROM information_schema.triggers WHERE trigger_schema = ? GROUP BY event_object_table', [$db]), 'n', 't');

        $out = []; $mapped = [];
        foreach ($groups as $code => $g) {
            $tables = [];
            foreach ($g['tables'] as $t) {
                $mapped[$t] = true;
                $i = $info[$t] ?? null;
                $tables[] = ['name' => $t, 'comment' => $i['c'] ?? '', 'engine' => $i['e'] ?? null, 'rows_estimate' => (int) ($i['r'] ?? 0), 'size_kb' => (int) ($i['kb'] ?? 0),
                             'foreign_keys' => (int) ($fkBy[$t] ?? 0), 'protected' => (int) ($trg[$t] ?? 0) > 0, 'exists' => $i !== null];
            }
            $out[] = ['code' => $code, 'name' => $g['name'], 'tables' => $tables];
        }

        $applied = self::appliedMigrations();
        $files = array_map('basename', glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
        sort($files);
        $pending = array_values(array_diff($files, array_keys($applied)));
        $unmapped = array_values(array_diff(array_keys($info), array_keys($mapped), ['schema_migrations']));
        $missing = array_values(array_filter(array_keys($mapped), fn($t) => !isset($info[$t])));
        $nonInno = array_values(array_filter(array_keys($info), fn($t) => ($info[$t]['e'] ?? '') !== 'InnoDB'));

        $health = [
            ['check' => 'All migrations applied', 'ok' => !$pending, 'detail' => $pending ? 'Pending: ' . implode(', ', $pending) . '. Run: php database/migrate.php migrate' : count($applied) . ' applied'],
            ['check' => 'Every expected table exists', 'ok' => !$missing, 'detail' => $missing ? 'Missing: ' . implode(', ', $missing) : 'All ' . count($mapped) . ' tables present'],
            ['check' => 'No undocumented tables', 'ok' => !$unmapped, 'detail' => $unmapped ? 'Not in a group: ' . implode(', ', $unmapped) : 'Every table belongs to a functional group'],
            ['check' => 'All tables use InnoDB (transactions + foreign keys)', 'ok' => !$nonInno, 'detail' => $nonInno ? 'Not InnoDB: ' . implode(', ', $nonInno) : 'Yes'],
            ['check' => 'History protection triggers installed', 'ok' => array_sum($trg) >= 50, 'detail' => array_sum($trg) . ' triggers guard ledgers and historical records'],
        ];
        return [
            'database' => $db,
            'totals' => ['tables' => count($mapped), 'foreign_keys' => array_sum(array_map('intval', $fkBy)), 'triggers' => array_sum(array_map('intval', $trg)),
                         'size_mb' => round(array_sum(array_map(fn($i) => (int) $i['kb'], $info)) / 1024, 2), 'migrations_applied' => count($applied), 'migrations_pending' => count($pending)],
            'health' => $health,
            'groups' => $out,
            'migrations' => array_map(fn($f) => ['file' => $f, 'applied_at' => $applied[$f] ?? null], $files),
        ];
    }

    /** @return array<string,string> migration file => applied_at */
    private static function appliedMigrations(): array
    {
        try { return array_column(Database::fetchAll('SELECT migration, applied_at FROM schema_migrations'), 'applied_at', 'migration'); }
        catch (\Throwable) { return []; }
    }

    public static function table(string $name): array
    {
        $group = null; $db = (string) Config::get('database.name');
        foreach (Config::get('schema_groups') as $code => $g) { if (in_array($name, $g['tables'], true)) { $group = $code . ': ' . $g['name']; } }
        if ($group === null) { throw HttpException::notFound('Unknown table.'); }   // whitelist: no user text ever reaches SQL identifiers
        $cols = Database::fetchAll('SELECT column_name AS name, column_type AS type, is_nullable AS nullable, column_key AS `key`, column_default AS `default`, generation_expression AS generated FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', [$db, $name]);
        $fk = Database::fetchAll('SELECT column_name AS column_name, referenced_table_name AS references_table FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND referenced_table_name IS NOT NULL', [$db, $name]);
        $by = Database::fetchAll('SELECT table_name AS from_table, column_name AS from_column FROM information_schema.key_column_usage WHERE table_schema = ? AND referenced_table_name = ? ORDER BY table_name', [$db, $name]);
        $trg = Database::fetchAll('SELECT trigger_name AS name, event_manipulation AS event FROM information_schema.triggers WHERE trigger_schema = ? AND event_object_table = ?', [$db, $name]);
        $comment = (string) (Database::fetchOne('SELECT table_comment AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$db, $name])['c'] ?? '');
        $count = (int) Database::fetchOne('SELECT COUNT(*) AS c FROM `' . $name . '`')['c'];
        return ['name' => $name, 'group' => $group, 'comment' => $comment, 'row_count' => $count, 'columns' => $cols, 'references' => $fk, 'referenced_by' => $by, 'protections' => $trg];
    }
}
