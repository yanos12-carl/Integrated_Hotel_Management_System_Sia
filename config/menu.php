<?php
declare(strict_types=1);

/**
 * Sidebar structure.
 *
 * The sidebar is a compact icon rail. The pages used most ('primary') are pinned
 * straight to it; everything else lives inside a group, and hovering a group
 * opens its flyout. An item appears only if the signed-in user holds its
 * permission (or none is required).
 *
 * Later phases append each department's groups here.
 */
return [
    'General' => [
        'icon' => 'home', 'short' => 'General', 'note' => 'Your everyday pages',
        'primary' => ['/home'],
        'items' => [
            ['label' => 'Home',            'href' => '/home',            'icon' => 'home'],
            ['label' => 'My profile',      'href' => '/profile',         'icon' => 'users'],
            ['label' => 'Change password', 'href' => '/change-password', 'icon' => 'key'],
        ],
    ],
    'Administration' => [
        'icon' => 'shield', 'short' => 'Admin', 'note' => 'People, roles and settings',
        'primary' => ['/admin', '/admin/analytics'],
        'items' => [
            ['label' => 'Dashboard',           'href' => '/admin',             'icon' => 'monitor',  'perm' => 'admin.dashboard'],
            ['label' => 'Analytics',           'href' => '/admin/analytics',   'icon' => 'chart',    'perm' => 'admin.dashboard'],
            ['label' => 'Employees',           'href' => '/admin/users',       'icon' => 'users',    'perm' => 'user.view'],
            ['label' => 'Departments',         'href' => '/admin/departments', 'icon' => 'building', 'perm' => 'department.view'],
            ['label' => 'Roles & permissions', 'href' => '/admin/roles',       'icon' => 'shield',   'perm' => 'role.view'],
            ['label' => 'System settings',     'href' => '/admin/settings',    'icon' => 'settings', 'perm' => 'settings.view'],
            ['label' => 'Audit log',       'href' => '/admin/audit',     'icon' => 'list',    'perm' => 'audit.view'],
            ['label' => 'Login activity',  'href' => '/admin/logins',    'icon' => 'login',   'perm' => 'login_activity.view'],
            ['label' => 'Active sessions', 'href' => '/admin/sessions',  'icon' => 'monitor', 'perm' => 'session.manage'],
            ['label' => 'System errors',   'href' => '/admin/errors',    'icon' => 'alert',   'perm' => 'error_log.view'],
            ['label' => 'Approvals',       'href' => '/admin/approvals', 'icon' => 'check',   'perm' => 'approval.view'],
        ],
    ],
    'Communication' => [
        'icon' => 'megaphone', 'short' => 'Comms', 'note' => 'Notices for the whole hotel',
        'items' => [
            ['label' => 'Notifications', 'href' => '/admin/notifications', 'icon' => 'bell', 'perm' => 'notification.manage'],
        ],
    ],
    'Data' => [
        'icon' => 'database', 'short' => 'Data', 'note' => 'Schema health and backups',
        'items' => [
            ['label' => 'Central database', 'href' => '/admin/database', 'icon' => 'database', 'perm' => 'database.view'],
            ['label' => 'Backups',          'href' => '/admin/backups',  'icon' => 'save',     'perm' => 'backup.manage'],
        ],
    ],
    'Security' => [
        'icon' => 'shield', 'short' => 'Security', 'note' => 'Incidents, patrols and visitor records',
        'primary' => ['/security'],
        'items' => [
            ['label' => 'Security dashboard', 'href' => '/security',              'icon' => 'monitor', 'perm' => 'security.incident.view'],
            ['label' => 'Incidents',          'href' => '/security/incidents',    'icon' => 'alert',   'perm' => 'security.incident.view'],
            ['label' => 'Visitor register',   'href' => '/security/visitors',     'icon' => 'users',   'perm' => 'visitor.manage'],
            ['label' => 'Guard shifts',       'href' => '/security/shifts',       'icon' => 'list',    'perm' => 'patrol.manage'],
            ['label' => 'Patrols',            'href' => '/security/patrols',      'icon' => 'check',   'perm' => 'patrol.manage'],
            ['label' => 'CCTV references',    'href' => '/security/evidence',     'icon' => 'shield',  'perm' => 'security.evidence.view'],
            ['label' => 'Safety inspections', 'href' => '/security/inspections',  'icon' => 'alert',   'perm' => 'patrol.manage'],
        ],
    ],
];
