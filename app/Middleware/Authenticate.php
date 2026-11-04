<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\{Context, HttpException, Request, Session};
use App\Services\AuthService;

/** Requires a valid session; reloads the user and permissions on every request so changes apply immediately. */
final class Authenticate
{
    private const ALLOWED_WHEN_PASSWORD_CHANGE_REQUIRED = ['/api/v1/auth/me', '/api/v1/auth/change-password', '/api/v1/auth/logout', '/api/v1/auth/csrf', '/security/logout'];
    private const SECURITY_AUTH_ENDPOINTS = ['/api/v1/auth/me', '/api/v1/auth/change-password', '/api/v1/auth/logout', '/api/v1/auth/csrf', '/api/v1/auth/avatar', '/security/logout'];

    public function handle(Request $r): void
    {
        $uid = Session::userId();
        if (!$uid) {
            throw HttpException::unauthorized();
        }
        $user = AuthService::currentUser($uid);
        if (!$user) {                       // deactivated, locked or deleted while signed in
            Session::destroy();
            throw HttpException::unauthorized('Your session has ended. Please sign in again.');
        }
        Context::set('user_id', $uid);
        $r->attributes['user'] = $user;
        if (($user['department']['code'] ?? null) === 'SECURITY'
            && !in_array($r->path, self::SECURITY_AUTH_ENDPOINTS, true)
            && $r->path !== '/security'
            && !str_starts_with($r->path, '/security/')
            && !str_starts_with($r->path, '/api/v1/security/')
            && $r->path !== '/api/v1/notifications'
            && !str_starts_with($r->path, '/api/v1/notifications/')) {
            throw new HttpException(403, 'You do not have access to this Security feature.');
        }
        if ((int) $user['must_change_password'] === 1 && !in_array($r->path, self::ALLOWED_WHEN_PASSWORD_CHANGE_REQUIRED, true)) {
            throw new HttpException(403, 'You must change your password before continuing.', ['code' => 'password_change_required']);
        }
    }
}
