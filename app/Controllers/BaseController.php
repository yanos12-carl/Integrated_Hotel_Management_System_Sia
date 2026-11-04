<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Paginator, Request, Response};

abstract class BaseController
{
    protected function user(Request $r): array { return $r->attributes['user']; }
    protected function uid(Request $r): int { return (int) $r->attributes['user']['id']; }

    protected function paged(array $rows, int $total, int $page, int $per): Response
    {
        return Response::success($rows, 'OK', 200, Paginator::meta($page, $per, $total));
    }
}
