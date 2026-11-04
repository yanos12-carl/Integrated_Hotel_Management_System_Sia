-- Migration 013: Security app compatibility tables in the shared IHMS database.
ALTER TABLE users
  ADD COLUMN full_name VARCHAR(161) NOT NULL DEFAULT '',
  ADD COLUMN job_title VARCHAR(100) NULL,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN last_seen DATETIME NULL,
  ADD COLUMN reset_requested TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN reset_code_hash VARCHAR(255) NULL,
  ADD COLUMN reset_expires DATETIME NULL,
  ADD COLUMN profile_photo_filename VARCHAR(255) NULL;

UPDATE users
SET full_name = TRIM(CONCAT(first_name, ' ', last_name)),
    is_active = IF(status = 'active', 1, 0);

CREATE TRIGGER trg_users_security_compat_insert
BEFORE INSERT ON users
FOR EACH ROW
SET NEW.full_name = IF(NEW.full_name = '', TRIM(CONCAT(NEW.first_name, ' ', NEW.last_name)), NEW.full_name),
    NEW.is_active = IF(NEW.status = 'active', 1, 0);

CREATE TRIGGER trg_users_security_compat_update
BEFORE UPDATE ON users
FOR EACH ROW
SET NEW.first_name = IF(
      NOT (NEW.full_name <=> OLD.full_name),
      SUBSTRING_INDEX(TRIM(NEW.full_name), ' ', 1),
      NEW.first_name
    ),
    NEW.last_name = IF(
      NOT (NEW.full_name <=> OLD.full_name),
      TRIM(SUBSTRING(TRIM(NEW.full_name), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(NEW.full_name), ' ', 1)) + 1)),
      NEW.last_name
    ),
    NEW.full_name = IF(
      NOT (NEW.first_name <=> OLD.first_name) OR NOT (NEW.last_name <=> OLD.last_name),
      TRIM(CONCAT(NEW.first_name, ' ', NEW.last_name)),
      NEW.full_name
    ),
    NEW.is_active = IF(
      NOT (NEW.status <=> OLD.status),
      IF(NEW.status = 'active', 1, 0),
      NEW.is_active
    ),
    NEW.status = IF(
      NOT (NEW.is_active <=> OLD.is_active),
      IF(NEW.is_active = 1, 'active', 'inactive'),
      NEW.status
    ),
    NEW.locked_until = IF(
      NEW.is_active = 1 AND NOT (NEW.is_active <=> OLD.is_active),
      NULL,
      NEW.locked_until
    );

CREATE TABLE incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(150) NULL,
  category VARCHAR(100) NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  status ENUM('open','investigating','resolved') NOT NULL DEFAULT 'open',
  reported_by BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  escalated_at DATETIME NULL,
  escalated_to VARCHAR(100) NULL,
  resolved_at DATETIME NULL,
  room_no VARCHAR(10) NULL,
  involved VARCHAR(200) NULL,
  item_desc VARCHAR(200) NULL,
  injury_details TEXT NULL,
  incident_at DATETIME NULL,
  photo_filename VARCHAR(255) NULL,
  property_report_type VARCHAR(100) NULL,
  contact VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_app_incidents_status (status, severity),
  KEY ix_security_app_incidents_created (created_at),
  KEY ix_security_app_incidents_reported_by (reported_by),
  CONSTRAINT fk_security_app_incidents_reporter FOREIGN KEY (reported_by) REFERENCES users (id),
  CONSTRAINT fk_security_app_incidents_assignee FOREIGN KEY (assigned_to) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security app incident reports';

CREATE TABLE security_app_incident_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','investigating','resolved') NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_app_incident_updates_incident (incident_id, created_at),
  CONSTRAINT fk_security_app_incident_updates_incident FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE,
  CONSTRAINT fk_security_app_incident_updates_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security app incident update history';

