<?php
declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    /** @return array{0:int,1:int,2:int} [page, perPage, offset] */
    public static function params(Request $r, int $default = 15, int $max = 100): array
    {
        $page = max(1, (int) $r->input('page', 1));
        $per  = min($max, max(1, (int) $r->input('per_page', $default)));
        return [$page, $per, ($page - 1) * $per];
    }

    public static function meta(int $page, int $per, int $total): array
    {
        return ['page' => $page, 'per_page' => $per, 'total' => $total, 'pages' => max(1, (int) ceil($total / $per))];
    }

    /** Safe ORDER BY from a whitelist: sort=col, dir=asc|desc. */
    public static function order(Request $r, array $allowed, string $default, string $defaultDir = 'DESC'): string
    {
        $col = (string) $r->input('sort', '');
        $dir = strtolower((string) $r->input('dir', '')) === 'asc' ? 'ASC' : (strtolower((string) $r->input('dir', '')) === 'desc' ? 'DESC' : $defaultDir);
        return ($allowed[$col] ?? $default) . ' ' . $dir;
    }
}
