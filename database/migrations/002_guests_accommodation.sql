-- Migration 002: Group B, guests and accommodation

CREATE TABLE guests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guest_code VARCHAR(20) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  date_of_birth DATE NULL,
  nationality VARCHAR(60) NULL,
  id_type ENUM('passport','drivers_license','national_id','philsys','umid','other') NULL,
  id_number VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(30) NULL,
  email_normalized VARCHAR(190) NULL,
  phone_normalized VARCHAR(30) NULL,
  address_line VARCHAR(190) NULL,
  city VARCHAR(80) NULL,
  country VARCHAR(60) NULL,
  is_vip TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guests_code (guest_code),
  UNIQUE KEY uq_guests_identity (id_type, id_number),
  KEY ix_guests_name (last_name, first_name),
  KEY ix_guests_email (email_normalized),
  KEY ix_guests_phone (phone_normalized),
  CONSTRAINT fk_guests_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One authoritative record per identified guest; duplicate detection via identity and normalised email/phone';

CREATE TABLE guest_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guest_id BIGINT UNSIGNED NOT NULL,
  contact_type ENUM('email','phone','address') NOT NULL,
  label VARCHAR(40) NULL,
  value VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gc_guest (guest_id),
  CONSTRAINT fk_guest_contacts_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional guest emails, phones and addresses';

CREATE TABLE guest_preferences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guest_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(40) NOT NULL,
  preference VARCHAR(255) NOT NULL,
  is_restricted TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gp_guest (guest_id),
  CONSTRAINT fk_guest_preferences_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_guest_preferences_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest preferences and notes; restricted rows need explicit permission to read';

CREATE TABLE room_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  max_adults TINYINT UNSIGNED NOT NULL DEFAULT 2,
  max_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bed_configuration VARCHAR(80) NULL,
  size_sqm DECIMAL(6,2) NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Room categories (e.g. Deluxe, Suite)';

