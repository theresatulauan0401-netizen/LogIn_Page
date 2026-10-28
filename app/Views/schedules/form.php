<?php
/** @var array|null $schedule @var bool $locked @var array $buses @var array $routes */
$editing = $schedule !== null;
$action = $editing ? '/schedules/' . (int) $schedule['id'] : '/schedules';
$fmt = static fn (?string $v): string => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
?>
<?php if ($locked): ?>
    <div class="alert alert-info"><i class="bi bi-lock me-1"></i>This trip already has bookings, so the bus, route and fare are locked. You can still adjust the departure and arrival times.</div>
<?php endif; ?>
<div class="card"><div class="card-body">
    <form method="post" action="<?= e(url($action)) ?>" novalidate data-loading>
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_select('bus_id', 'Bus', $buses, ['required' => true, 'placeholder' => 'Select an active bus', 'disabled' => $locked, 'value' => $schedule['bus_id'] ?? '']) ?>
            <?= form_select('route_id', 'Route', $routes, ['required' => true, 'placeholder' => 'Select an active route', 'disabled' => $locked, 'value' => $schedule['route_id'] ?? '']) ?>
            <?= form_input('departure_datetime', 'Departure', ['type' => 'datetime-local', 'required' => true, 'value' => $fmt($schedule['departure_datetime'] ?? null), 'help' => 'Must be in the future.']) ?>
            <?= form_input('arrival_datetime', 'Arrival', ['type' => 'datetime-local', 'value' => $fmt($schedule['arrival_datetime'] ?? null), 'help' => 'Leave blank to use the route\'s estimated duration.']) ?>
            <?= form_input('fare', 'Fare (PHP)', ['type' => 'number', 'step' => '0.01', 'min' => 0, 'disabled' => $locked, 'value' => $schedule['fare'] ?? '', 'help' => 'Leave blank to use the route\'s base fare.']) ?>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Create schedule' ?></button>
            <a class="btn btn-light" href="<?= e(url($editing ? '/schedules/' . (int) $schedule['id'] : '/schedules')) ?>">Cancel</a>
        </div>
    </form>
</div></div>
