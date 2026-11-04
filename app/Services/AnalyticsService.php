<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Read-only analytics for the Admin dashboard and the Analytics page.
 * Every figure is computed live from the central database: no caching, no writes,
 * and no figure is exposed that the Admin pages do not already show.
 */
final class AnalyticsService
{
    /** @var int[] Report windows a caller may ask for. */
    private const RANGES = [7, 14, 30, 90];

    public static function range(int $days): int
    {
        return in_array($days, self::RANGES, true) ? $days : 14;
    }

    public static function report(int $days = 14): array
    {
        $days  = self::range($days);
        $from  = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to    = date('Y-m-d');
        $dates = self::dates($from, $to);

        return [
            'range'        => ['days' => $days, 'from' => $from, 'to' => $to, 'label' => $days . ' days'],
            'kpi'          => self::kpi($from),
            'occupancy'    => self::occupancy(),
            'revenue'      => self::revenue($from, $dates),
            'front_office' => self::frontOffice($from, $dates),
            'operations'   => self::operations($from),
            'workforce'    => self::workforce($from, $dates),
            'audit'        => self::audit($from, $dates),
        ];
    }

    // ------------------------------------------------------------------ KPI

    /** Headline counters shown as cards across the dashboard. */
    public static function kpi(string $from): array
    {
        $rooms = Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'occupied'), 0) AS occupied,
                    COALESCE(SUM(status = 'vacant_ready'), 0) AS available,
                    COALESCE(SUM(status IN ('checkout_pending_clean','cleaning','awaiting_inspection')), 0) AS servicing
             FROM rooms WHERE is_active = 1"
        ) ?? ['total' => 0, 'occupied' => 0, 'available' => 0, 'servicing' => 0];

        $total    = (int) $rooms['total'];
        $occupied = (int) $rooms['occupied'];

        return [
            'occupancy_rate'        => $total > 0 ? round($occupied / $total * 100, 1) : 0.0,
            'rooms_total'           => $total,
            'rooms_occupied'        => $occupied,
            'rooms_available'       => (int) $rooms['available'],
            'rooms_servicing'       => (int) $rooms['servicing'],
            'in_house_stays'        => self::count("SELECT COUNT(*) c FROM stays WHERE status = 'in_house'"),
            'in_house_guests'       => self::count("SELECT COUNT(DISTINCT guest_id) c FROM stays WHERE status = 'in_house'"),
            'arrivals_today'        => self::count('SELECT COUNT(*) c FROM stays WHERE DATE(checked_in_at) = CURDATE()'),
            'departures_today'      => self::count("SELECT COUNT(*) c FROM stays WHERE status = 'in_house' AND DATE(expected_checkout_at) = CURDATE()"),
            'reservations_today'    => self::count("SELECT COUNT(*) c FROM reservations WHERE arrival_date = CURDATE() AND status NOT IN ('cancelled','no_show')"),
            'reservations_upcoming' => self::count("SELECT COUNT(*) c FROM reservations WHERE arrival_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('cancelled','no_show')"),
            'guests_active'         => self::count("SELECT COUNT(*) c FROM guests WHERE status = 'active'"),
            'revenue_today'         => self::money("SELECT COALESCE(SUM(amount), 0) v FROM payments WHERE status = 'verified' AND DATE(received_at) = CURDATE()"),
            'revenue_period'        => self::money("SELECT COALESCE(SUM(amount), 0) v FROM payments WHERE status = 'verified' AND received_at >= ?", [$from]),
            'invoiced_period'       => self::money("SELECT COALESCE(SUM(total), 0) v FROM invoices WHERE status = 'issued' AND issue_date >= ?", [$from]),
            'outstanding_balance'   => self::money(
                "SELECT COALESCE(SUM(ch.amount), 0) - COALESCE(SUM(pa.total), 0) v
                 FROM folios f
                 LEFT JOIN folio_charges ch ON ch.folio_id = f.id
                 LEFT JOIN (SELECT folio_id, SUM(amount) AS total FROM payments WHERE status = 'verified' GROUP BY folio_id) pa ON pa.folio_id = f.id
                 WHERE f.status = 'open'"
            ),
            'open_folios'           => self::count("SELECT COUNT(*) c FROM folios WHERE status = 'open'"),
            'employees_total'       => self::count('SELECT COUNT(*) c FROM users'),
            'employees_active'      => self::count("SELECT COUNT(*) c FROM users WHERE status = 'active'"),
            'active_sessions'       => self::count('SELECT COUNT(*) c FROM sessions WHERE user_id IS NOT NULL AND last_activity > ?', [time() - 1800]),
            'successful_logins_24h' => self::count('SELECT COUNT(*) c FROM login_attempts WHERE success = 1 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'failed_logins_24h'     => self::count('SELECT COUNT(*) c FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'errors_24h'            => self::count('SELECT COUNT(*) c FROM error_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)'),
            'pending_approvals'     => self::count("SELECT COUNT(*) c FROM approval_requests WHERE status = 'pending'"),
            'open_work_orders'      => self::count("SELECT COUNT(*) c FROM work_orders WHERE status IN ('open','assigned','in_progress','on_hold')"),
            'overdue_work_orders'   => self::count("SELECT COUNT(*) c FROM work_orders WHERE status IN ('open','assigned','in_progress','on_hold') AND due_at IS NOT NULL AND due_at < NOW()"),
            'open_incidents'        => self::count("SELECT COUNT(*) c FROM security_incidents WHERE status IN ('reported','assigned','investigating','escalated')"),
            'pending_housekeeping'  => self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status IN ('pending','assigned','in_progress','awaiting_inspection')"),
            'open_orders'           => self::count("SELECT COUNT(*) c FROM orders WHERE status IN ('placed','accepted','preparing','ready')"),
        ];
    }

    // ------------------------------------------------------------- Occupancy

    public static function occupancy(): array
    {
        $byStatus = array_map(static fn(array $r): array => [
            'label' => self::roomStatusLabel((string) $r['status']),
            'value' => (int) $r['value'],
            'tone'  => self::roomStatusTone((string) $r['status']),
        ], Database::fetchAll(
            "SELECT status, COUNT(*) AS value FROM rooms WHERE is_active = 1 GROUP BY status ORDER BY value DESC"
        ));

        $byType = array_map(static fn(array $r): array => [
            'label'    => (string) $r['label'],
            'code'     => (string) $r['code'],
            'total'    => (int) $r['total'],
            'occupied' => (int) $r['occupied'],
            'rate'     => (int) $r['total'] > 0 ? round((int) $r['occupied'] / (int) $r['total'] * 100, 1) : 0.0,
        ], Database::fetchAll(
            "SELECT rt.code, rt.name AS label, COUNT(r.id) AS total, COALESCE(SUM(r.status = 'occupied'), 0) AS occupied
             FROM room_types rt LEFT JOIN rooms r ON r.room_type_id = rt.id AND r.is_active = 1
             WHERE rt.is_active = 1 GROUP BY rt.id, rt.code, rt.name ORDER BY rt.sort_order, rt.name"
        ));

        return ['by_status' => $byStatus, 'by_type' => $byType];
    }

    // ---------------------------------------------------------------- Revenue

    public static function revenue(string $from, array $dates): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(received_at) AS d, COALESCE(SUM(amount), 0) AS value, COUNT(*) AS count
             FROM payments WHERE status = 'verified' AND received_at >= ? GROUP BY DATE(received_at) ORDER BY d",
            [$from]
        );

        $byMethod = array_map(static fn(array $r): array => [
            'label' => self::methodLabel((string) $r['method']),
            'value' => (float) $r['value'],
            'count' => (int) $r['count'],
        ], Database::fetchAll(
            "SELECT method, COALESCE(SUM(amount), 0) AS value, COUNT(*) AS count
             FROM payments WHERE status = 'verified' AND received_at >= ? GROUP BY method ORDER BY value DESC",
            [$from]
        ));

        $total = array_sum(array_column($byMethod, 'value'));
        $count = array_sum(array_column($byMethod, 'count'));

        return [
            'series'   => self::fillSeries($dates, $rows, 'value'),
            'counts'   => self::fillSeries($dates, $rows, 'count'),
            'by_method'=> $byMethod,
            'total'    => $total,
            'count'    => $count,
            'average'  => $count > 0 ? round($total / $count, 2) : 0.0,
        ];
    }

    // ----------------------------------------------------------- Front office

    public static function frontOffice(string $from, array $dates): array
    {
        $sources = array_map(static fn(array $r): array => [
            'label' => self::sourceLabel((string) $r['source']),
            'value' => (int) $r['value'],
        ], Database::fetchAll(
            "SELECT source, COUNT(*) AS value FROM reservations
             WHERE created_at >= ? GROUP BY source ORDER BY value DESC",
            [$from]
        ));

        $statuses = array_map(static fn(array $r): array => [
            'label' => ucfirst(str_replace('_', ' ', (string) $r['status'])),
            'value' => (int) $r['value'],
        ], Database::fetchAll(
            "SELECT status, COUNT(*) AS value FROM reservations GROUP BY status ORDER BY value DESC"
        ));

        $arrivals = self::fillSeries($dates, Database::fetchAll(
            "SELECT arrival_date AS d, COUNT(*) AS value FROM reservations
             WHERE arrival_date BETWEEN ? AND CURDATE() AND status NOT IN ('cancelled','no_show') GROUP BY arrival_date ORDER BY d",
            [$from]
        ), 'value');

        $departures = self::fillSeries($dates, Database::fetchAll(
            "SELECT departure_date AS d, COUNT(*) AS value FROM reservations
             WHERE departure_date BETWEEN ? AND CURDATE() AND status NOT IN ('cancelled','no_show') GROUP BY departure_date ORDER BY d",
            [$from]
        ), 'value');

        $nights = self::money(
            "SELECT COALESCE(SUM(DATEDIFF(rr.check_out_date, rr.check_in_date)), 0) v
             FROM reservation_rooms rr JOIN reservations r ON r.id = rr.reservation_id
             WHERE r.status NOT IN ('cancelled','no_show') AND rr.status <> 'cancelled'"
        );

        return [
            'by_source'   => $sources,
            'by_status'   => $statuses,
            'arrivals'    => $arrivals,
            'departures'  => $departures,
            'nights_booked'=> (int) $nights,
            'reservations'=> (int) self::count('SELECT COUNT(*) c FROM reservations'),
        ];
    }

    // -------------------------------------------------------------- Operations

    public static function operations(string $from): array
    {
        $housekeeping = [
            'pending'  => self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status IN ('pending','assigned')"),
            'active'   => self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status = 'in_progress'"),
            'inspect'  => self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status = 'awaiting_inspection'"),
            'overdue'  => self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status IN ('pending','assigned','in_progress') AND due_at IS NOT NULL AND due_at < NOW()"),
            'completed'=> self::count("SELECT COUNT(*) c FROM housekeeping_tasks WHERE status IN ('completed','inspected_ok') AND DATE(completed_at) >= ?", [$from]),
        ];

        $maintenance = [
            'open'    => self::count("SELECT COUNT(*) c FROM work_orders WHERE status IN ('open','assigned','in_progress','on_hold')"),
            'overdue' => self::count("SELECT COUNT(*) c FROM work_orders WHERE status IN ('open','assigned','in_progress','on_hold') AND due_at IS NOT NULL AND due_at < NOW()"),
            'blocking'=> self::count("SELECT COUNT(*) c FROM work_orders WHERE blocks_room = 1 AND status NOT IN ('verified','closed','cancelled')"),
            'done'    => self::count("SELECT COUNT(*) c FROM work_orders WHERE status IN ('verified','closed') AND DATE(updated_at) >= ?", [$from]),
        ];

        $security = [
            'open'     => self::count("SELECT COUNT(*) c FROM security_incidents WHERE status IN ('reported','assigned','investigating','escalated')"),
            'critical' => self::count("SELECT COUNT(*) c FROM security_incidents WHERE severity IN ('high','critical') AND status NOT IN ('resolved','closed')"),
            'resolved' => self::count("SELECT COUNT(*) c FROM security_incidents WHERE status IN ('resolved','closed') AND DATE(updated_at) >= ?", [$from]),
            'by_severity' => array_map(static fn(array $r): array => [
                'label' => ucfirst((string) $r['severity']),
                'value' => (int) $r['value'],
            ], Database::fetchAll(
                "SELECT severity, COUNT(*) AS value FROM security_incidents
                 WHERE status NOT IN ('resolved','closed') GROUP BY severity ORDER BY FIELD(severity,'critical','high','medium','low')"
            )),
        ];

        $kitchen = [
            'open_orders' => self::count("SELECT COUNT(*) c FROM orders WHERE status IN ('placed','accepted','preparing','ready')"),
            'orders_today'=> self::count('SELECT COUNT(*) c FROM orders WHERE DATE(created_at) = CURDATE()'),
            'waste_records'=> self::count('SELECT COUNT(*) c FROM food_waste_records WHERE waste_date >= ?', [$from]),
            'low_stock'   => self::count(
                "SELECT COUNT(*) c FROM (
                     SELECT ii.id FROM inventory_items ii
                     LEFT JOIN stock_balances sb ON sb.inventory_item_id = ii.id
                     WHERE ii.is_active = 1
                     GROUP BY ii.id, ii.reorder_level
                     HAVING COALESCE(SUM(sb.quantity), 0) <= ii.reorder_level
                 ) z"
            ),
        ];

        return [
            'housekeeping' => $housekeeping,
            'maintenance'  => $maintenance,
            'security'     => $security,
            'kitchen'      => $kitchen,
        ];
    }

    // --------------------------------------------------------------- Workforce

    public static function workforce(string $from, array $dates): array
    {
        $departments = array_map(static fn(array $r): array => [
            'label'   => (string) $r['label'],
            'total'   => (int) $r['total'],
            'active'  => (int) $r['active'],
            'inactive'=> (int) $r['inactive'],
        ], Database::fetchAll(
            "SELECT d.name AS label, COUNT(u.id) AS total,
                    COALESCE(SUM(u.status = 'active'), 0) AS active,
                    COALESCE(SUM(u.status <> 'active'), 0) AS inactive
             FROM departments d LEFT JOIN users u ON u.department_id = d.id
             GROUP BY d.id, d.name ORDER BY d.id"
        ));

        $roles = array_map(static fn(array $r): array => [
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
        ], Database::fetchAll(
            "SELECT r.name AS label, COUNT(ur.user_id) AS value
             FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id
             GROUP BY r.id, r.name ORDER BY value DESC, r.name"
        ));

        $signins = Database::fetchAll(
            "SELECT DATE(attempted_at) AS d,
                    COALESCE(SUM(success = 1), 0) AS ok,
                    COALESCE(SUM(success = 0), 0) AS failed
             FROM login_attempts WHERE attempted_at >= ? GROUP BY DATE(attempted_at) ORDER BY d",
            [$from]
        );

        $okSeries = self::fillSeries($dates, $signins, 'ok');
        $failedSeries = self::fillSeries($dates, $signins, 'failed');

        $failures = array_map(static fn(array $r): array => [
            'label' => self::failureLabel((string) $r['reason']),
            'value' => (int) $r['value'],
        ], Database::fetchAll(
            "SELECT reason, COUNT(*) AS value FROM login_attempts
             WHERE success = 0 AND attempted_at >= ? GROUP BY reason ORDER BY value DESC",
            [$from]
        ));

        return [
            'by_department' => $departments,
            'by_role'       => $roles,
            'signins'       => ['ok' => $okSeries, 'failed' => $failedSeries],
            'failure_reasons' => $failures,
        ];
    }

    // ------------------------------------------------------------------ Audit

    public static function audit(string $from, array $dates): array
    {
        $actions = array_map(static fn(array $r): array => [
            'label' => (string) $r['label'],
            'value' => (int) $r['value'],
        ], Database::fetchAll(
            "SELECT action AS label, COUNT(*) AS value FROM audit_logs
             WHERE created_at >= ? GROUP BY action ORDER BY value DESC LIMIT 8",
            [$from]
        ));

        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS value FROM audit_logs WHERE created_at >= ? GROUP BY DATE(created_at) ORDER BY d",
            [$from]
        );

        $errors = self::fillSeries($dates, Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS value FROM error_logs WHERE created_at >= ? GROUP BY DATE(created_at) ORDER BY d",
            [$from]
        ), 'value');

        return [
            'by_action' => $actions,
            'series'    => self::fillSeries($dates, $rows, 'value'),
            'errors'    => $errors,
        ];
    }

    // ----------------------------------------------------------------- Helpers

    private static function count(string $sql, array $params = []): int
    {
        $row = Database::fetchOne($sql, $params);
        return $row === null ? 0 : (int) $row['c'];
    }

    private static function money(string $sql, array $params = []): float
    {
        $row = Database::fetchOne($sql, $params);
        return $row === null ? 0.0 : (float) $row['v'];
    }

    /** @return array<int, array{date: string, label: string}> */
    private static function dates(string $from, string $to): array
    {
        $out = [];
        $cur = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        while ($cur <= $end) {
            $out[] = ['date' => $cur->format('Y-m-d'), 'label' => $cur->format('M j')];
            $cur = $cur->modify('+1 day');
        }
        return $out;
    }

    /**
     * Turns grouped rows into a gap-free daily series aligned with $dates.
     *
     * @param array<int, array{date: string, label: string}> $dates
     */
    private static function fillSeries(array $dates, array $rows, string $key): array
    {
        $map = [];
        foreach ($rows as $r) { $map[(string) $r['d']] = $r; }
        $out = [];
        foreach ($dates as $d) {
            $row  = $map[$d['date']] ?? null;
            $out[] = [
                'date'  => $d['date'],
                'label' => $d['label'],
                'value' => $row === null ? 0.0 : (float) $row[$key],
            ];
        }
        return $out;
    }

    private static function roomStatusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    private static function roomStatusTone(string $status): string
    {
        return match ($status) {
            'occupied'              => 'rose',
            'vacant_ready'          => 'sage',
            'checkout_pending_clean'=> 'khaki',
            'cleaning'              => 'khaki',
            'awaiting_inspection'   => 'amber',
            default                 => 'ink',
        };
    }

    private static function methodLabel(string $method): string
    {
        return match ($method) {
            'gcash'         => 'GCash',
            'maya'          => 'Maya',
            'bank_transfer' => 'Bank transfer',
            default         => ucfirst($method),
        };
    }

    private static function sourceLabel(string $source): string
    {
        return match ($source) {
            'walk_in' => 'Walk-in',
            'phone'   => 'Telephone',
            'online'  => 'Online',
            'agent'   => 'Travel agent',
            default   => ucfirst($source),
        };
    }

    private static function failureLabel(string $reason): string
    {
        return match ($reason) {
            ''                  => 'Unspecified',
            'invalid_password'  => 'Wrong password',
            'unknown_user'      => 'Unknown account',
            'inactive_user'     => 'Inactive account',
            'locked'            => 'Locked account',
            'csrf'              => 'Invalid request',
            default             => ucfirst(str_replace('_', ' ', $reason)),
        };
    }
}
