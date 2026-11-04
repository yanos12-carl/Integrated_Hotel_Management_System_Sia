<?php
declare(strict_types=1);

/**
 * Optional demo dataset for the Admin dashboard and Analytics page.
 *
 *   php database/seed-demo.php           seed a sample hotel month (refuses if data already exists)
 *   php database/seed-demo.php --status  show what is in the database
 *
 * Every record is prefixed "DM-" so it can be told apart from real entries.
 * The ledgers are append-only by design, so demo rows cannot be deleted: to clear
 * them, rebuild the database with  php database/migrate.php fresh --confirm=<dbname>.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Run this from the command line.\n"); exit(1); }
if (Config::get('app.env') === 'production') { fwrite(STDERR, "Refusing to seed demo data in production.\n"); exit(1); }

$args = array_slice($argv, 1);
$statusOnly = in_array('--status', $args, true);
$force      = in_array('--force', $args, true);

/** Deterministic pseudo-random so a reseed produces the same hotel. */
mt_srand(20261022);
$pick = static fn(array $a) => $a[mt_rand(0, count($a) - 1)];

$one = static function (string $sql, array $p = []) {
    $row = Database::fetchOne($sql, $p);
    return $row === false || $row === null ? null : reset($row);
};

if ($statusOnly) {
    $t = static function (string $table): int {
        $row = Database::fetchOne("SELECT COUNT(*) c FROM `$table`");
        return (int) ($row['c'] ?? 0);
    };
    foreach (['room_types', 'rooms', 'guests', 'reservations', 'stays', 'folios', 'folio_charges', 'payments', 'invoices', 'orders', 'order_items', 'housekeeping_tasks', 'work_orders', 'security_incidents'] as $tbl) {
        printf("  %-22s %6d rows\n", $tbl, $t($tbl));
    }
    exit(0);
}

$existing = (int) $one('SELECT COUNT(*) FROM rooms');
if ($existing > 0 && !$force) {
    fwrite(STDERR, "This database already holds {$existing} room(s). Demo data is not seeded.\n");
    fwrite(STDERR, "Use --force to add the demo hotel anyway, or rebuild with:\n");
    fwrite(STDERR, "  php database/migrate.php fresh --confirm=" . Config::get('database.name') . "\n");
    exit(1);
}

$adminId = (int) $one("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.code='admin' AND u.status='active' ORDER BY u.id LIMIT 1");
if ($adminId === 0) { fwrite(STDERR, "No active administrator found. Sign in once and create an admin before seeding.\n"); exit(1); }

echo "Seeding demo hotel data (prefix DM-) ...\n";
$now = time();

