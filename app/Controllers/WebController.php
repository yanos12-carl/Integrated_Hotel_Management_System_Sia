<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Csrf, Request, Response, Session};
use App\Services\AuthService;

/** Browser pages (HTML). All data changes still go through the JSON API. */
final class WebController
{
    private const ADMIN_PAGES = [
        ''              => ['dashboard',     'Admin dashboard',     'admin.dashboard', 'Live figures from the central database'],
        'analytics'     => ['analytics',     'Analytics',           'admin.dashboard', 'Occupancy, revenue, workload and access activity'],
        'users'         => ['users',         'Employees',           'user.view',       'Every account, role and status in one list'],
        'departments'   => ['departments',   'Departments',         'department.view', 'The nine teams that run the hotel'],
        'roles'         => ['roles',         'Roles & permissions', 'role.view',       'Who may do what, and the audit trail of changes'],
        'settings'      => ['settings',      'System settings',     'settings.view',   'Hotel name, rates, tax and security policy'],
        'audit'         => ['audit',         'Audit log',           'audit.view',      'Every change, written down and kept'],
        'logins'        => ['logins',        'Login activity',      'login_activity.view', 'Sign-in attempts, successful and failed'],
        'sessions'      => ['sessions',      'Active sessions',     'session.manage',  'Who is signed in right now'],
        'errors'        => ['errors',        'System errors',       'error_log.view',  'What went wrong, with a traceable id'],
        'approvals'     => ['approvals',     'Approvals',           'approval.view',   'Requests waiting for a decision'],
        'notifications' => ['notifications', 'Notifications',       'notification.manage', 'Broadcast messages to the whole hotel'],
        'database'      => ['database',      'Central database',    'database.view',   'All 107 tables, their keys and their health'],
        'backups'       => ['backups',       'Backups',             'backup.manage',  'Snapshots you can restore from'],
    ];
    private const SECURITY_PAGES = [
        ''             => ['dashboard',   'Security dashboard',   'security.incident.view',  'Incidents, visitors and today’s activity'],
        'incidents'    => ['incidents',   'Incidents',             'security.incident.view',  'Report and follow up on security incidents'],
        'visitors'     => ['visitors',    'Visitor register',      'visitor.manage',          'Check visitors in and out'],
        'shifts'       => ['shifts',      'Guard shifts',           'patrol.manage',           'Schedule coverage and record handovers'],
        'patrols'      => ['patrols',     'Patrols',                'patrol.manage',           'Checkpoints, schedules and patrol records'],
        'evidence'     => ['evidence',    'CCTV references',       'security.evidence.view',  'Restricted footage references'],
        'inspections'  => ['inspections', 'Safety inspections',    'patrol.manage',           'Record safety checks and follow-ups'],
    ];
    private function app(): string { return (string) Config::get('app.name'); }

    private function userOrRedirect(bool $allowForced = false, bool $allowSecurity = false): array|Response
    {
        $id = Session::userId();
        $user = $id ? AuthService::currentUser($id) : null;
        if (!$user) {
            if ($id) { Session::destroy(); }
            return Response::redirect(url('/login'));
        }
        if (!$allowSecurity && ($user['department']['code'] ?? null) === 'SECURITY') {
            return Response::redirect(url('/security'));
        }
        if ((int) $user['must_change_password'] === 1 && !$allowForced) {
            return Response::redirect(url('/change-password'));
        }
        return $user;
    }

    private function auth(string $tpl, string $title, array $data = []): Response
    {
        return Response::html(view('layouts/auth', ['title' => $title, 'csrf' => Csrf::token(), 'appName' => $this->app(), 'content' => view($tpl, $data)]));
    }

    public function root(Request $r, array $p = []): Response
    {
        return Response::redirect(url(Session::userId() ? '/home' : '/login'));
    }

    public function login(Request $r, array $p = []): Response
    {
        if (Session::userId() && AuthService::currentUser((int) Session::userId())) { return Response::redirect(url('/home')); }
        return $this->auth('auth/login', 'Sign in');
    }

    public function forgot(Request $r, array $p = []): Response { return $this->auth('auth/forgot', 'Forgot password'); }

    public function reset(Request $r, array $p = []): Response
    {
        return $this->auth('auth/reset', 'Reset password', ['token' => (string) ($r->query['token'] ?? '')]);
    }

