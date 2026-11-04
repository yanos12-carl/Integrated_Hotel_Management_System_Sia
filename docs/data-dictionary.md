# IHMS Data Dictionary

_Generated from the live database by `php database/tools/generate-docs.php`. 124 tables, 287 foreign keys._

**Conventions.** `id BIGINT UNSIGNED` primary keys; money `DECIMAL(12,2)`; quantities `DECIMAL(14,3)`; every table is InnoDB utf8mb4; `created_at`/`updated_at` timestamps. Statuses are `ENUM`s. History and ledger tables are append-only and protected by triggers (see *Protections* per table).

| Group | Area | Tables |
|---|---|---|
| A | Shared system and access control | 17 |
| B | Guests and accommodation | 19 |
| C | Billing and finance | 16 |
| D | Food and beverage | 11 |
| E | Kitchen and inventory | 13 |
| F | Housekeeping | 11 |
| G | Maintenance | 9 |
| H | Security | 27 |

---

## Group A: Shared system and access control

### `departments`
_The nine hotel departments_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(30) | no | UQ |  |  |
| `name` | varchar(100) | no | UQ |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_departments_code` (code)  
**Unique** `uq_departments_name` (name)

### `users`
_Employee accounts (password stored only as a hash)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `department_id` | bigint(20) unsigned | no | idx |  | → `departments.id` |
| `employee_no` | varchar(30) | no | UQ |  |  |
| `username` | varchar(60) | no | UQ |  |  |
| `email` | varchar(190) | no | UQ |  |  |
| `password_hash` | varchar(255) | no |  |  |  |
| `first_name` | varchar(80) | no |  |  |  |
| `last_name` | varchar(80) | no |  |  |  |
| `phone` | varchar(30) | yes |  | `NULL` |  |
| `avatar_path` | varchar(255) | yes |  | `NULL` |  |
| `status` | enum('active','inactive','locked') | no | idx | `'active'` |  |
| `must_change_password` | tinyint(1) | no |  | `1` |  |
| `locked_until` | datetime | yes |  | `NULL` |  |
| `last_login_at` | datetime | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |
| `full_name` | varchar(161) | no |  | `''` |  |
| `job_title` | varchar(100) | yes |  | `NULL` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `last_seen` | datetime | yes |  | `NULL` |  |
| `reset_requested` | tinyint(1) | no |  | `0` |  |
| `reset_code_hash` | varchar(255) | yes |  | `NULL` |  |
| `reset_expires` | datetime | yes |  | `NULL` |  |
| `profile_photo_filename` | varchar(255) | yes |  | `NULL` |  |

**Unique** `uq_users_email` (email)  
**Unique** `uq_users_employee_no` (employee_no)  
**Unique** `uq_users_username` (username)  
**Protection** trigger `trg_users_security_compat_insert` (INSERT)  
**Protection** trigger `trg_users_security_compat_update` (UPDATE)

### `roles`
_Named sets of permissions, optionally limited to one department_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(50) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `is_system` | tinyint(1) | no |  | `0` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_roles_code` (code)

### `permissions`
_Catalogue of every action the system can authorise (e.g. refund.process)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(80) | no | UQ |  |  |
| `name` | varchar(150) | no |  |  |  |
| `module` | varchar(50) | no | idx |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_permissions_code` (code)

### `user_roles`
_Which roles each employee holds_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `user_id` | bigint(20) unsigned | no | PK |  | → `users.id` |
| `role_id` | bigint(20) unsigned | no | PK |  | → `roles.id` |
| `assigned_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `assigned_at` | datetime | no |  | `current_timestamp()` |  |

### `role_permissions`
_Which permissions each role grants_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `role_id` | bigint(20) unsigned | no | PK |  | → `roles.id` |
| `permission_id` | bigint(20) unsigned | no | PK |  | → `permissions.id` |

### `user_departments`
_Extra departments an employee also belongs to (besides the primary one)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `user_id` | bigint(20) unsigned | no | PK |  | → `users.id` |
| `department_id` | bigint(20) unsigned | no | PK |  | → `departments.id` |

### `login_attempts`
_Every sign-in attempt, successful or not, for lockout and monitoring_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `username` | varchar(190) | no | idx |  |  |
| `user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `ip_address` | varchar(45) | no | idx |  |  |
| `user_agent` | varchar(255) | yes |  | `NULL` |  |
| `success` | tinyint(1) | no |  |  |  |
| `reason` | varchar(60) | yes |  | `NULL` |  |
| `attempted_at` | datetime | no |  | `current_timestamp()` |  |

### `sessions`
_Active browser sessions (revocable by Admin)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | varchar(128) | no | PK |  |  |
| `user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `ip_address` | varchar(45) | yes |  | `NULL` |  |
| `user_agent` | varchar(255) | yes |  | `NULL` |  |
| `payload` | mediumtext | no |  |  |  |
| `last_activity` | int(10) unsigned | no | idx |  |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `password_resets`
_Password reset tokens (stored hashed, single use, expiring)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `token_hash` | char(64) | no | UQ |  |  |
| `expires_at` | datetime | no |  |  |  |
| `used_at` | datetime | yes |  | `NULL` |  |
| `requested_ip` | varchar(45) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_password_resets_token` (token_hash)

### `system_settings`
_Hotel-wide settings (tax rates, check-in times, security limits)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `setting_key` | varchar(80) | no | PK |  |  |
| `setting_value` | text | yes |  | `NULL` |  |
| `value_type` | enum('string','int','decimal','bool','json') | no |  | `'string'` |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `updated_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `notifications`
_Cross-department events: what happened and which record it concerns_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `type` | varchar(60) | no |  |  |  |
| `title` | varchar(150) | no |  |  |  |
| `message` | varchar(500) | no |  |  |  |
| `severity` | enum('info','warning','critical') | no |  | `'info'` |  |
| `entity_type` | varchar(60) | yes | idx | `NULL` |  |
| `entity_id` | bigint(20) unsigned | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no | idx | `current_timestamp()` |  |

### `notification_recipients`
_Who received each notification and when they read it_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `notification_id` | bigint(20) unsigned | no | idx |  | → `notifications.id` |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `read_at` | datetime | yes |  | `NULL` |  |

**Unique** `uq_nr_notification_user` (notification_id,user_id)

### `audit_logs`
_Append-only trail of important actions with before/after values_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `action` | varchar(80) | no | idx |  |  |
| `entity_type` | varchar(60) | no | idx |  |  |
| `entity_id` | bigint(20) unsigned | yes |  | `NULL` |  |
| `old_values` | longtext | yes |  | `NULL` |  |
| `new_values` | longtext | yes |  | `NULL` |  |
| `ip_address` | varchar(45) | yes |  | `NULL` |  |
| `user_agent` | varchar(255) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `old_values`: `json_valid(`old_values`)`  
**Check** `new_values`: `json_valid(`new_values`)`  
**Protection** trigger `trg_audit_logs_no_update` (UPDATE)  
**Protection** trigger `trg_audit_logs_no_delete` (DELETE)

### `error_logs`
_Unexpected system errors, matched to the error id shown to users_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `error_id` | char(12) | no | idx |  |  |
| `level` | varchar(10) | no |  |  |  |
| `message` | varchar(500) | no |  |  |  |
| `context` | longtext | yes |  | `NULL` |  |
| `method` | varchar(10) | yes |  | `NULL` |  |
| `path` | varchar(255) | yes |  | `NULL` |  |
| `user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no | idx | `current_timestamp()` |  |

**Check** `context`: `json_valid(`context`)`

### `approval_requests`
_Requests that need a second person to approve (refunds, voids, overrides)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `request_type` | varchar(60) | no |  |  |  |
| `entity_type` | varchar(60) | no | idx |  |  |
| `entity_id` | bigint(20) unsigned | no |  |  |  |
| `required_permission` | varchar(80) | no |  |  |  |
| `requested_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `reason` | varchar(500) | no |  |  |  |
| `payload` | longtext | yes |  | `NULL` |  |
| `status` | enum('pending','approved','rejected','cancelled') | no | idx | `'pending'` |  |
| `decided_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `decided_at` | datetime | yes |  | `NULL` |  |
| `decision_note` | varchar(500) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `payload`: `json_valid(`payload`)`  
**Protection** trigger `trg_approval_requests_no_delete` (DELETE)

### `approval_history`
_Append-only history of each approval request_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `approval_request_id` | bigint(20) unsigned | no | idx |  | → `approval_requests.id` |
| `actor_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `action` | enum('requested','approved','rejected','cancelled','commented') | no |  |  |  |
| `note` | varchar(500) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_approval_history_no_update` (UPDATE)  
**Protection** trigger `trg_approval_history_no_delete` (DELETE)

---

## Group B: Guests and accommodation

### `guests`
_One authoritative record per identified guest; duplicate detection via identity and normalised email/phone_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `guest_code` | varchar(20) | no | UQ |  |  |
| `first_name` | varchar(80) | no |  |  |  |
| `last_name` | varchar(80) | no | idx |  |  |
| `date_of_birth` | date | yes |  | `NULL` |  |
| `nationality` | varchar(60) | yes |  | `NULL` |  |
| `id_type` | enum('passport','drivers_license','national_id','philsys','umid','other') | yes | idx | `NULL` |  |
| `id_number` | varchar(60) | yes |  | `NULL` |  |
| `email` | varchar(190) | yes |  | `NULL` |  |
| `phone` | varchar(30) | yes |  | `NULL` |  |
| `email_normalized` | varchar(190) | yes | idx | `NULL` |  |
| `phone_normalized` | varchar(30) | yes | idx | `NULL` |  |
| `address_line` | varchar(190) | yes |  | `NULL` |  |
| `city` | varchar(80) | yes |  | `NULL` |  |
| `country` | varchar(60) | yes |  | `NULL` |  |
| `is_vip` | tinyint(1) | no |  | `0` |  |
| `status` | enum('active','archived') | no |  | `'active'` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_guests_code` (guest_code)  
**Unique** `uq_guests_identity` (id_type,id_number)  
**Protection** trigger `trg_guests_no_delete` (DELETE)

