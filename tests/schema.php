<?php
declare(strict_types=1);

/** Central-database tests: structure, constraints, triggers. Everything runs in rolled-back transactions. */
use App\Core\{Config, Database};

echo "Central database (all departments)\n";
if (!$dbUp || !$isTesting) { test('Central database tests', fn() => 'skip'); return; }

function expectDbError(callable $fn, string $needle = ''): void
{
    try { $fn(); } catch (PDOException $e) {
        if ($needle !== '' && stripos($e->getMessage(), $needle) === false) { throw new RuntimeException("DB error did not mention '$needle': " . $e->getMessage()); }
        return;
    }
    throw new RuntimeException('database accepted a statement that should have been rejected' . ($needle ? " ($needle)" : ''));
}
function sx(callable $fn): void { Database::begin(); try { $fn(); } finally { Database::rollback(); } }
$n = 0;
function uid(): string { static $i = 0; return substr(bin2hex(random_bytes(4)), 0, 7) . (++$i); }

function sxUser(): int
{
    $d = (int) Database::fetchOne("SELECT id FROM departments WHERE code='ADMIN'")['id'];
    $u = uid();
    return Database::insert('INSERT INTO users (department_id, employee_no, username, email, password_hash, first_name, last_name) VALUES (?,?,?,?,?,?,?)', [$d, "S$u", "s$u", "s$u@t.local", 'x', 'S', 'T']);
}
function sxGuest(?int $idNo = null): int
{
    $u = uid();
    return Database::insert('INSERT INTO guests (guest_code, first_name, last_name) VALUES (?,?,?)', ["G$u", 'Test', 'Guest']);
}
function sxRoom(): array
{
    $u = uid();
    $t = Database::insert('INSERT INTO room_types (code, name) VALUES (?,?)', ["T$u", 'Type']);
    $r = Database::insert('INSERT INTO rooms (room_number, room_type_id) VALUES (?,?)', [substr("R$u", 0, 10), $t]);
    return [$t, $r];
}
function sxStay(?int $room = null): array
{
    $user = sxUser(); $guest = sxGuest(); [$type, $r] = sxRoom(); $room ??= $r;
    $res = Database::insert("INSERT INTO reservations (reservation_no, primary_guest_id, arrival_date, departure_date) VALUES (?,?,'2030-01-01','2030-01-03')", ['RS' . uid(), $guest]);
    $rr = Database::insert("INSERT INTO reservation_rooms (reservation_id, room_type_id, room_id, check_in_date, check_out_date, nightly_rate) VALUES (?,?,?,'2030-01-01','2030-01-03',1000)", [$res, $type, $room]);
    $st = Database::insert("INSERT INTO stays (reservation_room_id, guest_id, room_id, checked_in_at, checked_in_by) VALUES (?,?,?,NOW(),?)", [$rr, $guest, $room, $user]);
    return compact('user', 'guest', 'room', 'res', 'rr', 'st');
}
function sxFolio(array $s): int { return Database::insert("INSERT INTO folios (folio_no, guest_id, stay_id) VALUES (?,?,?)", ['F' . uid(), $s['guest'], $s['st']]); }
function sxPayment(int $folio, int $user, string $key = null, string $ref = null): int
{
    return Database::insert("INSERT INTO payments (payment_no, folio_id, method, amount, received_by, idempotency_key, reference_no) VALUES (?,?,'gcash',500,?,?,?)", ['P' . uid(), $folio, $user, $key ?? uid(), $ref]);
}

test('123 tables exist in 8 functional groups (A to H) and every table is mapped to a group', function () {
    $groups = Config::get('schema_groups');
    $mapped = [];
    foreach ($groups as $g) { foreach ($g['tables'] as $t) { $mapped[$t] = true; } }
    $db = Config::get('database.name');
    $actual = array_column(Database::fetchAll("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type='BASE TABLE' AND table_name <> 'schema_migrations'", [$db]), 't');
    sort($actual);
    $missing = array_diff($actual, array_keys($mapped)); $ghost = array_diff(array_keys($mapped), $actual);
    check(!$missing, 'tables not in a group: ' . implode(', ', $missing));
    check(!$ghost, 'mapped but missing tables: ' . implode(', ', $ghost));
    same(count($actual), 123);
});

