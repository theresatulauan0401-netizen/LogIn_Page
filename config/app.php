<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'Bus Reservation System'),
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Manila'),
    'session'  => [
        'name'     => 'BRS_SESSION',
        'lifetime' => 7200,          // idle timeout in seconds (2 hours)
    ],
    'throttle' => [
        'max_attempts'   => 5,       // failed logins allowed per e-mail ...
        'max_attempts_per_ip' => 20, // ... and per IP (higher: shared school/office networks)
        'window_minutes' => 15,      // ... within this window (per e-mail and per IP)
    ],
    'per_page' => 15,
];
