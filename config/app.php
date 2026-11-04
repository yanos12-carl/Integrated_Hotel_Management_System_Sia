<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'Integrated Hotel Management System'),
    'env'      => Env::get('APP_ENV', 'local'),
    'debug'    => Env::bool('APP_DEBUG', false),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Manila'),
    'url'      => Env::get('APP_URL', 'http://localhost/hotel-management-system'),
    'session'  => [
        'name'     => 'IHMS_SESSION',
        'lifetime' => (int) Env::get('SESSION_LIFETIME_MINUTES', '30'),
        'secure'   => Env::bool('SESSION_SECURE', false),
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],
];