CREATE TABLE security_staff (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_no VARCHAR(20) NOT NULL,
  full_name VARCHAR(161) NOT NULL,
  job_title VARCHAR(100) NOT NULL,
  contact VARCHAR(100) NULL,
  post VARCHAR(100) NULL,
  status ENUM('Active','On leave','Inactive') NOT NULL DEFAULT 'Active',
  date_hired DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_staff_no (staff_no),
  KEY ix_security_staff_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security personnel roster';

CREATE TABLE cctv_cameras (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  location VARCHAR(150) NULL,
  nvr_ref VARCHAR(100) NULL,
  status ENUM('Online','Offline','Maintenance') NOT NULL DEFAULT 'Online',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_cctv_camera_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security app CCTV camera register';

CREATE TABLE cctv_refs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NOT NULL,
  camera_name VARCHAR(100) NOT NULL,
  footage_from DATETIME NOT NULL,
  footage_to DATETIME NULL,
  clip_ref VARCHAR(150) NULL,
  notes VARCHAR(500) NULL,
  added_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_cctv_refs_incident (incident_id, footage_from),
  CONSTRAINT fk_security_cctv_refs_incident FOREIGN KEY (incident_id) REFERENCES incidents (id) ON DELETE CASCADE,
  CONSTRAINT fk_security_cctv_refs_user FOREIGN KEY (added_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CCTV footage references attached to Security reports';

CREATE TABLE visitors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_name VARCHAR(120) NOT NULL,
  id_presented VARCHAR(100) NULL,
  guest_visited VARCHAR(150) NULL,
  room_no VARCHAR(20) NULL,
  purpose VARCHAR(200) NULL,
  time_in DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  time_out DATETIME NULL,
  logged_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_visitors_time (time_in),
  CONSTRAINT fk_security_visitors_user FOREIGN KEY (logged_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security app visitor register';

CREATE TABLE patrol_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area VARCHAR(100) NOT NULL,
  result ENUM('clear','issue') NOT NULL,
  notes VARCHAR(500) NULL,
  patrolled_by BIGINT UNSIGNED NOT NULL,
  patrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_patrol_logs_time (patrolled_at),
  KEY ix_security_patrol_logs_area_time (area, patrolled_at),
  CONSTRAINT fk_security_patrol_logs_user FOREIGN KEY (patrolled_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security patrol records';

CREATE TABLE shift_schedule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id BIGINT UNSIGNED NOT NULL,
  shift_date DATE NOT NULL,
  shift ENUM('Day','Mid','Night') NOT NULL,
  post VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_shift_staff_date (staff_id, shift_date),
  KEY ix_security_shift_date (shift_date, shift),
  CONSTRAINT fk_security_shift_staff FOREIGN KEY (staff_id) REFERENCES security_staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security staff shift assignments';

CREATE TABLE checkpoints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  location VARCHAR(150) NULL,
  interval_minutes INT UNSIGNED NOT NULL DEFAULT 120,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_security_checkpoint_name (name),
  KEY ix_security_checkpoint_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security patrol checkpoints';

CREATE TABLE patrol_schedule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area VARCHAR(100) NOT NULL,
  scheduled_time TIME NOT NULL,
  assigned_to VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_patrol_schedule_active_time (active, scheduled_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security patrol timetable';

CREATE TABLE fire_inspections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inspected_on DATE NOT NULL,
  location VARCHAR(150) NOT NULL,
  item VARCHAR(100) NOT NULL,
  result ENUM('Passed','Needs attention','Failed') NOT NULL,
  next_due DATE NULL,
  notes VARCHAR(500) NULL,
  inspected_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_fire_inspection_date (inspected_on),
  CONSTRAINT fk_security_fire_inspection_user FOREIGN KEY (inspected_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security fire-safety inspection records';

CREATE TABLE key_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  key_ref VARCHAR(100) NOT NULL,
  issued_to VARCHAR(150) NOT NULL,
  purpose VARCHAR(200) NULL,
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(500) NULL,
  issued_by BIGINT UNSIGNED NOT NULL,
  returned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_key_logs_returned (returned_at, issued_at),
  CONSTRAINT fk_security_key_logs_user FOREIGN KEY (issued_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security key and access-card issue log';

CREATE TABLE restricted_access (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  area VARCHAR(100) NOT NULL,
  person_name VARCHAR(150) NOT NULL,
  company VARCHAR(150) NULL,
  purpose VARCHAR(200) NULL,
  authorized_by VARCHAR(150) NULL,
  time_in DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  time_out DATETIME NULL,
  logged_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_restricted_access_time (time_in, time_out),
  CONSTRAINT fk_security_restricted_access_user FOREIGN KEY (logged_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security restricted-area access log';

CREATE TABLE emergencies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind VARCHAR(100) NOT NULL,
  location VARCHAR(150) NULL,
  description TEXT NOT NULL,
  status ENUM('Active','Contained','Closed') NOT NULL DEFAULT 'Active',
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  reported_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_emergencies_status (status, closed_at),
  KEY ix_security_emergencies_opened (opened_at),
  CONSTRAINT fk_security_emergencies_user FOREIGN KEY (reported_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security emergency response records';

CREATE TABLE case_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref_type VARCHAR(80) NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  agency VARCHAR(100) NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_security_case_notes_reference (ref_type, ref_id, created_at),
  CONSTRAINT fk_security_case_notes_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security case and emergency action notes';

CREATE TABLE security_sso_tickets (
  ticket_id CHAR(48) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id),
  KEY ix_security_sso_tickets_expiry (expires_at),
  CONSTRAINT fk_security_sso_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Single-use Security sign-in tickets';
