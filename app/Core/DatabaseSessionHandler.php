<?php
declare(strict_types=1);

namespace App\Core;

/** Sessions live in the `sessions` table so Admin can list and revoke them. */
final class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function __construct(private int $lifetimeSeconds) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        try {
            $row = Database::fetchOne('SELECT payload FROM sessions WHERE id = ? AND last_activity > ?', [$id, time() - $this->lifetimeSeconds]);
            return $row['payload'] ?? '';
        } catch (\Throwable $e) {
            Logger::error('Session read failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            Database::execute(
                'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity) VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload), last_activity = VALUES(last_activity)',
                [$id, $_SESSION['user_id'] ?? null, Context::get('ip'), Context::get('ua'), $data, time()]
            );
            return true;
        } catch (\Throwable $e) {
            Logger::error('Session write failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        Database::execute('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return Database::execute('DELETE FROM sessions WHERE last_activity < ?', [time() - $max_lifetime]);
    }

    /** Strict mode: unknown/expired/revoked ids are replaced by a fresh one (blocks fixation + honours revocation). */
    public function validateId(string $id): bool
    {
        return Database::fetchOne('SELECT 1 AS ok FROM sessions WHERE id = ? AND last_activity > ?', [$id, time() - $this->lifetimeSeconds]) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        Database::execute('UPDATE sessions SET last_activity = ? WHERE id = ?', [time(), $id]);
        return true;
    }
}