### `guest_contacts`
_Additional guest emails, phones and addresses_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `contact_type` | enum('email','phone','address') | no |  |  |  |
| `label` | varchar(40) | yes |  | `NULL` |  |
| `value` | varchar(255) | no |  |  |  |
| `is_primary` | tinyint(1) | no |  | `0` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `guest_preferences`
_Guest preferences and notes; restricted rows need explicit permission to read_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `category` | varchar(40) | no |  |  |  |
| `preference` | varchar(255) | no |  |  |  |
| `is_restricted` | tinyint(1) | no |  | `0` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `room_types`
_Room categories (e.g. Deluxe, Suite)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `max_adults` | tinyint(3) unsigned | no |  | `2` |  |
| `max_children` | tinyint(3) unsigned | no |  | `0` |  |
| `bed_configuration` | varchar(80) | yes |  | `NULL` |  |
| `size_sqm` | decimal(6,2) | yes |  | `NULL` |  |
| `sort_order` | smallint(6) | no |  | `0` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_room_types_code` (code)

### `rooms`
_Physical rooms. status is the operational/housekeeping state; restrictions (maintenance, out of order) live in room_blocks_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `room_number` | varchar(10) | no | UQ |  |  |
| `floor` | smallint(6) | yes |  | `NULL` |  |
| `room_type_id` | bigint(20) unsigned | no | idx |  | → `room_types.id` |
| `status` | enum('vacant_ready','occupied','checkout_pending_clean','cleaning','awaiting_inspection') | no | idx | `'vacant_ready'` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `row_version` | int(10) unsigned | no |  | `0` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_rooms_number` (room_number)

### `room_amenities`
_Amenity catalogue (WiFi, minibar, balcony, ...)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(30) | no | UQ |  |  |
| `name` | varchar(80) | no |  |  |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_room_amenities_code` (code)

### `room_type_amenities`
_Which amenities each room type includes_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `room_type_id` | bigint(20) unsigned | no | PK |  | → `room_types.id` |
| `amenity_id` | bigint(20) unsigned | no | PK |  | → `room_amenities.id` |

### `rate_plans`
_Pricing plans (rack rate, corporate, promo)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `includes_breakfast` | tinyint(1) | no |  | `0` |  |
| `cancellation_hours` | int(10) unsigned | no |  | `24` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_rate_plans_code` (code)

### `room_rates`
_Nightly price per rate plan, room type and date range. Overlaps are prevented by the rate service_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `rate_plan_id` | bigint(20) unsigned | no | idx |  | → `rate_plans.id` |
| `room_type_id` | bigint(20) unsigned | no | idx |  | → `room_types.id` |
| `valid_from` | date | no |  |  |  |
| `valid_to` | date | no |  |  |  |
| `day_type` | enum('all','weekday','weekend') | no |  | `'all'` |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_room_rates_amount`: ``amount` >= 0`  
**Check** `ck_room_rates_dates`: ``valid_to` >= `valid_from``

### `room_blocks`
_Restrictions that stop a room being sold: maintenance, out of order, group holds. work_order_id is added in migration 009_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `block_type` | enum('maintenance','out_of_order','out_of_service','group_hold','other') | no |  |  |  |
| `reason` | varchar(255) | no |  |  |  |
| `work_order_id` | bigint(20) unsigned | yes | idx | `NULL` | → `work_orders.id` |
| `starts_on` | date | no |  |  |  |
| `ends_on` | date | yes |  | `NULL` |  |
| `status` | enum('active','released') | no |  | `'active'` |  |
| `created_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `released_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `released_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_room_blocks_dates`: ``ends_on` is null or `ends_on` >= `starts_on``

### `reservations`
_Reservation header. Overlap protection is enforced in the reservation service under a row lock on the room_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `reservation_no` | varchar(20) | no | UQ |  |  |
| `primary_guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `status` | enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') | no | idx | `'pending'` |  |
| `source` | enum('walk_in','phone','online','agent','other') | no |  | `'phone'` |  |
| `arrival_date` | date | no |  |  |  |
| `departure_date` | date | no | idx |  |  |
| `adults` | tinyint(3) unsigned | no |  | `1` |  |
| `children` | tinyint(3) unsigned | no |  | `0` |  |
| `rate_plan_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rate_plans.id` |
| `is_group` | tinyint(1) | no |  | `0` |  |
| `group_name` | varchar(120) | yes |  | `NULL` |  |
| `special_requests` | text | yes |  | `NULL` |  |
| `cancelled_at` | datetime | yes |  | `NULL` |  |
| `cancel_reason` | varchar(255) | yes |  | `NULL` |  |
| `idempotency_key` | varchar(64) | yes | UQ | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_reservations_idem` (idempotency_key)  
**Unique** `uq_reservations_no` (reservation_no)  
**Check** `ck_reservations_dates`: ``departure_date` > `arrival_date``  
**Protection** trigger `trg_reservations_no_delete` (DELETE)

### `reservation_guests`
_Everyone on a reservation (primary + companions)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `reservation_id` | bigint(20) unsigned | no | PK |  | → `reservations.id` |
| `guest_id` | bigint(20) unsigned | no | PK |  | → `guests.id` |
| `role` | enum('primary','companion') | no |  | `'companion'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_reservation_guests_no_delete` (DELETE)

### `reservation_rooms`
_Rooms on a reservation: booked by room type, then assigned to a physical room_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `reservation_id` | bigint(20) unsigned | no | idx |  | → `reservations.id` |
| `room_type_id` | bigint(20) unsigned | no | idx |  | → `room_types.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `rate_plan_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rate_plans.id` |
| `check_in_date` | date | no |  |  |  |
| `check_out_date` | date | no |  |  |  |
| `nightly_rate` | decimal(12,2) | no |  |  |  |
| `adults` | tinyint(3) unsigned | no |  | `1` |  |
| `children` | tinyint(3) unsigned | no |  | `0` |  |
| `status` | enum('booked','assigned','checked_in','checked_out','cancelled','no_show') | no |  | `'booked'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_rr_dates`: ``check_out_date` > `check_in_date``  
**Check** `ck_rr_rate`: ``nightly_rate` >= 0`  
**Protection** trigger `trg_reservation_rooms_no_delete` (DELETE)

### `reservation_status_history`
_Every reservation status change (append-only)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `reservation_id` | bigint(20) unsigned | no | idx |  | → `reservations.id` |
| `from_status` | varchar(20) | yes |  | `NULL` |  |
| `to_status` | varchar(20) | no |  |  |  |
| `reason` | varchar(255) | yes |  | `NULL` |  |
| `changed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `changed_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_reservation_status_history_no_update` (UPDATE)  
**Protection** trigger `trg_reservation_status_history_no_delete` (DELETE)

### `stays`
_A guest actually in a room: check-in and checkout record. One in-house stay per room is enforced by the unique active_room_id_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `reservation_room_id` | bigint(20) unsigned | no | UQ |  | → `reservation_rooms.id` |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `status` | enum('in_house','checked_out') | no |  | `'in_house'` |  |
| `checked_in_at` | datetime | no |  |  |  |
| `expected_checkout_at` | datetime | yes |  | `NULL` |  |
| `checked_out_at` | datetime | yes |  | `NULL` |  |
| `is_early_checkin` | tinyint(1) | no |  | `0` |  |
| `is_late_checkout` | tinyint(1) | no |  | `0` |  |
| `checked_in_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `checked_out_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `checkout_approval_id` | bigint(20) unsigned | yes | idx | `NULL` | → `approval_requests.id` |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `active_room_id` | bigint(20) unsigned | yes | UQ | `NULL` | generated: `if(`status` = 'in_house',`room_id`,NULL)` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_stays_one_active_per_room` (active_room_id)  
**Unique** `uq_stays_reservation_room` (reservation_room_id)  
**Protection** trigger `trg_stays_no_delete` (DELETE)