    public function changePassword(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect(true, true);
        if ($u instanceof Response) { return $u; }
        return $this->auth('auth/change-password', 'Change password', ['forced' => (int) $u['must_change_password'] === 1]);
    }

    private function shell(array $user, string $tpl, string $title, string $active, array $extra = []): Response
    {
        $html = view($tpl, $extra + ['user' => $user]);
        return Response::html(view('layouts/app', $extra + [
            'user' => $user, 'title' => $title, 'csrf' => Csrf::token(), 'appName' => $this->app(), 'activePath' => $active, 'content' => $html,
        ]));
    }

    public function home(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect();
        return $u instanceof Response ? $u : $this->shell($u, 'home', 'Home', '/home', ['subtitle' => 'Your department, unread alerts and account shortcuts']);
    }

    public function securityLaunch(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect(false, true);
        if ($u instanceof Response) { return $u; }
        if (($u['department']['code'] ?? null) !== 'SECURITY') {
            return Response::redirect(url('/home'));
        }
        return Response::redirect(url('/security'));
    }

    public function security(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect(false, true);
        if ($u instanceof Response) { return $u; }
        if (($u['department']['code'] ?? null) !== 'SECURITY') {
            return Response::redirect(url('/home'));
        }

        $key = (string) ($p['page'] ?? '');
        if (!isset(self::SECURITY_PAGES[$key])) {
            return $this->shell($u, 'errors/message', 'Page not found', '', ['heading' => 'Page not found', 'text' => 'That Security page does not exist.'])->withStatus(404);
        }
        [$pageKey, $title, $permission, $subtitle] = self::SECURITY_PAGES[$key];
        if (!in_array($permission, $u['permissions'], true)) {
            return $this->shell($u, 'errors/message', 'Access denied', '', ['heading' => 'Access denied', 'text' => 'Your account does not have permission to view this Security page.'])->withStatus(403);
        }
        $active = $key === '' ? '/security' : '/security/' . $key;
        return $this->shell($u, 'security/page', $title, $active, [
            'pageKey' => 'security-' . $pageKey,
            'script' => 'security.js',
            'subtitle' => $subtitle,
        ]);
    }

    public function securityLogout(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect(true, true);
        if ($u instanceof Response) { return $u; }
        if (($u['department']['code'] ?? null) !== 'SECURITY') {
            return Response::redirect(url('/home'));
        }
        $action = htmlspecialchars(url('/security/logout'), ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8');
        return Response::html(
            '<!doctype html><html lang="en"><meta charset="utf-8"><title>Signing out</title>'
            . '<body><form id="securityLogout" method="post" action="' . $action . '">'
            . '<input type="hidden" name="_csrf" value="' . $token . '">'
            . '<p>Signing out of IHMS…</p><button type="submit">Continue</button></form>'
            . '<script>document.getElementById("securityLogout").submit();</script></body></html>'
        );
    }

    public function securityLogoutSubmit(Request $r, array $p = []): Response
    {
        if (($r->attributes['user']['department']['code'] ?? null) !== 'SECURITY') {
            return Response::redirect(url('/home'));
        }
        AuthService::logout();
        return Response::redirect(url('/login'));
    }

    public function profile(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect(false, true);
        return $u instanceof Response ? $u : $this->shell($u, 'profile', 'My profile', '/profile', ['subtitle' => 'Your account details and profile picture']);
    }

    public function admin(Request $r, array $p = []): Response
    {
        $u = $this->userOrRedirect();
        if ($u instanceof Response) { return $u; }
        $key = (string) ($p['page'] ?? '');
        if (!isset(self::ADMIN_PAGES[$key])) {
            return $this->shell($u, 'errors/message', 'Page not found', '', ['heading' => 'Page not found', 'text' => 'That page does not exist.'])->withStatus(404);
        }
        [$pageKey, $title, $perm, $subtitle] = self::ADMIN_PAGES[$key];
        if (!in_array($perm, $u['permissions'], true)) {
            return $this->shell($u, 'errors/message', 'Access denied', '', ['heading' => 'Access denied', 'text' => 'Your account does not have permission to view this page.'])->withStatus(403);
        }
        return $this->shell($u, 'admin/page', $title, $key === '' ? '/admin' : '/admin/' . $key, ['pageKey' => $pageKey, 'script' => 'admin.js', 'subtitle' => $subtitle]);
    }
}
