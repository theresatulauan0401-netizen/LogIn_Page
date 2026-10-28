<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\BusController;
use App\Controllers\DashboardController;
use App\Controllers\PassengerController;
use App\Controllers\PaymentController;
use App\Controllers\ReportController;
use App\Controllers\RouteController;
use App\Controllers\ScheduleController;
use App\Controllers\SettingsController;
use App\Controllers\TicketController;
use App\Controllers\UserController;
use App\Core\Router;

/** @var Router $router */

// ---- Public -----------------------------------------------------------------
$router->get('/',       [AuthController::class, 'root']);
$router->get('/login',  [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'],     ['csrf', 'guest']);

// ---- Authenticated (every POST carries 'csrf'; controllers also authorize()) ---
$router->post('/logout',   [AuthController::class, 'logout'],   ['auth', 'csrf']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'can:dashboard.view']);

// Buses  (static paths such as /create are registered BEFORE /{id})
$router->get('/buses',               [BusController::class, 'index'],  ['auth', 'can:buses.view']);
$router->get('/buses/create',        [BusController::class, 'create'], ['auth', 'can:buses.create']);
$router->post('/buses',              [BusController::class, 'store'],  ['auth', 'csrf', 'can:buses.create']);
$router->get('/buses/{id}',          [BusController::class, 'show'],   ['auth', 'can:buses.view']);
$router->get('/buses/{id}/edit',     [BusController::class, 'edit'],   ['auth', 'can:buses.edit']);
$router->post('/buses/{id}',         [BusController::class, 'update'], ['auth', 'csrf', 'can:buses.edit']);
$router->post('/buses/{id}/status',  [BusController::class, 'status'], ['auth', 'csrf', 'can:buses.deactivate']);

// Routes
$router->get('/routes',              [RouteController::class, 'index'],  ['auth', 'can:routes.view']);
$router->get('/routes/create',       [RouteController::class, 'create'], ['auth', 'can:routes.create']);
$router->post('/routes',             [RouteController::class, 'store'],  ['auth', 'csrf', 'can:routes.create']);
$router->get('/routes/{id}',         [RouteController::class, 'show'],   ['auth', 'can:routes.view']);
$router->get('/routes/{id}/edit',    [RouteController::class, 'edit'],   ['auth', 'can:routes.edit']);
$router->post('/routes/{id}',        [RouteController::class, 'update'], ['auth', 'csrf', 'can:routes.edit']);
$router->post('/routes/{id}/status', [RouteController::class, 'status'], ['auth', 'csrf', 'can:routes.deactivate']);

// Schedules (status route: the controller checks schedules.cancel vs schedules.status)
$router->get('/schedules',              [ScheduleController::class, 'index'],  ['auth', 'can:schedules.view']);
$router->get('/schedules/create',       [ScheduleController::class, 'create'], ['auth', 'can:schedules.create']);
$router->post('/schedules',             [ScheduleController::class, 'store'],  ['auth', 'csrf', 'can:schedules.create']);
$router->get('/schedules/{id}',         [ScheduleController::class, 'show'],   ['auth', 'can:schedules.view']);
$router->get('/schedules/{id}/edit',    [ScheduleController::class, 'edit'],   ['auth', 'can:schedules.edit']);
$router->post('/schedules/{id}',        [ScheduleController::class, 'update'], ['auth', 'csrf', 'can:schedules.edit']);
$router->post('/schedules/{id}/status', [ScheduleController::class, 'status'], ['auth', 'csrf', 'can:schedules.view']);

// Passengers
$router->get('/passengers',             [PassengerController::class, 'index'],  ['auth', 'can:passengers.view']);
$router->get('/passengers/create',      [PassengerController::class, 'create'], ['auth', 'can:passengers.create']);
$router->post('/passengers',            [PassengerController::class, 'store'],  ['auth', 'csrf', 'can:passengers.create']);
$router->get('/passengers/{id}',        [PassengerController::class, 'show'],   ['auth', 'can:passengers.view']);
$router->get('/passengers/{id}/edit',   [PassengerController::class, 'edit'],   ['auth', 'can:passengers.edit']);
$router->post('/passengers/{id}',       [PassengerController::class, 'update'], ['auth', 'csrf', 'can:passengers.edit']);
$router->post('/passengers/{id}/status',[PassengerController::class, 'status'], ['auth', 'csrf', 'can:passengers.deactivate']);

// Bookings
$router->get('/bookings',               [BookingController::class, 'index'],  ['auth', 'can:bookings.view']);
$router->get('/bookings/create',        [BookingController::class, 'create'], ['auth', 'can:bookings.create']);
$router->post('/bookings',              [BookingController::class, 'store'],  ['auth', 'csrf', 'can:bookings.create']);
$router->get('/bookings/{id}',          [BookingController::class, 'show'],   ['auth', 'can:bookings.view']);
$router->post('/bookings/{id}/cancel',  [BookingController::class, 'cancel'], ['auth', 'csrf', 'can:bookings.cancel']);
$router->post('/bookings/{id}/finish',  [BookingController::class, 'finish'], ['auth', 'csrf', 'can:bookings.complete']);
$router->post('/bookings/{id}/ticket',  [TicketController::class, 'issue'],   ['auth', 'csrf', 'can:tickets.issue']);

// Payments
$router->get('/payments',                [PaymentController::class, 'index'],   ['auth', 'can:payments.view']);
$router->get('/payments/create',         [PaymentController::class, 'create'],  ['auth', 'can:payments.record']);
$router->post('/payments',               [PaymentController::class, 'store'],   ['auth', 'csrf', 'can:payments.record']);
$router->get('/payments/{id}',           [PaymentController::class, 'show'],    ['auth', 'can:payments.view']);
$router->get('/payments/{id}/receipt',   [PaymentController::class, 'receipt'], ['auth', 'can:payments.view']);
$router->post('/payments/{id}/resolve',  [PaymentController::class, 'resolve'], ['auth', 'csrf', 'can:payments.record']);
$router->post('/payments/{id}/refund',   [PaymentController::class, 'refund'],  ['auth', 'csrf', 'can:payments.refund']);

// Tickets (static /validate before /{id})
$router->get('/tickets',                 [TicketController::class, 'index'],        ['auth', 'can:tickets.view']);
$router->get('/tickets/validate',        [TicketController::class, 'validateForm'], ['auth', 'can:tickets.validate']);
$router->get('/tickets/{id}',            [TicketController::class, 'show'],         ['auth', 'can:tickets.view']);
$router->post('/tickets/{id}/use',       [TicketController::class, 'markUsed'],     ['auth', 'csrf', 'can:tickets.validate']);

// Users (service re-checks who may manage whom)
$router->get('/users',                 [UserController::class, 'index'],     ['auth', 'can:users.view']);
$router->get('/users/create',          [UserController::class, 'create'],    ['auth', 'can:users.manage_staff']);
$router->post('/users',                [UserController::class, 'store'],     ['auth', 'csrf', 'can:users.manage_staff']);
$router->get('/users/{id}/edit',       [UserController::class, 'edit'],      ['auth', 'can:users.manage_staff']);
$router->post('/users/{id}',           [UserController::class, 'update'],    ['auth', 'csrf', 'can:users.manage_staff']);
$router->post('/users/{id}/status',    [UserController::class, 'status'],    ['auth', 'csrf', 'can:users.manage_staff']);
$router->get('/users/{id}/password',   [UserController::class, 'resetForm'], ['auth', 'can:users.manage_staff']);
$router->post('/users/{id}/password',  [UserController::class, 'reset'],     ['auth', 'csrf', 'can:users.manage_staff']);

// Own password (any signed-in user)
$router->get('/account/password',      [UserController::class, 'accountForm'],       ['auth']);
$router->post('/account/password',     [UserController::class, 'changeOwnPassword'], ['auth', 'csrf']);

// Reports (export is a read-only GET; static path registered before nothing conflicting)
$router->get('/reports',               [ReportController::class, 'index'],  ['auth', 'can:reports.view']);
$router->get('/reports/export',        [ReportController::class, 'export'], ['auth', 'can:reports.view']);

// Settings (view: admin + super admin; edit: super admin only)
$router->get('/settings',              [SettingsController::class, 'index'],  ['auth', 'can:settings.view']);
$router->post('/settings',             [SettingsController::class, 'update'], ['auth', 'csrf', 'can:settings.edit']);
