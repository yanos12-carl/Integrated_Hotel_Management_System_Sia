# IHMS Entity-Relationship Diagrams

_Generated from the live database. Open in VS Code (Markdown Preview Mermaid Support), GitHub, or paste the `.mmd` files at mermaid.live. Tables from another group appear only as small reference boxes. Links from actor columns (`created_by`, `changed_by`, `recorded_by`, ...) to `users` are listed in each table but not drawn, to keep the diagrams readable._

## How the groups connect

```mermaid
flowchart LR
  A["A: Shared system and access control"]
  B["B: Guests and accommodation"]
  C["C: Billing and finance"]
  D["D: Food and beverage"]
  E["E: Kitchen and inventory"]
  F["F: Housekeeping"]
  G["G: Maintenance"]
  H["H: Security"]
  G -->|11 FKs| A
  G -->|5 FKs| B
  H -->|26 FKs| A
  C -->|21 FKs| A
  C -->|7 FKs| B
  E -->|2 FKs| D
  E -->|11 FKs| A
  B -->|16 FKs| A
  F -->|8 FKs| A
  F -->|7 FKs| B
  E -->|4 FKs| C
  F -->|1 FKs| E
  F -->|1 FKs| H
  G -->|1 FKs| C
  D -->|8 FKs| A
  D -->|5 FKs| B
  D -->|3 FKs| C
  B -->|1 FKs| G
  H -->|5 FKs| B
  G -->|2 FKs| E
```

---

## Group A: Shared system and access control (17 tables)

```mermaid
erDiagram
  departments {
    bigint id PK
    varchar code UK
    varchar name UK
  }
  users {
    bigint id PK
    bigint department_id FK
    varchar employee_no UK
    varchar username UK
    varchar email UK
    enum status 
    bigint created_by FK
  }
  roles {
    bigint id PK
    varchar code UK
    bigint department_id FK
  }
  permissions {
    bigint id PK
    varchar code UK
  }
  user_roles {
    bigint user_id PK
    bigint role_id PK
    bigint assigned_by FK
  }
  role_permissions {
    bigint role_id PK
    bigint permission_id PK
  }
  user_departments {
    bigint user_id PK
    bigint department_id PK
  }
  login_attempts {
    bigint id PK
    bigint user_id FK
  }
  sessions {
    varchar id PK
    bigint user_id FK
  }
  password_resets {
    bigint id PK
    bigint user_id FK
    char token_hash UK
  }
  system_settings {
    varchar setting_key PK
    bigint updated_by FK
  }
  notifications {
    bigint id PK
    bigint created_by FK
  }
  notification_recipients {
    bigint id PK
    bigint notification_id FK
    bigint user_id FK
  }
  audit_logs {
    bigint id PK
    bigint user_id FK
  }
  error_logs {
    bigint id PK
    bigint user_id FK
  }
  approval_requests {
    bigint id PK
    bigint requested_by FK
    enum status 
    bigint decided_by FK
  }
  approval_history {
    bigint id PK
    bigint approval_request_id FK
    bigint actor_id FK
  }
  users ||--o{ approval_history : "actor_id"
  approval_requests ||--o{ approval_history : "approval_request_id"
  users |o--o{ approval_requests : "decided_by"
  users ||--o{ approval_requests : "requested_by"
  users |o--o{ audit_logs : "user_id"
  users |o--o{ error_logs : "user_id"
  users |o--o{ login_attempts : "user_id"
  users |o--o{ notifications : "created_by"
  notifications ||--o{ notification_recipients : "notification_id"
  users ||--o{ notification_recipients : "user_id"
  users ||--o{ password_resets : "user_id"
  departments |o--o{ roles : "department_id"
  permissions ||--o{ role_permissions : "permission_id"
  roles ||--o{ role_permissions : "role_id"
  users |o--o{ sessions : "user_id"
  users |o--o{ system_settings : "updated_by"
  users |o--o{ users : "created_by"
  departments ||--o{ users : "department_id"
  departments ||--o{ user_departments : "department_id"
  users ||--o{ user_departments : "user_id"
  users |o--o{ user_roles : "assigned_by"
  roles ||--o{ user_roles : "role_id"
  users ||--o{ user_roles : "user_id"
```