### `stay_room_changes`
_Room changes and upgrades during a stay_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `stay_id` | bigint(20) unsigned | no | idx |  | → `stays.id` |
| `from_room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `to_room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `change_type` | enum('change','upgrade','downgrade') | no |  |  |  |
| `reason` | varchar(255) | yes |  | `NULL` |  |
| `changed_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `changed_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_stay_room_changes_no_update` (UPDATE)  
**Protection** trigger `trg_stay_room_changes_no_delete` (DELETE)

### `room_status_history`
_Every room status change (append-only)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `from_status` | varchar(30) | yes |  | `NULL` |  |
| `to_status` | varchar(30) | no |  |  |  |
| `reason` | varchar(255) | yes |  | `NULL` |  |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `changed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `changed_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_room_status_history_no_update` (UPDATE)  
**Protection** trigger `trg_room_status_history_no_delete` (DELETE)

### `guest_requests`
_Guest requests and complaints_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `request_no` | varchar(20) | no | UQ |  |  |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `reservation_id` | bigint(20) unsigned | yes | idx | `NULL` | → `reservations.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `request_type` | enum('request','complaint') | no |  | `'request'` |  |
| `category` | enum('housekeeping','maintenance','food','information','complaint','other') | no |  | `'other'` |  |
| `description` | text | no |  |  |  |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `status` | enum('open','assigned','in_progress','resolved','closed','cancelled') | no | idx | `'open'` |  |
| `satisfaction_rating` | tinyint(3) unsigned | yes |  | `NULL` |  |
| `resolution_note` | varchar(500) | yes |  | `NULL` |  |
| `resolved_at` | datetime | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_gr_no` (request_no)  
**Check** `ck_gr_rating`: ``satisfaction_rating` is null or `satisfaction_rating` between 1 and 5`

### `guest_request_assignments`
_Who is handling a guest request_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `guest_request_id` | bigint(20) unsigned | no | idx |  | → `guest_requests.id` |
| `assigned_user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `assigned_department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `assigned_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `note` | varchar(255) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_gra_target`: ``assigned_user_id` is not null or `assigned_department_id` is not null`

---

## Group C: Billing and finance

### `suppliers`
_Supplier master used by finance (payables) and inventory (receipts)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(150) | no | UQ |  |  |
| `contact_name` | varchar(100) | yes |  | `NULL` |  |
| `phone` | varchar(30) | yes |  | `NULL` |  |
| `email` | varchar(190) | yes |  | `NULL` |  |
| `address` | varchar(255) | yes |  | `NULL` |  |
| `payment_terms_days` | smallint(5) unsigned | no |  | `30` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_suppliers_code` (code)  
**Unique** `uq_suppliers_name` (name)

### `folios`
_Guest account for a stay or walk-in; the balance is always computed from charges and payments, never stored_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `folio_no` | varchar(20) | no | UQ |  |  |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `reservation_id` | bigint(20) unsigned | yes | idx | `NULL` | → `reservations.id` |
| `folio_type` | enum('guest','walk_in','group_master','house') | no |  | `'guest'` |  |
| `status` | enum('open','settled','closed') | no | idx | `'open'` |  |
| `currency` | char(3) | no |  | `'PHP'` |  |
| `closed_at` | datetime | yes |  | `NULL` |  |
| `closed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_folios_no` (folio_no)  
**Protection** trigger `trg_folios_no_delete` (DELETE)

### `folio_charges`
_Immutable ledger of charges. Corrections are new rows with reverses_charge_id; amount is signed_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `folio_id` | bigint(20) unsigned | no | idx |  | → `folios.id` |
| `charge_type` | enum('room','fnb','room_service','laundry','minibar','damage','service_charge','tax','discount','adjustment','other') | no |  |  |  |
| `description` | varchar(255) | no |  |  |  |
| `quantity` | decimal(10,2) | no |  | `1.00` |  |
| `unit_amount` | decimal(12,2) | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `tax_amount` | decimal(12,2) | no |  | `0.00` |  |
| `service_charge_amount` | decimal(12,2) | no |  | `0.00` |  |
| `business_date` | date | no |  |  |  |
| `source_type` | varchar(40) | yes | idx | `NULL` |  |
| `source_id` | bigint(20) unsigned | yes |  | `NULL` |  |
| `reverses_charge_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folio_charges.id` |
| `idempotency_key` | varchar(64) | no | UQ |  |  |
| `posted_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_fc_idem` (idempotency_key)  
**Protection** trigger `trg_folio_charges_no_update` (UPDATE)  
**Protection** trigger `trg_folio_charges_no_delete` (DELETE)

### `invoices`
_Issued invoices and credit notes. Totals are a legal snapshot frozen at issue; they cannot change afterwards_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `invoice_no` | varchar(20) | no | UQ |  |  |
| `folio_id` | bigint(20) unsigned | no | idx |  | → `folios.id` |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `invoice_type` | enum('invoice','credit_note') | no |  | `'invoice'` |  |
| `original_invoice_id` | bigint(20) unsigned | yes | idx | `NULL` | → `invoices.id` |
| `status` | enum('draft','issued','void') | no | idx | `'draft'` |  |
| `issue_date` | date | yes |  | `NULL` |  |
| `due_date` | date | yes |  | `NULL` |  |
| `subtotal` | decimal(12,2) | no |  | `0.00` |  |
| `tax_total` | decimal(12,2) | no |  | `0.00` |  |
| `service_total` | decimal(12,2) | no |  | `0.00` |  |
| `discount_total` | decimal(12,2) | no |  | `0.00` |  |
| `total` | decimal(12,2) | no |  | `0.00` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `issued_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `issued_at` | datetime | yes |  | `NULL` |  |
| `void_reason` | varchar(255) | yes |  | `NULL` |  |
| `voided_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `voided_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_invoices_no` (invoice_no)  
**Protection** trigger `trg_invoices_guard` (UPDATE)  
**Protection** trigger `trg_invoices_no_delete` (DELETE)

### `invoice_items`
_Line items of an invoice_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `invoice_id` | bigint(20) unsigned | no | idx |  | → `invoices.id` |
| `folio_charge_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folio_charges.id` |
| `description` | varchar(255) | no |  |  |  |
| `quantity` | decimal(10,2) | no |  | `1.00` |  |
| `unit_amount` | decimal(12,2) | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `tax_amount` | decimal(12,2) | no |  | `0.00` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_invoice_items_no_delete` (DELETE)

### `payments`
_Payments. status becomes verified only through the finance verification process, never from a form submit. Core fields are locked by trigger_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `payment_no` | varchar(20) | no | UQ |  |  |
| `folio_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folios.id` |
| `invoice_id` | bigint(20) unsigned | yes | idx | `NULL` | → `invoices.id` |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `method` | enum('cash','card','gcash','maya','bank_transfer') | no | idx |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `currency` | char(3) | no |  | `'PHP'` |  |
| `status` | enum('pending','verified','failed','reversed') | no | idx | `'pending'` |  |
| `reference_no` | varchar(100) | yes |  | `NULL` |  |
| `received_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `received_at` | datetime | no |  | `current_timestamp()` |  |
| `verified_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `verified_at` | datetime | yes |  | `NULL` |  |
| `idempotency_key` | varchar(64) | no | UQ |  |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_payments_idem` (idempotency_key)  
**Unique** `uq_payments_method_ref` (method,reference_no)  
**Unique** `uq_payments_no` (payment_no)  
**Check** `ck_payments_amount`: ``amount` > 0`  
**Protection** trigger `trg_payments_guard` (UPDATE)  
**Protection** trigger `trg_payments_no_delete` (DELETE)

### `payment_allocations`
_Which charge or invoice a payment settles (append-only)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `payment_id` | bigint(20) unsigned | no | idx |  | → `payments.id` |
| `folio_charge_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folio_charges.id` |
| `invoice_id` | bigint(20) unsigned | yes | idx | `NULL` | → `invoices.id` |
| `amount` | decimal(12,2) | no |  |  |  |
| `allocated_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_pa_target`: ``folio_charge_id` is not null or `invoice_id` is not null`  
**Check** `ck_pa_amount`: ``amount` > 0`  
**Protection** trigger `trg_payment_allocations_no_update` (UPDATE)  
**Protection** trigger `trg_payment_allocations_no_delete` (DELETE)

### `deposits`
_Advance deposits against reservations_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `deposit_no` | varchar(20) | no | UQ |  |  |
| `guest_id` | bigint(20) unsigned | no | idx |  | → `guests.id` |
| `reservation_id` | bigint(20) unsigned | yes | idx | `NULL` | → `reservations.id` |
| `payment_id` | bigint(20) unsigned | yes | idx | `NULL` | → `payments.id` |
| `amount` | decimal(12,2) | no |  |  |  |
| `status` | enum('held','applied','refunded','forfeited') | no |  | `'held'` |  |
| `applied_folio_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folios.id` |
| `applied_at` | datetime | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_deposits_no` (deposit_no)  
**Check** `ck_deposits_amount`: ``amount` > 0`  
**Protection** trigger `trg_deposits_no_delete` (DELETE)

