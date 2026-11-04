-- Migration 010: database-level protection of history. Records that must be kept for audit can be neither deleted nor (for ledgers) edited.

CREATE TRIGGER trg_audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs: historical records cannot be deleted';
CREATE TRIGGER trg_approval_history_no_delete BEFORE DELETE ON approval_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'approval_history: historical records cannot be deleted';
CREATE TRIGGER trg_approval_requests_no_delete BEFORE DELETE ON approval_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'approval_requests: historical records cannot be deleted';
CREATE TRIGGER trg_guests_no_delete BEFORE DELETE ON guests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'guests: historical records cannot be deleted';
CREATE TRIGGER trg_reservations_no_delete BEFORE DELETE ON reservations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservations: historical records cannot be deleted';
CREATE TRIGGER trg_reservation_rooms_no_delete BEFORE DELETE ON reservation_rooms FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_rooms: historical records cannot be deleted';
CREATE TRIGGER trg_reservation_guests_no_delete BEFORE DELETE ON reservation_guests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_guests: historical records cannot be deleted';
CREATE TRIGGER trg_reservation_status_history_no_delete BEFORE DELETE ON reservation_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_status_history: historical records cannot be deleted';
CREATE TRIGGER trg_stays_no_delete BEFORE DELETE ON stays FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stays: historical records cannot be deleted';
CREATE TRIGGER trg_stay_room_changes_no_delete BEFORE DELETE ON stay_room_changes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stay_room_changes: historical records cannot be deleted';
CREATE TRIGGER trg_room_status_history_no_delete BEFORE DELETE ON room_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'room_status_history: historical records cannot be deleted';
CREATE TRIGGER trg_folios_no_delete BEFORE DELETE ON folios FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folios: historical records cannot be deleted';
CREATE TRIGGER trg_folio_charges_no_delete BEFORE DELETE ON folio_charges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio_charges: historical records cannot be deleted';
CREATE TRIGGER trg_invoices_no_delete BEFORE DELETE ON invoices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoices: historical records cannot be deleted';
CREATE TRIGGER trg_invoice_items_no_delete BEFORE DELETE ON invoice_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoice_items: historical records cannot be deleted';
CREATE TRIGGER trg_payments_no_delete BEFORE DELETE ON payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments: historical records cannot be deleted';
CREATE TRIGGER trg_payment_allocations_no_delete BEFORE DELETE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment_allocations: historical records cannot be deleted';
CREATE TRIGGER trg_deposits_no_delete BEFORE DELETE ON deposits FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'deposits: historical records cannot be deleted';
CREATE TRIGGER trg_refunds_no_delete BEFORE DELETE ON refunds FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refunds: historical records cannot be deleted';
CREATE TRIGGER trg_financial_adjustments_no_delete BEFORE DELETE ON financial_adjustments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'financial_adjustments: historical records cannot be deleted';
CREATE TRIGGER trg_supplier_invoices_no_delete BEFORE DELETE ON supplier_invoices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'supplier_invoices: historical records cannot be deleted';
CREATE TRIGGER trg_expenses_no_delete BEFORE DELETE ON expenses FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'expenses: historical records cannot be deleted';
CREATE TRIGGER trg_financial_periods_no_delete BEFORE DELETE ON financial_periods FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'financial_periods: historical records cannot be deleted';
CREATE TRIGGER trg_daily_reconciliations_no_delete BEFORE DELETE ON daily_reconciliations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'daily_reconciliations: historical records cannot be deleted';
CREATE TRIGGER trg_financial_transaction_refs_no_delete BEFORE DELETE ON financial_transaction_refs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'financial_transaction_refs: historical records cannot be deleted';
CREATE TRIGGER trg_orders_no_delete BEFORE DELETE ON orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'orders: historical records cannot be deleted';
CREATE TRIGGER trg_order_items_no_delete BEFORE DELETE ON order_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_items: historical records cannot be deleted';
CREATE TRIGGER trg_order_status_history_no_delete BEFORE DELETE ON order_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_status_history: historical records cannot be deleted';
CREATE TRIGGER trg_order_voids_no_delete BEFORE DELETE ON order_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_voids: historical records cannot be deleted';
CREATE TRIGGER trg_room_service_charges_no_delete BEFORE DELETE ON room_service_charges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'room_service_charges: historical records cannot be deleted';
CREATE TRIGGER trg_order_payments_no_delete BEFORE DELETE ON order_payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_payments: historical records cannot be deleted';
CREATE TRIGGER trg_inventory_movements_no_delete BEFORE DELETE ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory_movements: historical records cannot be deleted';
CREATE TRIGGER trg_inventory_batches_no_delete BEFORE DELETE ON inventory_batches FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory_batches: historical records cannot be deleted';
CREATE TRIGGER trg_stock_receipts_no_delete BEFORE DELETE ON stock_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_receipts: historical records cannot be deleted';
CREATE TRIGGER trg_stock_receipt_items_no_delete BEFORE DELETE ON stock_receipt_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_receipt_items: historical records cannot be deleted';
CREATE TRIGGER trg_stock_adjustments_no_delete BEFORE DELETE ON stock_adjustments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_adjustments: historical records cannot be deleted';
CREATE TRIGGER trg_food_waste_records_no_delete BEFORE DELETE ON food_waste_records FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'food_waste_records: historical records cannot be deleted';
CREATE TRIGGER trg_work_orders_no_delete BEFORE DELETE ON work_orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'work_orders: historical records cannot be deleted';
CREATE TRIGGER trg_maintenance_costs_no_delete BEFORE DELETE ON maintenance_costs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'maintenance_costs: historical records cannot be deleted';
CREATE TRIGGER trg_lost_found_items_no_delete BEFORE DELETE ON lost_found_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lost_found_items: historical records cannot be deleted';
CREATE TRIGGER trg_security_incidents_no_delete BEFORE DELETE ON security_incidents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'security_incidents: historical records cannot be deleted';
CREATE TRIGGER trg_incident_updates_no_delete BEFORE DELETE ON incident_updates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'incident_updates: historical records cannot be deleted';
CREATE TRIGGER trg_cctv_references_no_delete BEFORE DELETE ON cctv_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cctv_references: historical records cannot be deleted';
CREATE TRIGGER trg_audit_logs_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_approval_history_no_update BEFORE UPDATE ON approval_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'approval_history: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_room_status_history_no_update BEFORE UPDATE ON room_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'room_status_history: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_reservation_status_history_no_update BEFORE UPDATE ON reservation_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_status_history: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_stay_room_changes_no_update BEFORE UPDATE ON stay_room_changes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stay_room_changes: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_folio_charges_no_update BEFORE UPDATE ON folio_charges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'folio_charges: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_payment_allocations_no_update BEFORE UPDATE ON payment_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payment_allocations: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_financial_transaction_refs_no_update BEFORE UPDATE ON financial_transaction_refs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'financial_transaction_refs: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_order_status_history_no_update BEFORE UPDATE ON order_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_status_history: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_order_voids_no_update BEFORE UPDATE ON order_voids FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'order_voids: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_inventory_movements_no_update BEFORE UPDATE ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory_movements: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_stock_receipt_items_no_update BEFORE UPDATE ON stock_receipt_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stock_receipt_items: ledger rows are append-only; post a reversal instead';
CREATE TRIGGER trg_incident_updates_no_update BEFORE UPDATE ON incident_updates FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'incident_updates: ledger rows are append-only; post a reversal instead';

