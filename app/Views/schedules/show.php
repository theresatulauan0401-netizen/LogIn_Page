<?php
/** @var array $schedule @var string[] $seats @var array $heldSeats @var int $booked @var int $activeBookings @var string[] $transitions */
$id = (int) $schedule['id'];
$capacity = (int) $schedule['capacity'];
$pct = $capacity > 0 ? (int) round($booked / $capacity * 100) : 0;
$labels = ['boarding' => 'Start boarding', 'departed' => 'Mark departed', 'completed' => 'Mark completed', 'cancelled' => 'Cancel trip'];
?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h2 class="h5 mb-0"><?= e($schedule['schedule_code']) ?></h2><?= status_badge((string) $schedule['status']) ?>
        </div>
        <dl class="detail-list row mb-0">
            <dt class="col-4">Route</dt><dd class="col-8"><a href="<?= e(url('/routes/' . (int) $schedule['route_id'])) ?>"><?= e($schedule['route_code']) ?></a> &middot; <?= e($schedule['origin']) ?> to <?= e($schedule['destination']) ?></dd>
            <dt class="col-4">Bus</dt><dd class="col-8"><a href="<?= e(url('/buses/' . (int) $schedule['bus_id'])) ?>"><?= e($schedule['bus_number']) ?></a> &middot; <?= e($schedule['bus_type']) ?> (<?= e($schedule['plate_number']) ?>)</dd>
            <dt class="col-4">Departure</dt><dd class="col-8"><?= e(format_datetime($schedule['departure_datetime'])) ?></dd>
            <dt class="col-4">Arrival</dt><dd class="col-8"><?= e(format_datetime($schedule['arrival_datetime'])) ?></dd>
            <dt class="col-4">Fare</dt><dd class="col-8"><?= e(format_money($schedule['fare'])) ?></dd>
            <dt class="col-4">Seats</dt>
            <dd class="col-8">
                <div class="progress mb-1" style="height:8px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                <span class="small"><?= (int) $schedule['available_seats'] ?> available &middot; <?= $booked ?> held &middot; <?= $capacity ?> total</span>
            </dd>
        </dl>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($schedule['status'] === 'scheduled' && can('bookings.create')): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/bookings/create?schedule_id=' . $id)) ?>"><i class="bi bi-plus-circle me-1"></i>Book a seat</a>
            <?php endif; ?>
            <?php if ($schedule['status'] === 'scheduled' && can('schedules.edit')): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/schedules/' . $id . '/edit')) ?>">Edit</a>
            <?php endif; ?>
            <?php foreach ($transitions as $next):
                $perm = $next === 'cancelled' ? 'schedules.cancel' : 'schedules.status';
                if (!can($perm)) { continue; }
                $danger = $next === 'cancelled'; ?>
                <?= post_button('/schedules/' . $id . '/status', $labels[$next] ?? ucfirst($next), $danger ? 'btn-outline-danger' : 'btn-outline-primary',
                    $danger ? 'Cancel this trip? This cannot be undone.' : null, ['status' => $next]) ?>
            <?php endforeach; ?>
        </div>
        <?php if ($activeBookings > 0 && in_array('cancelled', $transitions, true)): ?>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i><?= $activeBookings ?> pending/confirmed booking(s) must be cancelled before this trip can be cancelled.</p>
        <?php endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Seat map</h2>
        <div class="d-flex gap-3 small mb-3">
            <span><span class="badge seat free">&nbsp;</span> Free</span>
            <span><span class="badge seat held">&nbsp;</span> Pending</span>
            <span><span class="badge seat booked">&nbsp;</span> Confirmed / completed</span>
        </div>
        <div class="seat-grid">
            <?php foreach ($seats as $seat):
                $state = $heldSeats[$seat] ?? null;
                $cls = $state === null ? 'free' : ($state === 'pending' ? 'held' : 'booked'); ?>
                <div class="seat <?= $cls ?>" title="<?= e($seat . ($state ? ' - ' . $state : ' - free')) ?>"><?= e($seat) ?></div>
            <?php endforeach; ?>
        </div>
    </div></div></div>
</div>