test('All tables are InnoDB utf8mb4, have a primary key and a creation timestamp', function () {
    $db = Config::get('database.name');
    foreach (Database::fetchAll("SELECT table_name t, engine e, table_collation c FROM information_schema.tables WHERE table_schema = ? AND table_type='BASE TABLE'", [$db]) as $r) {
        same($r['e'], 'InnoDB'); check(str_starts_with((string) $r['c'], 'utf8mb4'), $r['t'] . ' collation ' . $r['c']);
    }
    $noPk = Database::fetchAll("SELECT t.table_name n FROM information_schema.tables t LEFT JOIN information_schema.table_constraints c ON c.table_schema=t.table_schema AND c.table_name=t.table_name AND c.constraint_type='PRIMARY KEY' WHERE t.table_schema=? AND t.table_type='BASE TABLE' AND c.constraint_name IS NULL", [$db]);
    same($noPk, []);
    $noTs = Database::fetchAll("SELECT t.table_name n FROM information_schema.tables t WHERE t.table_schema=? AND t.table_type='BASE TABLE' AND t.table_name NOT IN ('schema_migrations','system_settings','room_type_amenities','reservation_guests','user_roles','role_permissions','user_departments','notification_recipients') AND NOT EXISTS (SELECT 1 FROM information_schema.columns c WHERE c.table_schema=t.table_schema AND c.table_name=t.table_name AND c.column_name IN ('created_at','changed_at','attempted_at','last_activity'))", [$db]);
    same($noTs, []);
});

test('Every foreign key points at an existing table and cross-group links resolve', function () {
    $db = Config::get('database.name');
    $fks = Database::fetchAll("SELECT table_name t, referenced_table_name r FROM information_schema.key_column_usage WHERE table_schema=? AND referenced_table_name IS NOT NULL", [$db]);
    check(count($fks) > 200, 'expected 200+ foreign keys, found ' . count($fks));
    $pairs = array_map(fn($f) => $f['t'] . '>' . $f['r'], $fks);
    foreach (['room_blocks>work_orders', 'lost_found_items>security_incidents', 'folio_charges>folios', 'orders>stays', 'stays>approval_requests', 'work_order_parts>inventory_movements', 'room_service_charges>folio_charges', 'housekeeping_tasks>stays'] as $p) { check(in_array($p, $pairs, true), "missing FK $p"); }
});

test('Duplicate guest identity, room number and payment reference are rejected', function () {
    sx(function () {
        Database::execute("INSERT INTO guests (guest_code, first_name, last_name, id_type, id_number) VALUES ('GA1','A','B','passport','P123')");
        expectDbError(fn() => Database::execute("INSERT INTO guests (guest_code, first_name, last_name, id_type, id_number) VALUES ('GA2','A','B','passport','P123')"), 'uq_guests_identity');
        Database::execute("INSERT INTO guests (guest_code, first_name, last_name) VALUES ('GA3','A','B'), ('GA4','A','B')");   // guests without ID are allowed
        [$t] = sxRoom(); Database::execute("INSERT INTO rooms (room_number, room_type_id) VALUES ('101X', ?)", [$t]);
        expectDbError(fn() => Database::execute("INSERT INTO rooms (room_number, room_type_id) VALUES ('101X', ?)", [$t]), 'uq_rooms_number');
        $s = sxStay(); $f = sxFolio($s);
        sxPayment($f, $s['user'], null, 'GC-REF-1');
        expectDbError(fn() => sxPayment($f, $s['user'], null, 'GC-REF-1'), 'uq_payments_method_ref');
    });
});

