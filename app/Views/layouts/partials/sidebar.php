<?php
$modules = [
    ['Dashboard',  '/dashboard',  'bi-speedometer2',        'dashboard.view'],
    ['Buses',      '/buses',      'bi-bus-front',           'buses.view'],
    ['Routes',     '/routes',     'bi-signpost-split',      'routes.view'],
    ['Schedules',  '/schedules',  'bi-calendar-event',      'schedules.view'],
    ['Passengers', '/passengers', 'bi-people',              'passengers.view'],
    ['Bookings',   '/bookings',   'bi-journal-check',       'bookings.view'],
    ['Payments',   '/payments',   'bi-cash-coin',           'payments.view'],
    ['Tickets',    '/tickets',    'bi-ticket-perforated',   'tickets.view'],
    ['Users',      '/users',      'bi-person-gear',         'users.view'],
    ['Reports',    '/reports',    'bi-bar-chart-line',      'reports.view'],
    ['Settings',   '/settings',   'bi-gear',                'settings.view'],
];
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <div class="sidebar-brand">
        <i class="bi bi-bus-front-fill"></i>
        <span class="sidebar-label"><?= e(setting('company_name', config('app.name'))) ?></span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($modules as [$label, $href, $icon, $permission]):
            if (!can($permission)) { continue; }
            $active = $path === $href || str_starts_with($path, $href . '/'); ?>
            <a class="sidebar-link <?= $active ? 'active' : '' ?>" href="<?= e(url($href)) ?>" title="<?= e($label) ?>">
                <i class="bi <?= e($icon) ?>"></i><span class="sidebar-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= e(url('/logout')) ?>" class="sidebar-logout">
        <?= csrf_field() ?>
        <button type="submit" class="sidebar-link w-100 border-0 bg-transparent text-start" title="Logout">
            <i class="bi bi-box-arrow-left"></i><span class="sidebar-label">Logout</span>
        </button>
    </form>
</aside>