---

## Group B: Guests and accommodation (19 tables)

```mermaid
erDiagram
  guests {
    bigint id PK
    varchar guest_code UK
    enum status 
    bigint created_by FK
  }
  guest_contacts {
    bigint id PK
    bigint guest_id FK
  }
  guest_preferences {
    bigint id PK
    bigint guest_id FK
    bigint created_by FK
  }
  room_types {
    bigint id PK
    varchar code UK
  }
  rooms {
    bigint id PK
    varchar room_number UK
    bigint room_type_id FK
    enum status 
  }
  room_amenities {
    bigint id PK
    varchar code UK
  }
  room_type_amenities {
    bigint room_type_id PK
    bigint amenity_id PK
  }
  rate_plans {
    bigint id PK
    varchar code UK
  }
  room_rates {
    bigint id PK
    bigint rate_plan_id FK
    bigint room_type_id FK
    bigint created_by FK
  }
  room_blocks {
    bigint id PK
    bigint room_id FK
    bigint work_order_id FK
    enum status 
    bigint created_by FK
    bigint released_by FK
  }
  reservations {
    bigint id PK
    varchar reservation_no UK
    bigint primary_guest_id FK
    enum status 
    bigint rate_plan_id FK
    varchar idempotency_key UK
    bigint created_by FK
  }
  reservation_guests {
    bigint reservation_id PK
    bigint guest_id PK
  }
  reservation_rooms {
    bigint id PK
    bigint reservation_id FK
    bigint room_type_id FK
    bigint room_id FK
    bigint rate_plan_id FK
    enum status 
  }
  reservation_status_history {
    bigint id PK
    bigint reservation_id FK
    bigint changed_by FK
  }
  stays {
    bigint id PK
    bigint reservation_room_id FK
    bigint guest_id FK
    bigint room_id FK
    enum status 
    bigint checked_in_by FK
    bigint checked_out_by FK
    bigint checkout_approval_id FK
    bigint active_room_id UK
  }
  stay_room_changes {
    bigint id PK
    bigint stay_id FK
    bigint from_room_id FK
    bigint to_room_id FK
    bigint changed_by FK
  }
  room_status_history {
    bigint id PK
    bigint room_id FK
    bigint stay_id FK
    bigint changed_by FK
  }
  guest_requests {
    bigint id PK
    varchar request_no UK
    bigint guest_id FK
    bigint stay_id FK
    bigint reservation_id FK
    bigint room_id FK
    enum status 
    bigint created_by FK
  }
  guest_request_assignments {
    bigint id PK
    bigint guest_request_id FK
    bigint assigned_user_id FK
    bigint assigned_department_id FK
    bigint assigned_by FK
  }
  departments {
    bigint id PK
  }
  work_orders {
    bigint id PK
  }
  approval_requests {
    bigint id PK
  }
  guests ||--o{ guest_contacts : "guest_id"
  guests ||--o{ guest_preferences : "guest_id"
  guests |o--o{ guest_requests : "guest_id"
  reservations |o--o{ guest_requests : "reservation_id"
  rooms |o--o{ guest_requests : "room_id"
  stays |o--o{ guest_requests : "stay_id"
  departments |o--o{ guest_request_assignments : "assigned_department_id"
  guest_requests ||--o{ guest_request_assignments : "guest_request_id"
  guests ||--o{ reservations : "primary_guest_id"
  rate_plans |o--o{ reservations : "rate_plan_id"
  guests ||--o{ reservation_guests : "guest_id"
  reservations ||--o{ reservation_guests : "reservation_id"
  rate_plans |o--o{ reservation_rooms : "rate_plan_id"
  reservations ||--o{ reservation_rooms : "reservation_id"
  rooms |o--o{ reservation_rooms : "room_id"
  room_types ||--o{ reservation_rooms : "room_type_id"
  reservations ||--o{ reservation_status_history : "reservation_id"
  room_types ||--o{ rooms : "room_type_id"
  rooms ||--o{ room_blocks : "room_id"
  work_orders |o--o{ room_blocks : "work_order_id"
  rate_plans ||--o{ room_rates : "rate_plan_id"
  room_types ||--o{ room_rates : "room_type_id"
  rooms ||--o{ room_status_history : "room_id"
  stays |o--o{ room_status_history : "stay_id"
  room_amenities ||--o{ room_type_amenities : "amenity_id"
  room_types ||--o{ room_type_amenities : "room_type_id"
  approval_requests |o--o{ stays : "checkout_approval_id"
  guests ||--o{ stays : "guest_id"
  reservation_rooms ||--o{ stays : "reservation_room_id"
  rooms ||--o{ stays : "room_id"
  rooms ||--o{ stay_room_changes : "from_room_id"
  stays ||--o{ stay_room_changes : "stay_id"
  rooms ||--o{ stay_room_changes : "to_room_id"
```