Database::transaction(function () use ($one, $pick, $adminId, $now) {

    // ---- room types -------------------------------------------------------
    $roomTypes = [
        ['DM-DLK', 'Deluxe King',      2, 1, '1 King',   32, 1, 4800],
        ['DM-DLT', 'Deluxe Twin',      2, 2, '2 Queen',  34, 2, 4600],
        ['DM-PRM', 'Premium Suite',    3, 2, '1 King + Sofa', 58, 3, 9800],
        ['DM-FAM', 'Family Room',      4, 3, '2 Queen + Bunk', 46, 4, 7200],
    ];
    $typeIds = [];
    $rates   = [];
    foreach ($roomTypes as [$code, $name, $adults, $children, $bed, $sqm, $order, $rate]) {
        $typeIds[$code] = Database::insert(
            'INSERT INTO room_types (code, name, description, max_adults, max_children, bed_configuration, size_sqm, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,1)',
            [$code, $name, $name . ' room for the ' . strtolower($name) . ' experience', $adults, $children, $bed, $sqm, $order]
        );
        $rates[$code] = $rate;
    }
    $rate = $rates;

    // ---- rooms ------------------------------------------------------------
    $rooms = [];
    $floor = 2;
    $num = 101;
    foreach ($typeIds as $code => $typeId) {
        $count = ['DM-DLK' => 10, 'DM-DLT' => 8, 'DM-PRM' => 4, 'DM-FAM' => 4][$code];
        for ($i = 0; $i < $count; $i++) {
            $id = Database::insert('INSERT INTO rooms (room_number, floor, room_type_id, status, is_active) VALUES (?,?,?,"vacant_ready",1)',
                ['D' . $num, $floor, $typeId]);
            $rooms[] = ['id' => $id, 'number' => 'D' . $num, 'type' => $code, 'rate' => $rate[$code]];
            $num++;
            if ($num % 10 === 1) { $floor++; }
        }
    }

    // ---- guests -----------------------------------------------------------
    $first = ['Maria', 'Jose', 'Anna', 'Mark', 'Grace', 'Paolo', 'Celine', 'Ramon', 'Tina', 'Luis', 'Bea', 'Carlo', 'Diana', 'Elias', 'Farah', 'Gino', 'Hana', 'Ivan', 'Joy', 'Ken', 'Liza', 'Mika', 'Nina', 'Omar', 'Pia', 'Rene', 'Sofia', 'Tara', 'Umi', 'Vince'];
    $last  = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Villanueva', 'Ramos', 'Delgado', 'Mercado', 'Aquino', 'Castillo', 'Navarro', 'Salazar', 'Padilla', 'Domingo', 'Lorenzo'];
    $nat   = ['Philippines', 'Singapore', 'Japan', 'United States', 'Australia', 'Canada', 'United Kingdom', 'Germany'];
    $guests = [];
    for ($i = 1; $i <= 64; $i++) {
        $fn    = $pick($first);
        $ln    = $pick($last);
        $email = strtolower($fn . '.' . $ln . $i) . '@demo.local';
        $phone = '+63' . mt_rand(900000000, 999999999);
        $id = Database::insert(
            'INSERT INTO guests (guest_code, first_name, last_name, nationality, id_type, id_number, email, phone, email_normalized, phone_normalized, is_vip, status, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                'DM-G' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), $fn, $ln, $pick($nat),
                mt_rand(0, 3) === 0 ? 'passport' : 'national_id',
                'DM-ID-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                $email, $phone, $email, $phone,
                mt_rand(0, 9) === 0 ? 1 : 0, 'active', $adminId, date('Y-m-d H:i:s', $now - mt_rand(86400, 86400 * 60)),
            ]
        );
        $guests[] = ['id' => $id, 'name' => "$fn $ln", 'vip' => mt_rand(0, 9) === 0];
    }

    // ---- menu -------------------------------------------------------------
    $catId = (int) $one("SELECT id FROM menu_categories ORDER BY id LIMIT 1");
    if ($catId === 0) {
        $catId = Database::insert('INSERT INTO menu_categories (code, name, service_area, display_order, is_active) VALUES (?,?,"both",1,1)', ['DM-CAT', 'Demo menu']);
    }
    $menu = [];
    $dishes = [
        ['DM-M-001', 'Chicken Adobo', 420, 'kitchen'], ['DM-M-002', 'Beef Kalatang', 480, 'kitchen'],
        ['DM-M-003', 'Pancit Bihon', 320, 'kitchen'], ['DM-M-004', 'Garlic Rice', 110, 'kitchen'],
        ['DM-M-005', 'Halo Halo', 280, 'pastry'],  ['DM-M-006', 'Cold Brew', 220, 'bar'],
        ['DM-M-007', 'Tropical Punch', 260, 'bar'], ['DM-M-008', 'Club Sandwich', 350, 'kitchen'],
    ];
    foreach ($dishes as [$sku, $name, $price, $station]) {
        $menu[] = ['id' => Database::insert('INSERT INTO menu_items (category_id, sku, name, price, station, is_available, is_active) VALUES (?,?,?,?,?,1,1)', [$catId, $sku, $name, $price, $station]), 'name' => $name, 'price' => $price];
    }

    // ---- stays, folios, charges, payments, invoices ------------------------
    $sources = ['walk_in', 'phone', 'online', 'agent', 'other'];
    $stayRows = [];
    $folioRows = [];
    $seq = 0;
    $occupiedRooms = [];

    // 45 days of history, today, and the next fortnight of arrivals.
    $plan = [];
    for ($d = 44; $d >= 1; $d--) { $plan[] = ['offset' => -$d, 'state' => 'past']; }
    $plan[] = ['offset' => 0, 'state' => 'current'];
    for ($d = 1; $d <= 14; $d++) { $plan[] = ['offset' => $d, 'state' => 'future']; }

    foreach ($plan as $slot) {
        $offset   = $slot['offset'];
        $state    = $slot['state'];
        $arrival  = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));
        $bookings = $state === 'future' ? mt_rand(1, 3) : mt_rand(2, 5);
        for ($b = 0; $b < $bookings; $b++) {
            $seq++;
            $guest     = $pick($guests);
            $room      = $pick($rooms);
            // only one in-house stay may exist per room
            if ($state === 'current') {
                $guard = 0;
                while (in_array($room['id'], $occupiedRooms, true) && $guard++ < count($rooms)) { $room = $pick($rooms); }
                if (in_array($room['id'], $occupiedRooms, true)) { continue; }
                $occupiedRooms[] = $room['id'];
            }
            $nights    = mt_rand(1, 4);
            $departure = date('Y-m-d', strtotime("{$arrival} +{$nights} days"));
            $source    = $pick($sources);
            $future    = $state === 'future';
            $current   = $state === 'current';

            $resStatus = $future ? 'confirmed' : ($current ? 'checked_in' : 'checked_out');
            $rrStatus  = $future ? 'booked' : ($current ? 'checked_in' : 'checked_out');

            $resId = Database::insert(
                'INSERT INTO reservations (reservation_no, primary_guest_id, status, source, arrival_date, departure_date, adults, children, is_group, special_requests, idempotency_key, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?)',
                [
                    'DM-R' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT), $guest['id'], $resStatus, $source,
                    $arrival, $departure, mt_rand(1, 3), mt_rand(0, 2),
                    $guest['vip'] ? 'VIP guest, welcome drink on arrival' : null,
                    'DM-RES-' . $seq, $adminId, date('Y-m-d H:i:s', strtotime("{$arrival} -" . mt_rand(1, 20) . ' days')),
                ]
            );

            $rrId = Database::insert(
                'INSERT INTO reservation_rooms (reservation_id, room_type_id, room_id, check_in_date, check_out_date, nightly_rate, adults, children, status) VALUES (?,?,?,?,?,?,?,?,?)',
                [$resId, (int) $one('SELECT room_type_id FROM rooms WHERE id=?', [$room['id']]), $room['id'], $arrival, $departure, $room['rate'], 2, 0, $rrStatus]
            );

            if ($future) { continue; }

            $checkIn  = strtotime($arrival . ' 14:00:00');
            $checkOut = strtotime($departure . ' 12:00:00');
            $stayId = Database::insert(
                'INSERT INTO stays (reservation_room_id, guest_id, room_id, status, checked_in_at, expected_checkout_at, checked_out_at, is_early_checkin, is_late_checkout, checked_in_by, checked_out_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,0,0,?,?,?,?)',
                [
                    $rrId, $guest['id'], $room['id'], $current ? 'in_house' : 'checked_out',
                    date('Y-m-d H:i:s', $checkIn), date('Y-m-d H:i:s', $checkOut),
                    $current ? null : date('Y-m-d H:i:s', $checkOut),
                    $adminId, $current ? null : $adminId,
                    date('Y-m-d H:i:s', $checkIn), date('Y-m-d H:i:s', $current ? $now : $checkOut),
                ]
            );

            if ($current) {
                Database::execute("UPDATE rooms SET status='occupied' WHERE id=?", [$room['id']]);
            } elseif (mt_rand(0, 5) === 0) {
                Database::execute("UPDATE rooms SET status=? WHERE id=?", [$pick(['cleaning', 'awaiting_inspection', 'checkout_pending_clean']), $room['id']]);
            }

            // folio
            $folioNo = 'DM-F' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $folioId = Database::insert(
                'INSERT INTO folios (folio_no, guest_id, stay_id, reservation_id, folio_type, status, currency, created_by, created_at) VALUES (?,?,?,?,"guest",?, "PHP", ?, ?)',
                [$folioNo, $guest['id'], $stayId, $resId, $current ? 'open' : 'settled', $adminId, date('Y-m-d H:i:s', $checkIn)]
            );

            // room charges, one per night
            $chargeSeq = 0;
            for ($n = 0; $n < $nights; $n++) {
                $chargeSeq++;
                $biz = date('Y-m-d', strtotime("{$arrival} +{$n} days"));
                Database::insert(
                    'INSERT INTO folio_charges (folio_id, charge_type, description, quantity, unit_amount, amount, tax_amount, business_date, source_type, source_id, idempotency_key, posted_by, created_at) VALUES (?,?,?,1,?,?,?,?,?,?,?,?,?)',
                    [$folioId, 'room', 'Room charge · night ' . ($n + 1), $room['rate'], $room['rate'], 0, $biz, 'stay', $stayId, 'DM-CH-' . $seq . '-' . $chargeSeq, $adminId, date('Y-m-d H:i:s', strtotime($biz . ' 23:00:00'))]
                );
            }

            // a few F&B charges
            $fnbCount = mt_rand(0, 3);
            for ($f = 0; $f < $fnbCount; $f++) {
                $dish = $pick($menu);
                $qty  = mt_rand(1, 3);
                $amt  = $dish['price'] * $qty;
                $biz  = date('Y-m-d', strtotime($arrival . ' +' . mt_rand(0, max(0, $nights - 1)) . ' days'));
                Database::insert(
                    'INSERT INTO folio_charges (folio_id, charge_type, description, quantity, unit_amount, amount, tax_amount, business_date, source_type, source_id, idempotency_key, posted_by, created_at) VALUES (?,?,?,?,?,?,0,?,?,?,?,?,?)',
                    [$folioId, 'fnb', $dish['name'] . ' ×' . $qty, $qty, $dish['price'], $amt, $biz, 'order', null, 'DM-FNB-' . $seq . '-' . $f, $adminId, date('Y-m-d H:i:s', strtotime($biz . ' 20:00:00'))]
                );
            }

            // payment (verified) and sometimes an invoice
            $total = (float) $one('SELECT COALESCE(SUM(amount),0) FROM folio_charges WHERE folio_id=?', [$folioId]);
            if ($total > 0 && !$current) {
                $method = $pick(['cash', 'card', 'gcash', 'maya', 'bank_transfer']);
                $paid   = round($total * (mt_rand(0, 9) === 0 ? 0.6 : 1.0), 2);
                Database::insert(
                    'INSERT INTO payments (payment_no, folio_id, guest_id, method, amount, currency, status, reference_no, received_by, received_at, verified_by, verified_at, idempotency_key, created_at) VALUES (?,?,?,?,?,"PHP","verified",?,?,?,?,?,?,?)',
                    [
                        'DM-P' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT), $folioId, $guest['id'], $method, $paid,
                        'DM-REF-' . $seq, $adminId, date('Y-m-d H:i:s', strtotime($departure . ' 11:00:00')),
                        $adminId, date('Y-m-d H:i:s', strtotime($departure . ' 11:05:00')), 'DM-PAY-' . $seq, date('Y-m-d H:i:s', strtotime($departure . ' 11:05:00')),
                    ]
                );
                if (mt_rand(0, 1) === 1) {
                    $invTotal = round($total * 1.12, 2);
                    Database::insert(
                        'INSERT INTO invoices (invoice_no, folio_id, guest_id, invoice_type, status, issue_date, due_date, subtotal, tax_total, service_total, discount_total, total, issued_by, issued_at, created_at) VALUES (?,?,?,"invoice","issued",?,?,?,?,0,0,?,?,?,?)',
                        [
                            'DM-I' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT), $folioId, $guest['id'],
                            $departure, date('Y-m-d', strtotime($departure . ' +7 days')),
                            $total, round($total * 0.12, 2), $invTotal, $adminId,
                            date('Y-m-d H:i:s', strtotime($departure . ' 12:00:00')), date('Y-m-d H:i:s', strtotime($departure . ' 12:00:00')),
                        ]
                    );
                }
            }

            $stayRows[] = ['stay' => $stayId, 'guest' => $guest['id'], 'room' => $room['id'], 'arrival' => $arrival, 'current' => $current, 'folio' => $folioId];
            $folioRows[] = $folioId;
        }
    }

    // ---- F&B orders -------------------------------------------------------
    $orderSeq = 0;
    foreach (array_slice($stayRows, 0, 40) as $s) {
        $orderSeq++;
        $placedAt = date('Y-m-d H:i:s', strtotime($s['arrival'] . ' 19:00:00'));
        $orderId = Database::insert(
            'INSERT INTO orders (order_no, order_type, status, room_id, guest_id, customer_name, priority, vat_rate, service_charge_rate, discount_rate, payment_status, billed_at, idempotency_key, placed_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,0.12,0.10,0,?,?,?,?,?,?)',
            [
                'DM-O' . str_pad((string) $orderSeq, 4, '0', STR_PAD_LEFT), 'room_service', 'billed',
                $s['room'], $s['guest'], 'In-house guest', $pick(['low', 'normal', 'normal', 'high']),
                'charged_to_room', $placedAt, 'DM-ORD-' . $orderSeq, $adminId, $placedAt, $placedAt,
            ]
        );
        $items = mt_rand(1, 3);
        for ($i = 0; $i < $items; $i++) {
            $dish = $pick($menu);
            Database::insert(
                'INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price, station, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
                [$orderId, $dish['id'], $dish['name'], mt_rand(1, 3), $dish['price'], 'kitchen', 'served', $placedAt, $placedAt]
            );
        }
    }

    // ---- housekeeping -----------------------------------------------------
    $hkTypes = ['checkout_clean', 'stayover_clean', 'deep_clean', 'turndown', 'public_area'];
    for ($i = 1; $i <= 34; $i++) {
        $room  = $pick($rooms);
        $due   = date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 2) . ' days +' . mt_rand(9, 20) . ' hours'));
        $state = $pick(['pending', 'pending', 'assigned', 'in_progress', 'awaiting_inspection', 'completed', 'completed']);
        Database::insert(
            'INSERT INTO housekeeping_tasks (task_no, task_type, room_id, priority, status, due_at, started_at, completed_at, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                'DM-H' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), $pick($hkTypes), $room['id'],
                $pick(['low', 'normal', 'normal', 'high', 'urgent']), $state, $due,
                in_array($state, ['in_progress', 'awaiting_inspection', 'completed'], true) ? $due : null,
                $state === 'completed' ? $due : null,
                $adminId, $due, $due,
            ]
        );
    }

    // ---- maintenance ------------------------------------------------------
    $woTitles = ['Aircon not cooling', 'Shower head leaking', 'Replace room key card', 'TV remote missing', 'Lamp flickering', 'Wi-Fi access point down', 'Door hinge loose', 'Toilet seat loose'];
    for ($i = 1; $i <= 16; $i++) {
        $room  = $pick($rooms);
        $state = $pick(['open', 'open', 'assigned', 'in_progress', 'completed', 'verified']);
        Database::insert(
            'INSERT INTO work_orders (work_order_no, room_id, title, description, priority, status, due_at, started_at, completed_at, blocks_room, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                'DM-W' . str_pad((string) $i, 4, '0', STR_PAD_LEFT), $room['id'], $pick($woTitles),
                'Reported by the guest during the stay.', $pick(['low', 'normal', 'normal', 'high']),
                $state, date('Y-m-d H:i:s', strtotime('+' . mt_rand(-2, 4) . ' days')),
                in_array($state, ['in_progress', 'completed', 'verified'], true) ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
                in_array($state, ['completed', 'verified'], true) ? date('Y-m-d H:i:s', strtotime('-6 hours')) : null,
                mt_rand(0, 3) === 0 ? 1 : 0, $adminId, date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 10) . ' days')), date('Y-m-d H:i:s'),
            ]
        );
    }

    // ---- security ---------------------------------------------------------
    $incidents = [
        ['missing_property', 'low', 'resolved', 'Guest reported a missing phone charger', 'Front desk, room D203'],
        ['disturbance', 'medium', 'investigating', 'Noise complaint from the floor above', 'Room D402'],
        ['trespass', 'medium', 'assigned', 'Unverified person in the pool area', 'Pool deck'],
        ['damage', 'low', 'closed', 'Damaged lobby planter', 'Main lobby'],
        ['safety', 'high', 'escalated', 'Slippery floor near the entrance', 'Main entrance'],
        ['theft', 'high', 'reported', 'Missing item from the gift shop', 'Gift shop'],
    ];
    foreach ($incidents as $i => [$cat, $sev, $st, $title, $where]) {
        Database::insert(
            'INSERT INTO security_incidents (incident_no, category, severity, status, title, description, location_description, occurred_at, is_restricted, reported_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,1,?,?,?)',
            [
                'DM-S' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), $cat, $sev, $st, $title,
                $title . '. Handled by the security team.', $where,
                date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 20) . ' days')), $adminId,
                date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 20) . ' days')), date('Y-m-d H:i:s'),
            ]
        );
    }
});

echo "Done. Open the Admin dashboard to see the analytics.\n";
echo "Rebuild without the demo data:  php database/migrate.php fresh --confirm=" . Config::get('database.name') . "\n";
