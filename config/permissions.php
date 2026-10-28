<?php

declare(strict_types=1);

/*
 * Role => permissions. "*" = everything, "module.*" = every action of a module.
 * Checked on the SERVER by Auth::can() / RoleMiddleware / controllers.
 *
 * Privilege-escalation rule: only super_admin holds users.manage_all. An admin
 * holds users.manage_staff and may only create/edit booking_staff accounts
 * (enforced again in UserService, not just here).
 */
return [
    'super_admin' => ['*'],
    'admin' => [
        'dashboard.view',
        'buses.*', 'routes.*', 'schedules.*', 'passengers.*', 'bookings.*',
        'payments.*', 'tickets.*', 'reports.*',
        'users.view', 'users.manage_staff',
        'settings.view',
    ],
    'booking_staff' => [
        'dashboard.view',
        'buses.view', 'routes.view', 'schedules.view',
        'passengers.view', 'passengers.create', 'passengers.edit',
        'bookings.view', 'bookings.create', 'bookings.confirm', 'bookings.cancel',
        'payments.view', 'payments.record',
        'tickets.view', 'tickets.issue', 'tickets.validate',
    ],
];
