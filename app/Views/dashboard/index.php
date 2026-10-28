<?php
/** @var array $data */
$c = $data['counts'];
$cards = [
    ['Total buses',        $c['total_buses'] ?? 0,        'bi-bus-front',         'primary', '/buses'],
    ['Active buses',       $c['active_buses'] ?? 0,       'bi-check2-circle',     'success', '/buses?status=active'],
    ['Total routes',       $c['total_routes'] ?? 0,       'bi-signpost-split',    'primary', '/routes'],
    ['Upcoming schedules', $c['upcoming_schedules'] ?? 0, 'bi-calendar-event',    'info',    '/schedules'],
    ['Total passengers',   $c['total_passengers'] ?? 0,   'bi-people',            'primary', '/passengers'],
    ['Total bookings',     $c['total_bookings'] ?? 0,     'bi-journal-check',     'primary', '/bookings'],
    ['Pending bookings',   $c['pending_bookings'] ?? 0,   'bi-hourglass-split',   'warning', '/bookings?status=pending'],
    ['Confirmed bookings', $c['confirmed_bookings'] ?? 0, 'bi-patch-check',       'success', '/bookings?status=confirmed'],
    ['Cancelled bookings', $c['cancelled_bookings'] ?? 0, 'bi-x-octagon',         'danger',  '/bookings?status=cancelled'],
];
$quick = [
    ['New booking',  '/bookings/create',  'bi-plus-circle',    'bookings.create'],
    ['Add passenger','/passengers/create','bi-person-plus',    'passengers.create'],
    ['New schedule', '/schedules/create', 'bi-calendar-plus',  'schedules.create'],
    ['Add bus',      '/buses/create',     'bi-bus-front',      'buses.create'],
    ['Add route',    '/routes/create',    'bi-signpost-split', 'routes.create'],
    ['View reports', '/reports',          'bi-bar-chart-line', 'reports.view'],
];
?>
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon, $color, $href]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a href="<?= e(url($href)) ?>" class="stat-card text-decoration-none">
                <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><i class="bi <?= e($icon) ?>"></i></div>
                <div>
                    <div class="stat-value"><?= number_format((int) $value) ?></div>
                    <div class="stat-label"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="stat-card stat-card-accent">
            <div class="stat-icon bg-white text-primary"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-value"><?= e(format_money($c['total_revenue'] ?? 0)) ?></div>
                <div class="stat-label">Revenue (successful payments)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h2 class="card-title h6">Revenue &middot; last 7 days</h2>
            <?php if ($data['revenueChart']['hasData']): ?>
                <div class="chart-box"><canvas data-chart="bar" data-currency="1"
                    data-labels="<?= e(json_encode($data['revenueChart']['labels'])) ?>"
                    data-values="<?= e(json_encode($data['revenueChart']['data'])) ?>"
                    data-label="Revenue (PHP)"></canvas></div>
            <?php else: ?>
                <div class="empty-state"><i class="bi bi-graph-up"></i><p>No successful payments in the last 7 days.</p></div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="card-title h6">Bookings by status</h2>
            <?php if ($data['statusChart']['hasData']): ?>
                <div class="chart-box"><canvas data-chart="doughnut"
                    data-labels="<?= e(json_encode($data['statusChart']['labels'])) ?>"
                    data-values="<?= e(json_encode($data['statusChart']['data'])) ?>"></canvas></div>
            <?php else: ?>
                <div class="empty-state"><i class="bi bi-pie-chart"></i><p>No bookings yet.</p></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent bookings</span>
                <?php if (can('bookings.view')): ?><a href="<?= e(url('/bookings')) ?>" class="small">View all</a><?php endif; ?>
            </div>
            <?php if (!$data['recentBookings']): ?>
                <div class="empty-state"><i class="bi bi-journal"></i><p>No bookings recorded yet.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Reference</th><th>Passenger</th><th>Route</th><th>Seat</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($data['recentBookings'] as $b): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($b['booking_reference']) ?></td>
                                <td><?= e($b['passenger_name']) ?></td>
                                <td><?= e($b['route_name']) ?></td>
                                <td><?= e($b['seat_number']) ?></td>
                                <td class="text-end"><?= e(format_money($b['total_amount'])) ?></td>
                                <td><?= status_badge((string) $b['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header fw-semibold">Quick actions</div>
            <div class="card-body d-grid gap-2">
                <?php $shown = 0; foreach ($quick as [$label, $href, $icon, $permission]):
                    if (!can($permission)) { continue; } $shown++; ?>
                    <a class="btn btn-outline-primary text-start" href="<?= e(url($href)) ?>"><i class="bi <?= e($icon) ?> me-2"></i><?= e($label) ?></a>
                <?php endforeach; if ($shown === 0): ?>
                    <span class="text-muted small">No actions available for your role.</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Signed in as</div>
            <div class="card-body">
                <?php $me = auth()->user(); ?>
                <div class="fw-semibold"><?= e($me['name'] ?? '') ?></div>
                <div class="text-muted small"><?= e($me['email'] ?? '') ?></div>
                <div class="mt-2"><span class="badge text-bg-primary"><?= e(ucwords(str_replace('_', ' ', (string) ($me['role'] ?? '')))) ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Upcoming departures</span>
        <?php if (can('schedules.view')): ?><a href="<?= e(url('/schedules')) ?>" class="small">View all</a><?php endif; ?>
    </div>
    <?php if (!$data['upcoming']): ?>
        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No upcoming departures scheduled.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Schedule</th><th>Route</th><th>Bus</th><th>Departure</th><th class="text-end">Seats left</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($data['upcoming'] as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($s['schedule_code']) ?></td>
                        <td><?= e($s['route_name']) ?></td>
                        <td><?= e($s['bus_number']) ?></td>
                        <td><?= e(format_datetime($s['departure_datetime'])) ?></td>
                        <td class="text-end"><?= (int) $s['available_seats'] ?></td>
                        <td><?= status_badge((string) $s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
