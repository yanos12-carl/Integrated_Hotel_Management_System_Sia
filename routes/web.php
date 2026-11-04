<?php
declare(strict_types=1);

use App\Controllers\WebController;
use App\Core\Router;

/** Browser pages. Authentication is checked inside the controller (redirects to /login). */
return static function (Router $router): void {
    $router->get('/', [WebController::class, 'root']);
    $router->get('/login', [WebController::class, 'login']);
    $router->get('/forgot-password', [WebController::class, 'forgot']);
    $router->get('/reset-password', [WebController::class, 'reset']);
    $router->get('/change-password', [WebController::class, 'changePassword']);
    $router->get('/home', [WebController::class, 'home']);
    $router->get('/security/launch', [WebController::class, 'securityLaunch']);
    $router->get('/security/logout', [WebController::class, 'securityLogout']);
    $router->post('/security/logout', [WebController::class, 'securityLogoutSubmit'], [
        'App\Middleware\Authenticate',
        'App\Middleware\VerifyCsrf',
    ]);
    $router->get('/security', [WebController::class, 'security']);
    $router->get('/security/{page:[a-z-]+}', [WebController::class, 'security']);
    $router->get('/profile', [WebController::class, 'profile']);
    $router->get('/admin', [WebController::class, 'admin']);
    $router->get('/admin/{page:[a-z]+}', [WebController::class, 'admin']);
};
