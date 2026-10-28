<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Container;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/helpers.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Manila'));

// Errors are logged, never shown to visitors (unless APP_DEBUG=true on a dev PC).
$debug = (bool) Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    Logger::error('Uncaught exception: ' . $e->getMessage(), [
        'type' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo $debug ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
                : 'Something went wrong. Please try again later.';
});

// ---- Dependency-injection container -------------------------------------------
$container = new Container();
Container::setInstance($container);

$container->singleton(Request::class, static fn () => Request::capture());
$container->singleton(Session::class, static fn () => new Session((array) Config::get('app.session')));
$container->singleton(Database::class, static fn () => new Database(
    (array) Config::get('database'),
    (string) Config::get('app.timezone', 'Asia/Manila')
));
$container->singleton(PDO::class, static fn (Container $c) => $c->get(Database::class)->connection());
$container->singleton(Csrf::class, static fn (Container $c) => new Csrf($c->get(Session::class)));
$container->singleton(Auth::class, static fn (Container $c) => new Auth(
    $c->get(Session::class),
    (array) Config::get('permissions')
));

return $container;