test('Repeated requests cannot duplicate payments, charges, stock movements, orders or reservations', function () {
    sx(function () {
        $s = sxStay(); $f = sxFolio($s);
        sxPayment($f, $s['user'], 'KEY-PAY-1');
        expectDbError(fn() => sxPayment($f, $s['user'], 'KEY-PAY-1'), 'uq_payments_idem');
        Database::execute("INSERT INTO folio_charges (folio_id, charge_type, description, unit_amount, amount, business_date, idempotency_key) VALUES (?,'room','Night 1',1000,1000,CURDATE(),'KEY-CHG-1')", [$f]);
        expectDbError(fn() => Database::execute("INSERT INTO folio_charges (folio_id, charge_type, description, unit_amount, amount, business_date, idempotency_key) VALUES (?,'room','Night 1',1000,1000,CURDATE(),'KEY-CHG-1')", [$f]), 'uq_fc_idem');
        $loc = Database::insert("INSERT INTO inventory_locations (code, name) VALUES (?, 'Main')", ['L' . uid()]);
        $item = Database::insert("INSERT INTO inventory_items (sku, name, item_type, unit) VALUES (?, 'Rice', 'ingredient', 'kg')", ['SK' . uid()]);
        $mv = "INSERT INTO inventory_movements (inventory_item_id, location_id, movement_type, quantity_delta, balance_after, idempotency_key) VALUES (?,?,'receipt',5,5,'KEY-MV-1')";
        Database::execute($mv, [$item, $loc]);
        expectDbError(fn() => Database::execute($mv, [$item, $loc]), 'uq_im_idem');
        $o = "INSERT INTO orders (order_no, order_type, placed_by, idempotency_key) VALUES (?, 'dine_in', ?, 'KEY-ORD-1')";
        Database::execute($o, ['O' . uid(), $s['user']]);
        expectDbError(fn() => Database::execute($o, ['O' . uid(), $s['user']]), 'uq_orders_idem');
        $r = "INSERT INTO reservations (reservation_no, primary_guest_id, arrival_date, departure_date, idempotency_key) VALUES (?,?,'2030-02-01','2030-02-02','KEY-RES-1')";
        Database::execute($r, ['R' . uid(), $s['guest']]);
        expectDbError(fn() => Database::execute($r, ['R' . uid(), $s['guest']]), 'uq_reservations_idem');
    });
});

test('CHECK rules: date order, negative money/stock, ratings', function () {
    sx(function () {
        $s = sxStay();
        expectDbError(fn() => Database::execute("INSERT INTO reservations (reservation_no, primary_guest_id, arrival_date, departure_date) VALUES ('BADD', ?, '2030-05-05','2030-05-05')", [$s['guest']]), 'ck_reservations_dates');
        $loc = Database::insert("INSERT INTO inventory_locations (code, name) VALUES (?, 'Main')", ['L' . uid()]);
        $item = Database::insert("INSERT INTO inventory_items (sku, name, item_type, unit) VALUES (?, 'Oil', 'ingredient', 'l')", ['SK' . uid()]);
        expectDbError(fn() => Database::execute('INSERT INTO stock_balances (inventory_item_id, location_id, quantity) VALUES (?,?,-1)', [$item, $loc]), 'ck_sb_nonnegative');
        Database::execute('INSERT INTO stock_balances (inventory_item_id, location_id, quantity) VALUES (?,?,10)', [$item, $loc]);
        expectDbError(fn() => Database::execute('UPDATE stock_balances SET quantity = quantity - 11 WHERE inventory_item_id = ?', [$item]), 'ck_sb_nonnegative');
        $f = sxFolio($s);
        expectDbError(fn() => Database::execute("INSERT INTO payments (payment_no, folio_id, method, amount, received_by, idempotency_key) VALUES ('PZ', ?, 'cash', 0, ?, 'K0')", [$f, $s['user']]), 'ck_payments_amount');
        expectDbError(fn() => Database::execute("INSERT INTO payments (payment_no, folio_id, method, amount, received_by, idempotency_key) VALUES ('PY', ?, 'paypal', 5, ?, 'K1')", [$f, $s['user']]));   // unsupported method
        expectDbError(fn() => Database::execute("INSERT INTO guest_requests (request_no, description, satisfaction_rating) VALUES ('GRX','x',6)"), 'ck_gr_rating');
    });
});

