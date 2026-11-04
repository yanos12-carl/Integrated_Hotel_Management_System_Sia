<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/** Single PDO connection (prepared statements only) with nestable transactions. */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c   = Config::get('database');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            try {
                self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                        "SET NAMES %s COLLATE %s, time_zone = '%s', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
                        $c['charset'], $c['collation'], preg_replace('/[^+\-0-9:A-Za-z\/_]/', '', (string) $c['timezone'])
                    ),
                ]);
            } catch (PDOException $e) {
                // Don't leak DSN/credentials: wrap, log the driver message privately.
                Logger::error('Database connection failed', ['driver_message' => $e->getMessage()]);
                throw new \RuntimeException('Database connection failed.', 0);
            }
        }
        return self::$pdo;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo   = $pdo;
        self::$depth = 0;
    }

    /** Run $fn atomically. Nested calls join the outer transaction. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();
        if (self::$depth === 0) {
            $pdo->beginTransaction();
        }
        self::$depth++;
        try {
            $result = $fn($pdo);
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$depth--;
            if (self::$depth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function run(string $sql, array $params): \PDOStatement
    {
        $st = self::connection()->prepare($sql);
        $i = 1;
        foreach ($params as $v) {
            $type = is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $st->bindValue($i++, $v, $type);
        }
        $st->execute();
        return $st;
    }

    /** Test helpers: open/discard an outer transaction that service-level transaction() calls join. */
    public static function begin(): void
    {
        if (self::$depth === 0) { self::connection()->beginTransaction(); }
        self::$depth++;
    }

    public static function rollback(): void
    {
        self::$depth = max(0, self::$depth - 1);
        if (self::$depth === 0 && self::connection()->inTransaction()) { self::connection()->rollBack(); }
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);
        return (int) self::connection()->lastInsertId();
    }
}