CREATE TRIGGER trg_payments_guard BEFORE UPDATE ON payments FOR EACH ROW
BEGIN
  IF NOT (OLD.payment_no <=> NEW.payment_no AND OLD.folio_id <=> NEW.folio_id AND OLD.invoice_id <=> NEW.invoice_id AND OLD.guest_id <=> NEW.guest_id
          AND OLD.method <=> NEW.method AND OLD.amount <=> NEW.amount AND OLD.currency <=> NEW.currency AND OLD.idempotency_key <=> NEW.idempotency_key
          AND OLD.received_by <=> NEW.received_by AND OLD.received_at <=> NEW.received_at AND (OLD.reference_no IS NULL OR OLD.reference_no <=> NEW.reference_no)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments: amount, method, reference and ownership are immutable';
  END IF;
  IF OLD.status IN ('failed','reversed') AND NEW.status <> OLD.status THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments: failed or reversed payments cannot change status';
  END IF;
  IF OLD.status = 'verified' AND NEW.status NOT IN ('verified','reversed') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments: a verified payment can only be reversed';
  END IF;
END;

CREATE TRIGGER trg_invoices_guard BEFORE UPDATE ON invoices FOR EACH ROW
BEGIN
  IF OLD.status <> 'draft' AND NOT (OLD.invoice_no <=> NEW.invoice_no AND OLD.folio_id <=> NEW.folio_id AND OLD.guest_id <=> NEW.guest_id AND OLD.invoice_type <=> NEW.invoice_type
          AND OLD.subtotal <=> NEW.subtotal AND OLD.tax_total <=> NEW.tax_total AND OLD.service_total <=> NEW.service_total AND OLD.discount_total <=> NEW.discount_total AND OLD.total <=> NEW.total
          AND OLD.issue_date <=> NEW.issue_date) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoices: an issued invoice cannot be edited; issue a credit note';
  END IF;
  IF (OLD.status = 'void' AND NEW.status <> 'void') OR (OLD.status = 'issued' AND NEW.status = 'draft') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'invoices: invalid status transition';
  END IF;
END;