### `refunds`
_Refund requests and their processing; needs an approved approval_request_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `refund_no` | varchar(20) | no | UQ |  |  |
| `payment_id` | bigint(20) unsigned | no | idx |  | → `payments.id` |
| `folio_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folios.id` |
| `amount` | decimal(12,2) | no |  |  |  |
| `reason` | varchar(255) | no |  |  |  |
| `status` | enum('requested','approved','rejected','processed') | no |  | `'requested'` |  |
| `approval_request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `approval_requests.id` |
| `method` | enum('cash','card','gcash','maya','bank_transfer') | yes |  | `NULL` |  |
| `reference_no` | varchar(100) | yes |  | `NULL` |  |
| `requested_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `processed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `processed_at` | datetime | yes |  | `NULL` |  |
| `idempotency_key` | varchar(64) | no | UQ |  |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_refunds_idem` (idempotency_key)  
**Unique** `uq_refunds_no` (refund_no)  
**Check** `ck_refunds_amount`: ``amount` > 0`  
**Protection** trigger `trg_refunds_no_delete` (DELETE)

### `financial_adjustments`
_Discounts, write-offs, corrections and waivers on a folio_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `adjustment_no` | varchar(20) | no | UQ |  |  |
| `folio_id` | bigint(20) unsigned | no | idx |  | → `folios.id` |
| `folio_charge_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folio_charges.id` |
| `adjustment_type` | enum('discount','write_off','correction','waiver') | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `reason` | varchar(255) | no |  |  |  |
| `status` | enum('pending','approved','rejected','applied') | no |  | `'pending'` |  |
| `approval_request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `approval_requests.id` |
| `applied_charge_id` | bigint(20) unsigned | yes | idx | `NULL` | → `folio_charges.id` |
| `created_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_fa_no` (adjustment_no)  
**Check** `ck_fa_amount`: ``amount` > 0`  
**Protection** trigger `trg_financial_adjustments_no_delete` (DELETE)

### `expense_categories`
_Expense classification incl. payroll-related expenses (no separate HR department)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(30) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `category_type` | enum('operating','payroll','utilities','supplies','maintenance','other') | no |  | `'operating'` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_ec_code` (code)

### `supplier_invoices`
_Bills received from suppliers (payables)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `supplier_id` | bigint(20) unsigned | no | idx |  | → `suppliers.id` |
| `supplier_invoice_no` | varchar(60) | no |  |  |  |
| `invoice_date` | date | no |  |  |  |
| `due_date` | date | yes |  | `NULL` |  |
| `subtotal` | decimal(12,2) | no |  |  |  |
| `tax_total` | decimal(12,2) | no |  | `0.00` |  |
| `total` | decimal(12,2) | no |  |  |  |
| `status` | enum('open','partially_paid','paid','void') | no | idx | `'open'` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_si_supplier_no` (supplier_id,supplier_invoice_no)  
**Protection** trigger `trg_supplier_invoices_no_delete` (DELETE)

### `expenses`
_Operating expenses; payments of a supplier invoice link via supplier_invoice_id_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `expense_no` | varchar(20) | no | UQ |  |  |
| `category_id` | bigint(20) unsigned | no | idx |  | → `expense_categories.id` |
| `supplier_id` | bigint(20) unsigned | yes | idx | `NULL` | → `suppliers.id` |
| `supplier_invoice_id` | bigint(20) unsigned | yes | idx | `NULL` | → `supplier_invoices.id` |
| `department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `expense_date` | date | no | idx |  |  |
| `description` | varchar(255) | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `tax_amount` | decimal(12,2) | no |  | `0.00` |  |
| `payment_method` | enum('cash','card','gcash','maya','bank_transfer','check') | yes |  | `NULL` |  |
| `reference_no` | varchar(100) | yes |  | `NULL` |  |
| `status` | enum('recorded','approved','paid','void') | no |  | `'recorded'` |  |
| `receipt_file` | varchar(255) | yes |  | `NULL` |  |
| `recorded_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `approved_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_exp_no` (expense_no)  
**Check** `ck_expenses_amount`: ``amount` >= 0`  
**Protection** trigger `trg_expenses_no_delete` (DELETE)

### `financial_periods`
_Accounting periods and closing_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `period_code` | varchar(10) | no | UQ |  |  |
| `start_date` | date | no |  |  |  |
| `end_date` | date | no |  |  |  |
| `status` | enum('open','closing','closed') | no |  | `'open'` |  |
| `closed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `closed_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_fp_code` (period_code)  
**Check** `ck_fp_dates`: ``end_date` >= `start_date``  
**Protection** trigger `trg_financial_periods_no_delete` (DELETE)

### `daily_reconciliations`
_End-of-day reconciliation snapshots (variance = counted - expected, computed when read)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `business_date` | date | no | idx |  |  |
| `scope` | enum('front_office','fnb','all') | no |  | `'all'` |  |
| `expected_amount` | decimal(12,2) | no |  |  |  |
| `counted_amount` | decimal(12,2) | no |  |  |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `status` | enum('open','submitted','approved') | no |  | `'open'` |  |
| `reconciled_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `approved_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_dr_date_scope` (business_date,scope)  
**Protection** trigger `trg_daily_reconciliations_no_delete` (DELETE)

### `financial_transaction_refs`
_Immutable cross-reference of every money movement for audit and reporting_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `txn_ref` | varchar(30) | no | UQ |  |  |
| `txn_type` | enum('charge','payment','refund','adjustment','deposit','expense') | no |  |  |  |
| `entity_type` | varchar(40) | no | idx |  |  |
| `entity_id` | bigint(20) unsigned | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `direction` | enum('debit','credit') | no |  |  |  |
| `business_date` | date | no | idx |  |  |
| `period_id` | bigint(20) unsigned | yes | idx | `NULL` | → `financial_periods.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_ftr_ref` (txn_ref)  
**Protection** trigger `trg_financial_transaction_refs_no_update` (UPDATE)  
**Protection** trigger `trg_financial_transaction_refs_no_delete` (DELETE)

---

## Group D: Food and beverage

### `menu_categories`
_Menu sections_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(80) | no |  |  |  |
| `service_area` | enum('restaurant','room_service','both') | no |  | `'both'` |  |
| `display_order` | smallint(6) | no |  | `0` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_mc_code` (code)

### `menu_items`
_Sellable items and prices_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `category_id` | bigint(20) unsigned | no | idx |  | → `menu_categories.id` |
| `sku` | varchar(30) | no | UQ |  |  |
| `name` | varchar(120) | no |  |  |  |
| `description` | varchar(255) | yes |  | `NULL` |  |
| `price` | decimal(12,2) | no |  |  |  |
| `station` | enum('kitchen','bar','pastry') | no |  | `'kitchen'` |  |
| `preparation_minutes` | smallint(5) unsigned | yes |  | `NULL` |  |
| `is_available` | tinyint(1) | no |  | `1` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_mi_sku` (sku)  
**Check** `ck_mi_price`: ``price` >= 0`

### `restaurant_tables`
_Dining tables_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `table_no` | varchar(10) | no | UQ |  |  |
| `seats` | tinyint(3) unsigned | no |  | `2` |  |
| `area` | varchar(40) | yes |  | `NULL` |  |
| `status` | enum('available','occupied','reserved','out_of_service') | no |  | `'available'` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_rt_no` (table_no)

### `table_reservations`
_Table bookings_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `table_id` | bigint(20) unsigned | no | idx |  | → `restaurant_tables.id` |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `guest_name` | varchar(120) | no |  |  |  |
| `phone` | varchar(30) | yes |  | `NULL` |  |
| `party_size` | tinyint(3) unsigned | no |  |  |  |
| `reserved_for` | datetime | no |  |  |  |
| `duration_minutes` | smallint(5) unsigned | no |  | `90` |  |
| `status` | enum('booked','seated','completed','cancelled','no_show') | no |  | `'booked'` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `orders`
_One order record shared by F&B, Kitchen and Finance. Totals are computed from order_items using the snapshotted rates_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_no` | varchar(20) | no | UQ |  |  |
| `order_type` | enum('dine_in','takeaway','room_service') | no |  |  |  |
| `status` | enum('placed','accepted','preparing','ready','served','delivered','billed','cancelled','voided') | no | idx | `'placed'` |  |
| `table_id` | bigint(20) unsigned | yes | idx | `NULL` | → `restaurant_tables.id` |
| `table_reservation_id` | bigint(20) unsigned | yes | idx | `NULL` | → `table_reservations.id` |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `customer_name` | varchar(120) | yes |  | `NULL` |  |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `vat_rate` | decimal(5,4) | no |  | `0.0000` |  |
| `service_charge_rate` | decimal(5,4) | no |  | `0.0000` |  |
| `discount_rate` | decimal(5,4) | no |  | `0.0000` |  |
| `discount_reason` | varchar(255) | yes |  | `NULL` |  |
| `discount_approved_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `payment_status` | enum('unpaid','charged_to_room','paid','refunded') | no |  | `'unpaid'` |  |
| `billed_at` | datetime | yes |  | `NULL` |  |
| `idempotency_key` | varchar(64) | yes | UQ | `NULL` |  |
| `placed_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_orders_idem` (idempotency_key)  
**Unique** `uq_orders_no` (order_no)  
**Protection** trigger `trg_orders_no_delete` (DELETE)

