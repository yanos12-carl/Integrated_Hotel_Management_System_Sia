<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{Database, HttpException};
use App\Validators\Validator;

final class SecurityService
{
    private const INCIDENT_CATEGORIES = ['theft', 'missing_property', 'disturbance', 'accident', 'damage', 'emergency', 'safety', 'trespass', 'other'];
    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    private const INCIDENT_STATUSES = ['reported', 'assigned', 'investigating', 'escalated', 'resolved', 'closed'];
    private const SHIFT_TYPES = ['morning', 'afternoon', 'night'];
    private const SHIFT_STATUSES = ['scheduled', 'on_duty', 'completed', 'absent'];
    private const PATROL_STATUSES = ['ok', 'issue_found', 'missed'];
    private const INSPECTION_TYPES = ['fire', 'emergency_exit', 'extinguisher', 'alarm', 'drill', 'other'];
    private const INSPECTION_RESULTS = ['pass', 'fail', 'action_required'];

    public static function summary(): array
    {
        return [
            'incidents_open' => self::count("SELECT COUNT(*) c FROM security_incidents WHERE status NOT IN ('resolved','closed')"),
            'incidents_critical' => self::count("SELECT COUNT(*) c FROM security_incidents WHERE severity IN ('high','critical') AND status NOT IN ('resolved','closed')"),
            'visitors_inside' => self::count("SELECT COUNT(*) c FROM visitor_logs WHERE status = 'in'"),
            'shifts_today' => self::count("SELECT COUNT(*) c FROM guard_shifts WHERE shift_date = CURDATE() AND status IN ('scheduled','on_duty')"),
            'patrols_today' => self::count("SELECT COUNT(*) c FROM patrol_records WHERE DATE(checked_at) = CURDATE()"),
            'inspections_needing_action' => self::count("SELECT COUNT(*) c FROM safety_inspections WHERE result IN ('fail','action_required')"),
        ];
    }

