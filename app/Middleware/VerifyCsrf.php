<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\{Csrf, HttpException, Request};

final class VerifyCsrf
{
    public function handle(Request $r): void
    {
        if (in_array($r->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!Csrf::verify($r)) {
            throw new HttpException(419, 'Your session token is missing or expired. Refresh the page and try again.');
        }
    }
}
