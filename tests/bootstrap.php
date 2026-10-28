<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php'; // composer install (also loads App\ and helpers.php)

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
date_default_timezone_set('Asia/Manila');