test('Only one in-house stay per room; the room is free again after checkout', function () {
    sx(function () {
        $a = sxStay();
        $guest = sxGuest(); [$type] = sxRoom();
        $res = Database::insert("INSERT INTO reservations (reservation_no, primary_guest_id, arrival_date, departure_date) VALUES (?,?,'2030-01-01','2030-01-03')", ['RS' . uid(), $guest]);
        $rr2 = Database::insert("INSERT INTO reservation_rooms (reservation_id, room_type_id, room_id, check_in_date, check_out_date, nightly_rate) VALUES (?,?,?,'2030-01-01','2030-01-03',1000)", [$res, $type, $a['room']]);
        $ins = fn() => Database::execute('INSERT INTO stays (reservation_room_id, guest_id, room_id, checked_in_at, checked_in_by) VALUES (?,?,?,NOW(),?)', [$rr2, $guest, $a['room'], $a['user']]);
        expectDbError($ins, 'uq_stays_one_active_per_room');
        Database::execute("UPDATE stays SET status='checked_out', checked_out_at=NOW() WHERE id = ?", [$a['st']]);
        $ins();   // now allowed
    });
});

test('Payments: core fields immutable, verification allowed, no un-verifying, no deletes', function () {
    sx(function () {
        $s = sxStay(); $f = sxFolio($s); $p = sxPayment($f, $s['user'], null, 'REF-9');
        expectDbError(fn() => Database::execute('UPDATE payments SET amount = 1 WHERE id = ?', [$p]), 'immutable');
        expectDbError(fn() => Database::execute("UPDATE payments SET method = 'cash' WHERE id = ?", [$p]), 'immutable');
        expectDbError(fn() => Database::execute("UPDATE payments SET reference_no = 'CHANGED' WHERE id = ?", [$p]), 'immutable');
        Database::execute("UPDATE payments SET status='verified', verified_by=?, verified_at=NOW() WHERE id = ?", [$s['user'], $p]);
        expectDbError(fn() => Database::execute("UPDATE payments SET status='pending' WHERE id = ?", [$p]), 'can only be reversed');
        Database::execute("UPDATE payments SET status='reversed' WHERE id = ?", [$p]);
        expectDbError(fn() => Database::execute("UPDATE payments SET status='verified' WHERE id = ?", [$p]), 'cannot change status');
        expectDbError(fn() => Database::execute('DELETE FROM payments WHERE id = ?', [$p]), 'cannot be deleted');
    });
});

test('Ledgers are append-only: charges, stock movements, audit log, status histories', function () {
    sx(function () {
        $s = sxStay(); $f = sxFolio($s);
        $c = Database::insert("INSERT INTO folio_charges (folio_id, charge_type, description, unit_amount, amount, business_date, idempotency_key) VALUES (?,'room','Night',1000,1000,CURDATE(),?)", [$f, uid()]);
        expectDbError(fn() => Database::execute('UPDATE folio_charges SET amount = 1 WHERE id = ?', [$c]), 'append-only');
        expectDbError(fn() => Database::execute('DELETE FROM folio_charges WHERE id = ?', [$c]), 'cannot be deleted');
        // a reversal is the sanctioned correction
        Database::execute("INSERT INTO folio_charges (folio_id, charge_type, description, unit_amount, amount, business_date, reverses_charge_id, idempotency_key) VALUES (?,'adjustment','Reversal',-1000,-1000,CURDATE(),?,?)", [$f, $c, uid()]);
        $net = Database::fetchOne('SELECT SUM(amount) a FROM folio_charges WHERE folio_id = ?', [$f])['a'];
        same((float) $net, 0.0);
        Database::execute("INSERT INTO audit_logs (action, entity_type) VALUES ('t.test','test')");
        expectDbError(fn() => Database::execute("UPDATE audit_logs SET action='x' WHERE action='t.test'"), 'append-only');
        expectDbError(fn() => Database::execute("DELETE FROM audit_logs WHERE action='t.test'"), 'cannot be deleted');
        Database::execute("INSERT INTO room_status_history (room_id, to_status) VALUES (?, 'cleaning')", [$s['room']]);
        expectDbError(fn() => Database::execute('UPDATE room_status_history SET to_status = ? WHERE room_id = ?', ['vacant_ready', $s['room']]), 'append-only');
    });
});