---

## Group C: Billing and finance (16 tables)

```mermaid
erDiagram
  suppliers {
    bigint id PK
    varchar code UK
    varchar name UK
  }
  folios {
    bigint id PK
    varchar folio_no UK
    bigint guest_id FK
    bigint stay_id FK
    bigint reservation_id FK
    enum status 
    bigint closed_by FK
    bigint created_by FK
  }
  folio_charges {
    bigint id PK
    bigint folio_id FK
    bigint reverses_charge_id FK
    varchar idempotency_key UK
    bigint posted_by FK
  }
  invoices {
    bigint id PK
    varchar invoice_no UK
    bigint folio_id FK
    bigint guest_id FK
    bigint original_invoice_id FK
    enum status 
    bigint issued_by FK
    bigint voided_by FK
  }
  invoice_items {
    bigint id PK
    bigint invoice_id FK
    bigint folio_charge_id FK
  }
  payments {
    bigint id PK
    varchar payment_no UK
    bigint folio_id FK
    bigint invoice_id FK
    bigint guest_id FK
    enum status 
    varchar reference_no 
    bigint received_by FK
    bigint verified_by FK
    varchar idempotency_key UK
  }
  payment_allocations {
    bigint id PK
    bigint payment_id FK
    bigint folio_charge_id FK
    bigint invoice_id FK
    bigint allocated_by FK
  }
  deposits {
    bigint id PK
    varchar deposit_no UK
    bigint guest_id FK
    bigint reservation_id FK
    bigint payment_id FK
    enum status 
    bigint applied_folio_id FK
    bigint created_by FK
  }
  refunds {
    bigint id PK
    varchar refund_no UK
    bigint payment_id FK
    bigint folio_id FK
    enum status 
    bigint approval_request_id FK
    varchar reference_no 
    bigint requested_by FK
    bigint processed_by FK
    varchar idempotency_key UK
  }
  financial_adjustments {
    bigint id PK
    varchar adjustment_no UK
    bigint folio_id FK
    bigint folio_charge_id FK
    enum status 
    bigint approval_request_id FK
    bigint applied_charge_id FK
    bigint created_by FK
  }
  expense_categories {
    bigint id PK
    varchar code UK
  }
  supplier_invoices {
    bigint id PK
    bigint supplier_id FK
    varchar supplier_invoice_no 
    enum status 
    bigint created_by FK
  }
  expenses {
    bigint id PK
    varchar expense_no UK
    bigint category_id FK
    bigint supplier_id FK
    bigint supplier_invoice_id FK
    bigint department_id FK
    varchar reference_no 
    enum status 
    bigint recorded_by FK
    bigint approved_by FK
  }
  financial_periods {
    bigint id PK
    varchar period_code UK
    enum status 
    bigint closed_by FK
  }
  daily_reconciliations {
    bigint id PK
    enum status 
    bigint reconciled_by FK
    bigint approved_by FK
  }
  financial_transaction_refs {
    bigint id PK
    varchar txn_ref UK
    bigint period_id FK
  }
  guests {
    bigint id PK
  }
  reservations {
    bigint id PK
  }
  departments {
    bigint id PK
  }
  approval_requests {
    bigint id PK
  }
  stays {
    bigint id PK
  }
  folios |o--o{ deposits : "applied_folio_id"
  guests ||--o{ deposits : "guest_id"
  payments |o--o{ deposits : "payment_id"
  reservations |o--o{ deposits : "reservation_id"
  expense_categories ||--o{ expenses : "category_id"
  departments |o--o{ expenses : "department_id"
  suppliers |o--o{ expenses : "supplier_id"
  supplier_invoices |o--o{ expenses : "supplier_invoice_id"
  folio_charges |o--o{ financial_adjustments : "applied_charge_id"
  approval_requests |o--o{ financial_adjustments : "approval_request_id"
  folio_charges |o--o{ financial_adjustments : "folio_charge_id"
  folios ||--o{ financial_adjustments : "folio_id"
  financial_periods |o--o{ financial_transaction_refs : "period_id"
  guests ||--o{ folios : "guest_id"
  reservations |o--o{ folios : "reservation_id"
  stays |o--o{ folios : "stay_id"
  folios ||--o{ folio_charges : "folio_id"
  folio_charges |o--o{ folio_charges : "reverses_charge_id"
  folios ||--o{ invoices : "folio_id"
  guests ||--o{ invoices : "guest_id"
  invoices |o--o{ invoices : "original_invoice_id"
  folio_charges |o--o{ invoice_items : "folio_charge_id"
  invoices ||--o{ invoice_items : "invoice_id"
  folios |o--o{ payments : "folio_id"
  guests |o--o{ payments : "guest_id"
  invoices |o--o{ payments : "invoice_id"
  folio_charges |o--o{ payment_allocations : "folio_charge_id"
  invoices |o--o{ payment_allocations : "invoice_id"
  payments ||--o{ payment_allocations : "payment_id"
  approval_requests |o--o{ refunds : "approval_request_id"
  folios |o--o{ refunds : "folio_id"
  payments ||--o{ refunds : "payment_id"
  suppliers ||--o{ supplier_invoices : "supplier_id"
```

