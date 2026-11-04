<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Context, Database, Logger};

/** Append-only audit trail. Called inside the same transaction as the action it records. */
final class AuditService
{
    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        Database::execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)',
            [
                $userId ?? Context::get('user_id'), $action, $entityType, $entityId,
                $old === null ? null : json_encode(Logger::redact($old), JSON_UNESCAPED_UNICODE),
                $new === null ? null : json_encode(Logger::redact($new), JSON_UNESCAPED_UNICODE),
                Context::get('ip'), Context::get('ua'),
            ]
        );
    }
}
