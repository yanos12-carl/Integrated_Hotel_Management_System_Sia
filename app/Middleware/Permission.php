<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\{HttpException, Request};
use App\Services\AuditService;

/** Usage in routes: 'App\Middleware\Permission:user.manage'. All listed permissions are required. */
final class Permission
{
    private array $codes;

    public function __construct(string ...$codes) { $this->codes = $codes; }

    public function handle(Request $r): void
    {
        $have = $r->attributes['user']['permissions'] ?? [];
        foreach ($this->codes as $code) {
            if (!in_array($code, $have, true)) {
                try { AuditService::log('security.permission_denied', 'permission', null, null, ['required' => $this->codes, 'path' => $r->path, 'method' => $r->method]); } catch (\Throwable) {}
                throw HttpException::forbidden();
            }
        }
    }
}