---

## Group D: Food and beverage (11 tables)

```mermaid
erDiagram
  menu_categories {
    bigint id PK
    varchar code UK
  }
  menu_items {
    bigint id PK
    bigint category_id FK
    varchar sku UK
  }
  restaurant_tables {
    bigint id PK
    varchar table_no UK
    enum status 
  }
  table_reservations {
    bigint id PK
    bigint table_id FK
    bigint guest_id FK
    enum status 
    bigint created_by FK
  }
  orders {
    bigint id PK
    varchar order_no UK
    enum status 
    bigint table_id FK
    bigint table_reservation_id FK
    bigint stay_id FK
    bigint room_id FK
    bigint guest_id FK
    bigint discount_approved_by FK
    varchar idempotency_key UK
    bigint placed_by FK
  }
  order_items {
    bigint id PK
    bigint order_id FK
    bigint menu_item_id FK
    enum status 
  }
  order_status_history {
    bigint id PK
    bigint order_id FK
    bigint changed_by FK
  }
  order_voids {
    bigint id PK
    bigint order_id FK
    bigint order_item_id FK
    bigint approval_request_id FK
    bigint voided_by FK
    bigint approved_by FK
  }
  room_service_charges {
    bigint id PK
    bigint order_id FK
    bigint folio_id FK
    bigint folio_charge_id FK
    bigint posted_by FK
  }
  order_payments {
    bigint id PK
    bigint order_id FK
    bigint payment_id FK
  }
  order_feedback {
    bigint id PK
    bigint order_id FK
    bigint guest_id FK
  }
  guests {
    bigint id PK
  }
  rooms {
    bigint id PK
  }
  stays {
    bigint id PK
  }
  payments {
    bigint id PK
  }
  approval_requests {
    bigint id PK
  }
  folio_charges {
    bigint id PK
  }
  folios {
    bigint id PK
  }
  menu_categories ||--o{ menu_items : "category_id"
  guests |o--o{ orders : "guest_id"
  rooms |o--o{ orders : "room_id"
  stays |o--o{ orders : "stay_id"
  restaurant_tables |o--o{ orders : "table_id"
  table_reservations |o--o{ orders : "table_reservation_id"
  guests |o--o{ order_feedback : "guest_id"
  orders ||--o{ order_feedback : "order_id"
  menu_items ||--o{ order_items : "menu_item_id"
  orders ||--o{ order_items : "order_id"
  orders ||--o{ order_payments : "order_id"
  payments ||--o{ order_payments : "payment_id"
  orders ||--o{ order_status_history : "order_id"
  approval_requests |o--o{ order_voids : "approval_request_id"
  orders ||--o{ order_voids : "order_id"
  order_items |o--o{ order_voids : "order_item_id"
  folio_charges ||--o{ room_service_charges : "folio_charge_id"
  folios ||--o{ room_service_charges : "folio_id"
  orders ||--o{ room_service_charges : "order_id"
  guests |o--o{ table_reservations : "guest_id"
  restaurant_tables ||--o{ table_reservations : "table_id"
```