CREATE TABLE rooms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_number VARCHAR(10) NOT NULL,
  floor SMALLINT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  status ENUM('vacant_ready','occupied','checkout_pending_clean','cleaning','awaiting_inspection') NOT NULL DEFAULT 'vacant_ready',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  row_version INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rooms_number (room_number),
  KEY ix_rooms_status (status),
  KEY ix_rooms_type (room_type_id),
  CONSTRAINT fk_rooms_room_type_id FOREIGN KEY (room_type_id) REFERENCES room_types (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical rooms. status is the operational/housekeeping state; restrictions (maintenance, out of order) live in room_blocks';

CREATE TABLE room_amenities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_amenities_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Amenity catalogue (WiFi, minibar, balcony, ...)';

CREATE TABLE room_type_amenities (
  room_type_id BIGINT UNSIGNED NOT NULL,
  amenity_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (room_type_id, amenity_id),
  CONSTRAINT fk_room_type_amenities_room_type_id FOREIGN KEY (room_type_id) REFERENCES room_types (id) ON DELETE CASCADE,
  CONSTRAINT fk_room_type_amenities_amenity_id FOREIGN KEY (amenity_id) REFERENCES room_amenities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Which amenities each room type includes';

CREATE TABLE rate_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  includes_breakfast TINYINT(1) NOT NULL DEFAULT 0,
  cancellation_hours INT UNSIGNED NOT NULL DEFAULT 24,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pricing plans (rack rate, corporate, promo)';

CREATE TABLE room_rates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rate_plan_id BIGINT UNSIGNED NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  valid_from DATE NOT NULL,
  valid_to DATE NOT NULL,
  day_type ENUM('all','weekday','weekend') NOT NULL DEFAULT 'all',
  amount DECIMAL(12,2) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_room_rates_amount CHECK (amount >= 0),
  CONSTRAINT ck_room_rates_dates CHECK (valid_to >= valid_from),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rr_lookup (rate_plan_id, room_type_id, valid_from, valid_to),
  CONSTRAINT fk_room_rates_rate_plan_id FOREIGN KEY (rate_plan_id) REFERENCES rate_plans (id),
  CONSTRAINT fk_room_rates_room_type_id FOREIGN KEY (room_type_id) REFERENCES room_types (id),
  CONSTRAINT fk_room_rates_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nightly price per rate plan, room type and date range. Overlaps are prevented by the rate service';

CREATE TABLE room_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id BIGINT UNSIGNED NOT NULL,
  block_type ENUM('maintenance','out_of_order','out_of_service','group_hold','other') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NULL,
  status ENUM('active','released') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NOT NULL,
  released_by BIGINT UNSIGNED NULL,
  released_at DATETIME NULL,
  CONSTRAINT ck_room_blocks_dates CHECK (ends_on IS NULL OR ends_on >= starts_on),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rb_room_active (room_id, status, starts_on, ends_on),
  CONSTRAINT fk_room_blocks_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_room_blocks_created_by FOREIGN KEY (created_by) REFERENCES users (id),
  CONSTRAINT fk_room_blocks_released_by FOREIGN KEY (released_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Restrictions that stop a room being sold: maintenance, out of order, group holds. work_order_id is added in migration 009';

CREATE TABLE reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_no VARCHAR(20) NOT NULL,
  primary_guest_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') NOT NULL DEFAULT 'pending',
  source ENUM('walk_in','phone','online','agent','other') NOT NULL DEFAULT 'phone',
  arrival_date DATE NOT NULL,
  departure_date DATE NOT NULL,
  adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
  children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rate_plan_id BIGINT UNSIGNED NULL,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  group_name VARCHAR(120) NULL,
  special_requests TEXT NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  idempotency_key VARCHAR(64) NULL,
  created_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_reservations_dates CHECK (departure_date > arrival_date),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reservations_no (reservation_no),
  UNIQUE KEY uq_reservations_idem (idempotency_key),
  KEY ix_res_status_arrival (status, arrival_date),
  KEY ix_res_departure (departure_date),
  KEY ix_res_guest (primary_guest_id),
  CONSTRAINT fk_reservations_primary_guest_id FOREIGN KEY (primary_guest_id) REFERENCES guests (id),
  CONSTRAINT fk_reservations_rate_plan_id FOREIGN KEY (rate_plan_id) REFERENCES rate_plans (id),
  CONSTRAINT fk_reservations_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reservation header. Overlap protection is enforced in the reservation service under a row lock on the room';

CREATE TABLE reservation_guests (
  reservation_id BIGINT UNSIGNED NOT NULL,
  guest_id BIGINT UNSIGNED NOT NULL,
  role ENUM('primary','companion') NOT NULL DEFAULT 'companion',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reservation_id, guest_id),
  CONSTRAINT fk_reservation_guests_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
  CONSTRAINT fk_reservation_guests_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Everyone on a reservation (primary + companions)';

CREATE TABLE reservation_rooms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  room_type_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  rate_plan_id BIGINT UNSIGNED NULL,
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  nightly_rate DECIMAL(12,2) NOT NULL,
  adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
  children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('booked','assigned','checked_in','checked_out','cancelled','no_show') NOT NULL DEFAULT 'booked',
  CONSTRAINT ck_rr_dates CHECK (check_out_date > check_in_date),
  CONSTRAINT ck_rr_rate CHECK (nightly_rate >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rr_room_dates (room_id, status, check_in_date, check_out_date),
  KEY ix_rr_type_dates (room_type_id, status, check_in_date, check_out_date),
  KEY ix_rr_reservation (reservation_id),
  CONSTRAINT fk_reservation_rooms_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservations (id),
  CONSTRAINT fk_reservation_rooms_room_type_id FOREIGN KEY (room_type_id) REFERENCES room_types (id),
  CONSTRAINT fk_reservation_rooms_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_reservation_rooms_rate_plan_id FOREIGN KEY (rate_plan_id) REFERENCES rate_plans (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rooms on a reservation: booked by room type, then assigned to a physical room';

CREATE TABLE reservation_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  reason VARCHAR(255) NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rsh_reservation (reservation_id),
  CONSTRAINT fk_reservation_status_history_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservations (id),
  CONSTRAINT fk_reservation_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Every reservation status change (append-only)';

CREATE TABLE stays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_room_id BIGINT UNSIGNED NOT NULL,
  guest_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NOT NULL,
  status ENUM('in_house','checked_out') NOT NULL DEFAULT 'in_house',
  checked_in_at DATETIME NOT NULL,
  expected_checkout_at DATETIME NULL,
  checked_out_at DATETIME NULL,
  is_early_checkin TINYINT(1) NOT NULL DEFAULT 0,
  is_late_checkout TINYINT(1) NOT NULL DEFAULT 0,
  checked_in_by BIGINT UNSIGNED NOT NULL,
  checked_out_by BIGINT UNSIGNED NULL,
  checkout_approval_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  active_room_id BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'in_house', room_id, NULL)) STORED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stays_reservation_room (reservation_room_id),
  UNIQUE KEY uq_stays_one_active_per_room (active_room_id),
  KEY ix_stays_room_status (room_id, status),
  KEY ix_stays_guest (guest_id),
  CONSTRAINT fk_stays_reservation_room_id FOREIGN KEY (reservation_room_id) REFERENCES reservation_rooms (id),
  CONSTRAINT fk_stays_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_stays_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_stays_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users (id),
  CONSTRAINT fk_stays_checked_out_by FOREIGN KEY (checked_out_by) REFERENCES users (id),
  CONSTRAINT fk_stays_checkout_approval_id FOREIGN KEY (checkout_approval_id) REFERENCES approval_requests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='A guest actually in a room: check-in and checkout record. One in-house stay per room is enforced by the unique active_room_id';

CREATE TABLE stay_room_changes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stay_id BIGINT UNSIGNED NOT NULL,
  from_room_id BIGINT UNSIGNED NOT NULL,
  to_room_id BIGINT UNSIGNED NOT NULL,
  change_type ENUM('change','upgrade','downgrade') NOT NULL,
  reason VARCHAR(255) NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_src_stay (stay_id),
  CONSTRAINT fk_stay_room_changes_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_stay_room_changes_from_room_id FOREIGN KEY (from_room_id) REFERENCES rooms (id),
  CONSTRAINT fk_stay_room_changes_to_room_id FOREIGN KEY (to_room_id) REFERENCES rooms (id),
  CONSTRAINT fk_stay_room_changes_changed_by FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Room changes and upgrades during a stay';

CREATE TABLE room_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  reason VARCHAR(255) NULL,
  stay_id BIGINT UNSIGNED NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rsth_room (room_id, changed_at),
  CONSTRAINT fk_room_status_history_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_room_status_history_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_room_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Every room status change (append-only)';

CREATE TABLE guest_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(20) NOT NULL,
  guest_id BIGINT UNSIGNED NULL,
  stay_id BIGINT UNSIGNED NULL,
  reservation_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NULL,
  request_type ENUM('request','complaint') NOT NULL DEFAULT 'request',
  category ENUM('housekeeping','maintenance','food','information','complaint','other') NOT NULL DEFAULT 'other',
  description TEXT NOT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','assigned','in_progress','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
  satisfaction_rating TINYINT UNSIGNED NULL,
  resolution_note VARCHAR(500) NULL,
  resolved_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_gr_rating CHECK (satisfaction_rating IS NULL OR satisfaction_rating BETWEEN 1 AND 5),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gr_no (request_no),
  KEY ix_gr_status (status, priority),
  KEY ix_gr_stay (stay_id),
  CONSTRAINT fk_guest_requests_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_guest_requests_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_guest_requests_reservation_id FOREIGN KEY (reservation_id) REFERENCES reservations (id),
  CONSTRAINT fk_guest_requests_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_guest_requests_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guest requests and complaints';

CREATE TABLE guest_request_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guest_request_id BIGINT UNSIGNED NOT NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_department_id BIGINT UNSIGNED NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  CONSTRAINT ck_gra_target CHECK (assigned_user_id IS NOT NULL OR assigned_department_id IS NOT NULL),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gra_request (guest_request_id),
  CONSTRAINT fk_guest_request_assignments_guest_request_id FOREIGN KEY (guest_request_id) REFERENCES guest_requests (id),
  CONSTRAINT fk_guest_request_assignments_assigned_user_id FOREIGN KEY (assigned_user_id) REFERENCES users (id),
  CONSTRAINT fk_guest_request_assignments_assigned_department_id FOREIGN KEY (assigned_department_id) REFERENCES departments (id),
  CONSTRAINT fk_guest_request_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Who is handling a guest request';
