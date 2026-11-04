<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Read-only queries behind the Admin dashboard, audit viewer, login activity, errors and sessions. */
final class AdminQueryService
{
    public static function summary(int $userId): array
    {
        $one = fn(string $sql, array $p = []) => (int) Database::fetchOne($sql, $p)['c'];
        return [
            'users' => [
                'total'    => $one('SELECT COUNT(*) c FROM users'),
                'active'   => $one("SELECT COUNT(*) c FROM users WHERE status = 'active'"),
                'inactive' => $one("SELECT COUNT(*) c FROM users WHERE status = 'inactive'"),
                'locked'   => $one('SELECT COUNT(*) c FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()'),
            ],
            'active_sessions'     => $one('SELECT COUNT(*) c FROM sessions WHERE user_id IS NOT NULL AND last_activity > ?', [time() - 1800]),
            'failed_logins_24h'   => $one('SELECT COUNT(*) c FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'successful_logins_24h' => $one('SELECT COUNT(*) c FROM login_attempts WHERE success = 1 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'errors_24h'          => $one('SELECT COUNT(*) c FROM error_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'pending_approvals'   => $one("SELECT COUNT(*) c FROM approval_requests WHERE status = 'pending'"),
            'unread_notifications' => NotificationService::unreadCount($userId),
            'by_department' => Database::fetchAll("SELECT d.name, COUNT(u.id) AS total, SUM(u.status = 'active') AS active FROM departments d LEFT JOIN users u ON u.department_id = d.id GROUP BY d.id, d.name ORDER BY d.id"),
            'recent_audit'  => Database::fetchAll("SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at, COALESCE(CONCAT(u.first_name,' ',u.last_name),'System') AS actor FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8"),
        ];
    }

    public static function audit(array $f, string $order, int $limit, int $offset): array
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['q']))           { $where[] = '(a.action LIKE ? OR a.entity_type LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l); }
        if (!empty($f['user_id']))     { $where[] = 'a.user_id = ?'; $p[] = (int) $f['user_id']; }
        if (!empty($f['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'a.created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to']))   { $where[] = 'a.created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        $w = implode(' AND ', $where);
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM audit_logs a WHERE $w", $p)['c'];
        $rows = Database::fetchAll(
            "SELECT a.id, a.action, a.entity_type, a.entity_id, a.old_values, a.new_values, a.ip_address, a.created_at, COALESCE(CONCAT(u.first_name,' ',u.last_name),'System') AS actor
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $w ORDER BY $order LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset])
        );
        return [$rows, $total];
    }

    public static function logins(array $f, string $order, int $limit, int $offset): array
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['q'])) { $where[] = '(l.username LIKE ? OR l.ip_address LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l); }
        if (isset($f['success']) && $f['success'] !== '') { $where[] = 'l.success = ?'; $p[] = (int) $f['success']; }
        $w = implode(' AND ', $where);
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM login_attempts l WHERE $w", $p)['c'];
        $rows = Database::fetchAll("SELECT l.id, l.username, l.ip_address, l.user_agent, l.success, l.reason, l.attempted_at FROM login_attempts l WHERE $w ORDER BY $order LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset]));
        return [$rows, $total];
    }

    public static function errors(array $f, int $limit, int $offset): array
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['q'])) { $where[] = '(e.error_id LIKE ? OR e.message LIKE ? OR e.path LIKE ?)'; $l = '%' . addcslashes((string) $f['q'], '%_') . '%'; array_push($p, $l, $l, $l); }
        $w = implode(' AND ', $where);
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM error_logs e WHERE $w", $p)['c'];
        $rows = Database::fetchAll("SELECT e.id, e.error_id, e.level, e.message, e.context, e.method, e.path, e.created_at FROM error_logs e WHERE $w ORDER BY e.id DESC LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset]));
        return [$rows, $total];
    }

    /** Session handles are SHA-256 of the id: the real id (a credential) is never sent to the browser. */
    public static function sessions(int $limit, int $offset): array
    {
        $current = hash('sha256', (string) session_id());
        $total = (int) Database::fetchOne('SELECT COUNT(*) c FROM sessions WHERE user_id IS NOT NULL AND last_activity > ?', [time() - \App\Core\Session::lifetimeSeconds()])['c'];
        $rows = Database::fetchAll(
            "SELECT SHA2(s.id,256) AS handle, CONCAT(u.first_name,' ',u.last_name) AS user, u.username, s.ip_address, s.user_agent, FROM_UNIXTIME(s.last_activity) AS last_activity, s.created_at
             FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.last_activity > ? ORDER BY s.last_activity DESC LIMIT ? OFFSET ?", [time() - \App\Core\Session::lifetimeSeconds(), $limit, $offset]
        );
        foreach ($rows as &$r) { $r['is_current'] = $r['handle'] === $current; }
        return [$rows, $total];
    }

    public static function revokeSession(string $handle, int $actorId): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $handle)) { throw \App\Core\HttpException::notFound('Session not found.'); }
        if ($handle === hash('sha256', (string) session_id())) { throw \App\Core\HttpException::conflict('Use Sign out to end your own session.'); }
        $row = Database::fetchOne('SELECT s.user_id, u.username FROM sessions s LEFT JOIN users u ON u.id = s.user_id WHERE SHA2(s.id,256) = ?', [$handle]) ?? throw \App\Core\HttpException::notFound('Session not found.');
        Database::transaction(function () use ($handle, $row, $actorId) {
            Database::execute('DELETE FROM sessions WHERE SHA2(id,256) = ?', [$handle]);
            AuditService::log('session.revoke', 'user', $row['user_id'] ? (int) $row['user_id'] : null, null, null, $actorId);
        });
        if ($row['user_id']) {
            \App\Services\NotificationService::toUser((int) $row['user_id'], 'session.revoked', 'Your session was ended',
                'An administrator signed you out of a session. Sign in again if you were not the one who did it.',
                'warning', 'user', (int) $row['user_id'], $actorId);
        }
    }
}