---

## Group E: Kitchen and inventory (13 tables)

```mermaid
erDiagram
  inventory_locations {
    bigint id PK
    varchar code UK
    bigint department_id FK
  }
  inventory_items {
    bigint id PK
    varchar sku UK
    bigint owning_department_id FK
    bigint preferred_supplier_id FK
  }
  recipes {
    bigint id PK
    bigint menu_item_id FK
  }
  recipe_ingredients {
    bigint id PK
    bigint recipe_id FK
    bigint inventory_item_id FK
  }
  stock_balances {
    bigint id PK
    bigint inventory_item_id FK
    bigint location_id FK
  }
  stock_receipts {
    bigint id PK
    varchar receipt_no UK
    bigint supplier_id FK
    bigint supplier_invoice_id FK
    bigint location_id FK
    enum status 
    varchar idempotency_key UK
    bigint received_by FK
  }
  inventory_batches {
    bigint id PK
    bigint inventory_item_id FK
    bigint location_id FK
    varchar batch_no 
    bigint supplier_id FK
    bigint stock_receipt_id FK
    enum status 
  }
  stock_receipt_items {
    bigint id PK
    bigint stock_receipt_id FK
    bigint inventory_item_id FK
    bigint batch_id FK
  }
  inventory_movements {
    bigint id PK
    bigint inventory_item_id FK
    bigint location_id FK
    bigint batch_id FK
    varchar idempotency_key UK
    bigint performed_by FK
  }
  stock_adjustments {
    bigint id PK
    varchar adjustment_no UK
    bigint inventory_item_id FK
    bigint location_id FK
    enum status 
    bigint approval_request_id FK
    bigint movement_id FK
    bigint created_by FK
  }
  food_waste_records {
    bigint id PK
    bigint inventory_item_id FK
    bigint batch_id FK
    bigint menu_item_id FK
    bigint movement_id FK
    bigint recorded_by FK
  }
  temperature_logs {
    bigint id PK
    bigint location_id FK
    bigint checked_by FK
  }
  replenishment_requests {
    bigint id PK
    varchar request_no UK
    bigint requesting_department_id FK
    bigint location_id FK
    bigint inventory_item_id FK
    enum status 
    bigint requested_by FK
    bigint approved_by FK
  }
  menu_items {
    bigint id PK
  }
  suppliers {
    bigint id PK
  }
  departments {
    bigint id PK
  }
  approval_requests {
    bigint id PK
  }
  supplier_invoices {
    bigint id PK
  }
  inventory_batches |o--o{ food_waste_records : "batch_id"
  inventory_items ||--o{ food_waste_records : "inventory_item_id"
  menu_items |o--o{ food_waste_records : "menu_item_id"
  inventory_movements |o--o{ food_waste_records : "movement_id"
  inventory_items ||--o{ inventory_batches : "inventory_item_id"
  inventory_locations ||--o{ inventory_batches : "location_id"
  stock_receipts |o--o{ inventory_batches : "stock_receipt_id"
  suppliers |o--o{ inventory_batches : "supplier_id"
  departments |o--o{ inventory_items : "owning_department_id"
  suppliers |o--o{ inventory_items : "preferred_supplier_id"
  departments |o--o{ inventory_locations : "department_id"
  inventory_batches |o--o{ inventory_movements : "batch_id"
  inventory_items ||--o{ inventory_movements : "inventory_item_id"
  inventory_locations ||--o{ inventory_movements : "location_id"
  menu_items ||--o{ recipes : "menu_item_id"
  inventory_items ||--o{ recipe_ingredients : "inventory_item_id"
  recipes ||--o{ recipe_ingredients : "recipe_id"
  inventory_items ||--o{ replenishment_requests : "inventory_item_id"
  inventory_locations ||--o{ replenishment_requests : "location_id"
  departments ||--o{ replenishment_requests : "requesting_department_id"
  approval_requests |o--o{ stock_adjustments : "approval_request_id"
  inventory_items ||--o{ stock_adjustments : "inventory_item_id"
  inventory_locations ||--o{ stock_adjustments : "location_id"
  inventory_movements |o--o{ stock_adjustments : "movement_id"
  inventory_items ||--o{ stock_balances : "inventory_item_id"
  inventory_locations ||--o{ stock_balances : "location_id"
  inventory_locations ||--o{ stock_receipts : "location_id"
  suppliers |o--o{ stock_receipts : "supplier_id"
  supplier_invoices |o--o{ stock_receipts : "supplier_invoice_id"
  inventory_batches |o--o{ stock_receipt_items : "batch_id"
  inventory_items ||--o{ stock_receipt_items : "inventory_item_id"
  stock_receipts ||--o{ stock_receipt_items : "stock_receipt_id"
  inventory_locations |o--o{ temperature_logs : "location_id"
```

