-- Idempotent seed: reference data finance needs on day one, plus the Admin database-overview permission.
INSERT INTO expense_categories (code, name, category_type) VALUES
 ('UTILITIES','Utilities (power, water, internet)','utilities'),
 ('PAYROLL','Payroll and employee benefits','payroll'),
 ('SUPPLIES','Operating supplies','supplies'),
 ('REPAIRS','Repairs and maintenance','maintenance'),
 ('FOOD_COST','Food and beverage purchases','operating'),
 ('MARKETING','Marketing and commissions','operating'),
 ('OTHER','Other operating expenses','other')
ON DUPLICATE KEY UPDATE code = code;

INSERT INTO permissions (code, name, module) VALUES ('database.view','View central database overview and health','system')
ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'database.view' WHERE r.code = 'admin';
