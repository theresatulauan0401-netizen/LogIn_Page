<?php
/** @var \App\Models\Bus|null $bus */
$editing = $bus !== null;
$action = $editing ? '/buses/' . $bus->id() : '/buses';
?>
<datalist id="busTypes"><option value="Ordinary Bus"><option value="Air Conditioned"><option value="Deluxe Bus"><option value="Sleeper Bus"></datalist>
<div class="card"><div class="card-body">
    <form method="post" action="<?= e(url($action)) ?>" novalidate data-loading>
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_input('bus_number', 'Bus number', ['required' => true, 'maxlength' => 50, 'value' => $bus?->busNumber(), 'placeholder' => 'BUS-004']) ?>
            <?= form_input('plate_number', 'Plate number', ['required' => true, 'maxlength' => 30, 'value' => $bus?->plateNumber(), 'placeholder' => 'ABC-1234']) ?>
            <?= form_input('bus_type', 'Bus type', ['required' => true, 'maxlength' => 100, 'value' => $bus?->busType(), 'list' => 'busTypes']) ?>
            <?= form_input('bus_model', 'Model', ['maxlength' => 100, 'value' => $bus?->busModel()]) ?>
            <?= form_input('capacity', 'Seat capacity', ['type' => 'number', 'required' => true, 'min' => 1, 'max' => 100, 'value' => $bus?->capacity(),
                'help' => $editing ? 'Cannot change while the bus has scheduled or boarding trips.' : 'Seats are numbered 1A, 1B, 1C, 1D, 2A ...']) ?>
            <?= form_select('status', 'Status', ['active' => 'Active', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'], ['required' => true, 'value' => $bus?->status() ?? 'active']) ?>
            <div class="col-12">
                <label for="f_amenities" class="form-label">Amenities</label>
                <textarea class="form-control <?= field_error('amenities') ? 'is-invalid' : '' ?>" id="f_amenities" name="amenities" rows="2" maxlength="500"
                          placeholder="Comma separated, e.g. Air conditioning, WiFi, USB charging"><?= e(old('amenities', $bus ? implode(', ', $bus->amenities()) : '')) ?></textarea>
                <div class="invalid-feedback"><?= e(field_error('amenities')) ?></div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add bus' ?></button>
            <a class="btn btn-light" href="<?= e(url($editing ? '/buses/' . $bus->id() : '/buses')) ?>">Cancel</a>
        </div>
    </form>
</div></div>
