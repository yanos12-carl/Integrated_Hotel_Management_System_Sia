-- Migration 005: Group E, kitchen and inventory (shared by Kitchen, Housekeeping and Maintenance)

CREATE TABLE inventory_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  location_type ENUM('kitchen','storeroom','housekeeping','maintenance','bar','other') NOT NULL DEFAULT 'storeroom',
  department_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_il_code (code),
  CONSTRAINT fk_inventory_locations_department_id FOREIGN KEY (department_id) REFERENCES departments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Storerooms and stock points';

CREATE TABLE inventory_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  item_type ENUM('ingredient','beverage','housekeeping','maintenance','amenity','other') NOT NULL,
  unit VARCHAR(20) NOT NULL,
  reorder_level DECIMAL(14,3) NOT NULL DEFAULT 0,
  par_level DECIMAL(14,3) NULL,
  standard_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  is_perishable TINYINT(1) NOT NULL DEFAULT 0,
  shelf_life_days SMALLINT UNSIGNED NULL,
  owning_department_id BIGINT UNSIGNED NULL,
  preferred_supplier_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ii_sku (sku),
  KEY ix_ii_type (item_type, is_active),
  CONSTRAINT fk_inventory_items_owning_department_id FOREIGN KEY (owning_department_id) REFERENCES departments (id),
  CONSTRAINT fk_inventory_items_preferred_supplier_id FOREIGN KEY (preferred_supplier_id) REFERENCES suppliers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ingredients (item_type=ingredient) and supplies for every department';

CREATE TABLE recipes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_item_id BIGINT UNSIGNED NOT NULL,
  yield_portions DECIMAL(8,2) NOT NULL DEFAULT 1,
  instructions TEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rec_menu_item (menu_item_id, is_active),
  CONSTRAINT fk_recipes_menu_item_id FOREIGN KEY (menu_item_id) REFERENCES menu_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One active recipe per menu item';

CREATE TABLE recipe_ingredients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipe_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  waste_factor DECIMAL(5,4) NOT NULL DEFAULT 0,
  CONSTRAINT ck_ri_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ri_recipe_item (recipe_id, inventory_item_id),
  CONSTRAINT fk_recipe_ingredients_recipe_id FOREIGN KEY (recipe_id) REFERENCES recipes (id),
  CONSTRAINT fk_recipe_ingredients_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ingredient quantities per recipe yield (used to calculate consumption)';

