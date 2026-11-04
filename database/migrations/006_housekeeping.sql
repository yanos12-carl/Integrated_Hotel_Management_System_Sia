-- Migration 006: Group F, housekeeping

CREATE TABLE housekeeping_tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_no VARCHAR(20) NOT NULL,
  task_type ENUM('checkout_clean','stayover_clean','deep_clean','inspection_recheck','public_area','turndown','other') NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  area_name VARCHAR(100) NULL,
  stay_id BIGINT UNSIGNED NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('pending','assigned','in_progress','completed','awaiting_inspection','inspected_ok','failed_inspection','cancelled') NOT NULL DEFAULT 'pending',
  due_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  recleaning_of_task_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(40) NULL,
  source_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_hkt_target CHECK (room_id IS NOT NULL OR area_name IS NOT NULL),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hkt_no (task_no),
  KEY ix_hkt_room_status (room_id, status),
  KEY ix_hkt_status_due (status, due_at),
  CONSTRAINT fk_housekeeping_tasks_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_housekeeping_tasks_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_housekeeping_tasks_recleaning_of_task_id FOREIGN KEY (recleaning_of_task_id) REFERENCES housekeeping_tasks (id),
  CONSTRAINT fk_housekeeping_tasks_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cleaning/inspection tasks. A checkout creates one automatically; the room is ready only after an inspection passes';

CREATE TABLE task_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  unassigned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ta_task (task_id),
  KEY ix_ta_user (user_id, unassigned_at),
  CONSTRAINT fk_task_assignments_task_id FOREIGN KEY (task_id) REFERENCES housekeeping_tasks (id),
  CONSTRAINT fk_task_assignments_user_id FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_task_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Who is assigned to a task';

CREATE TABLE cleaning_checklists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  applies_to ENUM('room','public_area') NOT NULL DEFAULT 'room',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklist templates';

CREATE TABLE checklist_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  checklist_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  display_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ci_checklist (checklist_id, display_order),
  CONSTRAINT fk_checklist_items_checklist_id FOREIGN KEY (checklist_id) REFERENCES cleaning_checklists (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items of a checklist';

CREATE TABLE task_checklist_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id BIGINT UNSIGNED NOT NULL,
  checklist_item_id BIGINT UNSIGNED NOT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  completed_by BIGINT UNSIGNED NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tcr_task_item (task_id, checklist_item_id),
  CONSTRAINT fk_task_checklist_results_task_id FOREIGN KEY (task_id) REFERENCES housekeeping_tasks (id),
  CONSTRAINT fk_task_checklist_results_checklist_item_id FOREIGN KEY (checklist_item_id) REFERENCES checklist_items (id),
  CONSTRAINT fk_task_checklist_results_completed_by FOREIGN KEY (completed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Checklist progress on a task';

CREATE TABLE room_inspections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NULL,
  inspector_id BIGINT UNSIGNED NOT NULL,
  result ENUM('passed','failed') NOT NULL,
  notes VARCHAR(500) NULL,
  inspected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rinsp_room (room_id, inspected_at),
  CONSTRAINT fk_room_inspections_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_room_inspections_task_id FOREIGN KEY (task_id) REFERENCES housekeeping_tasks (id),
  CONSTRAINT fk_room_inspections_inspector_id FOREIGN KEY (inspector_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Room inspections after cleaning';

CREATE TABLE inspection_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inspection_id BIGINT UNSIGNED NOT NULL,
  item_label VARCHAR(150) NOT NULL,
  passed TINYINT(1) NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ir_inspection (inspection_id),
  CONSTRAINT fk_inspection_results_inspection_id FOREIGN KEY (inspection_id) REFERENCES room_inspections (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item-level inspection findings';

CREATE TABLE linen_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(100) NOT NULL,
  item_type ENUM('linen','towel','amenity','supply') NOT NULL,
  par_level INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_li_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Linen and guest amenities catalogue. On-hand counts are derived from linen_movements';

CREATE TABLE linen_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  linen_item_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('purchase','issue','return','soiled','laundry_out','laundry_in','damaged','lost') NOT NULL,
  quantity INT NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  performed_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_lm_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lm_item_type (linen_item_id, movement_type),
  KEY ix_lm_time (created_at),
  CONSTRAINT fk_linen_movements_linen_item_id FOREIGN KEY (linen_item_id) REFERENCES linen_items (id),
  CONSTRAINT fk_linen_movements_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_linen_movements_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_linen_movements_performed_by FOREIGN KEY (performed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Linen/amenity movement ledger (issue, soiled, laundry, damaged, lost); also records missing/damaged items';

CREATE TABLE lost_found_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_no VARCHAR(20) NOT NULL,
  description VARCHAR(255) NOT NULL,
  category VARCHAR(40) NULL,
  found_room_id BIGINT UNSIGNED NULL,
  found_location VARCHAR(150) NULL,
  guest_id BIGINT UNSIGNED NULL,
  found_by BIGINT UNSIGNED NOT NULL,
  found_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  storage_location VARCHAR(100) NULL,
  status ENUM('stored','claimed','returned','disposed','transferred_security') NOT NULL DEFAULT 'stored',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lf_no (item_no),
  KEY ix_lf_status (status),
  CONSTRAINT fk_lost_found_items_found_room_id FOREIGN KEY (found_room_id) REFERENCES rooms (id),
  CONSTRAINT fk_lost_found_items_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_lost_found_items_found_by FOREIGN KEY (found_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lost-and-found register. security_incident_id is added in migration 009';

CREATE TABLE lost_found_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id BIGINT UNSIGNED NOT NULL,
  claimant_name VARCHAR(120) NOT NULL,
  guest_id BIGINT UNSIGNED NULL,
  id_verified TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','approved','rejected','released') NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL,
  handled_by BIGINT UNSIGNED NULL,
  released_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_lfc_item (item_id),
  CONSTRAINT fk_lost_found_claims_item_id FOREIGN KEY (item_id) REFERENCES lost_found_items (id),
  CONSTRAINT fk_lost_found_claims_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_lost_found_claims_handled_by FOREIGN KEY (handled_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Claims against lost-and-found items';
