-- Migration 007: Group G, maintenance

CREATE TABLE assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_tag VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(60) NULL,
  location_description VARCHAR(150) NULL,
  room_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  manufacturer VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  serial_no VARCHAR(80) NULL,
  purchase_date DATE NULL,
  warranty_until DATE NULL,
  status ENUM('active','under_repair','retired') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assets_tag (asset_tag),
  KEY ix_assets_room (room_id),
  CONSTRAINT fk_assets_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_assets_department_id FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Equipment and asset register';

CREATE TABLE maintenance_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(20) NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  requesting_department_id BIGINT UNSIGNED NOT NULL,
  room_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NULL,
  guest_request_id BIGINT UNSIGNED NULL,
  location_description VARCHAR(150) NULL,
  category VARCHAR(40) NULL,
  description TEXT NOT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('submitted','triaged','converted','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mr_no (request_no),
  KEY ix_mr_status (status, priority),
  KEY ix_mr_room (room_id),
  CONSTRAINT fk_maintenance_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users (id),
  CONSTRAINT fk_maintenance_requests_requesting_department_id FOREIGN KEY (requesting_department_id) REFERENCES departments (id),
  CONSTRAINT fk_maintenance_requests_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_maintenance_requests_asset_id FOREIGN KEY (asset_id) REFERENCES assets (id),
  CONSTRAINT fk_maintenance_requests_guest_request_id FOREIGN KEY (guest_request_id) REFERENCES guest_requests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Requests raised by any department';

CREATE TABLE preventive_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  frequency_days SMALLINT UNSIGNED NOT NULL,
  last_done_on DATE NULL,
  next_due_on DATE NOT NULL,
  instructions TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ps_due (is_active, next_due_on),
  CONSTRAINT fk_preventive_schedules_asset_id FOREIGN KEY (asset_id) REFERENCES assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recurring preventive maintenance';

CREATE TABLE work_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_no VARCHAR(20) NOT NULL,
  request_id BIGINT UNSIGNED NULL,
  preventive_schedule_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NULL,
  asset_id BIGINT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','assigned','in_progress','on_hold','completed','verified','closed','cancelled') NOT NULL DEFAULT 'open',
  due_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  closed_at DATETIME NULL,
  blocks_room TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wo_no (work_order_no),
  KEY ix_wo_status (status, priority),
  KEY ix_wo_room (room_id),
  CONSTRAINT fk_work_orders_request_id FOREIGN KEY (request_id) REFERENCES maintenance_requests (id),
  CONSTRAINT fk_work_orders_preventive_schedule_id FOREIGN KEY (preventive_schedule_id) REFERENCES preventive_schedules (id),
  CONSTRAINT fk_work_orders_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_work_orders_asset_id FOREIGN KEY (asset_id) REFERENCES assets (id),
  CONSTRAINT fk_work_orders_verified_by FOREIGN KEY (verified_by) REFERENCES users (id),
  CONSTRAINT fk_work_orders_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Repair/maintenance jobs. A room restriction is cleared only after verified status';

CREATE TABLE work_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id BIGINT UNSIGNED NOT NULL,
  technician_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  unassigned_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_wa_order (work_order_id),
  KEY ix_wa_tech (technician_id, unassigned_at),
  CONSTRAINT fk_work_assignments_work_order_id FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  CONSTRAINT fk_work_assignments_technician_id FOREIGN KEY (technician_id) REFERENCES users (id),
  CONSTRAINT fk_work_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Technician assignments';

CREATE TABLE work_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  log_type ENUM('note','status_change','labor','inspection') NOT NULL DEFAULT 'note',
  note VARCHAR(500) NULL,
  hours_worked DECIMAL(5,2) NULL,
  logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_wl_order (work_order_id, logged_at),
  CONSTRAINT fk_work_logs_work_order_id FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  CONSTRAINT fk_work_logs_user_id FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Work notes and labour hours';

CREATE TABLE work_order_parts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id BIGINT UNSIGNED NULL,
  movement_id BIGINT UNSIGNED NULL,
  description VARCHAR(150) NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  added_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_wop_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_wop_order (work_order_id),
  CONSTRAINT fk_work_order_parts_work_order_id FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  CONSTRAINT fk_work_order_parts_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_work_order_parts_movement_id FOREIGN KEY (movement_id) REFERENCES inventory_movements (id),
  CONSTRAINT fk_work_order_parts_added_by FOREIGN KEY (added_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parts and materials used; stock parts link to the inventory movement that consumed them';

CREATE TABLE maintenance_costs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id BIGINT UNSIGNED NOT NULL,
  cost_type ENUM('labor','parts','external_service','other') NOT NULL,
  description VARCHAR(150) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  expense_id BIGINT UNSIGNED NULL,
  recorded_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_mc_amount CHECK (amount >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mcost_order (work_order_id),
  CONSTRAINT fk_maintenance_costs_work_order_id FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  CONSTRAINT fk_maintenance_costs_expense_id FOREIGN KEY (expense_id) REFERENCES expenses (id),
  CONSTRAINT fk_maintenance_costs_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Costs of a work order (labour, parts, external); external costs can link to an expense';

CREATE TABLE room_defects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id BIGINT UNSIGNED NOT NULL,
  request_id BIGINT UNSIGNED NULL,
  work_order_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  severity ENUM('minor','major','blocking') NOT NULL DEFAULT 'minor',
  status ENUM('open','in_repair','resolved') NOT NULL DEFAULT 'open',
  reported_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rd_room_status (room_id, status),
  CONSTRAINT fk_room_defects_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_room_defects_request_id FOREIGN KEY (request_id) REFERENCES maintenance_requests (id),
  CONSTRAINT fk_room_defects_work_order_id FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  CONSTRAINT fk_room_defects_reported_by FOREIGN KEY (reported_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defects found in rooms; repeated defects on the same room show recurring problems';