test('Historical records cannot be deleted: guests, reservations, stays, orders, incidents, work orders', function () {
    sx(function () {
        $s = sxStay();
        foreach ([['guests', $s['guest']], ['reservations', $s['res']], ['reservation_rooms', $s['rr']], ['stays', $s['st']]] as [$t, $id]) {
            expectDbError(fn() => Database::execute("DELETE FROM $t WHERE id = ?", [$id]), 'cannot be deleted');
        }
        $inc = Database::insert("INSERT INTO security_incidents (incident_no, category, title, description, occurred_at, reported_by) VALUES (?,'theft','t','d',NOW(),?)", ['IN' . uid(), $s['user']]);
        expectDbError(fn() => Database::execute('DELETE FROM security_incidents WHERE id = ?', [$inc]), 'cannot be deleted');
        $wo = Database::insert("INSERT INTO work_orders (work_order_no, title, created_by) VALUES (?, 'Fix AC', ?)", ['WO' . uid(), $s['user']]);
        expectDbError(fn() => Database::execute('DELETE FROM work_orders WHERE id = ?', [$wo]), 'cannot be deleted');
    });
});

test('Issued invoices are frozen; drafts can be edited; credit notes link to the original', function () {
    sx(function () {
        $s = sxStay(); $f = sxFolio($s);
        $i = Database::insert("INSERT INTO invoices (invoice_no, folio_id, guest_id, total) VALUES (?,?,?,100)", ['IN' . uid(), $f, $s['guest']]);
        Database::execute('UPDATE invoices SET total = 120 WHERE id = ?', [$i]);   // draft: editable
        Database::execute("UPDATE invoices SET status='issued', issue_date=CURDATE(), issued_by=?, issued_at=NOW() WHERE id = ?", [$s['user'], $i]);
        expectDbError(fn() => Database::execute('UPDATE invoices SET total = 5 WHERE id = ?', [$i]), 'cannot be edited');
        $cn = Database::insert("INSERT INTO invoices (invoice_no, folio_id, guest_id, invoice_type, original_invoice_id, total) VALUES (?,?,?,'credit_note',?,-120)", ['CN' . uid(), $f, $s['guest'], $i]);
        check($cn > 0);
        Database::execute("UPDATE invoices SET status='void', void_reason='test' WHERE id = ?", [$i]);
        expectDbError(fn() => Database::execute("UPDATE invoices SET status='issued' WHERE id = ?", [$i]), 'invalid status');
    });
});

test('Foreign keys reject orphans (e.g. reservation for a guest that does not exist)', function () {
    sx(function () {
        expectDbError(fn() => Database::execute("INSERT INTO reservations (reservation_no, primary_guest_id, arrival_date, departure_date) VALUES ('ORPH', 999999999, '2030-01-01','2030-01-02')"), 'foreign key');
        expectDbError(fn() => Database::execute("INSERT INTO orders (order_no, order_type, placed_by, room_id) VALUES ('ORPH2','room_service',1,999999999)"), 'foreign key');
    });
});

test('Maintenance restriction links to a work order; lost items can link to a security incident', function () {
    sx(function () {
        $s = sxStay();
        $wo = Database::insert("INSERT INTO work_orders (work_order_no, title, created_by, room_id, blocks_room) VALUES (?, 'Leak', ?, ?, 1)", ['WO' . uid(), $s['user'], $s['room']]);
        $b = Database::insert("INSERT INTO room_blocks (room_id, block_type, reason, work_order_id, starts_on, created_by) VALUES (?, 'maintenance', 'Leak', ?, CURDATE(), ?)", [$s['room'], $wo, $s['user']]);
        same((int) Database::fetchOne('SELECT work_order_id w FROM room_blocks WHERE id = ?', [$b])['w'], $wo);
        $inc = Database::insert("INSERT INTO security_incidents (incident_no, category, title, description, occurred_at, reported_by) VALUES (?,'missing_property','t','d',NOW(),?)", ['IN' . uid(), $s['user']]);
        Database::insert("INSERT INTO lost_found_items (item_no, description, found_by, security_incident_id) VALUES (?, 'Wallet', ?, ?)", ['LF' . uid(), $s['user'], $inc]);
        expectDbError(fn() => Database::execute("INSERT INTO room_blocks (room_id, block_type, reason, starts_on, ends_on, created_by) VALUES (?, 'maintenance', 'x', '2030-02-02', '2030-02-01', ?)", [$s['room'], $s['user']]), 'ck_room_blocks_dates');
    });
});

