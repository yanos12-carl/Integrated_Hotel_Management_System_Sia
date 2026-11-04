<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Csrf, Database, Request, Response};
use App\Services\AuthService;
use App\Validators\Validator;

final class AuthController extends BaseController
{
    public function csrf(Request $r, array $p = []): Response
    {
        return Response::success(['csrf_token' => Csrf::token()]);
    }

    public function login(Request $r, array $p = []): Response
    {
        $d = Validator::validate($r->body, ['username' => 'required|string|max:190', 'password' => 'required|string|max:200']);
        return Response::success(AuthService::login($d['username'], $d['password']), 'Signed in.');
    }

    public function logout(Request $r, array $p = []): Response
    {
        AuthService::logout();
        return Response::success(null, 'Signed out.');
    }

    public function me(Request $r, array $p = []): Response
    {
        return Response::success(['user' => $this->user($r), 'csrf_token' => Csrf::token()]);
    }

    public function changePassword(Request $r, array $p = []): Response
    {
        $uid = $this->uid($r);
        $d = Validator::validate($r->body, ['current_password' => 'required|string|max:200', 'new_password' => 'required|string|max:200']);
        AuthService::changePassword($uid, $d['current_password'], $d['new_password']);
        $user = AuthService::currentUser($uid);
        return Response::success([
            'security_launch' => ($user['department']['code'] ?? null) === 'SECURITY',
        ], 'Password changed.');
    }

    public function avatar(Request $r, array $p = []): Response
    {
        $d = Validator::validate($r->body, ['image' => 'required|string|max:500000']);
        AuthService::uploadAvatar($this->uid($r), $d['image']);
        return Response::success(['avatar_url' => AuthService::avatarUrl(Database::fetchOne('SELECT avatar_path FROM users WHERE id = ?', [$this->uid($r)])['avatar_path'] ?? null)], 'Profile picture updated.');
    }

    public function forgot(Request $r, array $p = []): Response
    {
        $d = Validator::validate($r->body, ['email' => 'required|email|max:190']);
        AuthService::requestPasswordReset($d['email']);
        return Response::success(null, 'If that email belongs to an active account, a reset link has been sent.');
    }

    public function reset(Request $r, array $p = []): Response
    {
        $d = Validator::validate($r->body, ['token' => 'required|string|max:128', 'new_password' => 'required|string|max:200']);
        AuthService::resetPassword($d['token'], $d['new_password']);
        return Response::success(null, 'Password updated. You can now sign in.');
    }
}
