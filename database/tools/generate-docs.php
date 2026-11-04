<?php
declare(strict_types=1);

/**
 * Regenerates docs/data-dictionary.md and docs/erd/*.md|*.mmd from the LIVE database, so documentation always matches the schema.
 *   php database/tools/generate-docs.php
 */
if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
require __DIR__ . '/../../app/bootstrap.php';

use App\Core\{Config, Database};

$db     = Config::get('database.name');
$groups = Config::get('schema_groups');
$q = fn(string $sql, array $p = []) => Database::fetchAll($sql, $p);

$cols = [];
foreach ($q("SELECT table_name t, column_name c, column_type ty, is_nullable n, column_key k, column_default d, extra e, generation_expression g FROM information_schema.columns WHERE table_schema = ? ORDER BY table_name, ordinal_position", [$db]) as $r) { $cols[$r['t']][] = $r; }
$tables = []; foreach ($q("SELECT table_name t, table_comment c FROM information_schema.tables WHERE table_schema = ? AND table_type='BASE TABLE'", [$db]) as $r) { $tables[$r['t']] = $r['c']; }
$fks = $q("SELECT k.table_name t, k.column_name c, k.referenced_table_name rt, k.constraint_name n FROM information_schema.key_column_usage k WHERE k.table_schema = ? AND k.referenced_table_name IS NOT NULL ORDER BY k.table_name, k.constraint_name", [$db]);
$fkMap = []; foreach ($fks as $f) { $fkMap[$f['t']][$f['c']] = $f['rt']; }
$checks = []; try { foreach ($q("SELECT table_name t, constraint_name n, check_clause cl FROM information_schema.check_constraints WHERE constraint_schema = ?", [$db]) as $r) { $checks[$r['t'] ?? ''][] = $r; } } catch (Throwable) {}
$uniques = []; foreach ($q("SELECT table_name t, index_name i, GROUP_CONCAT(column_name ORDER BY seq_in_index) cs FROM information_schema.statistics WHERE table_schema = ? AND non_unique = 0 AND index_name <> 'PRIMARY' GROUP BY table_name, index_name", [$db]) as $r) { $uniques[$r['t']][] = $r; }
$triggers = []; foreach ($q("SELECT event_object_table t, trigger_name n, event_manipulation e FROM information_schema.triggers WHERE trigger_schema = ?", [$db]) as $r) { $triggers[$r['t']][] = $r; }

$groupOf = []; foreach ($groups as $g => $v) { foreach ($v['tables'] as $t) { $groupOf[$t] = $g; } }
$total = count($tables);

// ---------- Data dictionary
$md  = "# IHMS Data Dictionary\n\n_Generated from the live database by `php database/tools/generate-docs.php`. $total tables, " . count($fks) . " foreign keys._\n\n";
$md .= "**Conventions.** `id BIGINT UNSIGNED` primary keys; money `DECIMAL(12,2)`; quantities `DECIMAL(14,3)`; every table is InnoDB utf8mb4; `created_at`/`updated_at` timestamps. Statuses are `ENUM`s. History and ledger tables are append-only and protected by triggers (see *Protections* per table).\n\n";
$md .= "| Group | Area | Tables |\n|---|---|---|\n";
foreach ($groups as $g => $v) { $md .= "| $g | " . $v['name'] . ' | ' . count($v['tables']) . " |\n"; }
foreach ($groups as $g => $v) {
    $md .= "\n---\n\n## Group $g: " . $v['name'] . "\n";
    foreach ($v['tables'] as $t) {
        $md .= "\n### `$t`\n" . ($tables[$t] !== '' ? '_' . $tables[$t] . "_\n" : '') . "\n| Column | Type | Null | Key | Default | Notes |\n|---|---|---|---|---|---|\n";
        foreach ($cols[$t] as $c) {
            $notes = [];
            if (isset($fkMap[$t][$c['c']])) { $notes[] = '→ `' . $fkMap[$t][$c['c']] . '.id`'; }
            if ($c['g']) { $notes[] = 'generated: `' . $c['g'] . '`'; }
            if (str_contains((string) $c['e'], 'auto_increment')) { $notes[] = 'auto increment'; }
            $key = $c['k'] === 'PRI' ? 'PK' : ($c['k'] === 'UNI' ? 'UQ' : ($c['k'] === 'MUL' ? 'idx' : ''));
            $md .= '| `' . $c['c'] . '` | ' . $c['ty'] . ' | ' . ($c['n'] === 'YES' ? 'yes' : 'no') . " | $key | " . ($c['d'] === null ? '' : '`' . str_replace('|', '\\|', (string) $c['d']) . '`') . ' | ' . implode('; ', $notes) . " |\n";
        }
        $extra = [];
        foreach ($uniques[$t] ?? [] as $u) { $extra[] = '**Unique** `' . $u['i'] . '` (' . $u['cs'] . ')'; }
        foreach ($checks[$t] ?? [] as $ch) { $extra[] = '**Check** `' . $ch['n'] . '`: `' . str_replace('|', '\\|', $ch['cl']) . '`'; }
        foreach ($triggers[$t] ?? [] as $tr) { $extra[] = '**Protection** trigger `' . $tr['n'] . '` (' . $tr['e'] . ')'; }
        if ($extra) { $md .= "\n" . implode("  \n", $extra) . "\n"; }
    }
}
file_put_contents(BASE_PATH . '/docs/data-dictionary.md', $md);

