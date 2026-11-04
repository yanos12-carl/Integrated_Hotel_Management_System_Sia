-- Migration 008: Group H, security

CREATE TABLE security_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_no VARCHAR(20) NOT NULL,
  category ENUM('theft','missing_property','disturbance','accident','damage','emergency','safety','trespass','other') NOT NULL,
  severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  status ENUM('reported','assigned','investigating','escalated','resolved','closed') NOT NULL DEFAULT 'reported',
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  location_description VARCHAR(150) NULL,
  room_id BIGINT UNSIGNED NULL,
  guest_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  is_restricted TINYINT(1) NOT NULL DEFAULT 1,
  escalated_at DATETIME NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  reported_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_si_no (incident_no),
  KEY ix_sinc_status_sev (status, severity),
  KEY ix_sinc_occurred (occurred_at),
  CONSTRAINT fk_security_incidents_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_security_incidents_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_security_incidents_reported_by FOREIGN KEY (reported_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Security incidents. Restricted by default: only permitted staff may read';

CREATE TABLE incident_updates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NOT NULL,
  update_type ENUM('note','status_change','investigation','evidence','escalation') NOT NULL DEFAULT 'note',
  note TEXT NOT NULL,
  is_restricted TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_iu_incident (incident_id, created_at),
  CONSTRAINT fk_incident_updates_incident_id FOREIGN KEY (incident_id) REFERENCES security_incidents (id),
  CONSTRAINT fk_incident_updates_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Investigation notes and status trail (append-only)';

CREATE TABLE incident_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  unassigned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ia_incident (incident_id),
  CONSTRAINT fk_incident_assignments_incident_id FOREIGN KEY (incident_id) REFERENCES security_incidents (id),
  CONSTRAINT fk_incident_assignments_assigned_to FOREIGN KEY (assigned_to) REFERENCES users (id),
  CONSTRAINT fk_incident_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Who investigates an incident';

CREATE TABLE visitor_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_name VARCHAR(120) NOT NULL,
  id_type VARCHAR(30) NULL,
  id_number VARCHAR(60) NULL,
  purpose VARCHAR(150) NULL,
  host_guest_id BIGINT UNSIGNED NULL,
  host_user_id BIGINT UNSIGNED NULL,
  stay_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NULL,
  badge_no VARCHAR(20) NULL,
  status ENUM('in','out') NOT NULL DEFAULT 'in',
  checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_out_at DATETIME NULL,
  checked_in_by BIGINT UNSIGNED NOT NULL,
  checked_out_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_vl_status (status, checked_in_at),
  CONSTRAINT fk_visitor_logs_host_guest_id FOREIGN KEY (host_guest_id) REFERENCES guests (id),
  CONSTRAINT fk_visitor_logs_host_user_id FOREIGN KEY (host_user_id) REFERENCES users (id),
  CONSTRAINT fk_visitor_logs_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_visitor_logs_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_visitor_logs_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES users (id),
  CONSTRAINT fk_visitor_logs_checked_out_by FOREIGN KEY (checked_out_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Visitor register';

CREATE TABLE guard_shifts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guard_user_id BIGINT UNSIGNED NOT NULL,
  shift_date DATE NOT NULL,
  shift_type ENUM('morning','afternoon','night') NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  post VARCHAR(80) NULL,
  status ENUM('scheduled','on_duty','completed','absent') NOT NULL DEFAULT 'scheduled',
  CONSTRAINT ck_gs_times CHECK (ends_at > starts_at),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_gs_date (shift_date, shift_type),
  KEY ix_gs_guard (guard_user_id, shift_date),
  CONSTRAINT fk_guard_shifts_guard_user_id FOREIGN KEY (guard_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Guard duty roster';

CREATE TABLE shift_handovers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_shift_id BIGINT UNSIGNED NOT NULL,
  to_shift_id BIGINT UNSIGNED NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  to_user_id BIGINT UNSIGNED NULL,
  summary TEXT NOT NULL,
  open_issues TEXT NULL,
  equipment_status VARCHAR(255) NULL,
  handed_over_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sh_from (from_shift_id),
  CONSTRAINT fk_shift_handovers_from_shift_id FOREIGN KEY (from_shift_id) REFERENCES guard_shifts (id),
  CONSTRAINT fk_shift_handovers_to_shift_id FOREIGN KEY (to_shift_id) REFERENCES guard_shifts (id),
  CONSTRAINT fk_shift_handovers_from_user_id FOREIGN KEY (from_user_id) REFERENCES users (id),
  CONSTRAINT fk_shift_handovers_to_user_id FOREIGN KEY (to_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='End-of-shift handover notes';

CREATE TABLE patrol_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  route_description VARCHAR(255) NULL,
  start_time TIME NOT NULL,
  interval_minutes SMALLINT UNSIGNED NULL,
  days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planned patrol rounds';

CREATE TABLE patrol_checkpoints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  location_description VARCHAR(150) NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checkpoints on patrol routes';

CREATE TABLE patrol_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patrol_schedule_id BIGINT UNSIGNED NULL,
  guard_shift_id BIGINT UNSIGNED NULL,
  checkpoint_id BIGINT UNSIGNED NOT NULL,
  guard_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('ok','issue_found','missed') NOT NULL DEFAULT 'ok',
  notes VARCHAR(255) NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pr_checkpoint_time (checkpoint_id, checked_at),
  KEY ix_pr_guard (guard_user_id, checked_at),
  CONSTRAINT fk_patrol_records_patrol_schedule_id FOREIGN KEY (patrol_schedule_id) REFERENCES patrol_schedules (id),
  CONSTRAINT fk_patrol_records_guard_shift_id FOREIGN KEY (guard_shift_id) REFERENCES guard_shifts (id),
  CONSTRAINT fk_patrol_records_checkpoint_id FOREIGN KEY (checkpoint_id) REFERENCES patrol_checkpoints (id),
  CONSTRAINT fk_patrol_records_guard_user_id FOREIGN KEY (guard_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checkpoint scans and findings';

CREATE TABLE cctv_references (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_id BIGINT UNSIGNED NULL,
  camera_code VARCHAR(30) NOT NULL,
  camera_location VARCHAR(100) NULL,
  footage_from DATETIME NOT NULL,
  footage_to DATETIME NOT NULL,
  storage_ref VARCHAR(255) NULL,
  checksum_sha256 CHAR(64) NULL,
  retention_until DATE NULL,
  is_restricted TINYINT(1) NOT NULL DEFAULT 1,
  recorded_by BIGINT UNSIGNED NOT NULL,
  CONSTRAINT ck_cctv_range CHECK (footage_to >= footage_from),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cctv_incident (incident_id),
  KEY ix_cctv_camera_time (camera_code, footage_from),
  CONSTRAINT fk_cctv_references_incident_id FOREIGN KEY (incident_id) REFERENCES security_incidents (id),
  CONSTRAINT fk_cctv_references_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CCTV evidence metadata only. storage_ref is an internal key, never a public URL; files live in private storage';

CREATE TABLE safety_inspections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inspection_type ENUM('fire','emergency_exit','extinguisher','alarm','drill','other') NOT NULL,
  area VARCHAR(120) NOT NULL,
  inspected_by BIGINT UNSIGNED NOT NULL,
  inspected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  result ENUM('pass','fail','action_required') NOT NULL,
  notes VARCHAR(500) NULL,
  next_due_on DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sfi_type_time (inspection_type, inspected_at),
  CONSTRAINT fk_safety_inspections_inspected_by FOREIGN KEY (inspected_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fire, exit, alarm and drill inspections';