CREATE TABLE stock_balances (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  row_version INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT ck_sb_nonnegative CHECK (quantity >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sb_item_location (inventory_item_id, location_id),
  CONSTRAINT fk_stock_balances_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_stock_balances_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Current stock per item and location. Changed only by the inventory service together with an inventory_movements row; never below zero';

CREATE TABLE stock_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no VARCHAR(20) NOT NULL,
  supplier_id BIGINT UNSIGNED NULL,
  supplier_invoice_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  delivery_note VARCHAR(100) NULL,
  status ENUM('posted','void') NOT NULL DEFAULT 'posted',
  idempotency_key VARCHAR(64) NOT NULL,
  received_by BIGINT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sr_no (receipt_no),
  UNIQUE KEY uq_sr_idem (idempotency_key),
  CONSTRAINT fk_stock_receipts_supplier_id FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_stock_receipts_supplier_invoice_id FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices (id),
  CONSTRAINT fk_stock_receipts_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_stock_receipts_received_by FOREIGN KEY (received_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Goods received from suppliers';

CREATE TABLE inventory_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  batch_no VARCHAR(40) NOT NULL,
  received_on DATE NOT NULL,
  expires_on DATE NULL,
  quantity_received DECIMAL(14,3) NOT NULL,
  quantity_remaining DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  supplier_id BIGINT UNSIGNED NULL,
  stock_receipt_id BIGINT UNSIGNED NULL,
  status ENUM('active','depleted','expired','discarded') NOT NULL DEFAULT 'active',
  CONSTRAINT ck_ib_remaining CHECK (quantity_remaining >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ib_item_batch_loc (inventory_item_id, batch_no, location_id),
  KEY ix_ib_expiry (status, expires_on),
  CONSTRAINT fk_inventory_batches_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_inventory_batches_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_inventory_batches_supplier_id FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_inventory_batches_stock_receipt_id FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Batches with expiry dates (FIFO/FEFO)';

CREATE TABLE stock_receipt_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stock_receipt_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  quantity DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
  CONSTRAINT ck_sri_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_sri_receipt (stock_receipt_id),
  CONSTRAINT fk_stock_receipt_items_stock_receipt_id FOREIGN KEY (stock_receipt_id) REFERENCES stock_receipts (id),
  CONSTRAINT fk_stock_receipt_items_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_stock_receipt_items_batch_id FOREIGN KEY (batch_id) REFERENCES inventory_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lines of a stock receipt';

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  movement_type ENUM('receipt','consumption','transfer_out','transfer_in','adjustment','waste','return','count_correction') NOT NULL,
  quantity_delta DECIMAL(14,3) NOT NULL,
  balance_after DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(12,4) NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  idempotency_key VARCHAR(64) NOT NULL,
  performed_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_im_delta CHECK (quantity_delta <> 0),
  CONSTRAINT ck_im_balance CHECK (balance_after >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_im_idem (idempotency_key),
  KEY ix_im_item_loc_time (inventory_item_id, location_id, created_at),
  KEY ix_im_ref (reference_type, reference_id),
  CONSTRAINT fk_inventory_movements_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_inventory_movements_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_inventory_movements_batch_id FOREIGN KEY (batch_id) REFERENCES inventory_batches (id),
  CONSTRAINT fk_inventory_movements_performed_by FOREIGN KEY (performed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only stock ledger: receipts, consumption, transfers, waste, adjustments. quantity_delta is signed';

CREATE TABLE stock_adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  adjustment_no VARCHAR(20) NOT NULL,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  system_quantity DECIMAL(14,3) NOT NULL,
  counted_quantity DECIMAL(14,3) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected','posted') NOT NULL DEFAULT 'pending',
  approval_request_id BIGINT UNSIGNED NULL,
  movement_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sa_no (adjustment_no),
  CONSTRAINT fk_stock_adjustments_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_stock_adjustments_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_stock_adjustments_approval_request_id FOREIGN KEY (approval_request_id) REFERENCES approval_requests (id),
  CONSTRAINT fk_stock_adjustments_movement_id FOREIGN KEY (movement_id) REFERENCES inventory_movements (id),
  CONSTRAINT fk_stock_adjustments_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Counted vs system stock corrections, with approval for large differences';

CREATE TABLE food_waste_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NULL,
  menu_item_id BIGINT UNSIGNED NULL,
  quantity DECIMAL(14,3) NOT NULL,
  reason ENUM('spoilage','expired','preparation_error','overproduction','customer_return','other') NOT NULL,
  movement_id BIGINT UNSIGNED NULL,
  waste_date DATE NOT NULL,
  recorded_by BIGINT UNSIGNED NOT NULL,
  CONSTRAINT ck_fw_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_fw_date (waste_date),
  CONSTRAINT fk_food_waste_records_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_food_waste_records_batch_id FOREIGN KEY (batch_id) REFERENCES inventory_batches (id),
  CONSTRAINT fk_food_waste_records_menu_item_id FOREIGN KEY (menu_item_id) REFERENCES menu_items (id),
  CONSTRAINT fk_food_waste_records_movement_id FOREIGN KEY (movement_id) REFERENCES inventory_movements (id),
  CONSTRAINT fk_food_waste_records_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Spoilage and waste';

CREATE TABLE temperature_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id BIGINT UNSIGNED NULL,
  equipment_name VARCHAR(80) NOT NULL,
  check_type ENUM('fridge','freezer','hot_hold','cooking','delivery') NOT NULL,
  temperature_c DECIMAL(5,2) NOT NULL,
  min_c DECIMAL(5,2) NULL,
  max_c DECIMAL(5,2) NULL,
  is_within_range TINYINT(1) NOT NULL,
  corrective_action VARCHAR(255) NULL,
  checked_by BIGINT UNSIGNED NOT NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tl_time (checked_at),
  KEY ix_tl_range (is_within_range, checked_at),
  CONSTRAINT fk_temperature_logs_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_temperature_logs_checked_by FOREIGN KEY (checked_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Food-safety temperature checks';

CREATE TABLE replenishment_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(20) NOT NULL,
  requesting_department_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id BIGINT UNSIGNED NOT NULL,
  quantity_requested DECIMAL(14,3) NOT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','approved','ordered','received','rejected','cancelled') NOT NULL DEFAULT 'open',
  notes VARCHAR(255) NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  CONSTRAINT ck_rr2_qty CHECK (quantity_requested > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rpr_no (request_no),
  KEY ix_rpr_status (status),
  CONSTRAINT fk_replenishment_requests_requesting_department_id FOREIGN KEY (requesting_department_id) REFERENCES departments (id),
  CONSTRAINT fk_replenishment_requests_location_id FOREIGN KEY (location_id) REFERENCES inventory_locations (id),
  CONSTRAINT fk_replenishment_requests_inventory_item_id FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
  CONSTRAINT fk_replenishment_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users (id),
  CONSTRAINT fk_replenishment_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock replenishment requests from any department';