---

## Group F: Housekeeping (11 tables)

```mermaid
erDiagram
  housekeeping_tasks {
    bigint id PK
    varchar task_no UK
    bigint room_id FK
    bigint stay_id FK
    enum status 
    bigint recleaning_of_task_id FK
    bigint created_by FK
  }
  task_assignments {
    bigint id PK
    bigint task_id FK
    bigint user_id FK
    bigint assigned_by FK
  }
  cleaning_checklists {
    bigint id PK
    varchar code UK
  }
  checklist_items {
    bigint id PK
    bigint checklist_id FK
  }
  task_checklist_results {
    bigint id PK
    bigint task_id FK
    bigint checklist_item_id FK
    bigint completed_by FK
  }
  room_inspections {
    bigint id PK
    bigint room_id FK
    bigint task_id FK
    bigint inspector_id FK
  }
  inspection_results {
    bigint id PK
    bigint inspection_id FK
  }
  linen_items {
    bigint id PK
    varchar code UK
  }
  linen_movements {
    bigint id PK
    bigint linen_item_id FK
    bigint room_id FK
    bigint location_id FK
    bigint performed_by FK
  }
  lost_found_items {
    bigint id PK
    varchar item_no UK
    bigint found_room_id FK
    bigint guest_id FK
    bigint found_by FK
    enum status 
    bigint security_incident_id FK
  }
  lost_found_claims {
    bigint id PK
    bigint item_id FK
    bigint guest_id FK
    enum status 
    bigint handled_by FK
  }
  rooms {
    bigint id PK
  }
  stays {
    bigint id PK
  }
  inventory_locations {
    bigint id PK
  }
  guests {
    bigint id PK
  }
  security_incidents {
    bigint id PK
  }
  cleaning_checklists ||--o{ checklist_items : "checklist_id"
  housekeeping_tasks |o--o{ housekeeping_tasks : "recleaning_of_task_id"
  rooms |o--o{ housekeeping_tasks : "room_id"
  stays |o--o{ housekeeping_tasks : "stay_id"
  room_inspections ||--o{ inspection_results : "inspection_id"
  linen_items ||--o{ linen_movements : "linen_item_id"
  inventory_locations |o--o{ linen_movements : "location_id"
  rooms |o--o{ linen_movements : "room_id"
  guests |o--o{ lost_found_claims : "guest_id"
  lost_found_items ||--o{ lost_found_claims : "item_id"
  security_incidents |o--o{ lost_found_items : "security_incident_id"
  rooms |o--o{ lost_found_items : "found_room_id"
  guests |o--o{ lost_found_items : "guest_id"
  rooms ||--o{ room_inspections : "room_id"
  housekeeping_tasks |o--o{ room_inspections : "task_id"
  housekeeping_tasks ||--o{ task_assignments : "task_id"
  checklist_items ||--o{ task_checklist_results : "checklist_item_id"
  housekeeping_tasks ||--o{ task_checklist_results : "task_id"
```