### `order_items`
_Items on an order; name and price are snapshots of the menu at order time_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | idx |  | → `orders.id` |
| `menu_item_id` | bigint(20) unsigned | no | idx |  | → `menu_items.id` |
| `item_name` | varchar(120) | no |  |  |  |
| `quantity` | smallint(5) unsigned | no |  | `1` |  |
| `unit_price` | decimal(12,2) | no |  |  |  |
| `special_instructions` | varchar(255) | yes |  | `NULL` |  |
| `station` | enum('kitchen','bar','pastry') | no |  | `'kitchen'` |  |
| `status` | enum('pending','preparing','ready','served','cancelled') | no |  | `'pending'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_oi_qty`: ``quantity` > 0`  
**Protection** trigger `trg_order_items_no_delete` (DELETE)

### `order_status_history`
_Every order status change with who and when (append-only); kitchen and F&B read the same trail_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | idx |  | → `orders.id` |
| `from_status` | varchar(20) | yes |  | `NULL` |  |
| `to_status` | varchar(20) | no |  |  |  |
| `changed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `note` | varchar(255) | yes |  | `NULL` |  |
| `changed_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_order_status_history_no_update` (UPDATE)  
**Protection** trigger `trg_order_status_history_no_delete` (DELETE)

### `order_voids`
_Order or item cancellations and voids with approval_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | idx |  | → `orders.id` |
| `order_item_id` | bigint(20) unsigned | yes | idx | `NULL` | → `order_items.id` |
| `reason` | varchar(255) | no |  |  |  |
| `void_amount` | decimal(12,2) | no |  | `0.00` |  |
| `approval_request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `approval_requests.id` |
| `voided_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `approved_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_order_voids_no_update` (UPDATE)  
**Protection** trigger `trg_order_voids_no_delete` (DELETE)

### `room_service_charges`
_Link between a room-service order and the folio charge that billed it (one per order)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | UQ |  | → `orders.id` |
| `folio_id` | bigint(20) unsigned | no | idx |  | → `folios.id` |
| `folio_charge_id` | bigint(20) unsigned | no | UQ |  | → `folio_charges.id` |
| `posted_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_rsc_charge` (folio_charge_id)  
**Unique** `uq_rsc_order` (order_id)  
**Protection** trigger `trg_room_service_charges_no_delete` (DELETE)

### `order_payments`
_Link between an order and the payment that settled it_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | idx |  | → `orders.id` |
| `payment_id` | bigint(20) unsigned | no | idx |  | → `payments.id` |
| `amount` | decimal(12,2) | no |  |  |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_op_order_payment` (order_id,payment_id)  
**Protection** trigger `trg_order_payments_no_delete` (DELETE)

### `order_feedback`
_Customer feedback on food and service_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `order_id` | bigint(20) unsigned | no | idx |  | → `orders.id` |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `rating` | tinyint(3) unsigned | no |  |  |  |
| `comment` | varchar(500) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_of_rating`: ``rating` between 1 and 5`

---

## Group E: Kitchen and inventory

### `inventory_locations`
_Storerooms and stock points_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `location_type` | enum('kitchen','storeroom','housekeeping','maintenance','bar','other') | no |  | `'storeroom'` |  |
| `department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_il_code` (code)

### `inventory_items`
_Ingredients (item_type=ingredient) and supplies for every department_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `sku` | varchar(30) | no | UQ |  |  |
| `name` | varchar(120) | no |  |  |  |
| `item_type` | enum('ingredient','beverage','housekeeping','maintenance','amenity','other') | no | idx |  |  |
| `unit` | varchar(20) | no |  |  |  |
| `reorder_level` | decimal(14,3) | no |  | `0.000` |  |
| `par_level` | decimal(14,3) | yes |  | `NULL` |  |
| `standard_cost` | decimal(12,4) | no |  | `0.0000` |  |
| `is_perishable` | tinyint(1) | no |  | `0` |  |
| `shelf_life_days` | smallint(5) unsigned | yes |  | `NULL` |  |
| `owning_department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `preferred_supplier_id` | bigint(20) unsigned | yes | idx | `NULL` | → `suppliers.id` |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_ii_sku` (sku)

### `recipes`
_One active recipe per menu item_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `menu_item_id` | bigint(20) unsigned | no | idx |  | → `menu_items.id` |
| `yield_portions` | decimal(8,2) | no |  | `1.00` |  |
| `instructions` | text | yes |  | `NULL` |  |
| `version` | int(10) unsigned | no |  | `1` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `recipe_ingredients`
_Ingredient quantities per recipe yield (used to calculate consumption)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `recipe_id` | bigint(20) unsigned | no | idx |  | → `recipes.id` |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `quantity` | decimal(14,3) | no |  |  |  |
| `waste_factor` | decimal(5,4) | no |  | `0.0000` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_ri_recipe_item` (recipe_id,inventory_item_id)  
**Check** `ck_ri_qty`: ``quantity` > 0`

### `stock_balances`
_Current stock per item and location. Changed only by the inventory service together with an inventory_movements row; never below zero_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `quantity` | decimal(14,3) | no |  | `0.000` |  |
| `row_version` | int(10) unsigned | no |  | `0` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_sb_item_location` (inventory_item_id,location_id)  
**Check** `ck_sb_nonnegative`: ``quantity` >= 0`

### `stock_receipts`
_Goods received from suppliers_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `receipt_no` | varchar(20) | no | UQ |  |  |
| `supplier_id` | bigint(20) unsigned | yes | idx | `NULL` | → `suppliers.id` |
| `supplier_invoice_id` | bigint(20) unsigned | yes | idx | `NULL` | → `supplier_invoices.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `delivery_note` | varchar(100) | yes |  | `NULL` |  |
| `status` | enum('posted','void') | no |  | `'posted'` |  |
| `idempotency_key` | varchar(64) | no | UQ |  |  |
| `received_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `received_at` | datetime | no |  | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_sr_idem` (idempotency_key)  
**Unique** `uq_sr_no` (receipt_no)  
**Protection** trigger `trg_stock_receipts_no_delete` (DELETE)

### `inventory_batches`
_Batches with expiry dates (FIFO/FEFO)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `batch_no` | varchar(40) | no |  |  |  |
| `received_on` | date | no |  |  |  |
| `expires_on` | date | yes |  | `NULL` |  |
| `quantity_received` | decimal(14,3) | no |  |  |  |
| `quantity_remaining` | decimal(14,3) | no |  |  |  |
| `unit_cost` | decimal(12,4) | no |  | `0.0000` |  |
| `supplier_id` | bigint(20) unsigned | yes | idx | `NULL` | → `suppliers.id` |
| `stock_receipt_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stock_receipts.id` |
| `status` | enum('active','depleted','expired','discarded') | no | idx | `'active'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_ib_item_batch_loc` (inventory_item_id,batch_no,location_id)  
**Check** `ck_ib_remaining`: ``quantity_remaining` >= 0`  
**Protection** trigger `trg_inventory_batches_no_delete` (DELETE)

### `stock_receipt_items`
_Lines of a stock receipt_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `stock_receipt_id` | bigint(20) unsigned | no | idx |  | → `stock_receipts.id` |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `batch_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_batches.id` |
| `quantity` | decimal(14,3) | no |  |  |  |
| `unit_cost` | decimal(12,4) | no |  | `0.0000` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_sri_qty`: ``quantity` > 0`  
**Protection** trigger `trg_stock_receipt_items_no_update` (UPDATE)  
**Protection** trigger `trg_stock_receipt_items_no_delete` (DELETE)

### `inventory_movements`
_Append-only stock ledger: receipts, consumption, transfers, waste, adjustments. quantity_delta is signed_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `batch_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_batches.id` |
| `movement_type` | enum('receipt','consumption','transfer_out','transfer_in','adjustment','waste','return','count_correction') | no |  |  |  |
| `quantity_delta` | decimal(14,3) | no |  |  |  |
| `balance_after` | decimal(14,3) | no |  |  |  |
| `unit_cost` | decimal(12,4) | yes |  | `NULL` |  |
| `reference_type` | varchar(40) | yes | idx | `NULL` |  |
| `reference_id` | bigint(20) unsigned | yes |  | `NULL` |  |
| `reason` | varchar(255) | yes |  | `NULL` |  |
| `idempotency_key` | varchar(64) | no | UQ |  |  |
| `performed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_im_idem` (idempotency_key)  
**Check** `ck_im_delta`: ``quantity_delta` <> 0`  
**Check** `ck_im_balance`: ``balance_after` >= 0`  
**Protection** trigger `trg_inventory_movements_no_update` (UPDATE)  
**Protection** trigger `trg_inventory_movements_no_delete` (DELETE)

### `stock_adjustments`
_Counted vs system stock corrections, with approval for large differences_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `adjustment_no` | varchar(20) | no | UQ |  |  |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `system_quantity` | decimal(14,3) | no |  |  |  |
| `counted_quantity` | decimal(14,3) | no |  |  |  |
| `reason` | varchar(255) | no |  |  |  |
| `status` | enum('pending','approved','rejected','posted') | no |  | `'pending'` |  |
| `approval_request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `approval_requests.id` |
| `movement_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_movements.id` |
| `created_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_sa_no` (adjustment_no)  
**Protection** trigger `trg_stock_adjustments_no_delete` (DELETE)

