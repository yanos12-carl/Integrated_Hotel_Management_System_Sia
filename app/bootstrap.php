<?php
declare(strict_types=1);

/** Boots the app: constants, PSR-4-style autoloader (App\ => app/), .env, timezone, error handling. */
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require BASE_PATH . '/app/Helpers/functions.php';
\App\Core\Env::load(BASE_PATH . '/.env');
date_default_timezone_set(\App\Core\Config::get('app.timezone', 'Asia/Manila'));
\App\Core\ErrorHandler::register();