    public static function securityUsers(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.username, u.first_name, u.last_name
             FROM users u JOIN departments d ON d.id = u.department_id
             WHERE d.code = 'SECURITY' AND u.status = 'active'
             ORDER BY u.last_name, u.first_name"
        );
    }

    public static function incidents(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::INCIDENT_STATUSES, true)) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(i.incident_no LIKE ? OR i.title LIKE ? OR i.location_description LIKE ?)';
            $like = '%' . addcslashes((string) $filters['q'], '%_') . '%';
            array_push($params, $like, $like, $like);
        }
        $clause = implode(' AND ', $where);
        $total = self::count("SELECT COUNT(*) c FROM security_incidents i WHERE $clause", $params);
        $rows = Database::fetchAll(
            "SELECT i.id, i.incident_no, i.category, i.severity, i.status, i.title, i.location_description,
                    i.occurred_at, i.created_at, reporter.username AS reporter,
                    (SELECT u.username FROM incident_assignments a JOIN users u ON u.id = a.assigned_to
                     WHERE a.incident_id = i.id AND a.unassigned_at IS NULL ORDER BY a.id DESC LIMIT 1) AS assigned_to,
                    (SELECT COUNT(*) FROM cctv_references c WHERE c.incident_id = i.id) AS evidence_count
             FROM security_incidents i JOIN users reporter ON reporter.id = i.reported_by
             WHERE $clause
             ORDER BY FIELD(i.status,'reported','assigned','investigating','escalated','resolved','closed'),
                      FIELD(i.severity,'critical','high','medium','low'), i.occurred_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        return [$rows, $total];
    }

    public static function incident(int $id): array
    {
        $row = Database::fetchOne(
            "SELECT i.*, reporter.username AS reporter
             FROM security_incidents i JOIN users reporter ON reporter.id = i.reported_by WHERE i.id = ?",
            [$id]
        ) ?? throw HttpException::notFound('Incident not found.');
        $row['updates'] = Database::fetchAll(
            'SELECT u.id, u.update_type, u.note, u.created_at, actor.username AS created_by
             FROM incident_updates u JOIN users actor ON actor.id = u.created_by
             WHERE u.incident_id = ? ORDER BY u.id DESC', [$id]
        );
        $row['assignments'] = Database::fetchAll(
            'SELECT a.id, a.assigned_to, a.assigned_by, a.created_at, a.unassigned_at, target.username AS username
             FROM incident_assignments a JOIN users target ON target.id = a.assigned_to
             WHERE a.incident_id = ? ORDER BY a.id DESC', [$id]
        );
        $row['evidence'] = Database::fetchAll(
            'SELECT c.id, c.camera_code, c.camera_location, c.footage_from, c.footage_to, c.storage_ref,
                    c.checksum_sha256, c.retention_until, c.is_restricted, c.recorded_by, c.created_at
             FROM cctv_references c WHERE c.incident_id = ? ORDER BY c.id DESC', [$id]
        );
        return $row;
    }

    public static function createIncident(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'category' => 'required|string|in:' . implode(',', self::INCIDENT_CATEGORIES),
            'severity' => 'required|string|in:' . implode(',', self::SEVERITIES),
            'title' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'location_description' => 'nullable|string|max:150',
            'room_id' => 'nullable|int|min:1',
            'guest_id' => 'nullable|int|min:1',
            'occurred_at' => 'required|string|max:19',
        ]);
        $data['occurred_at'] = self::dateTime($data['occurred_at'], 'occurred_at');
        $data['room_id'] = self::optionalId($data['room_id']);
        $data['guest_id'] = self::optionalId($data['guest_id']);
        self::assertReference('rooms', $data['room_id'], 'room_id');
        self::assertReference('guests', $data['guest_id'], 'guest_id');
        $incidentNo = 'SEC-' . strtoupper(bin2hex(random_bytes(6)));

        return Database::transaction(function () use ($data, $actorId, $incidentNo) {
            $id = Database::insert(
                'INSERT INTO security_incidents (incident_no, category, severity, title, description, location_description, room_id, guest_id, occurred_at, reported_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$incidentNo, $data['category'], $data['severity'], $data['title'], $data['description'], $data['location_description'], $data['room_id'], $data['guest_id'], $data['occurred_at'], $actorId]
            );
            AuditService::log('security.incident_reported', 'security_incident', $id, null, ['incident_no' => $incidentNo, 'category' => $data['category'], 'severity' => $data['severity']], $actorId);
            NotificationService::toPermission(
                'security.incident.manage', 'security.incident_reported', 'Security incident reported',
                $incidentNo . ' — ' . $data['title'], $data['severity'] === 'critical' ? 'critical' : ($data['severity'] === 'high' ? 'warning' : 'info'),
                'security_incident', $id, $actorId
            );
            return self::incident($id);
        });
    }

    public static function addIncidentUpdate(int $incidentId, array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'note' => 'required|string|max:5000',
            'update_type' => 'nullable|string|in:note,investigation,evidence',
        ]);
        $type = $data['update_type'] ?: 'note';
        return Database::transaction(function () use ($incidentId, $data, $type, $actorId) {
            self::incidentExists($incidentId);
            $id = Database::insert(
                'INSERT INTO incident_updates (incident_id, update_type, note, created_by) VALUES (?,?,?,?)',
                [$incidentId, $type, $data['note'], $actorId]
            );
            AuditService::log('security.incident_update_added', 'security_incident', $incidentId, null, ['update_type' => $type], $actorId);
            return Database::fetchOne(
                'SELECT iu.id, iu.update_type, iu.note, iu.created_at, u.username AS created_by FROM incident_updates iu JOIN users u ON u.id = iu.created_by WHERE iu.id = ?',
                [$id]
            );
        });
    }

    public static function updateIncidentStatus(int $incidentId, array $input, int $actorId): array
    {
        $data = Validator::validate($input, ['status' => 'required|string|in:' . implode(',', self::INCIDENT_STATUSES), 'note' => 'nullable|string|max:2000']);
        return Database::transaction(function () use ($incidentId, $data, $actorId) {
            $old = Database::fetchOne('SELECT status, severity FROM security_incidents WHERE id = ? FOR UPDATE', [$incidentId])
                ?? throw HttpException::notFound('Incident not found.');
            if ($old['status'] === 'closed' && $data['status'] !== 'closed') {
                throw HttpException::conflict('Closed incidents cannot be reopened.');
            }
            if ($old['status'] === $data['status']) {
                throw HttpException::conflict('Incident already has that status.');
            }
            Database::execute(
                "UPDATE security_incidents SET status = ?,
                    escalated_at = IF(? = 'escalated', COALESCE(escalated_at, NOW()), escalated_at),
                    resolved_at = IF(? IN ('resolved','closed'), COALESCE(resolved_at, NOW()), resolved_at),
                    closed_at = IF(? = 'closed', COALESCE(closed_at, NOW()), closed_at)
                 WHERE id = ?",
                [$data['status'], $data['status'], $data['status'], $data['status'], $incidentId]
            );
            $note = $data['note'] ?: 'Status changed from ' . $old['status'] . ' to ' . $data['status'] . '.';
            Database::insert(
                'INSERT INTO incident_updates (incident_id, update_type, note, created_by) VALUES (?,?,?,?)',
                [$incidentId, $data['status'] === 'escalated' ? 'escalation' : 'status_change', $note, $actorId]
            );
            AuditService::log('security.incident_status_changed', 'security_incident', $incidentId, ['status' => $old['status']], ['status' => $data['status']], $actorId);
            if ($data['status'] === 'escalated') {
                NotificationService::toPermission('security.incident.manage', 'security.incident_escalated', 'Security incident escalated', 'Incident #' . $incidentId . ' has been escalated.', 'warning', 'security_incident', $incidentId, $actorId);
            }
            return self::incident($incidentId);
        });
    }

    public static function assignIncident(int $incidentId, array $input, int $actorId): array
    {
        $data = Validator::validate($input, ['assigned_to' => 'required|int|min:1']);
        self::assertSecurityUser($data['assigned_to'], 'assigned_to');
        return Database::transaction(function () use ($incidentId, $data, $actorId) {
            self::incidentExists($incidentId);
            Database::execute('UPDATE incident_assignments SET unassigned_at = NOW() WHERE incident_id = ? AND unassigned_at IS NULL', [$incidentId]);
            Database::insert('INSERT INTO incident_assignments (incident_id, assigned_to, assigned_by) VALUES (?,?,?)', [$incidentId, $data['assigned_to'], $actorId]);
            Database::execute("UPDATE security_incidents SET status = IF(status = 'reported', 'assigned', status) WHERE id = ?", [$incidentId]);
            Database::insert('INSERT INTO incident_updates (incident_id, update_type, note, created_by) VALUES (?,?,?,?)', [$incidentId, 'investigation', 'Incident assigned to user #' . $data['assigned_to'] . '.', $actorId]);
            AuditService::log('security.incident_assigned', 'security_incident', $incidentId, null, ['assigned_to' => $data['assigned_to']], $actorId);
            $incident = Database::fetchOne('SELECT incident_no, title FROM security_incidents WHERE id = ?', [$incidentId]);
            NotificationService::toUser(
                $data['assigned_to'], 'security.incident_assigned', 'Security incident assigned',
                $incident['incident_no'] . ' — ' . $incident['title'], 'warning', 'security_incident', $incidentId, $actorId
            );
            return self::incident($incidentId);
        });
    }

    public static function cctvReferences(int $limit, int $offset): array
    {
        $total = self::count('SELECT COUNT(*) c FROM cctv_references');
        $rows = Database::fetchAll(
            'SELECT c.id, c.incident_id, i.incident_no, c.camera_code, c.camera_location, c.footage_from, c.footage_to,
                    c.storage_ref, c.checksum_sha256, c.retention_until, c.is_restricted, c.created_at, u.username AS recorded_by
             FROM cctv_references c LEFT JOIN security_incidents i ON i.id = c.incident_id
             JOIN users u ON u.id = c.recorded_by ORDER BY c.id DESC LIMIT ? OFFSET ?', [$limit, $offset]
        );
        return [$rows, $total];
    }

    public static function cameras(): array
    {
        return Database::fetchAll(
            'SELECT id, name, location, status, connection_type, browser_device_key, created_at, updated_at
             FROM cctv_cameras ORDER BY name'
        );
    }

    public static function saveCamera(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'browser_device_key' => 'required|string|min:64|max:64|regex:/^[a-fA-F0-9]{64}$/',
        ]);
        $deviceKey = strtolower($data['browser_device_key']);

        return Database::transaction(function () use ($data, $deviceKey, $actorId) {
            $existing = Database::fetchOne('SELECT id FROM cctv_cameras WHERE browser_device_key = ?', [$deviceKey]);
            try {
                if ($existing) {
                    $id = (int) $existing['id'];
                    Database::execute(
                        "UPDATE cctv_cameras SET name = ?, location = ?, connection_type = 'browser', added_by = ? WHERE id = ?",
                        [$data['name'], $data['location'], $actorId, $id]
                    );
                } else {
                    $id = Database::insert(
                        "INSERT INTO cctv_cameras (name, location, connection_type, browser_device_key, added_by) VALUES (?, ?, 'browser', ?, ?)",
                        [$data['name'], $data['location'], $deviceKey, $actorId]
                    );
                }
            } catch (\PDOException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw HttpException::conflict('A camera with that name is already registered.');
                }
                throw $e;
            }

            AuditService::log('security.camera_saved', 'cctv_camera', $id, null, [
                'name' => $data['name'],
                'location' => $data['location'],
                'connection_type' => 'browser',
            ], $actorId);

            return Database::fetchOne(
                'SELECT id, name, location, status, connection_type, browser_device_key, created_at, updated_at FROM cctv_cameras WHERE id = ?',
                [$id]
            );
        });
    }

    public static function removeCamera(int $id, int $actorId): void
    {
        Database::transaction(function () use ($id, $actorId): void {
            $camera = Database::fetchOne(
                'SELECT id, name, location, connection_type FROM cctv_cameras WHERE id = ? FOR UPDATE',
                [$id]
            );
            if (!$camera) {
                throw HttpException::notFound('Camera not found.');
            }

            AuditService::log('security.camera_removed', 'cctv_camera', $id, [
                'name' => $camera['name'],
                'location' => $camera['location'],
                'connection_type' => $camera['connection_type'],
            ], null, $actorId);
            Database::execute('DELETE FROM cctv_cameras WHERE id = ?', [$id]);
        });
    }

    public static function addCctvReference(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'incident_id' => 'nullable|int|min:1',
            'camera_code' => 'required|string|max:30',
            'camera_location' => 'nullable|string|max:100',
            'footage_from' => 'required|string|max:19',
            'footage_to' => 'required|string|max:19',
            'storage_ref' => 'nullable|string|max:255',
            'checksum_sha256' => 'nullable|string|max:64',
            'retention_until' => 'nullable|string|max:10',
        ]);
        $data['incident_id'] = self::optionalId($data['incident_id']);
        self::assertReference('security_incidents', $data['incident_id'], 'incident_id');
        $data['footage_from'] = self::dateTime($data['footage_from'], 'footage_from');
        $data['footage_to'] = self::dateTime($data['footage_to'], 'footage_to');
        if ($data['footage_to'] < $data['footage_from']) {
            throw HttpException::unprocessable(['footage_to' => 'Footage end must be at or after its start.']);
        }
        if ($data['checksum_sha256'] !== null && !preg_match('/^[a-fA-F0-9]{64}$/', $data['checksum_sha256'])) {
            throw HttpException::unprocessable(['checksum_sha256' => 'Checksum must be a 64-character SHA-256 hex value.']);
        }
        $data['retention_until'] = self::optionalDate($data['retention_until'], 'retention_until');
        return Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO cctv_references (incident_id, camera_code, camera_location, footage_from, footage_to, storage_ref, checksum_sha256, retention_until, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$data['incident_id'], $data['camera_code'], $data['camera_location'], $data['footage_from'], $data['footage_to'], $data['storage_ref'], $data['checksum_sha256'], $data['retention_until'], $actorId]
            );
            AuditService::log('security.cctv_reference_added', 'cctv_reference', $id, null, ['incident_id' => $data['incident_id'], 'camera_code' => $data['camera_code']], $actorId);
            return Database::fetchOne('SELECT * FROM cctv_references WHERE id = ?', [$id]);
        });
    }

    public static function visitors(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (in_array($filters['status'] ?? '', ['in', 'out'], true)) { $where[] = 'v.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['q'])) {
            $where[] = '(v.visitor_name LIKE ? OR v.badge_no LIKE ? OR v.purpose LIKE ?)';
            $like = '%' . addcslashes((string) $filters['q'], '%_') . '%';
            array_push($params, $like, $like, $like);
        }
        $clause = implode(' AND ', $where);
        $total = self::count("SELECT COUNT(*) c FROM visitor_logs v WHERE $clause", $params);
        $rows = Database::fetchAll(
            "SELECT v.id, v.visitor_name, v.id_type, v.id_number, v.purpose, v.host_guest_id, v.host_user_id,
                    v.stay_id, v.room_id, v.badge_no, v.status, v.checked_in_at, v.checked_out_at,
                    checkin.username AS checked_in_by, checkout.username AS checked_out_by
             FROM visitor_logs v JOIN users checkin ON checkin.id = v.checked_in_by
             LEFT JOIN users checkout ON checkout.id = v.checked_out_by
             WHERE $clause ORDER BY (v.status = 'in') DESC, v.checked_in_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        return [$rows, $total];
    }

    public static function checkInVisitor(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'visitor_name' => 'required|string|max:120',
            'id_type' => 'nullable|string|max:30',
            'id_number' => 'nullable|string|max:60',
            'purpose' => 'nullable|string|max:150',
            'host_guest_id' => 'nullable|int|min:1',
            'host_user_id' => 'nullable|int|min:1',
            'stay_id' => 'nullable|int|min:1',
            'room_id' => 'nullable|int|min:1',
            'badge_no' => 'nullable|string|max:20',
        ]);
        foreach (['host_guest_id', 'host_user_id', 'stay_id', 'room_id'] as $key) { $data[$key] = self::optionalId($data[$key]); }
        self::assertReference('guests', $data['host_guest_id'], 'host_guest_id');
        self::assertReference('users', $data['host_user_id'], 'host_user_id');
        self::assertReference('stays', $data['stay_id'], 'stay_id');
        self::assertReference('rooms', $data['room_id'], 'room_id');
        $id = Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO visitor_logs (visitor_name, id_type, id_number, purpose, host_guest_id, host_user_id, stay_id, room_id, badge_no, checked_in_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$data['visitor_name'], $data['id_type'], $data['id_number'], $data['purpose'], $data['host_guest_id'], $data['host_user_id'], $data['stay_id'], $data['room_id'], $data['badge_no'], $actorId]
            );
            AuditService::log('security.visitor_checked_in', 'visitor_log', $id, null, ['visitor_name' => $data['visitor_name'], 'room_id' => $data['room_id']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM visitor_logs WHERE id = ?', [$id]);
    }

    public static function checkOutVisitor(int $id, int $actorId): array
    {
        return Database::transaction(function () use ($id, $actorId) {
            $row = Database::fetchOne('SELECT status FROM visitor_logs WHERE id = ? FOR UPDATE', [$id]) ?? throw HttpException::notFound('Visitor record not found.');
            if ($row['status'] !== 'in') { throw HttpException::conflict('Visitor has already checked out.'); }
            Database::execute("UPDATE visitor_logs SET status = 'out', checked_out_at = NOW(), checked_out_by = ? WHERE id = ?", [$actorId, $id]);
            AuditService::log('security.visitor_checked_out', 'visitor_log', $id, ['status' => 'in'], ['status' => 'out'], $actorId);
            return Database::fetchOne('SELECT * FROM visitor_logs WHERE id = ?', [$id]);
        });
    }

    public static function shifts(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['date'])) { $where[] = 's.shift_date = ?'; $params[] = self::date($filters['date'], 'date'); }
        $clause = implode(' AND ', $where);
        $total = self::count("SELECT COUNT(*) c FROM guard_shifts s WHERE $clause", $params);
        $rows = Database::fetchAll(
            "SELECT s.*, u.username AS guard_username, u.first_name, u.last_name
             FROM guard_shifts s JOIN users u ON u.id = s.guard_user_id
             WHERE $clause ORDER BY s.shift_date DESC, s.starts_at LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        return [$rows, $total];
    }

    public static function createShift(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'guard_user_id' => 'required|int|min:1',
            'shift_date' => 'required|string|max:10',
            'shift_type' => 'required|string|in:' . implode(',', self::SHIFT_TYPES),
            'starts_at' => 'required|string|max:19',
            'ends_at' => 'required|string|max:19',
            'post' => 'nullable|string|max:80',
        ]);
        self::assertSecurityUser($data['guard_user_id'], 'guard_user_id');
        $data['shift_date'] = self::date($data['shift_date'], 'shift_date');
        $data['starts_at'] = self::dateTime($data['starts_at'], 'starts_at');
        $data['ends_at'] = self::dateTime($data['ends_at'], 'ends_at');
        if ($data['shift_date'] < date('Y-m-d')) {
            throw HttpException::unprocessable(['shift_date' => 'Shifts cannot be scheduled for a past date.']);
        }
        if ($data['ends_at'] <= $data['starts_at']) { throw HttpException::unprocessable(['ends_at' => 'Shift end must be after its start.']); }
        $id = Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO guard_shifts (guard_user_id, shift_date, shift_type, starts_at, ends_at, post) VALUES (?,?,?,?,?,?)',
                [$data['guard_user_id'], $data['shift_date'], $data['shift_type'], $data['starts_at'], $data['ends_at'], $data['post']]
            );
            AuditService::log('security.shift_scheduled', 'guard_shift', $id, null, ['guard_user_id' => $data['guard_user_id'], 'shift_date' => $data['shift_date']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM guard_shifts WHERE id = ?', [$id]);
    }

    public static function setShiftStatus(int $id, array $input, int $actorId): array
    {
        $data = Validator::validate($input, ['status' => 'required|string|in:' . implode(',', self::SHIFT_STATUSES)]);
        return Database::transaction(function () use ($id, $data, $actorId) {
            $old = Database::fetchOne('SELECT status FROM guard_shifts WHERE id = ? FOR UPDATE', [$id]) ?? throw HttpException::notFound('Shift not found.');
            $transitions = ['scheduled' => ['on_duty', 'absent'], 'on_duty' => ['completed'], 'completed' => [], 'absent' => []];
            if (!in_array($data['status'], $transitions[$old['status']], true)) {
                throw HttpException::conflict('That shift status transition is not allowed.');
            }
            Database::execute('UPDATE guard_shifts SET status = ? WHERE id = ?', [$data['status'], $id]);
            AuditService::log('security.shift_status_changed', 'guard_shift', $id, ['status' => $old['status']], ['status' => $data['status']], $actorId);
            return Database::fetchOne('SELECT * FROM guard_shifts WHERE id = ?', [$id]);
        });
    }

    public static function handovers(int $limit, int $offset): array
    {
        $total = self::count('SELECT COUNT(*) c FROM shift_handovers');
        $rows = Database::fetchAll(
            'SELECT h.*, f.username AS from_username, t.username AS to_username
             FROM shift_handovers h JOIN users f ON f.id = h.from_user_id
             LEFT JOIN users t ON t.id = h.to_user_id ORDER BY h.id DESC LIMIT ? OFFSET ?', [$limit, $offset]
        );
        return [$rows, $total];
    }

    public static function createHandover(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'from_shift_id' => 'required|int|min:1',
            'to_shift_id' => 'nullable|int|min:1',
            'to_user_id' => 'nullable|int|min:1',
            'summary' => 'required|string|max:5000',
            'open_issues' => 'nullable|string|max:5000',
            'equipment_status' => 'nullable|string|max:255',
        ]);
        $data['to_shift_id'] = self::optionalId($data['to_shift_id']);
        $data['to_user_id'] = self::optionalId($data['to_user_id']);
        self::assertReference('guard_shifts', $data['to_shift_id'], 'to_shift_id');
        if ($data['to_user_id'] !== null) { self::assertSecurityUser($data['to_user_id'], 'to_user_id'); }
        $from = Database::fetchOne('SELECT guard_user_id FROM guard_shifts WHERE id = ?', [$data['from_shift_id']]);
        if (!$from || (int) $from['guard_user_id'] !== $actorId) {
            throw HttpException::unprocessable(['from_shift_id' => 'Choose one of your assigned shifts.']);
        }
        if ($data['to_shift_id'] !== null) {
            $target = Database::fetchOne('SELECT guard_user_id FROM guard_shifts WHERE id = ?', [$data['to_shift_id']]);
            if ($data['to_user_id'] !== null && (int) $target['guard_user_id'] !== $data['to_user_id']) {
                throw HttpException::unprocessable(['to_user_id' => 'Choose the guard assigned to the receiving shift.']);
            }
            $data['to_user_id'] = (int) $target['guard_user_id'];
        }
        $id = Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO shift_handovers (from_shift_id, to_shift_id, from_user_id, to_user_id, summary, open_issues, equipment_status) VALUES (?,?,?,?,?,?,?)',
                [$data['from_shift_id'], $data['to_shift_id'], $actorId, $data['to_user_id'], $data['summary'], $data['open_issues'], $data['equipment_status']]
            );
            AuditService::log('security.shift_handover_created', 'shift_handover', $id, null, ['from_shift_id' => $data['from_shift_id'], 'to_user_id' => $data['to_user_id']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM shift_handovers WHERE id = ?', [$id]);
    }

    public static function acknowledgeHandover(int $id, int $actorId): array
    {
        return Database::transaction(function () use ($id, $actorId) {
            $row = Database::fetchOne('SELECT to_user_id, acknowledged_at FROM shift_handovers WHERE id = ? FOR UPDATE', [$id])
                ?? throw HttpException::notFound('Handover not found.');
            if ((int) $row['to_user_id'] !== $actorId) { throw HttpException::forbidden(); }
            if ($row['acknowledged_at'] !== null) { throw HttpException::conflict('Handover has already been acknowledged.'); }
            Database::execute('UPDATE shift_handovers SET acknowledged_at = NOW() WHERE id = ?', [$id]);
            AuditService::log('security.shift_handover_acknowledged', 'shift_handover', $id, null, ['acknowledged' => true], $actorId);
            return Database::fetchOne('SELECT * FROM shift_handovers WHERE id = ?', [$id]);
        });
    }

    public static function patrolSchedules(): array
    {
        return Database::fetchAll('SELECT * FROM patrol_schedules ORDER BY is_active DESC, name');
    }

    public static function createPatrolSchedule(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'name' => 'required|string|max:100',
            'route_description' => 'nullable|string|max:255',
            'start_time' => 'required|string|max:8',
            'interval_minutes' => 'nullable|int|min:1|max:65535',
            'days_of_week' => 'nullable|string|max:20',
        ]);
        $data['start_time'] = self::time($data['start_time'], 'start_time');
        $days = $data['days_of_week'] ?: '1,2,3,4,5,6,7';
        if (!preg_match('/^[1-7](,[1-7])*$/', $days)) {
            throw HttpException::unprocessable(['days_of_week' => 'Enter weekdays as comma-separated numbers from 1 to 7.']);
        }
        $id = Database::transaction(function () use ($data, $days, $actorId) {
            $id = Database::insert(
                'INSERT INTO patrol_schedules (name, route_description, start_time, interval_minutes, days_of_week) VALUES (?,?,?,?,?)',
                [$data['name'], $data['route_description'], $data['start_time'], $data['interval_minutes'], $days]
            );
            AuditService::log('security.patrol_schedule_created', 'patrol_schedule', $id, null, ['name' => $data['name']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM patrol_schedules WHERE id = ?', [$id]);
    }

    public static function checkpoints(): array
    {
        return Database::fetchAll(
            'SELECT c.*, (SELECT MAX(p.checked_at) FROM patrol_records p WHERE p.checkpoint_id = c.id) AS last_checked_at
             FROM patrol_checkpoints c ORDER BY c.is_active DESC, c.sequence_no, c.name'
        );
    }

    public static function createCheckpoint(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'code' => 'required|string|min:1|max:20|regex:/^[A-Za-z0-9._-]+$/',
            'name' => 'required|string|max:100',
            'location_description' => 'nullable|string|max:150',
            'sequence_no' => 'nullable|int|min:0|max:65535',
        ]);
        $id = Database::transaction(function () use ($data, $actorId) {
            try {
                $id = Database::insert(
                    'INSERT INTO patrol_checkpoints (code, name, location_description, sequence_no) VALUES (?,?,?,?)',
                    [$data['code'], $data['name'], $data['location_description'], $data['sequence_no'] ?? 0]
                );
            } catch (\PDOException $e) {
                if ((string) $e->getCode() === '23000') { throw HttpException::conflict('That checkpoint code is already in use.'); }
                throw $e;
            }
            AuditService::log('security.patrol_checkpoint_created', 'patrol_checkpoint', $id, null, ['code' => $data['code']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM patrol_checkpoints WHERE id = ?', [$id]);
    }

    public static function patrolRecords(int $limit, int $offset): array
    {
        $total = self::count('SELECT COUNT(*) c FROM patrol_records');
        $rows = Database::fetchAll(
            'SELECT p.*, c.code AS checkpoint_code, c.name AS checkpoint_name, u.username AS guard_username
             FROM patrol_records p JOIN patrol_checkpoints c ON c.id = p.checkpoint_id
             JOIN users u ON u.id = p.guard_user_id ORDER BY p.checked_at DESC LIMIT ? OFFSET ?', [$limit, $offset]
        );
        return [$rows, $total];
    }

    public static function recordPatrol(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'checkpoint_id' => 'required|int|min:1',
            'patrol_schedule_id' => 'nullable|int|min:1',
            'guard_shift_id' => 'nullable|int|min:1',
            'status' => 'required|string|in:' . implode(',', self::PATROL_STATUSES),
            'notes' => 'nullable|string|max:255',
        ]);
        $data['patrol_schedule_id'] = self::optionalId($data['patrol_schedule_id']);
        $data['guard_shift_id'] = self::optionalId($data['guard_shift_id']);
        self::assertReference('patrol_checkpoints', $data['checkpoint_id'], 'checkpoint_id');
        self::assertReference('patrol_schedules', $data['patrol_schedule_id'], 'patrol_schedule_id');
        if ($data['guard_shift_id'] !== null) {
            $shift = Database::fetchOne('SELECT guard_user_id FROM guard_shifts WHERE id = ?', [$data['guard_shift_id']]);
            if (!$shift || (int) $shift['guard_user_id'] !== $actorId) {
                throw HttpException::unprocessable(['guard_shift_id' => 'Choose one of your assigned shifts.']);
            }
        }
        $id = Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO patrol_records (patrol_schedule_id, guard_shift_id, checkpoint_id, guard_user_id, status, notes) VALUES (?,?,?,?,?,?)',
                [$data['patrol_schedule_id'], $data['guard_shift_id'], $data['checkpoint_id'], $actorId, $data['status'], $data['notes']]
            );
            AuditService::log('security.patrol_recorded', 'patrol_record', $id, null, ['checkpoint_id' => $data['checkpoint_id'], 'status' => $data['status']], $actorId);
            return $id;
        });
        return Database::fetchOne(
            'SELECT p.*, c.code AS checkpoint_code, c.name AS checkpoint_name FROM patrol_records p JOIN patrol_checkpoints c ON c.id = p.checkpoint_id WHERE p.id = ?',
            [$id]
        );
    }

    public static function inspections(int $limit, int $offset): array
    {
        $total = self::count('SELECT COUNT(*) c FROM safety_inspections');
        $rows = Database::fetchAll(
            'SELECT s.*, u.username AS inspector FROM safety_inspections s JOIN users u ON u.id = s.inspected_by ORDER BY s.inspected_at DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
        return [$rows, $total];
    }

    public static function createInspection(array $input, int $actorId): array
    {
        $data = Validator::validate($input, [
            'inspection_type' => 'required|string|in:' . implode(',', self::INSPECTION_TYPES),
            'area' => 'required|string|max:120',
            'result' => 'required|string|in:' . implode(',', self::INSPECTION_RESULTS),
            'notes' => 'nullable|string|max:500',
            'next_due_on' => 'nullable|string|max:10',
        ]);
        $data['next_due_on'] = self::optionalDate($data['next_due_on'], 'next_due_on');
        $id = Database::transaction(function () use ($data, $actorId) {
            $id = Database::insert(
                'INSERT INTO safety_inspections (inspection_type, area, inspected_by, result, notes, next_due_on) VALUES (?,?,?,?,?,?)',
                [$data['inspection_type'], $data['area'], $actorId, $data['result'], $data['notes'], $data['next_due_on']]
            );
            AuditService::log('security.safety_inspection_recorded', 'safety_inspection', $id, null, ['inspection_type' => $data['inspection_type'], 'result' => $data['result']], $actorId);
            return $id;
        });
        return Database::fetchOne('SELECT * FROM safety_inspections WHERE id = ?', [$id]);
    }

    private static function count(string $sql, array $params = []): int
    {
        return (int) (Database::fetchOne($sql, $params)['c'] ?? 0);
    }

    private static function incidentExists(int $id): void
    {
        if (!Database::fetchOne('SELECT id FROM security_incidents WHERE id = ?', [$id])) {
            throw HttpException::notFound('Incident not found.');
        }
    }

    private static function assertReference(string $table, ?int $id, string $field): void
    {
        if ($id === null) { return; }
        if (!in_array($table, ['rooms', 'guests', 'users', 'stays', 'security_incidents', 'guard_shifts', 'patrol_checkpoints', 'patrol_schedules'], true)) {
            throw new \LogicException('Unsupported reference table.');
        }
        if (!Database::fetchOne("SELECT id FROM $table WHERE id = ?", [$id])) {
            throw HttpException::unprocessable([$field => 'The selected record does not exist.']);
        }
    }

    private static function assertSecurityUser(int $id, string $field): void
    {
        if (!Database::fetchOne(
            "SELECT u.id FROM users u JOIN departments d ON d.id = u.department_id WHERE u.id = ? AND u.status = 'active' AND d.code = 'SECURITY'",
            [$id]
        )) {
            throw HttpException::unprocessable([$field => 'Choose an active Security department account.']);
        }
    }

    private static function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function dateTime(string $value, string $field): string
    {
        $normalized = str_replace('T', ' ', $value);
        foreach (['!Y-m-d H:i', '!Y-m-d H:i:s'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $normalized);
            if ($dt && $dt->format(substr($format, 1)) === $normalized) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        throw HttpException::unprocessable([$field => 'Enter a valid date and time.']);
    }

    private static function date(string $value, string $field): string
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            throw HttpException::unprocessable([$field => 'Enter a valid date.']);
        }
        return $value;
    }

    private static function optionalDate(?string $value, string $field): ?string
    {
        return $value === null || $value === '' ? null : self::date($value, $field);
    }

    private static function time(string $value, string $field): string
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw HttpException::unprocessable([$field => 'Enter a valid time.']);
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }
}