### `food_waste_records`
_Spoilage and waste_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `batch_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_batches.id` |
| `menu_item_id` | bigint(20) unsigned | yes | idx | `NULL` | → `menu_items.id` |
| `quantity` | decimal(14,3) | no |  |  |  |
| `reason` | enum('spoilage','expired','preparation_error','overproduction','customer_return','other') | no |  |  |  |
| `movement_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_movements.id` |
| `waste_date` | date | no | idx |  |  |
| `recorded_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_fw_qty`: ``quantity` > 0`  
**Protection** trigger `trg_food_waste_records_no_delete` (DELETE)

### `temperature_logs`
_Food-safety temperature checks_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `location_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_locations.id` |
| `equipment_name` | varchar(80) | no |  |  |  |
| `check_type` | enum('fridge','freezer','hot_hold','cooking','delivery') | no |  |  |  |
| `temperature_c` | decimal(5,2) | no |  |  |  |
| `min_c` | decimal(5,2) | yes |  | `NULL` |  |
| `max_c` | decimal(5,2) | yes |  | `NULL` |  |
| `is_within_range` | tinyint(1) | no | idx |  |  |
| `corrective_action` | varchar(255) | yes |  | `NULL` |  |
| `checked_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `checked_at` | datetime | no | idx | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `replenishment_requests`
_Stock replenishment requests from any department_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `request_no` | varchar(20) | no | UQ |  |  |
| `requesting_department_id` | bigint(20) unsigned | no | idx |  | → `departments.id` |
| `location_id` | bigint(20) unsigned | no | idx |  | → `inventory_locations.id` |
| `inventory_item_id` | bigint(20) unsigned | no | idx |  | → `inventory_items.id` |
| `quantity_requested` | decimal(14,3) | no |  |  |  |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `status` | enum('open','approved','ordered','received','rejected','cancelled') | no | idx | `'open'` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `requested_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `approved_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_rpr_no` (request_no)  
**Check** `ck_rr2_qty`: ``quantity_requested` > 0`

---

## Group F: Housekeeping

### `housekeeping_tasks`
_Cleaning/inspection tasks. A checkout creates one automatically; the room is ready only after an inspection passes_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `task_no` | varchar(20) | no | UQ |  |  |
| `task_type` | enum('checkout_clean','stayover_clean','deep_clean','inspection_recheck','public_area','turndown','other') | no |  |  |  |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `area_name` | varchar(100) | yes |  | `NULL` |  |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `status` | enum('pending','assigned','in_progress','completed','awaiting_inspection','inspected_ok','failed_inspection','cancelled') | no | idx | `'pending'` |  |
| `due_at` | datetime | yes |  | `NULL` |  |
| `started_at` | datetime | yes |  | `NULL` |  |
| `completed_at` | datetime | yes |  | `NULL` |  |
| `recleaning_of_task_id` | bigint(20) unsigned | yes | idx | `NULL` | → `housekeeping_tasks.id` |
| `source_type` | varchar(40) | yes |  | `NULL` |  |
| `source_id` | bigint(20) unsigned | yes |  | `NULL` |  |
| `created_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_hkt_no` (task_no)  
**Check** `ck_hkt_target`: ``room_id` is not null or `area_name` is not null`

### `task_assignments`
_Who is assigned to a task_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `task_id` | bigint(20) unsigned | no | idx |  | → `housekeeping_tasks.id` |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `assigned_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `unassigned_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `cleaning_checklists`
_Checklist templates_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(30) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `applies_to` | enum('room','public_area') | no |  | `'room'` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_cc_code` (code)

### `checklist_items`
_Items of a checklist_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `checklist_id` | bigint(20) unsigned | no | idx |  | → `cleaning_checklists.id` |
| `description` | varchar(255) | no |  |  |  |
| `is_required` | tinyint(1) | no |  | `1` |  |
| `display_order` | smallint(6) | no |  | `0` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `task_checklist_results`
_Checklist progress on a task_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `task_id` | bigint(20) unsigned | no | idx |  | → `housekeeping_tasks.id` |
| `checklist_item_id` | bigint(20) unsigned | no | idx |  | → `checklist_items.id` |
| `is_done` | tinyint(1) | no |  | `0` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `completed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `completed_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_tcr_task_item` (task_id,checklist_item_id)

### `room_inspections`
_Room inspections after cleaning_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `task_id` | bigint(20) unsigned | yes | idx | `NULL` | → `housekeeping_tasks.id` |
| `inspector_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `result` | enum('passed','failed') | no |  |  |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `inspected_at` | datetime | no |  | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `inspection_results`
_Item-level inspection findings_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inspection_id` | bigint(20) unsigned | no | idx |  | → `room_inspections.id` |
| `item_label` | varchar(150) | no |  |  |  |
| `passed` | tinyint(1) | no |  |  |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `linen_items`
_Linen and guest amenities catalogue. On-hand counts are derived from linen_movements_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(30) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `item_type` | enum('linen','towel','amenity','supply') | no |  |  |  |
| `par_level` | int(10) unsigned | yes |  | `NULL` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_li_code` (code)

### `linen_movements`
_Linen/amenity movement ledger (issue, soiled, laundry, damaged, lost); also records missing/damaged items_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `linen_item_id` | bigint(20) unsigned | no | idx |  | → `linen_items.id` |
| `movement_type` | enum('purchase','issue','return','soiled','laundry_out','laundry_in','damaged','lost') | no |  |  |  |
| `quantity` | int(11) | no |  |  |  |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `location_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_locations.id` |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `performed_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no | idx | `current_timestamp()` |  |

**Check** `ck_lm_qty`: ``quantity` > 0`

### `lost_found_items`
_Lost-and-found register. security_incident_id is added in migration 009_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `item_no` | varchar(20) | no | UQ |  |  |
| `description` | varchar(255) | no |  |  |  |
| `category` | varchar(40) | yes |  | `NULL` |  |
| `found_room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `found_location` | varchar(150) | yes |  | `NULL` |  |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `found_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `found_at` | datetime | no |  | `current_timestamp()` |  |
| `storage_location` | varchar(100) | yes |  | `NULL` |  |
| `status` | enum('stored','claimed','returned','disposed','transferred_security') | no | idx | `'stored'` |  |
| `security_incident_id` | bigint(20) unsigned | yes | idx | `NULL` | → `security_incidents.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_lf_no` (item_no)  
**Protection** trigger `trg_lost_found_items_no_delete` (DELETE)

### `lost_found_claims`
_Claims against lost-and-found items_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `item_id` | bigint(20) unsigned | no | idx |  | → `lost_found_items.id` |
| `claimant_name` | varchar(120) | no |  |  |  |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `id_verified` | tinyint(1) | no |  | `0` |  |
| `status` | enum('pending','approved','rejected','released') | no |  | `'pending'` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `handled_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `released_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

---

## Group G: Maintenance

### `assets`
_Equipment and asset register_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `asset_tag` | varchar(30) | no | UQ |  |  |
| `name` | varchar(120) | no |  |  |  |
| `category` | varchar(60) | yes |  | `NULL` |  |
| `location_description` | varchar(150) | yes |  | `NULL` |  |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `department_id` | bigint(20) unsigned | yes | idx | `NULL` | → `departments.id` |
| `manufacturer` | varchar(80) | yes |  | `NULL` |  |
| `model` | varchar(80) | yes |  | `NULL` |  |
| `serial_no` | varchar(80) | yes |  | `NULL` |  |
| `purchase_date` | date | yes |  | `NULL` |  |
| `warranty_until` | date | yes |  | `NULL` |  |
| `status` | enum('active','under_repair','retired') | no |  | `'active'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_assets_tag` (asset_tag)

### `maintenance_requests`
_Requests raised by any department_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `request_no` | varchar(20) | no | UQ |  |  |
| `requested_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `requesting_department_id` | bigint(20) unsigned | no | idx |  | → `departments.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `asset_id` | bigint(20) unsigned | yes | idx | `NULL` | → `assets.id` |
| `guest_request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guest_requests.id` |
| `location_description` | varchar(150) | yes |  | `NULL` |  |
| `category` | varchar(40) | yes |  | `NULL` |  |
| `description` | text | no |  |  |  |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `status` | enum('submitted','triaged','converted','rejected','cancelled') | no | idx | `'submitted'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_mr_no` (request_no)

