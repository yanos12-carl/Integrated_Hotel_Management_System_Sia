<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\HttpException;

final class PasswordPolicy
{
    /** @return string[] problems (empty = acceptable) */
    public static function problems(string $password, string $username = ''): array
    {
        $p = [];
        if (mb_strlen($password) < 10)                  { $p[] = 'at least 10 characters'; }
        if (mb_strlen($password) > 128)                 { $p[] = 'no more than 128 characters'; }
        if (!preg_match('/[A-Za-z]/', $password))       { $p[] = 'at least one letter'; }
        if (!preg_match('/\d/', $password))             { $p[] = 'at least one number'; }
        if ($username !== '' && stripos($password, $username) !== false) { $p[] = 'must not contain your username'; }
        return $p;
    }

    public static function assert(string $password, string $username = '', string $field = 'new_password'): void
    {
        $p = self::problems($password, $username);
        if ($p) {
            throw HttpException::unprocessable([$field => 'Password needs: ' . implode(', ', $p) . '.']);
        }
    }

    /** Random temporary password that satisfies the policy (shown once to the admin). */
    public static function generate(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out . random_int(10, 99);
    }
}
