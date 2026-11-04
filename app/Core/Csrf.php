<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        Session::start();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function verify(Request $r): bool
    {
        Session::start();
        $given = (string) ($r->header('x-csrf-token') ?? $r->body['_csrf'] ?? '');
        return $given !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $given);
    }
}
