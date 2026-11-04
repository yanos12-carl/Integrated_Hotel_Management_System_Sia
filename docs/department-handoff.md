# Department Developer Handoff

For the project manager to give to each department's developer. The **central database, Admin department, and Security department** are integrated in IHMS. Security uses the same PHP application, authenticated session, and central database as the other departments.

## 1. Rules every department must follow
1. **One database.** Use only the tables in `docs/data-dictionary.md` (ERD: `docs/erd/ihms-erd.md`). Add or change tables only by adding a new numbered file to `database/migrations/` (never edit an applied one) and running `php database/migrate.php migrate`. Then run `php database/tools/generate-docs.php` and add the new table names to the right group in `config/schema_groups.php` (the schema test fails if you forget).
2. **PDO prepared statements only** (`App\Core\Database`). No string-built SQL. No frontend access to MySQL.
3. **Wrap related writes in `Database::transaction(fn() => …)`**, and write the audit row (`AuditService::log`) and notification (`NotificationService::create / toPermission / toDepartment`) in the same transaction.
4. **Check permissions on every endpoint** with the `Authenticate` + `VerifyCsrf` + `Permission:<code>` middleware (see `routes/api.php`). The permission catalogue (65 codes, grouped by department) is already seeded; add new codes in a new seed file.
5. **Never delete or edit history.** The database refuses it (58 triggers). Correct a mistake by posting a *reversal* row (`folio_charges.reverses_charge_id`), a credit note, or a status change.
6. **Idempotency keys** are required for payments, folio charges, stock movements, refunds, stock receipts, orders and reservations. The client generates the key once per user action; a repeat returns the original record instead of creating a duplicate.
7. **Shared rules live in services, not controllers**, and must be reused by every department (see §3).
8. **Escape all output** (`e()` in PHP views, `esc()` in JS) and validate input with `App\Validators\Validator`.

## 2. Who writes which tables
"Owns" = creates and updates rows (through its own services). Everyone may read what their permissions allow.

| Department | Owns (writes) | Mostly reads |
|---|---|---|
| **Admin** (done) | Group A: users, roles, permissions, departments, settings, notifications, approvals, sessions | audit, errors |
| **Front Office** | guests, guest_contacts, guest_preferences, room_types, rooms (reservation-side fields), room_amenities, rate_plans, room_rates, reservations*, reservation_rooms, reservation_guests, stays, stay_room_changes, guest_requests*, deposits | folios, folio_charges, room_blocks, housekeeping_tasks |
| **Accounts and Finance** | folios, folio_charges, invoices, invoice_items, payments, payment_allocations, refunds, financial_adjustments, expenses, expense_categories, suppliers, supplier_invoices, financial_periods, daily_reconciliations, financial_transaction_refs | orders, stays, deposits |
| **Food and Beverage** | menu_categories, menu_items, restaurant_tables, table_reservations, orders, order_items, order_voids, order_feedback, room_service_charges*, order_payments* | recipes, stays |
| **Kitchen** | recipes, recipe_ingredients, temperature_logs, food_waste_records, stock_receipts (kitchen), order status progress | orders, order_items |
| **Inventory (Kitchen, Housekeeping, Maintenance share it)** | inventory_items, inventory_locations, stock_balances, inventory_batches, inventory_movements, stock_adjustments, replenishment_requests, stock_receipts | |
| **Housekeeping** | housekeeping_tasks, task_assignments, cleaning_checklists, checklist_items, task_checklist_results, room_inspections, inspection_results, linen_items, linen_movements, lost_found_items, lost_found_claims | rooms, stays |
| **Maintenance** | assets, maintenance_requests, work_orders, work_assignments, preventive_schedules, work_logs, work_order_parts, maintenance_costs, room_defects, **room_blocks** | rooms |
| **Security** | security_incidents, incident_updates, incident_assignments, visitor_logs, guard_shifts, shift_handovers, patrol_schedules, patrol_checkpoints, patrol_records, cctv_references, safety_inspections | guests, rooms |
| **Management** | nothing (read-only). Reports are queries over the tables above | everything permitted |

\* shared: created by one department's action but written through the shared service named below.

Security pages and workflows are integrated into IHMS. Security users sign in through the shared login, and their compact department navigation exposes incidents, visitor logs, guard shifts and handovers, patrols, CCTV references, and safety inspections. The JSON API uses the central Security tables in migration `008_security.sql`, shared sessions, CSRF protection, permission middleware, and audit logging. Security staff account requests go through the common Admin approval queue. The legacy compatibility tables from migration `013_security_app_central.sql` remain in the database and migration history; no separate Security server or login is required.