---

## Group G: Maintenance (9 tables)

```mermaid
erDiagram
  assets {
    bigint id PK
    varchar asset_tag UK
    bigint room_id FK
    bigint department_id FK
    varchar serial_no 
    enum status 
  }
  maintenance_requests {
    bigint id PK
    varchar request_no UK
    bigint requested_by FK
    bigint requesting_department_id FK
    bigint room_id FK
    bigint asset_id FK
    bigint guest_request_id FK
    enum status 
  }
  preventive_schedules {
    bigint id PK
    bigint asset_id FK
  }
  work_orders {
    bigint id PK
    varchar work_order_no UK
    bigint request_id FK
    bigint preventive_schedule_id FK
    bigint room_id FK
    bigint asset_id FK
    enum status 
    bigint verified_by FK
    bigint created_by FK
  }
  work_assignments {
    bigint id PK
    bigint work_order_id FK
    bigint technician_id FK
    bigint assigned_by FK
  }
  work_logs {
    bigint id PK
    bigint work_order_id FK
    bigint user_id FK
  }
  work_order_parts {
    bigint id PK
    bigint work_order_id FK
    bigint inventory_item_id FK
    bigint movement_id FK
    bigint added_by FK
  }
  maintenance_costs {
    bigint id PK
    bigint work_order_id FK
    bigint expense_id FK
    bigint recorded_by FK
  }
  room_defects {
    bigint id PK
    bigint room_id FK
    bigint request_id FK
    bigint work_order_id FK
    enum status 
    bigint reported_by FK
  }
  departments {
    bigint id PK
  }
  rooms {
    bigint id PK
  }
  expenses {
    bigint id PK
  }
  guest_requests {
    bigint id PK
  }
  inventory_items {
    bigint id PK
  }
  inventory_movements {
    bigint id PK
  }
  departments |o--o{ assets : "department_id"
  rooms |o--o{ assets : "room_id"
  expenses |o--o{ maintenance_costs : "expense_id"
  work_orders ||--o{ maintenance_costs : "work_order_id"
  assets |o--o{ maintenance_requests : "asset_id"
  guest_requests |o--o{ maintenance_requests : "guest_request_id"
  departments ||--o{ maintenance_requests : "requesting_department_id"
  rooms |o--o{ maintenance_requests : "room_id"
  assets ||--o{ preventive_schedules : "asset_id"
  maintenance_requests |o--o{ room_defects : "request_id"
  rooms ||--o{ room_defects : "room_id"
  work_orders |o--o{ room_defects : "work_order_id"
  work_orders ||--o{ work_assignments : "work_order_id"
  work_orders ||--o{ work_logs : "work_order_id"
  assets |o--o{ work_orders : "asset_id"
  preventive_schedules |o--o{ work_orders : "preventive_schedule_id"
  maintenance_requests |o--o{ work_orders : "request_id"
  rooms |o--o{ work_orders : "room_id"
  inventory_items |o--o{ work_order_parts : "inventory_item_id"
  inventory_movements |o--o{ work_order_parts : "movement_id"
  work_orders ||--o{ work_order_parts : "work_order_id"
```

---

## Group H: Security (27 tables)

