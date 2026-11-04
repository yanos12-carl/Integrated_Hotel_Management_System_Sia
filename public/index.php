<?php
declare(strict_types=1);

/** Single entry point (front controller). Everything flows: Router → Middleware → Controller → Service → Repository → MySQL. */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{ErrorHandler, Request, Router};

$router = new Router();
(require BASE_PATH . '/routes/api.php')($router);
(require BASE_PATH . '/routes/web.php')($router);

try {
    $response = $router->dispatch(Request::fromGlobals());
} catch (Throwable $e) {
    $response = ErrorHandler::toResponse($e);
}
$response->send();
