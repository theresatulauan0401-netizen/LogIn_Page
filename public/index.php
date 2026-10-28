<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RoleMiddleware;

$container = require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store'); // pages hold personal data; do not cache them

$container->get(Session::class)->start();

// Apply the timezone chosen in Settings (falls back to .env when the DB/setting is unavailable).
$configuredTz = setting('timezone');
if ($configuredTz && in_array($configuredTz, DateTimeZone::listIdentifiers(), true)) {
    date_default_timezone_set($configuredTz);
    $container->get(\App\Core\Database::class)->setTimezone($configuredTz);
}

$request = $container->get(Request::class);
$router = new Router($container, $request);
$router->alias('auth', AuthMiddleware::class);
$router->alias('guest', GuestMiddleware::class);
$router->alias('csrf', CsrfMiddleware::class);
$router->alias('role', RoleMiddleware::class);
$router->alias('can', PermissionMiddleware::class);

require dirname(__DIR__) . '/app/routes.php';

try {
    $router->dispatch();
} catch (HttpException $e) {
    http_response_code($e->status());
    echo partial('errors/error', [
        'status'  => $e->status(),
        'message' => $e->getMessage(),
        'loggedIn' => $container->get(Auth::class)->check(),
    ]);
}
