<?php
/** @var array $routes @var array $trips @var array|null $trip @var string[] $seats @var array $held @var array $passengers @var int|null $routeId */
$mode = old('passenger_mode', 'existing');
?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6">1. Route</h2>
    <form method="get" action="<?= e(url('/bookings/create')) ?>" class="row g-2">
        <div class="col-md-8">
            <select class="form-select" name="route_id" data-autosubmit aria-label="Route">
                <option value="">Select a route...</option>
                <?php foreach ($routes as $id => $label): ?><option value="<?= (int) $id ?>" <?= (int) $routeId === (int) $id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><button class="btn btn-outline-primary">Show trips</button></div>
    </form>
</div></div>

<?php if ($routeId && !$trip): ?>
<div class="card mb-3">
    <div class="card-header fw-semibold">2. Choose a trip</div>
    <?php if (!$trips): ?>
        <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No upcoming trips with free seats on this route.</p></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Trip</th><th>Departure</th><th>Arrival</th><th>Bus</th><th class="text-end">Fare</th><th class="text-end">Seats left</th><th></th></tr></thead><tbody>
        <?php foreach ($trips as $t): ?>
            <tr><td class="fw-semibold"><?= e($t['schedule_code']) ?></td><td><?= e(format_datetime($t['departure_datetime'])) ?></td>
                <td><?= e(format_datetime($t['arrival_datetime'])) ?></td><td><?= e($t['bus_number']) ?> <span class="text-muted small"><?= e($t['bus_type']) ?></span></td>
                <td class="text-end"><?= e(format_money($t['fare'])) ?></td><td class="text-end"><?= (int) $t['available_seats'] ?></td>
                <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= e(url('/bookings/create?route_id=' . (int) $routeId . '&schedule_id=' . (int) $t['id'])) ?>">Select</a></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($trip): ?>
<form method="post" action="<?= e(url('/bookings')) ?>" novalidate data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="schedule_id" value="<?= (int) $trip['id'] ?>">
    <div class="alert alert-info d-flex justify-content-between flex-wrap gap-2">
        <span><strong><?= e($trip['schedule_code']) ?></strong> &middot; <?= e($trip['origin']) ?> to <?= e($trip['destination']) ?> &middot; departs <?= e(format_datetime($trip['departure_datetime'])) ?></span>
        <span>Fare <strong><?= e(format_money($trip['fare'])) ?></strong> (set by the system)</span>
    </div>
    <div class="row g-3">
        <div class="col-lg-5"><div class="card h-100"><div class="card-body">
            <h2 class="h6">3. Pick a seat <span class="text-danger">*</span></h2>
            <?php if (field_error('seat_number')): ?><div class="text-danger small mb-2"><?= e(field_error('seat_number')) ?></div><?php endif; ?>
            <div class="seat-grid mb-3">
                <?php foreach ($seats as $seat): $state = $held[$seat] ?? null; ?>
                    <?php if ($state === null): ?>
                        <input type="radio" class="btn-check seat-input" name="seat_number" id="seat_<?= e($seat) ?>" value="<?= e($seat) ?>" <?= old('seat_number') === $seat ? 'checked' : '' ?> required>
                        <label class="seat free" for="seat_<?= e($seat) ?>"><?= e($seat) ?></label>
                    <?php else: ?>
                        <div class="seat <?= $state === 'pending' ? 'held' : 'booked' ?>" title="Taken"><?= e($seat) ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="small text-muted"><span class="badge seat free">&nbsp;</span> free &nbsp; <span class="badge seat held">&nbsp;</span> pending &nbsp; <span class="badge seat booked">&nbsp;</span> taken</div>
        </div></div></div>
        <div class="col-lg-7"><div class="card h-100"><div class="card-body">
            <h2 class="h6">4. Passenger</h2>
            <div class="mb-3">
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="passenger_mode" id="pm_existing" value="existing" <?= $mode === 'existing' ? 'checked' : '' ?>><label class="form-check-label" for="pm_existing">Existing passenger</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="passenger_mode" id="pm_new" value="new" <?= $mode === 'new' ? 'checked' : '' ?>><label class="form-check-label" for="pm_new">Register new passenger</label></div>
            </div>
            <div class="row g-3 mb-3" id="existingBox">
                <?= form_select('passenger_id', 'Passenger', $passengers, ['col' => 'col-12', 'placeholder' => 'Select a passenger...']) ?>
            </div>
            <div class="row g-3" id="newBox">
                <?= form_input('new_first_name', 'First name') ?>
                <?= form_input('new_last_name', 'Last name') ?>
                <?= form_input('new_phone', 'Phone', ['type' => 'tel']) ?>
                <?= form_input('new_email', 'E-mail (optional)', ['type' => 'email']) ?>
                <?= form_select('new_gender', 'Gender (optional)', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], ['placeholder' => 'Prefer not to say']) ?>
                <?= form_input('new_date_of_birth', 'Date of birth (optional)', ['type' => 'date', 'max' => date('Y-m-d')]) ?>
            </div>
            <div class="mt-3">
                <label for="notes" class="form-label">Notes (optional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000"><?= e(old('notes')) ?></textarea>
            </div>
        </div></div></div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Create booking</button>
        <a class="btn btn-light" href="<?= e(url('/bookings/create?route_id=' . (int) $routeId)) ?>">Back to trips</a>
    </div>
</form>
<?php endif; ?>