test('Order, kitchen status trail and billing references share one order record', function () {
    sx(function () {
        $s = sxStay(); $f = sxFolio($s);
        $cat = Database::insert("INSERT INTO menu_categories (code, name) VALUES (?, 'Mains')", ['C' . uid()]);
        $mi = Database::insert("INSERT INTO menu_items (category_id, sku, name, price) VALUES (?, ?, 'Adobo', 250)", [$cat, 'M' . uid()]);
        $o = Database::insert("INSERT INTO orders (order_no, order_type, stay_id, room_id, placed_by) VALUES (?, 'room_service', ?, ?, ?)", ['O' . uid(), $s['st'], $s['room'], $s['user']]);
        Database::execute("INSERT INTO order_items (order_id, menu_item_id, item_name, quantity, unit_price) VALUES (?,?,'Adobo',2,250)", [$o, $mi]);
        Database::execute("INSERT INTO order_status_history (order_id, from_status, to_status, changed_by) VALUES (?, NULL, 'placed', ?), (?, 'placed', 'preparing', ?)", [$o, $s['user'], $o, $s['user']]);
        $c = Database::insert("INSERT INTO folio_charges (folio_id, charge_type, description, unit_amount, amount, business_date, source_type, source_id, idempotency_key) VALUES (?,'room_service','Order',500,500,CURDATE(),'order',?,?)", [$f, $o, uid()]);
        Database::execute('INSERT INTO room_service_charges (order_id, folio_id, folio_charge_id) VALUES (?,?,?)', [$o, $f, $c]);
        expectDbError(fn() => Database::execute('INSERT INTO room_service_charges (order_id, folio_id, folio_charge_id) VALUES (?,?,?)', [$o, $f, $c]), 'uq_rsc');   // billed once
        $total = Database::fetchOne('SELECT SUM(quantity*unit_price) t FROM order_items WHERE order_id = ?', [$o])['t'];
        same((float) $total, 500.0);
    });
});

test('Seeds: nine departments, expense categories and the Admin-only database.view permission', function () {
    same((int) Database::fetchOne('SELECT COUNT(*) c FROM departments')['c'], 9);
    check((int) Database::fetchOne('SELECT COUNT(*) c FROM expense_categories')['c'] >= 7);
    $has = Database::fetchAll("SELECT r.code FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE p.code='database.view'");
    same(array_column($has, 'code'), ['admin']);
});

test('Admin > Central database: overview, schema health and table details (admin only)', function () use ($router) {
    Database::begin();
    try {
        mkUser('dbadmin', 'ADMIN', ['admin']); mkUser('clerk77', 'FRONT_OFFICE', ['front_office']);
        $a = new Client($router); $a->login('dbadmin', 'Str0ngPassw0rd');
        [$st, $b] = $a->call('GET', '/api/v1/admin/database');
        same($st, 200); same($b['data']['totals']['tables'], 123); same($b['data']['totals']['migrations_pending'], 0);
        foreach ($b['data']['health'] as $h) { check($h['ok'], 'health check failed: ' . $h['check'] . ' - ' . $h['detail']); }
        same(count($b['data']['groups']), 8);
        [$st, $b] = $a->call('GET', '/api/v1/admin/database/tables/payments');
        same($st, 200); same($b['data']['group'], 'C: Billing and finance'); check(count($b['data']['protections']) >= 2); check(count($b['data']['columns']) > 10);
        same($a->call('GET', '/api/v1/admin/database/tables/not_a_table')[0], 404);
        same($a->call('GET', '/api/v1/admin/database/tables/users;DROP')[0], 404);       // never reaches SQL
        $c = new Client($router); $c->login('clerk77', 'Str0ngPassw0rd');
        same($c->call('GET', '/api/v1/admin/database')[0], 403);
    } finally { Database::rollback(); }
});
