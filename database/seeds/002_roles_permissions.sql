-- Idempotent RBAC seed (generated). Re-running adds missing rows and never removes custom grants.

INSERT INTO permissions (code, name, module) VALUES
 ('admin.dashboard','View admin dashboard and system summaries','system'),
 ('user.view','View employee accounts','system'),
 ('user.manage','Create, edit, activate, deactivate and reset accounts','system'),
 ('department.view','View departments','system'),
 ('department.manage','Edit department details','system'),
 ('role.view','View roles and permissions','system'),
 ('role.manage','Create roles and assign permissions','system'),
 ('settings.view','View system settings','system'),
 ('settings.manage','Change system settings','system'),
 ('audit.view','View audit logs','system'),
 ('login_activity.view','View login activity and security events','system'),
 ('error_log.view','View API and system errors','system'),
 ('session.manage','View and revoke active sessions','system'),
 ('notification.manage','Broadcast and review notifications','system'),
 ('approval.view','View approval requests','system'),
 ('approval.decide','Approve or reject approval requests','system'),
 ('backup.manage','Create and download database backups','system'),
 ('guest.view','View guest profiles','front_office'),
 ('guest.manage','Create and edit guests','front_office'),
 ('reservation.view','View reservations','front_office'),
 ('reservation.manage','Create, modify and cancel reservations','front_office'),
 ('room.view','View rooms and availability','front_office'),
 ('room.assign','Assign and reassign rooms','front_office'),
 ('stay.checkin','Check guests in','front_office'),
 ('stay.checkout','Check guests out','front_office'),
 ('checkout.override','Approve checkout with an outstanding balance','front_office'),
 ('folio.view','View guest folios','front_office'),
 ('request.manage','Handle guest requests and complaints','front_office'),
 ('report.view','View management reports','management'),
 ('report.export','Export reports','management'),
 ('hk.task.view','View housekeeping tasks','housekeeping'),
 ('hk.task.manage','Assign and update housekeeping tasks','housekeeping'),
 ('hk.inspect','Record room inspections','housekeeping'),
 ('lostfound.manage','Manage lost-and-found','housekeeping'),
 ('linen.manage','Manage linen and amenities','housekeeping'),
 ('maint.request.create','Submit maintenance requests','housekeeping'),
 ('menu.manage','Manage menu and prices','fnb'),
 ('order.view','View orders','fnb'),
 ('order.manage','Create and edit orders','fnb'),
 ('order.void','Void or cancel orders','fnb'),
 ('folio.charge_room','Charge orders to a room folio','fnb'),
 ('fnb.report','View F&B sales reports','fnb'),
 ('kitchen.order.update','Update kitchen order status','kitchen'),
 ('inventory.view','View inventory','kitchen'),
 ('inventory.manage','Record stock movements','kitchen'),
 ('recipe.manage','Manage recipes','kitchen'),
 ('waste.record','Record food waste','kitchen'),
 ('foodsafety.record','Record food safety checks','kitchen'),
 ('maint.workorder.manage','Create and manage work orders','maintenance'),
 ('asset.manage','Manage equipment and assets','maintenance'),
 ('room.block','Place rooms out of order','maintenance'),
 ('room.restore_verify','Verify and restore rooms to service','maintenance'),
 ('payment.record','Record payments','finance'),
 ('payment.verify','Verify external payments','finance'),
 ('refund.request','Request refunds','finance'),
 ('refund.process','Process approved refunds','finance'),
 ('invoice.manage','Generate invoices and receipts','finance'),
 ('expense.manage','Record expenses and payables','finance'),
 ('period.close','Close financial periods','finance'),
 ('finance.report','View financial reports','finance'),
 ('security.incident.view','View security incidents','security'),
 ('security.incident.manage','Report and update incidents','security'),
 ('security.evidence.view','View CCTV references and evidence metadata','security'),
 ('visitor.manage','Manage visitor logs','security'),
 ('patrol.manage','Manage patrols and handovers','security')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT INTO roles (code, name, department_id, description, is_system) VALUES
 ('admin','System Administrator',(SELECT id FROM departments WHERE code='ADMIN'),'Controls access and system settings. Has NO financial, guest or security-evidence permissions by default.',1),
 ('front_office','Front Office Staff',(SELECT id FROM departments WHERE code='FRONT_OFFICE'),'Guest services, reservations and check-in/out',1),
 ('management','Management (read-only)',(SELECT id FROM departments WHERE code='MANAGEMENT'),'Executive reporting. Read-only unless extra permissions are assigned.',1),
 ('housekeeping','Housekeeping Staff',(SELECT id FROM departments WHERE code='HOUSEKEEPING'),'Cleaning, inspections, linen',1),
 ('fnb','Food and Beverage Staff',(SELECT id FROM departments WHERE code='FNB'),'Restaurant and room-service ordering',1),
 ('kitchen','Kitchen Staff',(SELECT id FROM departments WHERE code='KITCHEN'),'Preparation and kitchen inventory',1),
 ('maintenance','Maintenance Staff',(SELECT id FROM departments WHERE code='MAINTENANCE'),'Work orders and room restrictions',1),
 ('finance','Accounts and Finance Staff',(SELECT id FROM departments WHERE code='FINANCE'),'Official financial records',1),
 ('security','Security Staff',(SELECT id FROM departments WHERE code='SECURITY'),'Incidents, visitors, patrols',1)
ON DUPLICATE KEY UPDATE code = code;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('admin.dashboard','approval.view','audit.view','backup.manage','department.manage','department.view','error_log.view','login_activity.view','notification.manage','role.manage','role.view','session.manage','settings.manage','settings.view','user.manage','user.view') WHERE r.code='admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('approval.view','checkout.override','folio.view','guest.manage','guest.view','report.view','request.manage','reservation.manage','reservation.view','room.assign','room.view','stay.checkin','stay.checkout') WHERE r.code='front_office';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('approval.view','folio.view','guest.view','hk.task.view','inventory.view','order.view','report.export','report.view','reservation.view','room.view','security.incident.view') WHERE r.code='management';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('hk.inspect','hk.task.manage','hk.task.view','linen.manage','lostfound.manage','maint.request.create','room.view') WHERE r.code='housekeeping';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('fnb.report','folio.charge_room','folio.view','menu.manage','order.manage','order.view','order.void','room.view') WHERE r.code='fnb';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('foodsafety.record','inventory.manage','inventory.view','kitchen.order.update','maint.request.create','order.view','recipe.manage','waste.record') WHERE r.code='kitchen';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('asset.manage','inventory.view','maint.workorder.manage','room.block','room.restore_verify','room.view') WHERE r.code='maintenance';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('approval.decide','approval.view','expense.manage','finance.report','folio.view','guest.view','invoice.manage','order.view','payment.record','payment.verify','period.close','refund.process','refund.request','report.view') WHERE r.code='finance';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('guest.view','patrol.manage','room.view','security.evidence.view','security.incident.manage','security.incident.view','visitor.manage') WHERE r.code='security';
