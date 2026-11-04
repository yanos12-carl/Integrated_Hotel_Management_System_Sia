-- Idempotent seed: safe to run repeatedly. Never overwrites values an admin has changed.
INSERT INTO departments (code, name, description) VALUES
 ('ADMIN',        'Admin',                        'System administration, accounts, access control and audit'),
 ('FRONT_OFFICE', 'Front Office',                 'Guests, reservations, check-in/check-out, room assignment'),
 ('MANAGEMENT',   'Management',                   'Executive dashboards and reports (read-only by default)'),
 ('HOUSEKEEPING', 'Housekeeping',                 'Room cleaning, inspections, linen and lost-and-found'),
 ('FNB',          'Food and Beverage',            'Restaurant, takeaway and room-service ordering'),
 ('KITCHEN',      'Kitchen and Food Production',  'Order preparation, recipes, kitchen stock and food safety'),
 ('MAINTENANCE',  'Maintenance',                  'Work orders, assets, preventive maintenance, room defects'),
 ('FINANCE',      'Accounts and Finance',         'Folios, invoices, payments, refunds, expenses, reconciliation'),
 ('SECURITY',     'Security',                     'Incidents, visitors, patrols, shift handovers, CCTV references')
ON DUPLICATE KEY UPDATE code = code;

INSERT INTO system_settings (setting_key, setting_value, value_type, description) VALUES
 ('hotel.name',               'My Hotel',  'string',  'Hotel display name'),
 ('hotel.currency',           'PHP',       'string',  'ISO currency code'),
 ('hotel.check_in_time',      '14:00',     'string',  'Standard check-in time'),
 ('hotel.check_out_time',     '12:00',     'string',  'Standard check-out time'),
 ('billing.vat_rate',         '0.12',      'decimal', 'VAT rate'),
 ('billing.service_charge',   '0.10',      'decimal', 'Service charge rate'),
 ('security.session_minutes', '30',        'int',     'Idle session timeout'),
 ('security.max_login_attempts', '5',      'int',     'Failed logins before lockout'),
 ('security.lockout_minutes', '15',        'int',     'Lockout duration')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
