<?php
declare(strict_types=1);

namespace App\Core;

/** Per-request facts (IP, user agent, current user) used by audit logging and error logs. */
final class Context
{
    private static array $d = [];

    public static function fromRequest(Request $r): void
    {
        self::$d = [
            'ip'      => $r->ip,
            'ua'      => mb_substr((string) $r->header('user-agent', ''), 0, 255),
            'method'  => $r->method,
            'path'    => $r->path,
            'user_id' => null,
        ];
    }

    public static function set(string $key, mixed $value): void { self::$d[$key] = $value; }
    public static function get(string $key, mixed $default = null): mixed { return self::$d[$key] ?? $default; }
}