### `preventive_schedules`
_Recurring preventive maintenance_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `asset_id` | bigint(20) unsigned | no | idx |  | → `assets.id` |
| `title` | varchar(150) | no |  |  |  |
| `frequency_days` | smallint(5) unsigned | no |  |  |  |
| `last_done_on` | date | yes |  | `NULL` |  |
| `next_due_on` | date | no |  |  |  |
| `instructions` | text | yes |  | `NULL` |  |
| `is_active` | tinyint(1) | no | idx | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `work_orders`
_Repair/maintenance jobs. A room restriction is cleared only after verified status_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `work_order_no` | varchar(20) | no | UQ |  |  |
| `request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `maintenance_requests.id` |
| `preventive_schedule_id` | bigint(20) unsigned | yes | idx | `NULL` | → `preventive_schedules.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `asset_id` | bigint(20) unsigned | yes | idx | `NULL` | → `assets.id` |
| `title` | varchar(150) | no |  |  |  |
| `description` | text | yes |  | `NULL` |  |
| `priority` | enum('low','normal','high','urgent') | no |  | `'normal'` |  |
| `status` | enum('open','assigned','in_progress','on_hold','completed','verified','closed','cancelled') | no | idx | `'open'` |  |
| `due_at` | datetime | yes |  | `NULL` |  |
| `started_at` | datetime | yes |  | `NULL` |  |
| `completed_at` | datetime | yes |  | `NULL` |  |
| `verified_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `verified_at` | datetime | yes |  | `NULL` |  |
| `closed_at` | datetime | yes |  | `NULL` |  |
| `blocks_room` | tinyint(1) | no |  | `0` |  |
| `created_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_wo_no` (work_order_no)  
**Protection** trigger `trg_work_orders_no_delete` (DELETE)

### `work_assignments`
_Technician assignments_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `work_order_id` | bigint(20) unsigned | no | idx |  | → `work_orders.id` |
| `technician_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `assigned_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `unassigned_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `work_logs`
_Work notes and labour hours_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `work_order_id` | bigint(20) unsigned | no | idx |  | → `work_orders.id` |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `log_type` | enum('note','status_change','labor','inspection') | no |  | `'note'` |  |
| `note` | varchar(500) | yes |  | `NULL` |  |
| `hours_worked` | decimal(5,2) | yes |  | `NULL` |  |
| `logged_at` | datetime | no |  | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `work_order_parts`
_Parts and materials used; stock parts link to the inventory movement that consumed them_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `work_order_id` | bigint(20) unsigned | no | idx |  | → `work_orders.id` |
| `inventory_item_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_items.id` |
| `movement_id` | bigint(20) unsigned | yes | idx | `NULL` | → `inventory_movements.id` |
| `description` | varchar(150) | no |  |  |  |
| `quantity` | decimal(14,3) | no |  |  |  |
| `unit_cost` | decimal(12,4) | no |  | `0.0000` |  |
| `added_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_wop_qty`: ``quantity` > 0`

### `maintenance_costs`
_Costs of a work order (labour, parts, external); external costs can link to an expense_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `work_order_id` | bigint(20) unsigned | no | idx |  | → `work_orders.id` |
| `cost_type` | enum('labor','parts','external_service','other') | no |  |  |  |
| `description` | varchar(150) | no |  |  |  |
| `amount` | decimal(12,2) | no |  |  |  |
| `expense_id` | bigint(20) unsigned | yes | idx | `NULL` | → `expenses.id` |
| `recorded_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_mc_amount`: ``amount` >= 0`  
**Protection** trigger `trg_maintenance_costs_no_delete` (DELETE)

### `room_defects`
_Defects found in rooms; repeated defects on the same room show recurring problems_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `room_id` | bigint(20) unsigned | no | idx |  | → `rooms.id` |
| `request_id` | bigint(20) unsigned | yes | idx | `NULL` | → `maintenance_requests.id` |
| `work_order_id` | bigint(20) unsigned | yes | idx | `NULL` | → `work_orders.id` |
| `description` | varchar(255) | no |  |  |  |
| `severity` | enum('minor','major','blocking') | no |  | `'minor'` |  |
| `status` | enum('open','in_repair','resolved') | no |  | `'open'` |  |
| `reported_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `resolved_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

---

## Group H: Security

### `security_incidents`
_Security incidents. Restricted by default: only permitted staff may read_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_no` | varchar(20) | no | UQ |  |  |
| `category` | enum('theft','missing_property','disturbance','accident','damage','emergency','safety','trespass','other') | no |  |  |  |
| `severity` | enum('low','medium','high','critical') | no |  | `'low'` |  |
| `status` | enum('reported','assigned','investigating','escalated','resolved','closed') | no | idx | `'reported'` |  |
| `title` | varchar(150) | no |  |  |  |
| `description` | text | no |  |  |  |
| `location_description` | varchar(150) | yes |  | `NULL` |  |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `occurred_at` | datetime | no | idx |  |  |
| `is_restricted` | tinyint(1) | no |  | `1` |  |
| `escalated_at` | datetime | yes |  | `NULL` |  |
| `resolved_at` | datetime | yes |  | `NULL` |  |
| `closed_at` | datetime | yes |  | `NULL` |  |
| `reported_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_si_no` (incident_no)  
**Protection** trigger `trg_security_incidents_no_delete` (DELETE)

### `incident_updates`
_Investigation notes and status trail (append-only)_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_id` | bigint(20) unsigned | no | idx |  | → `security_incidents.id` |
| `update_type` | enum('note','status_change','investigation','evidence','escalation') | no |  | `'note'` |  |
| `note` | text | no |  |  |  |
| `is_restricted` | tinyint(1) | no |  | `1` |  |
| `created_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

**Protection** trigger `trg_incident_updates_no_update` (UPDATE)  
**Protection** trigger `trg_incident_updates_no_delete` (DELETE)

### `incident_assignments`
_Who investigates an incident_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_id` | bigint(20) unsigned | no | idx |  | → `security_incidents.id` |
| `assigned_to` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `assigned_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `unassigned_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `visitor_logs`
_Visitor register_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `visitor_name` | varchar(120) | no |  |  |  |
| `id_type` | varchar(30) | yes |  | `NULL` |  |
| `id_number` | varchar(60) | yes |  | `NULL` |  |
| `purpose` | varchar(150) | yes |  | `NULL` |  |
| `host_guest_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guests.id` |
| `host_user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `stay_id` | bigint(20) unsigned | yes | idx | `NULL` | → `stays.id` |
| `room_id` | bigint(20) unsigned | yes | idx | `NULL` | → `rooms.id` |
| `badge_no` | varchar(20) | yes |  | `NULL` |  |
| `status` | enum('in','out') | no | idx | `'in'` |  |
| `checked_in_at` | datetime | no |  | `current_timestamp()` |  |
| `checked_out_at` | datetime | yes |  | `NULL` |  |
| `checked_in_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `checked_out_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `guard_shifts`
_Guard duty roster_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `guard_user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `shift_date` | date | no | idx |  |  |
| `shift_type` | enum('morning','afternoon','night') | no |  |  |  |
| `starts_at` | datetime | no |  |  |  |
| `ends_at` | datetime | no |  |  |  |
| `post` | varchar(80) | yes |  | `NULL` |  |
| `status` | enum('scheduled','on_duty','completed','absent') | no |  | `'scheduled'` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_gs_times`: ``ends_at` > `starts_at``

### `shift_handovers`
_End-of-shift handover notes_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `from_shift_id` | bigint(20) unsigned | no | idx |  | → `guard_shifts.id` |
| `to_shift_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guard_shifts.id` |
| `from_user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `to_user_id` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `summary` | text | no |  |  |  |
| `open_issues` | text | yes |  | `NULL` |  |
| `equipment_status` | varchar(255) | yes |  | `NULL` |  |
| `handed_over_at` | datetime | no |  | `current_timestamp()` |  |
| `acknowledged_at` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `patrol_schedules`
_Planned patrol rounds_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `name` | varchar(100) | no |  |  |  |
| `route_description` | varchar(255) | yes |  | `NULL` |  |
| `start_time` | time | no |  |  |  |
| `interval_minutes` | smallint(5) unsigned | yes |  | `NULL` |  |
| `days_of_week` | varchar(20) | no |  | `'1,2,3,4,5,6,7'` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `patrol_checkpoints`
_Checkpoints on patrol routes_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `code` | varchar(20) | no | UQ |  |  |
| `name` | varchar(100) | no |  |  |  |
| `location_description` | varchar(150) | yes |  | `NULL` |  |
| `sequence_no` | smallint(5) unsigned | no |  | `0` |  |
| `is_active` | tinyint(1) | no |  | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_pc_code` (code)

### `patrol_records`
_Checkpoint scans and findings_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `patrol_schedule_id` | bigint(20) unsigned | yes | idx | `NULL` | → `patrol_schedules.id` |
| `guard_shift_id` | bigint(20) unsigned | yes | idx | `NULL` | → `guard_shifts.id` |
| `checkpoint_id` | bigint(20) unsigned | no | idx |  | → `patrol_checkpoints.id` |
| `guard_user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `status` | enum('ok','issue_found','missed') | no |  | `'ok'` |  |
| `notes` | varchar(255) | yes |  | `NULL` |  |
| `checked_at` | datetime | no |  | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `cctv_references`
_CCTV evidence metadata only. storage_ref is an internal key, never a public URL; files live in private storage_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_id` | bigint(20) unsigned | yes | idx | `NULL` | → `security_incidents.id` |
| `camera_code` | varchar(30) | no | idx |  |  |
| `camera_location` | varchar(100) | yes |  | `NULL` |  |
| `footage_from` | datetime | no |  |  |  |
| `footage_to` | datetime | no |  |  |  |
| `storage_ref` | varchar(255) | yes |  | `NULL` |  |
| `checksum_sha256` | char(64) | yes |  | `NULL` |  |
| `retention_until` | date | yes |  | `NULL` |  |
| `is_restricted` | tinyint(1) | no |  | `1` |  |
| `recorded_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Check** `ck_cctv_range`: ``footage_to` >= `footage_from``  
**Protection** trigger `trg_cctv_references_no_delete` (DELETE)

