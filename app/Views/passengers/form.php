<?php
/** @var \App\Models\Passenger|null $passenger */
$editing = $passenger !== null;
$action = $editing ? '/passengers/' . $passenger->id() : '/passengers';
?>
<div class="card"><div class="card-body">
    <p class="text-muted small">Only information needed for reservations is collected.</p>
    <form method="post" action="<?= e(url($action)) ?>" novalidate data-loading>
        <?= csrf_field() ?>
        <div class="row g-3">
            <?= form_input('first_name', 'First name', ['required' => true, 'maxlength' => 100, 'value' => $passenger?->firstName()]) ?>
            <?= form_input('last_name', 'Last name', ['required' => true, 'maxlength' => 100, 'value' => $passenger?->lastName()]) ?>
            <?= form_input('phone', 'Phone', ['required' => true, 'maxlength' => 30, 'type' => 'tel', 'value' => $passenger?->phone(), 'placeholder' => '09171234567']) ?>
            <?= form_input('email', 'E-mail (optional)', ['type' => 'email', 'maxlength' => 190, 'value' => $passenger?->email()]) ?>
            <?= form_select('gender', 'Gender (optional)', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], ['placeholder' => 'Prefer not to say', 'value' => $passenger?->gender() ?? '']) ?>
            <?= form_input('date_of_birth', 'Date of birth (optional)', ['type' => 'date', 'max' => date('Y-m-d'), 'value' => $passenger?->dateOfBirth()]) ?>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add passenger' ?></button>
            <a class="btn btn-light" href="<?= e(url($editing ? '/passengers/' . $passenger->id() : '/passengers')) ?>">Cancel</a>
        </div>
    </form>
</div></div>
