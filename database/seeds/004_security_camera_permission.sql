INSERT INTO permissions (code, name, module)
VALUES ('security.camera.manage', 'Register browser-discovered Security cameras', 'security')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'security.camera.manage'
WHERE r.code = 'security';