### `safety_inspections`
_Fire, exit, alarm and drill inspections_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inspection_type` | enum('fire','emergency_exit','extinguisher','alarm','drill','other') | no | idx |  |  |
| `area` | varchar(120) | no |  |  |  |
| `inspected_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `inspected_at` | datetime | no |  | `current_timestamp()` |  |
| `result` | enum('pass','fail','action_required') | no |  |  |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `next_due_on` | date | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `incidents`
_Security app incident reports_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `title` | varchar(150) | no |  |  |  |
| `description` | text | yes |  | `NULL` |  |
| `location` | varchar(150) | yes |  | `NULL` |  |
| `category` | varchar(100) | no |  |  |  |
| `severity` | enum('low','medium','high','critical') | no |  | `'low'` |  |
| `status` | enum('open','investigating','resolved') | no | idx | `'open'` |  |
| `reported_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `assigned_to` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |
| `escalation_level` | tinyint(3) unsigned | no |  | `0` |  |
| `escalated_at` | datetime | yes |  | `NULL` |  |
| `escalated_to` | varchar(100) | yes |  | `NULL` |  |
| `resolved_at` | datetime | yes |  | `NULL` |  |
| `room_no` | varchar(10) | yes |  | `NULL` |  |
| `involved` | varchar(200) | yes |  | `NULL` |  |
| `item_desc` | varchar(200) | yes |  | `NULL` |  |
| `injury_details` | text | yes |  | `NULL` |  |
| `incident_at` | datetime | yes |  | `NULL` |  |
| `photo_filename` | varchar(255) | yes |  | `NULL` |  |
| `property_report_type` | varchar(100) | yes |  | `NULL` |  |
| `contact` | varchar(100) | yes |  | `NULL` |  |
| `created_at` | datetime | no | idx | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `security_app_incident_updates`
_Security app incident update history_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_id` | bigint(20) unsigned | no | idx |  | → `incidents.id` |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `status` | enum('open','investigating','resolved') | no |  |  |  |
| `note` | varchar(500) | no |  | `''` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `security_staff`
_Security personnel roster_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `staff_no` | varchar(20) | no | UQ |  |  |
| `full_name` | varchar(161) | no |  |  |  |
| `job_title` | varchar(100) | no |  |  |  |
| `contact` | varchar(100) | yes |  | `NULL` |  |
| `post` | varchar(100) | yes |  | `NULL` |  |
| `status` | enum('Active','On leave','Inactive') | no | idx | `'Active'` |  |
| `date_hired` | datetime | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_security_staff_no` (staff_no)

### `cctv_cameras`
_Security app CCTV camera register_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `name` | varchar(100) | no | UQ |  |  |
| `location` | varchar(150) | yes |  | `NULL` |  |
| `nvr_ref` | varchar(100) | yes |  | `NULL` |  |
| `status` | enum('Online','Offline','Maintenance') | no |  | `'Online'` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |
| `browser_device_key` | char(64) | yes | UQ | `NULL` |  |
| `connection_type` | enum('manual','browser') | no |  | `'manual'` |  |
| `added_by` | bigint(20) unsigned | yes | idx | `NULL` | → `users.id` |

**Unique** `uq_security_camera_device_key` (browser_device_key)  
**Unique** `uq_security_cctv_camera_name` (name)

### `cctv_refs`
_CCTV footage references attached to Security reports_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `incident_id` | bigint(20) unsigned | no | idx |  | → `incidents.id` |
| `camera_name` | varchar(100) | no |  |  |  |
| `footage_from` | datetime | no |  |  |  |
| `footage_to` | datetime | yes |  | `NULL` |  |
| `clip_ref` | varchar(150) | yes |  | `NULL` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `added_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `visitors`
_Security app visitor register_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `visitor_name` | varchar(120) | no |  |  |  |
| `id_presented` | varchar(100) | yes |  | `NULL` |  |
| `guest_visited` | varchar(150) | yes |  | `NULL` |  |
| `room_no` | varchar(20) | yes |  | `NULL` |  |
| `purpose` | varchar(200) | yes |  | `NULL` |  |
| `time_in` | datetime | no | idx | `current_timestamp()` |  |
| `time_out` | datetime | yes |  | `NULL` |  |
| `logged_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `patrol_logs`
_Security patrol records_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `area` | varchar(100) | no | idx |  |  |
| `result` | enum('clear','issue') | no |  |  |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `patrolled_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `patrolled_at` | datetime | no | idx | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `shift_schedule`
_Security staff shift assignments_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `staff_id` | bigint(20) unsigned | no | idx |  | → `security_staff.id` |
| `shift_date` | date | no | idx |  |  |
| `shift` | enum('Day','Mid','Night') | no |  |  |  |
| `post` | varchar(100) | yes |  | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_security_shift_staff_date` (staff_id,shift_date)

### `checkpoints`
_Security patrol checkpoints_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `name` | varchar(100) | no | UQ |  |  |
| `location` | varchar(150) | yes |  | `NULL` |  |
| `interval_minutes` | int(10) unsigned | no |  | `120` |  |
| `active` | tinyint(1) | no | idx | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

**Unique** `uq_security_checkpoint_name` (name)

### `patrol_schedule`
_Security patrol timetable_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `area` | varchar(100) | no |  |  |  |
| `scheduled_time` | time | no |  |  |  |
| `assigned_to` | varchar(100) | yes |  | `NULL` |  |
| `active` | tinyint(1) | no | idx | `1` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `fire_inspections`
_Security fire-safety inspection records_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `inspected_on` | date | no | idx |  |  |
| `location` | varchar(150) | no |  |  |  |
| `item` | varchar(100) | no |  |  |  |
| `result` | enum('Passed','Needs attention','Failed') | no |  |  |  |
| `next_due` | date | yes |  | `NULL` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `inspected_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `key_logs`
_Security key and access-card issue log_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `key_ref` | varchar(100) | no |  |  |  |
| `issued_to` | varchar(150) | no |  |  |  |
| `purpose` | varchar(200) | yes |  | `NULL` |  |
| `issued_at` | datetime | no |  | `current_timestamp()` |  |
| `notes` | varchar(500) | yes |  | `NULL` |  |
| `issued_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `returned_at` | datetime | yes | idx | `NULL` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `restricted_access`
_Security restricted-area access log_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `area` | varchar(100) | no |  |  |  |
| `person_name` | varchar(150) | no |  |  |  |
| `company` | varchar(150) | yes |  | `NULL` |  |
| `purpose` | varchar(200) | yes |  | `NULL` |  |
| `authorized_by` | varchar(150) | yes |  | `NULL` |  |
| `time_in` | datetime | no | idx | `current_timestamp()` |  |
| `time_out` | datetime | yes |  | `NULL` |  |
| `logged_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `emergencies`
_Security emergency response records_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `kind` | varchar(100) | no |  |  |  |
| `location` | varchar(150) | yes |  | `NULL` |  |
| `description` | text | no |  |  |  |
| `status` | enum('Active','Contained','Closed') | no | idx | `'Active'` |  |
| `opened_at` | datetime | no | idx | `current_timestamp()` |  |
| `closed_at` | datetime | yes |  | `NULL` |  |
| `reported_by` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
| `updated_at` | datetime | no |  | `current_timestamp()` |  |

### `case_notes`
_Security case and emergency action notes_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `id` | bigint(20) unsigned | no | PK |  | auto increment |
| `ref_type` | varchar(80) | no | idx |  |  |
| `ref_id` | bigint(20) unsigned | no |  |  |  |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `status` | varchar(30) | no |  |  |  |
| `agency` | varchar(100) | yes |  | `NULL` |  |
| `note` | varchar(500) | no |  | `''` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |

### `security_sso_tickets`
_Single-use Security sign-in tickets_

| Column | Type | Null | Key | Default | Notes |
|---|---|---|---|---|---|
| `ticket_id` | char(48) | no | PK |  |  |
| `user_id` | bigint(20) unsigned | no | idx |  | → `users.id` |
| `expires_at` | datetime | no | idx |  |  |
| `consumed_at` | datetime | no |  | `current_timestamp()` |  |
| `created_at` | datetime | no |  | `current_timestamp()` |  |
