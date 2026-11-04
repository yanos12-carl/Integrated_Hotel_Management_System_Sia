<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Database, Logger, Request, Response};

final class HealthController
{
    /** GET /api/v1/ping : proves routing works, no database needed. */
    public function ping(Request $r, array $p = []): Response
    {
        return Response::success(['pong' => true, 'time' => date('c')]);
    }

    /** GET /api/v1/health : proves the API can reach the central database. */
    public function health(Request $r, array $p = []): Response
    {
        $db = 'up';
        try {
            Database::connection()->query('SELECT 1');
        } catch (\Throwable $e) {
            $db = 'down';
            Logger::warning('Health check: database unreachable');
        }
        $data = ['status' => $db === 'up' ? 'ok' : 'degraded', 'database' => $db, 'time' => date('c')];
        if (Config::get('app.debug', false) === true) {
            $data['php_version'] = PHP_VERSION;
        }
        return $db === 'up'
            ? Response::success($data, 'Healthy')
            : Response::error('Database unavailable.', 503, [], ['data' => $data]);
    }
}
