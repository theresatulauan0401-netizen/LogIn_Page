<?php /** @var array $values @var array $currencies @var array $timezones @var bool $editable */
$v = static fn (string $k, string $d = ''): string => (string) ($values[$k] ?? $d); ?>
<?php if (!$editable): ?><div class="alert alert-info"><i class="bi bi-lock me-1"></i>Only a Super Admin can change settings. You can view them here.</div><?php endif; ?>
<div class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/settings')) ?>" novalidate data-loading>
        <?= csrf_field() ?>
        <h2 class="h6">Company</h2>
        <div class="row g-3 mb-4">
            <?= form_input('company_name', 'Company name', ['required' => true, 'maxlength' => 150, 'value' => $v('company_name'), 'disabled' => !$editable]) ?>
            <?= form_input('company_email', 'Support e-mail', ['type' => 'email', 'maxlength' => 190, 'value' => $v('company_email'), 'disabled' => !$editable]) ?>
            <?= form_input('company_address', 'Address', ['maxlength' => 255, 'value' => $v('company_address'), 'disabled' => !$editable]) ?>
            <?= form_input('company_phone', 'Phone', ['type' => 'tel', 'maxlength' => 30, 'value' => $v('company_phone'), 'disabled' => !$editable]) ?>
        </div>
        <h2 class="h6">Regional</h2>
        <div class="row g-3 mb-4">
            <?= form_select('currency', 'Currency', $currencies, ['required' => true, 'value' => $v('currency', 'PHP'), 'disabled' => !$editable, 'help' => 'Changes how amounts are displayed.']) ?>
            <?= form_select('timezone', 'Timezone', $timezones, ['required' => true, 'value' => $v('timezone', 'Asia/Manila'), 'disabled' => !$editable]) ?>
        </div>
        <h2 class="h6">Bookings</h2>
        <div class="row g-3 mb-2">
            <div class="col-md-6"><div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="cancel_on" name="booking_cancellation_enabled" value="1" <?= $v('booking_cancellation_enabled', '1') === '1' ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>>
                <label class="form-check-label" for="cancel_on">Allow booking cancellations</label></div></div>
            <?= form_input('cancellation_cutoff_hours', 'Cancellation cutoff (hours before departure)', ['type' => 'number', 'min' => 0, 'max' => 168, 'required' => true,
                'value' => $v('cancellation_cutoff_hours', '2'), 'disabled' => !$editable, 'help' => 'Booking staff cannot cancel inside this window; administrators can.']) ?>
        </div>
        <?php if ($editable): ?><div class="mt-4"><button class="btn btn-primary" type="submit">Save settings</button></div><?php endif; ?>
    </form>
</div></div>