```mermaid
erDiagram
  security_incidents {
    bigint id PK
    varchar incident_no UK
    enum status 
    bigint room_id FK
    bigint guest_id FK
    bigint reported_by FK
  }
  incident_updates {
    bigint id PK
    bigint incident_id FK
    bigint created_by FK
  }
  incident_assignments {
    bigint id PK
    bigint incident_id FK
    bigint assigned_to FK
    bigint assigned_by FK
  }
  visitor_logs {
    bigint id PK
    bigint host_guest_id FK
    bigint host_user_id FK
    bigint stay_id FK
    bigint room_id FK
    varchar badge_no 
    enum status 
    bigint checked_in_by FK
    bigint checked_out_by FK
  }
  guard_shifts {
    bigint id PK
    bigint guard_user_id FK
    enum status 
  }
  shift_handovers {
    bigint id PK
    bigint from_shift_id FK
    bigint to_shift_id FK
    bigint from_user_id FK
    bigint to_user_id FK
  }
  patrol_schedules {
    bigint id PK
  }
  patrol_checkpoints {
    bigint id PK
    varchar code UK
    smallint sequence_no 
  }
  patrol_records {
    bigint id PK
    bigint patrol_schedule_id FK
    bigint guard_shift_id FK
    bigint checkpoint_id FK
    bigint guard_user_id FK
    enum status 
  }
  cctv_references {
    bigint id PK
    bigint incident_id FK
    bigint recorded_by FK
  }
  safety_inspections {
    bigint id PK
    bigint inspected_by FK
  }
  incidents {
    bigint id PK
    enum status 
    bigint reported_by FK
    bigint assigned_to FK
    varchar room_no 
  }
  security_app_incident_updates {
    bigint id PK
    bigint incident_id FK
    bigint user_id FK
    enum status 
  }
  security_staff {
    bigint id PK
    varchar staff_no UK
    enum status 
  }
  cctv_cameras {
    bigint id PK
    varchar name UK
    enum status 
    char browser_device_key UK
    bigint added_by FK
  }
  cctv_refs {
    bigint id PK
    bigint incident_id FK
    bigint added_by FK
  }
  visitors {
    bigint id PK
    varchar room_no 
    bigint logged_by FK
  }
  patrol_logs {
    bigint id PK
    bigint patrolled_by FK
  }
  shift_schedule {
    bigint id PK
    bigint staff_id FK
  }
  checkpoints {
    bigint id PK
    varchar name UK
  }
  patrol_schedule {
    bigint id PK
  }
  fire_inspections {
    bigint id PK
    bigint inspected_by FK
  }
  key_logs {
    bigint id PK
    bigint issued_by FK
  }
  restricted_access {
    bigint id PK
    bigint logged_by FK
  }
  emergencies {
    bigint id PK
    enum status 
    bigint reported_by FK
  }
  case_notes {
    bigint id PK
    bigint user_id FK
    varchar status 
  }
  security_sso_tickets {
    char ticket_id PK
    bigint user_id FK
  }
  guests {
    bigint id PK
  }
  rooms {
    bigint id PK
  }
  stays {
    bigint id PK
  }
  security_incidents |o--o{ cctv_references : "incident_id"
  incidents ||--o{ cctv_refs : "incident_id"
  security_incidents ||--o{ incident_assignments : "incident_id"
  security_incidents ||--o{ incident_updates : "incident_id"
  patrol_checkpoints ||--o{ patrol_records : "checkpoint_id"
  guard_shifts |o--o{ patrol_records : "guard_shift_id"
  patrol_schedules |o--o{ patrol_records : "patrol_schedule_id"
  incidents ||--o{ security_app_incident_updates : "incident_id"
  guests |o--o{ security_incidents : "guest_id"
  rooms |o--o{ security_incidents : "room_id"
  guard_shifts ||--o{ shift_handovers : "from_shift_id"
  guard_shifts |o--o{ shift_handovers : "to_shift_id"
  security_staff ||--o{ shift_schedule : "staff_id"
  guests |o--o{ visitor_logs : "host_guest_id"
  rooms |o--o{ visitor_logs : "room_id"
  stays |o--o{ visitor_logs : "stay_id"
```
