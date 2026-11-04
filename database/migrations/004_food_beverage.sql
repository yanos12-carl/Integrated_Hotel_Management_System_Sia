-- Migration 004: Group D, food and beverage

CREATE TABLE menu_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(80) NOT NULL,
  service_area ENUM('restaurant','room_service','both') NOT NULL DEFAULT 'both',
  display_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Menu sections';

CREATE TABLE menu_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  price DECIMAL(12,2) NOT NULL,
  station ENUM('kitchen','bar','pastry') NOT NULL DEFAULT 'kitchen',
  preparation_minutes SMALLINT UNSIGNED NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT ck_mi_price CHECK (price >= 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mi_sku (sku),
  KEY ix_mi_category (category_id, is_active),
  CONSTRAINT fk_menu_items_category_id FOREIGN KEY (category_id) REFERENCES menu_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sellable items and prices';

CREATE TABLE restaurant_tables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_no VARCHAR(10) NOT NULL,
  seats TINYINT UNSIGNED NOT NULL DEFAULT 2,
  area VARCHAR(40) NULL,
  status ENUM('available','occupied','reserved','out_of_service') NOT NULL DEFAULT 'available',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rt_no (table_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dining tables';

CREATE TABLE table_reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_id BIGINT UNSIGNED NOT NULL,
  guest_id BIGINT UNSIGNED NULL,
  guest_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  party_size TINYINT UNSIGNED NOT NULL,
  reserved_for DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  status ENUM('booked','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'booked',
  notes VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_tr_table_time (table_id, reserved_for),
  CONSTRAINT fk_table_reservations_table_id FOREIGN KEY (table_id) REFERENCES restaurant_tables (id),
  CONSTRAINT fk_table_reservations_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_table_reservations_created_by FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table bookings';

CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_no VARCHAR(20) NOT NULL,
  order_type ENUM('dine_in','takeaway','room_service') NOT NULL,
  status ENUM('placed','accepted','preparing','ready','served','delivered','billed','cancelled','voided') NOT NULL DEFAULT 'placed',
  table_id BIGINT UNSIGNED NULL,
  table_reservation_id BIGINT UNSIGNED NULL,
  stay_id BIGINT UNSIGNED NULL,
  room_id BIGINT UNSIGNED NULL,
  guest_id BIGINT UNSIGNED NULL,
  customer_name VARCHAR(120) NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  notes VARCHAR(500) NULL,
  vat_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
  service_charge_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
  discount_rate DECIMAL(5,4) NOT NULL DEFAULT 0,
  discount_reason VARCHAR(255) NULL,
  discount_approved_by BIGINT UNSIGNED NULL,
  payment_status ENUM('unpaid','charged_to_room','paid','refunded') NOT NULL DEFAULT 'unpaid',
  billed_at DATETIME NULL,
  idempotency_key VARCHAR(64) NULL,
  placed_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_no (order_no),
  UNIQUE KEY uq_orders_idem (idempotency_key),
  KEY ix_orders_status (status, created_at),
  KEY ix_orders_room (room_id),
  KEY ix_orders_stay (stay_id),
  CONSTRAINT fk_orders_table_id FOREIGN KEY (table_id) REFERENCES restaurant_tables (id),
  CONSTRAINT fk_orders_table_reservation_id FOREIGN KEY (table_reservation_id) REFERENCES table_reservations (id),
  CONSTRAINT fk_orders_stay_id FOREIGN KEY (stay_id) REFERENCES stays (id),
  CONSTRAINT fk_orders_room_id FOREIGN KEY (room_id) REFERENCES rooms (id),
  CONSTRAINT fk_orders_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id),
  CONSTRAINT fk_orders_discount_approved_by FOREIGN KEY (discount_approved_by) REFERENCES users (id),
  CONSTRAINT fk_orders_placed_by FOREIGN KEY (placed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One order record shared by F&B, Kitchen and Finance. Totals are computed from order_items using the snapshotted rates';

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  menu_item_id BIGINT UNSIGNED NOT NULL,
  item_name VARCHAR(120) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  special_instructions VARCHAR(255) NULL,
  station ENUM('kitchen','bar','pastry') NOT NULL DEFAULT 'kitchen',
  status ENUM('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  CONSTRAINT ck_oi_qty CHECK (quantity > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_oi_order (order_id),
  KEY ix_oi_item (menu_item_id),
  CONSTRAINT fk_order_items_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_order_items_menu_item_id FOREIGN KEY (menu_item_id) REFERENCES menu_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items on an order; name and price are snapshots of the menu at order time';

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_osh_order (order_id, changed_at),
  CONSTRAINT fk_order_status_history_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_order_status_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Every order status change with who and when (append-only); kitchen and F&B read the same trail';

CREATE TABLE order_voids (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NOT NULL,
  void_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  approval_request_id BIGINT UNSIGNED NULL,
  voided_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ov_order (order_id),
  CONSTRAINT fk_order_voids_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_order_voids_order_item_id FOREIGN KEY (order_item_id) REFERENCES order_items (id),
  CONSTRAINT fk_order_voids_approval_request_id FOREIGN KEY (approval_request_id) REFERENCES approval_requests (id),
  CONSTRAINT fk_order_voids_voided_by FOREIGN KEY (voided_by) REFERENCES users (id),
  CONSTRAINT fk_order_voids_approved_by FOREIGN KEY (approved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Order or item cancellations and voids with approval';

CREATE TABLE room_service_charges (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  folio_id BIGINT UNSIGNED NOT NULL,
  folio_charge_id BIGINT UNSIGNED NOT NULL,
  posted_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rsc_order (order_id),
  UNIQUE KEY uq_rsc_charge (folio_charge_id),
  CONSTRAINT fk_room_service_charges_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_room_service_charges_folio_id FOREIGN KEY (folio_id) REFERENCES folios (id),
  CONSTRAINT fk_room_service_charges_folio_charge_id FOREIGN KEY (folio_charge_id) REFERENCES folio_charges (id),
  CONSTRAINT fk_room_service_charges_posted_by FOREIGN KEY (posted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link between a room-service order and the folio charge that billed it (one per order)';

CREATE TABLE order_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_op_order_payment (order_id, payment_id),
  CONSTRAINT fk_order_payments_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_order_payments_payment_id FOREIGN KEY (payment_id) REFERENCES payments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Link between an order and the payment that settled it';

CREATE TABLE order_feedback (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  guest_id BIGINT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment VARCHAR(500) NULL,
  CONSTRAINT ck_of_rating CHECK (rating BETWEEN 1 AND 5),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_of_order (order_id),
  CONSTRAINT fk_order_feedback_order_id FOREIGN KEY (order_id) REFERENCES orders (id),
  CONSTRAINT fk_order_feedback_guest_id FOREIGN KEY (guest_id) REFERENCES guests (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer feedback on food and service';