// ---------- ERD (Mermaid): one diagram per group; tables of other groups appear as reference boxes
$erdMd = "# IHMS Entity-Relationship Diagrams\n\n_Generated from the live database. Open in VS Code (Markdown Preview Mermaid Support), GitHub, or paste the `.mmd` files at mermaid.live. Tables from another group appear only as small reference boxes. Links from actor columns (`created_by`, `changed_by`, `recorded_by`, ...) to `users` are listed in each table but not drawn, to keep the diagrams readable._\n\n";
$erdMd .= "## How the groups connect\n\n```mermaid\nflowchart LR\n";
$link = [];
foreach ($fks as $f) { $a = $groupOf[$f['t']] ?? '?'; $b = $groupOf[$f['rt']] ?? '?'; if ($a !== $b) { $link["$a>$b"] = ($link["$a>$b"] ?? 0) + 1; } }
foreach ($groups as $g => $v) { $erdMd .= "  {$g}[\"{$g}: " . $v['name'] . "\"]\n"; }
foreach ($link as $k => $n) { [$a, $b] = explode('>', $k); $erdMd .= "  $a -->|$n FKs| $b\n"; }
$erdMd .= "```\n";
foreach ($groups as $g => $v) {
    $set = array_flip($v['tables']); $refs = [];
    $rel = [];
    foreach ($fks as $f) {
        if (!isset($set[$f['t']]) && !isset($set[$f['rt']])) { continue; }
        if (!isset($set[$f['t']])) { continue; }          // only arrows that start inside this group
        if ($f['rt'] === 'users' && $g !== 'A') { continue; }   // actor columns (created_by, changed_by...) are listed but not drawn: they would hide the business relationships
        if (!isset($set[$f['rt']])) { $refs[$f['rt']] = true; }
        $null = $cols[$f['t']] ? (array_values(array_filter($cols[$f['t']], fn($c) => $c['c'] === $f['c']))[0]['n'] ?? 'NO') : 'NO';
        $rel[] = '  ' . $f['rt'] . ($null === 'YES' ? ' |o--o{ ' : ' ||--o{ ') . $f['t'] . ' : "' . $f['c'] . '"';
    }
    $m = "erDiagram\n";
    foreach ($v['tables'] as $t) {
        $m .= "  $t {\n";
        foreach ($cols[$t] as $c) {
            $isFk = isset($fkMap[$t][$c['c']]); $isPk = $c['k'] === 'PRI'; $isUq = $c['k'] === 'UNI';
            if (!($isFk || $isPk || $isUq || $c['c'] === 'status' || str_ends_with($c['c'], '_no'))) { continue; }
            $ty = preg_replace('/[^A-Za-z0-9_]/', '', explode('(', $c['ty'])[0]);
            $m .= "    $ty {$c['c']} " . ($isPk ? 'PK' : ($isFk ? 'FK' : ($isUq ? 'UK' : ''))) . "\n";
        }
        $m .= "  }\n";
    }
    foreach (array_keys($refs) as $r) { $m .= "  $r {\n    bigint id PK\n  }\n"; }
    $m .= implode("\n", array_unique($rel)) . "\n";
    file_put_contents(BASE_PATH . "/docs/erd/group-$g.mmd", $m);
    $erdMd .= "\n---\n\n## Group $g: " . $v['name'] . " (" . count($v['tables']) . " tables)\n\n```mermaid\n$m```\n";
}
file_put_contents(BASE_PATH . '/docs/erd/ihms-erd.md', $erdMd);
echo "Wrote docs/data-dictionary.md (" . round(strlen($md) / 1024) . " KB) and docs/erd/ihms-erd.md + 8 group-*.mmd files.\n";
