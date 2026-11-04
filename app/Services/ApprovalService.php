<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};
use App\Validators\Validator;

/** Generic approval workflow reused by refunds, voids, checkout overrides, etc. in later phases. */
final class ApprovalService
{
    public static function request(string $type, string $entityType, int $entityId, string $requiredPermission, int $requestedBy, string $reason, ?array $payload = null): int
    {
        return Database::transaction(function () use ($type, $entityType, $entityId, $requiredPermission, $requestedBy, $reason, $payload) {
            $id = Database::insert(
                'INSERT INTO approval_requests (request_type, entity_type, entity_id, required_permission, requested_by, reason, payload) VALUES (?,?,?,?,?,?,?)',
                [$type, $entityType, $entityId, $requiredPermission, $requestedBy, mb_substr($reason, 0, 500), $payload === null ? null : json_encode($payload)]
            );
            Database::execute("INSERT INTO approval_history (approval_request_id, actor_id, action, note) VALUES (?,?,'requested',?)", [$id, $requestedBy, mb_substr($reason, 0, 500)]);
            AuditService::log('approval.requested', 'approval_request', $id, null, ['type' => $type, 'entity' => "$entityType#$entityId"], $requestedBy);
            NotificationService::toPermission($requiredPermission, 'approval.pending', 'Approval needed', "A $type request needs your decision.", 'warning', 'approval_request', $id, $requestedBy);
            return $id;
        });
    }

    public static function list(?string $status, int $limit, int $offset, array $viewerPermissions): array
    {
        $where = ['1=1']; $p = [];
        $canManageUsers = in_array('user.manage', $viewerPermissions, true)
            && in_array('admin.dashboard', $viewerPermissions, true);
        if (!$canManageUsers) { $where[] = "a.request_type <> 'user.create'"; }
        if ($status && in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true)) { $where[] = 'a.status = ?'; $p[] = $status; }
        $w = implode(' AND ', $where);
        $total = (int) Database::fetchOne("SELECT COUNT(*) c FROM approval_requests a WHERE $w", $p)['c'];
        $rows = Database::fetchAll(
            "SELECT a.id, a.request_type, a.entity_type, a.entity_id, a.required_permission, a.reason, a.payload, a.status, a.decision_note, a.created_at, a.decided_at,
                    CONCAT(rb.first_name,' ',rb.last_name) AS requested_by, CONCAT(db.first_name,' ',db.last_name) AS decided_by
             FROM approval_requests a JOIN users rb ON rb.id = a.requested_by LEFT JOIN users db ON db.id = a.decided_by
             WHERE $w ORDER BY a.id DESC LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset])
        );
        foreach ($rows as &$r) {
            $r['can_decide'] = $r['status'] === 'pending' && in_array($r['required_permission'], $viewerPermissions, true);
            if ($r['request_type'] === 'user.create') {
                $r['can_decide'] = $r['can_decide'] && in_array('admin.dashboard', $viewerPermissions, true);
            }
            $r['request_data'] = $canManageUsers && $r['request_type'] === 'user.create'
                ? (json_decode((string) $r['payload'], true) ?: null)
                : null;
            unset($r['payload']);
        }
        return [$rows, $total];
    }

    public static function decide(int $id, array $in, int $actorId, array $actorPermissions): array
    {
        $d = Validator::validate($in, ['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:500']);
        return Database::transaction(function () use ($id, $d, $actorId, $actorPermissions) {
            $a = Database::fetchOne('SELECT * FROM approval_requests WHERE id = ? FOR UPDATE', [$id]) ?? throw HttpException::notFound('Approval request not found.');
            if ($a['status'] !== 'pending')                                   { throw HttpException::conflict('This request has already been decided.'); }
            if (!in_array($a['required_permission'], $actorPermissions, true)) { throw HttpException::forbidden('You are not authorised to decide this request.'); }
            if ($a['request_type'] === 'user.create' && !in_array('admin.dashboard', $actorPermissions, true)) {
                throw HttpException::forbidden('Only an administrator may decide an account request.');
            }
            if ((int) $a['requested_by'] === $actorId)                         { throw HttpException::forbidden('You cannot approve or reject your own request.'); }
            $account = null;
            if ($a['request_type'] === 'user.create' && $d['decision'] === 'approved') {
                $request = json_decode((string) $a['payload'], true);
                if (!is_array($request) || empty($request['department_id'])) {
                    throw HttpException::conflict('The account request is incomplete and cannot be approved.');
                }
                $account = UserService::approveAccountRequest($request, $actorId);
            }
            Database::execute('UPDATE approval_requests SET status=?, decided_by=?, decided_at=NOW(), decision_note=? WHERE id=?', [$d['decision'], $actorId, $d['note'], $id]);
            Database::execute('INSERT INTO approval_history (approval_request_id, actor_id, action, note) VALUES (?,?,?,?)', [$id, $actorId, $d['decision'] === 'approved' ? 'approved' : 'rejected', $d['note']]);
            AuditService::log('approval.' . $d['decision'], 'approval_request', $id, ['status' => 'pending'], ['status' => $d['decision']], $actorId);
            NotificationService::create('approval.decided', 'Request ' . $d['decision'], "Your {$a['request_type']} request was {$d['decision']}.", $d['decision'] === 'approved' ? 'info' : 'warning', 'approval_request', $id, [(int) $a['requested_by']], $actorId);
            $result = Database::fetchOne('SELECT id, status, decision_note, decided_at FROM approval_requests WHERE id = ?', [$id]);
            if ($account !== null) {
                $result['account'] = $account['user'];
                $result['temporary_password'] = $account['temporary_password'];
            }
            return $result;
        });
    }
}
