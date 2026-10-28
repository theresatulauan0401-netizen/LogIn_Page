<?php
/** @var \App\Models\Route|null $route */
$editing = $route !== null;
$action = $editing ? '/routes/' . $route->id() : '/routes';
?>
<div class="card"><div class="card-body">
    <form method="post" action="<?= e(url($action)) ?>" novalidate data-loading>
        <?= csrf_field() ?>
        <div class="row g-3">
            <?php if ($editing): ?>
                <?= form_input('route_code_display', 'Route code', ['value' => $route->routeCode(), 'disabled' => true, 'help' => 'Generated automatically.']) ?>
                <div class="col-md-6"></div>
            <?php endif; ?>
            <?= form_input('origin', 'Origin', ['required' => true, 'maxlength' => 150, 'value' => $route?->origin()]) ?>
            <?= form_input('destination', 'Destination', ['required' => true, 'maxlength' => 150, 'value' => $route?->destination()]) ?>
            <?= form_input('distance_km', 'Distance (km)', ['type' => 'number', 'step' => '0.01', 'min' => 0, 'value' => $route?->distanceKm(), 'col' => 'col-md-4']) ?>
            <?= form_input('estimated_duration_minutes', 'Estimated duration (minutes)', ['type' => 'number', 'min' => 1, 'max' => 5000, 'value' => $route?->durationMinutes(), 'col' => 'col-md-4',
                'help' => 'Used to suggest arrival times for new schedules.']) ?>
            <?= form_input('base_fare', 'Base fare (PHP)', ['type' => 'number', 'step' => '0.01', 'min' => 0, 'required' => true, 'value' => $route?->baseFare(), 'col' => 'col-md-4',
                'help' => 'Default fare for new schedules. Existing schedules keep their own fare.']) ?>
            <?= form_select('status', 'Status', ['active' => 'Active', 'inactive' => 'Inactive'], ['required' => true, 'value' => $route?->status() ?? 'active']) ?>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add route' ?></button>
            <a class="btn btn-light" href="<?= e(url($editing ? '/routes/' . $route->id() : '/routes')) ?>">Cancel</a>
        </div>
    </form>
</div></div>
