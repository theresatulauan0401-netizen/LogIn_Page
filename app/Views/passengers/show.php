<?php /** @var \App\Models\Passenger $passenger @var array $history @var int $activeBookings */ ?>
<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3"><h2 class="h5 mb-0"><?= e($passenger->code()) ?></h2><?= status_badge($passenger->status()) ?></div>
        <dl class="detail-list mb-2">
            <dt>Name</dt><dd><?= e($passenger->fullName()) ?></dd>
            <dt>Phone</dt><dd><?= e($passenger->phone()) ?></dd>
            <dt>E-mail</dt><dd><?= e($passenger->email() ?: '—') ?></dd>
            <dt>Gender</dt><dd><?= e($passenger->gender() ?: '—') ?></dd>
            <dt>Date of birth</dt><dd><?= e(format_date($passenger->dateOfBirth())) ?></dd>
        </dl>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($passenger->isActive() && can('bookings.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/bookings/create')) ?>">New booking</a><?php endif; ?>
            <?php if (can('passengers.edit')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/passengers/' . $passenger->id() . '/edit')) ?>">Edit</a><?php endif; ?>
            <?php if (can('passengers.deactivate')): ?>
                <?php if ($passenger->isActive()): ?>
                    <?= post_button('/passengers/' . $passenger->id() . '/status', 'Deactivate', 'btn-outline-danger', 'Deactivate this passenger? Their booking history is kept.', ['status' => 'inactive']) ?>
                <?php else: ?>
                    <?= post_button('/passengers/' . $passenger->id() . '/status', 'Activate', 'btn-outline-success', null, ['status' => 'active']) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($activeBookings > 0): ?><p class="small text-muted mt-3 mb-0"><?= $activeBookings ?> pending/confirmed booking(s) prevent deactivation.</p><?php endif; ?>
    </div></div></div>
    <div class="col-lg-8"><div class="card h-100">
        <div class="card-header fw-semibold">Booking history</div>
        <?php if (!$history): ?><div class="empty-state"><i class="bi bi-journal"></i><p>No bookings yet.</p></div>
        <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Reference</th><th>Route</th><th>Departure</th><th>Seat</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($history as $b): ?>
                <tr><td><a href="<?= e(url('/bookings/' . (int) $b['id'])) ?>"><?= e($b['booking_reference']) ?></a></td>
                    <td><?= e($b['route_name']) ?></td><td><?= e(format_datetime($b['departure_datetime'])) ?></td><td><?= e($b['seat_number']) ?></td>
                    <td class="text-end"><?= e(format_money($b['total_amount'])) ?></td><td><?= status_badge((string) $b['status']) ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>