## 3. Shared services to build once (agree an owner)
| Service | Why one copy | Tables |
|---|---|---|
| `RoomStatusService` | The only code allowed to change `rooms.status`; writes `room_status_history` | rooms, room_status_history |
| `AvailabilityService` | Date-overlap check + restrictions; **lock the room row** (`SELECT … FOR UPDATE`) before assigning | reservation_rooms, room_blocks, stays |
| `FolioService` / `PaymentService` | Post charges and payments, compute balances, record `financial_transaction_refs` | folios, folio_charges, payments, payment_allocations |
| `InventoryService` | Insert the movement **and** update `stock_balances` in one transaction; the DB rejects negative stock | inventory_movements, stock_balances, inventory_batches |
| `ApprovalService` (**done**) | Second-person approval | approval_requests, approval_history |

## 4. Workflow data contracts
- **Checkout:** in one transaction: check folio balance (SUM of charges − allocated payments) is 0 or `stays.checkout_approval_id` is approved → `stays.status='checked_out'` → `rooms.status='checkout_pending_clean'` (+ history row) → insert `housekeeping_tasks (task_type='checkout_clean', source_type='stay')` → notifications → audit.
- **Room ready:** only when the latest `room_inspections.result='passed'` for the task **and** no active row in `room_blocks` for the room.
- **Order → Kitchen → Finance:** one `orders` row; each status change adds `order_status_history`; room-service posts one `folio_charges` row and one `room_service_charges` row (the DB allows only one per order).
- **Maintenance block:** `work_orders.blocks_room=1` → insert `room_blocks (work_order_id, status='active')`; release at `work_orders.status='verified'` by setting `room_blocks.status='released'`.
- **Stock:** movement insert (`quantity_delta` signed, `balance_after` ≥ 0, unique `idempotency_key`) + balance update, then low-stock/expiry notification.

## 5. Adding a department module (checklist)
Each unfinished department has a starter workspace under `departments/` (see `departments/README.md`). Use it to organize that team's incoming or in-progress code. These folders are not loaded by the application; integrate working features into the shared `app/`, `routes/`, `resources/views/`, and `public/assets/` structure below.

1. Migration for any new tables (+ comment on the table) → `config/schema_groups.php` → regenerate docs.
2. Seed new permissions and grant them to the department role (`database/seeds/`).
3. Repository + service in `app/Repositories`, `app/Services`; thin controller in `app/Controllers`.
4. Routes in `routes/api.php` inside an `AUTH` group with `CSRF` and `perm()` middleware.
5. Sidebar items in `config/menu.php` — add a **group** (`'General' => ['icon' => '…', 'short' => '…', 'note' => '…', 'primary' => ['/path'], 'items' => [[label, href, icon, perm], …]]`). The sidebar is a compact icon rail: `primary` pages are pinned to it, the rest open in the group's flyout on hover. Page keys in `WebController`, page script in `public/assets/js/`. Reuse `IHMS.DataTable`, `IHMS.formModal`, `IHMS.modal`, `IHMS.confirmDialog`, `IHMS.icon` from `app.js` and the styles in `app.css`.
6. Tests in `tests/` (copy the patterns in `tests/phase3.php` and `tests/schema.php`: rolled-back transactions). Run `php tests/run.php`: everything must pass before merging.

## 6. Analytics (already built, no work needed from you)
`GET /api/v1/admin/analytics?days=7|14|30|90` (permission `admin.dashboard`) is read-only and computes every figure live from the tables you already own. The Admin dashboard and the **Admin → Analytics** page read it, so a department's panels light up as soon as its services write real rows — no extra endpoints, no reporting tables, no scheduled jobs.

| Panel it fills | Tables it reads |
|---|---|
| Occupancy, room status, room types | rooms, room_types, stays, reservations, reservation_rooms |
| Revenue, revenue by method, invoices, folio balance | payments, invoices, folios, folio_charges |
| Arrivals, departures, booking channels | reservations, reservation_rooms, stays |
| Housekeeping queue | housekeeping_tasks |
| Maintenance queue | work_orders |
| Security queue | security_incidents |
| Kitchen and F&B queue | orders, order_items, stock_balances, inventory_items, food_waste_records |
| Employees, roles, sign-in activity, audit activity | users, departments, roles, user_roles, login_attempts, audit_logs, error_logs |

Rules to keep the figures honest: write the ledger rows the panel reads (a verified `payments` row, a `folio_charges` row, a `housekeeping_tasks` row) in the same transaction as the business action, and keep `status` and date columns accurate — the analytics group by `status`, `business_date`, `arrival_date` and `received_at` exactly as the schema defines them.
