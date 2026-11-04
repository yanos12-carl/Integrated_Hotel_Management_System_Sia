<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Database, Paginator, Request, Response};
use App\Services\{AuditService, NotificationService};
use App\Validators\Validator;

final class NotificationController extends BaseController
{
    public function index(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 20);
        [$rows, $total] = NotificationService::listFor($this->uid($r), (bool) $r->input('unread'), $per, $off);
        return $this->paged($rows, $total, $page, $per);
    }

    public function unreadCount(Request $r, array $p = []): Response
    {
        return Response::success(['count' => NotificationService::unreadCount($this->uid($r))]);
    }

    public function read(Request $r, array $p = []): Response
    {
        NotificationService::markRead($this->uid($r), (int) $p['id']);
        return Response::success(null, 'Marked as read.');
    }

    public function readAll(Request $r, array $p = []): Response
    {
        return Response::success(['updated' => NotificationService::markAllRead($this->uid($r))]);
    }

    /** Admin: everything sent, with read counts. */
    public function adminIndex(Request $r, array $p = []): Response
    {
        [$page, $per, $off] = Paginator::params($r, 15);
        $total = (int) Database::fetchOne('SELECT COUNT(*) c FROM notifications')['c'];
        $rows = Database::fetchAll(
            "SELECT n.id, n.type, n.title, n.message, n.severity, n.entity_type, n.entity_id, n.created_at,
                    (SELECT COUNT(*) FROM notification_recipients x WHERE x.notification_id = n.id) AS recipients,
                    (SELECT COUNT(*) FROM notification_recipients x WHERE x.notification_id = n.id AND x.read_at IS NOT NULL) AS read_count
             FROM notifications n ORDER BY n.id DESC LIMIT ? OFFSET ?", [$per, $off]
        );
        return $this->paged($rows, $total, $page, $per);
    }

    public function broadcast(Request $r, array $p = []): Response
    {
        $d = Validator::validate($r->body, ['title' => 'required|string|max:150', 'message' => 'required|string|max:500', 'severity' => 'required|in:info,warning,critical', 'department_id' => 'nullable|int']);
        $actor = $this->uid($r);
        if ($d['department_id']) {
            $ids = array_column(Database::fetchAll("SELECT id FROM users WHERE department_id = ? AND status = 'active'", [$d['department_id']]), 'id');
        } else {
            $ids = array_column(Database::fetchAll("SELECT id FROM users WHERE status = 'active'"), 'id');
        }
        $nid = NotificationService::create('admin.broadcast', $d['title'], $d['message'], $d['severity'], null, null, $ids, $actor);
        AuditService::log('notification.broadcast', 'notification', $nid, null, ['title' => $d['title'], 'department_id' => $d['department_id'], 'recipients' => count($ids)], $actor);
        return Response::created(['id' => $nid, 'recipients' => count($ids)], 'Notification sent.');
    }
}
