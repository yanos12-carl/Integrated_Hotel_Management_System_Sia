<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};

/** Every notification names the event, the related record, each recipient, a timestamp and a read status. */
final class NotificationService
{
    /** @param int[] $userIds */
    public static function create(string $type, string $title, string $message, string $severity, ?string $entityType, ?int $entityId, array $userIds, ?int $createdBy = null): ?int
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return null;
        }
        return Database::transaction(function () use ($type, $title, $message, $severity, $entityType, $entityId, $userIds, $createdBy) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $active = array_column(Database::fetchAll("SELECT id FROM users WHERE status = 'active' AND id IN ($in)", $userIds), 'id');
            if (!$active) {
                return null;
            }
            $id = Database::insert(
                'INSERT INTO notifications (type, title, message, severity, entity_type, entity_id, created_by) VALUES (?,?,?,?,?,?,?)',
                [$type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $severity, $entityType, $entityId, $createdBy]
            );
            foreach ($active as $uid) {
                Database::execute('INSERT INTO notification_recipients (notification_id, user_id) VALUES (?,?)', [$id, (int) $uid]);
            }
            return $id;
        });
    }

    /**
     * Notify a single account even when it is inactive or locked. Account events (deactivation,
     * password reset, role change) must reach the employee who owns the record.
     */
    public static function toUser(int $userId, string $type, string $title, string $message, string $severity = 'info', ?string $entityType = null, ?int $entityId = null, ?int $by = null): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        return Database::transaction(function () use ($userId, $type, $title, $message, $severity, $entityType, $entityId, $by) {
            $id = Database::insert(
                'INSERT INTO notifications (type, title, message, severity, entity_type, entity_id, created_by) VALUES (?,?,?,?,?,?,?)',
                [$type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $severity, $entityType, $entityId, $by]
            );
            Database::execute('INSERT INTO notification_recipients (notification_id, user_id) VALUES (?,?)', [$id, $userId]);
            return $id;
        });
    }

    /** Same as toPermission() but also reaches inactive accounts, so nobody misses an access change. */
    public static function toPermissionIncludingInactive(string $permission, string $type, string $title, string $message, string $severity = 'info', ?string $entityType = null, ?int $entityId = null, ?int $by = null): ?int
    {
        $ids = array_column(Database::fetchAll(
            'SELECT DISTINCT ur.user_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE p.code = ?', [$permission]
        ), 'user_id');
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return null;
        }
        return Database::transaction(function () use ($ids, $type, $title, $message, $severity, $entityType, $entityId, $by) {
            $id = Database::insert(
                'INSERT INTO notifications (type, title, message, severity, entity_type, entity_id, created_by) VALUES (?,?,?,?,?,?,?)',
                [$type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $severity, $entityType, $entityId, $by]
            );
            foreach ($ids as $uid) {
                Database::execute('INSERT INTO notification_recipients (notification_id, user_id) VALUES (?,?)', [$id, $uid]);
            }
            return $id;
        });
    }

    public static function toPermission(string $permission, string $type, string $title, string $message, string $severity = 'info', ?string $entityType = null, ?int $entityId = null, ?int $by = null): ?int
    {
        $ids = array_column(Database::fetchAll(
            'SELECT DISTINCT ur.user_id FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE p.code = ?', [$permission]
        ), 'user_id');
        return self::create($type, $title, $message, $severity, $entityType, $entityId, $ids, $by);
    }

    public static function toDepartment(int $departmentId, string $type, string $title, string $message, string $severity = 'info', ?int $by = null): ?int
    {
        $ids = array_column(Database::fetchAll("SELECT id FROM users WHERE department_id = ? AND status = 'active'", [$departmentId]), 'id');
        return self::create($type, $title, $message, $severity, null, null, $ids, $by);
    }

    public static function listFor(int $userId, bool $unreadOnly, int $limit, int $offset): array
    {
        $where = $unreadOnly ? 'AND nr.read_at IS NULL' : '';
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM notification_recipients nr WHERE nr.user_id = ? $where", [$userId])['c'];
        $rows  = Database::fetchAll(
            "SELECT n.id, n.type, n.title, n.message, n.severity, n.entity_type, n.entity_id, n.created_at, nr.read_at
             FROM notification_recipients nr JOIN notifications n ON n.id = nr.notification_id
             WHERE nr.user_id = ? $where ORDER BY n.id DESC LIMIT ? OFFSET ?", [$userId, $limit, $offset]
        );
        return [$rows, $total];
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::fetchOne('SELECT COUNT(*) c FROM notification_recipients WHERE user_id = ? AND read_at IS NULL', [$userId])['c'];
    }

    public static function markRead(int $userId, int $notificationId): void
    {
        $n = Database::execute('UPDATE notification_recipients SET read_at = COALESCE(read_at, NOW()) WHERE user_id = ? AND notification_id = ?', [$userId, $notificationId]);
        if ($n === 0 && !Database::fetchOne('SELECT 1 FROM notification_recipients WHERE user_id = ? AND notification_id = ?', [$userId, $notificationId])) {
            throw HttpException::notFound('Notification not found.');
        }
    }

    public static function markAllRead(int $userId): int
    {
        return Database::execute('UPDATE notification_recipients SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
