-- Migration 011: purpose descriptions for the Group A tables (shown in Admin > Central database and the data dictionary).

ALTER TABLE departments COMMENT = 'The nine hotel departments';
ALTER TABLE users COMMENT = 'Employee accounts (password stored only as a hash)';
ALTER TABLE roles COMMENT = 'Named sets of permissions, optionally limited to one department';
ALTER TABLE permissions COMMENT = 'Catalogue of every action the system can authorise (e.g. refund.process)';
ALTER TABLE user_roles COMMENT = 'Which roles each employee holds';
ALTER TABLE role_permissions COMMENT = 'Which permissions each role grants';
ALTER TABLE user_departments COMMENT = 'Extra departments an employee also belongs to (besides the primary one)';
ALTER TABLE login_attempts COMMENT = 'Every sign-in attempt, successful or not, for lockout and monitoring';
ALTER TABLE sessions COMMENT = 'Active browser sessions (revocable by Admin)';
ALTER TABLE password_resets COMMENT = 'Password reset tokens (stored hashed, single use, expiring)';
ALTER TABLE system_settings COMMENT = 'Hotel-wide settings (tax rates, check-in times, security limits)';
ALTER TABLE notifications COMMENT = 'Cross-department events: what happened and which record it concerns';
ALTER TABLE notification_recipients COMMENT = 'Who received each notification and when they read it';
ALTER TABLE audit_logs COMMENT = 'Append-only trail of important actions with before/after values';
ALTER TABLE error_logs COMMENT = 'Unexpected system errors, matched to the error id shown to users';
ALTER TABLE approval_requests COMMENT = 'Requests that need a second person to approve (refunds, voids, overrides)';
ALTER TABLE approval_history COMMENT = 'Append-only history of each approval request';
