-- Migration 009: cross-group foreign keys that could not be declared earlier (tables created later)
ALTER TABLE room_blocks
  ADD COLUMN work_order_id BIGINT UNSIGNED NULL AFTER reason,
  ADD CONSTRAINT fk_room_blocks_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders (id),
  ADD KEY ix_room_blocks_work_order (work_order_id);

ALTER TABLE lost_found_items
  ADD COLUMN security_incident_id BIGINT UNSIGNED NULL AFTER status,
  ADD CONSTRAINT fk_lost_found_incident FOREIGN KEY (security_incident_id) REFERENCES security_incidents (id),
  ADD KEY ix_lost_found_incident (security_incident_id);
