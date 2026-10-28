<?php

declare(strict_types=1);

use App\Core\Env;

// Credentials come from the untracked ".env" file, never from source code.
return [
    'host'     => Env::get('DB_HOST', '127.0.0.1'),
    'port'     => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_NAME', 'bus_reservation_system'),
    'username' => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASS', ''),
    'charset'  => 'utf8mb4',
];
